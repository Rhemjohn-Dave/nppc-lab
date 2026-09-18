<?php

namespace Tests\Feature;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\ControlledDocumentGenerator;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DynamicTestMatrixReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_matrix_package_prints_only_selected_test_rows(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $total->update(['method' => 'Custom MPN method']);

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-FOOD-DEMO',
            'name' => 'Food analysis demo',
            'default_price' => 500,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 99,
        ]);
        $package->syncTypes([$total->id, $thermo->id, $hpc->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'FOOD-MATRIX-01',
                'name' => 'Food matrix result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'FOOD-MATRIX-01')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'food_matrix',
                        'label' => 'Test results',
                        'field_type' => 'dynamic_test_matrix',
                        'page_number' => 1,
                        'x' => 15,
                        'y' => 60,
                        'width' => 180,
                        'height' => 80,
                        'font_size' => 8,
                        'table_config' => [
                            'columns' => [
                                ['key' => 'test', 'label' => 'TEST', 'width_pct' => 55],
                                ['key' => 'result', 'label' => 'Result', 'width_pct' => 25],
                                ['key' => 'remarks', 'label' => 'Remarks', 'width_pct' => 20],
                            ],
                            'row_height_mm' => 5,
                            'header_row' => true,
                            'border' => true,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Matrix Customer',
            'customer_email' => 'matrix@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Food sample', 'matrix' => 'Solid'],
            ],
            'package_ids' => [$package->id],
            'analysis_type_ids' => [$total->id, $thermo->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing([$hpc->id], $job->waivedTypeIds());

        $lines = $job->analyses()->get();
        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 225,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        foreach ($job->fresh()->analyses as $line) {
            $this->actingAs($line->assignee ?? $analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => '<1.1',
                    'result_pass_fail' => 'Passed',
                    'result_method' => $line->analysis_type_id === $total->id
                        ? 'Analyst override method'
                        : 'Standard Method',
                    'result_unit' => 'MPN/100ml',
                    'result_remarks' => 'Pass',
                ])
                ->assertRedirect();
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages']);
        foreach ($job->analyses as $line) {
            $this->assertSame('completed', $line->status->value);
        }
        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            null,
            $form->fresh(['analysisPackage']),
        );

        $this->assertArrayNotHasKey('test_1_result', $values);
        $this->assertArrayNotHasKey('test_3_result', $values);
        $this->assertIsArray($values['food_matrix']);
        $this->assertCount(2, $values['food_matrix']);
        $this->assertSame($total->name, $values['food_matrix'][0]['test']);
        $this->assertSame('Analyst override method', $values['food_matrix'][0]['test_method']);
        $this->assertSame('Passed', $values['food_matrix'][0]['pass_fail']);
        $this->assertSame('<1.1 MPN/100ml (Passed)', $values['food_matrix'][0]['result']);

        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);
        $this->assertSame('combined', $report->kind);
        $this->assertCount(2, $report->analyses);

        $pdf = app(ControlledDocumentGenerator::class)->fromResultForm(
            $form,
            $job,
            $admin,
            $report->analyses,
        )['binary'];

        $this->assertStringStartsWith('%PDF', $pdf);

        $previewLine = $job->analyses->firstOrFail();
        $this->actingAs($admin)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_matrix_package_without_matrix_field_is_unavailable(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-MATRIX-NOFIELD',
            'name' => 'Matrix missing field',
            'default_price' => 100,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 100,
        ]);
        $package->syncTypes([$total->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'MATRIX-NOFIELD',
                'name' => 'No matrix region',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'No matrix field',
            'customer_email' => 'nomatrix@example.com',
            'samples' => [['description' => 'Sample', 'matrix' => 'Solid']],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);

        $this->assertSame('unavailable', $report->kind);
        $this->assertStringContainsString('Dynamic test matrix', (string) $report->message);
    }

    public function test_proximate_package_is_seeded_as_dynamic_matrix_with_methods(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-PROXIMATE')->firstOrFail();
        $this->assertSame(AnalysisPackageReportLayout::DynamicMatrix, $package->report_layout);

        $fat = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();
        $this->assertSame('Soxhlet Extraction Method', $fat->method);

        $rows = \App\Support\DynamicTestMatrix::previewRowsForPackage($package);
        $this->assertGreaterThanOrEqual(8, count($rows));
        $this->assertSame($fat->name, $rows[0]['test']);
        $this->assertSame('Soxhlet Extraction Method', $rows[0]['test_method']);
    }

    public function test_dw_physico_package_is_seeded_as_dynamic_matrix_with_acceptable_values(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $this->assertSame(AnalysisPackageReportLayout::DynamicMatrix, $package->report_layout);
        $this->assertSame('LSP-7.8-FO37', $package->form_code);

        $nitrate = AnalysisType::query()->where('code', 'DW-06')->firstOrFail();
        $this->assertSame('Nitrate (mg/L)', $nitrate->name);
        $this->assertSame('Nitrate Electrode Method (4500-NO3-D)', $nitrate->method);
        $this->assertSame('50 mg/L', $nitrate->acceptable_values);

        $color = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $this->assertSame('Colorimetric Method (2120 B)', $color->method);
        $this->assertSame('10 color units', $color->acceptable_values);

        $rows = \App\Support\DynamicTestMatrix::previewRowsForPackage($package);
        $this->assertSame('Color (Apparent Color)', $rows[0]['test']);
        $this->assertSame('Colorimetric Method (2120 B)', $rows[0]['method']);
        $this->assertSame('10 color units', $rows[0]['acceptable_values']);
        $this->assertSame('Total Dissolved Solids (mg/L)', $rows[1]['test']);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO37')->firstOrFail();
        $this->assertSame(2, (int) $form->analyst_signatory_slots);
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());

        $matrix = $revision->fields()->where('field_type', 'dynamic_test_matrix')->first();
        $this->assertNotNull($matrix);
        $columns = $matrix->table_config['columns'] ?? [];
        $keys = collect($columns)->pluck('key')->all();
        $this->assertSame(['test', 'method', 'result', 'acceptable_values', 'remarks'], $keys);
        $this->assertTrue((bool) ($matrix->table_config['header_row'] ?? false));
        $this->assertTrue((bool) ($matrix->table_config['border'] ?? false));
    }

    public function test_dw_physico_designer_uses_package_preset_and_catalog_preview_rows(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO37')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->get("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/designer")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/form-designer')
                ->where('matrix_default_config.columns.0.key', 'test')
                ->where('matrix_default_config.columns.1.key', 'method')
                ->where('matrix_default_config.columns.3.key', 'acceptable_values')
                ->where('matrix_preview_rows.0.test', 'Color (Apparent Color)')
                ->where('matrix_preview_rows.0.method', 'Colorimetric Method (2120 B)')
                ->where('matrix_preview_rows.0.acceptable_values', '10 color units')
                ->where('matrix_preview_rows.1.test', 'Total Dissolved Solids (mg/L)'));

        $values = app(FieldValueResolver::class)->sampleValues($revision->load(['fields', 'form.analysisPackage.analysisTypes']));
        $matrix = $revision->fields()->where('field_type', 'dynamic_test_matrix')->firstOrFail();
        $this->assertIsArray($values[$matrix->name] ?? null);
        $this->assertSame('Color (Apparent Color)', $values[$matrix->name][0]['test']);
        $this->assertSame('Colorimetric Method (2120 B)', $values[$matrix->name][0]['method']);
        $this->assertSame('10 color units', $values[$matrix->name][0]['acceptable_values']);
        $this->assertNotSame('% Fat', $values[$matrix->name][0]['test']);
    }

    public function test_dw_physico_print_includes_only_selected_tests_with_acceptable_values(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO37')->firstOrFail();
        $color = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $ph = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();
        $tds = AnalysisType::query()->where('code', 'DW-03')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'DW Physico Customer',
                'customer_email' => 'dw@example.com',
                'classification' => 'Potability',
                'samples' => [
                    ['description' => 'Bottled water', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [$package->id],
                'analysis_type_ids' => [$color->id, $ph->id, $tds->id],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();
        $this->assertCount(3, $lines);

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        foreach ($job->fresh()->analyses as $line) {
            $value = match ((int) $line->analysis_type_id) {
                $color->id => '5',
                $ph->id => '7.1',
                $tds->id => '120',
                default => '1',
            };

            $this->actingAs($line->assignee ?? $analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => $value,
                    'result_pass_fail' => 'Passed',
                    'result_method' => \App\Support\DynamicTestMatrix::methodForAnalysisType($line->analysisType) ?? 'Standard Method',
                    'result_remarks' => 'Pass',
                    'result_signatory_name' => $analyst->name,
                    'result_signatory_prc' => '1234567',
                    'result_signatory_name_2' => 'Second Analyst',
                    'result_signatory_prc_2' => '7654321',
                ])
                ->assertRedirect();
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages']);
        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            null,
            $form->fresh(['analysisPackage']),
        );

        $this->assertIsArray($values['dw_physico_matrix']);
        $this->assertCount(3, $values['dw_physico_matrix']);
        $this->assertSame('Color (Apparent Color)', $values['dw_physico_matrix'][0]['test']);
        $this->assertSame('Colorimetric Method (2120 B)', $values['dw_physico_matrix'][0]['method']);
        $this->assertSame('10 color units', $values['dw_physico_matrix'][0]['acceptable_values']);
        $this->assertSame('5 (Passed)', $values['dw_physico_matrix'][0]['result']);
        // Issue 7 package order: Color, TDS, Turbidity, pH — selected subset keeps package order.
        $this->assertSame('Total Dissolved Solids (mg/L)', $values['dw_physico_matrix'][1]['test']);
        $this->assertSame('pH', $values['dw_physico_matrix'][2]['test']);

        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);
        $this->assertSame('combined', $report->kind);

        $pdf = app(ControlledDocumentGenerator::class)->fromResultForm(
            $form,
            $job,
            $admin,
            $report->analyses,
        )['binary'];

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
    }

    public function test_dw_old_fo3_is_seeded_as_types_only_dynamic_matrix(): void
    {
        $this->seed();

        $chloride = AnalysisType::query()->where('code', 'DW-14')->firstOrFail();
        $this->assertSame('Chloride (mg/L)', $chloride->name);
        $this->assertSame('Argentometric Method'."\n".'4500-Cl- B', $chloride->method);
        $this->assertSame('250 mg/L', $chloride->acceptable_values);

        $iron = AnalysisType::query()->where('code', 'DW-18')->firstOrFail();
        $this->assertSame('Iron (mg/L)', $iron->name);
        $this->assertTrue($iron->show_on_kiosk);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $this->assertNull($form->analysis_package_id);
        $this->assertSame(2, (int) $form->analyst_signatory_slots);

        $codes = $form->analysisTypes()->orderByPivot('slot')->pluck('code')->all();
        $this->assertSame(\App\Support\OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes(), $codes);

        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());

        $matrix = $revision->fields()->where('field_type', 'dynamic_test_matrix')->first();
        $this->assertNotNull($matrix);
        $this->assertSame('dw_old_fo3_matrix', $matrix->name);
        $keys = collect($matrix->table_config['columns'] ?? [])->pluck('key')->all();
        $this->assertSame(['test', 'method', 'result', 'acceptable_values', 'remarks'], $keys);

        $rows = \App\Support\DynamicTestMatrix::previewRowsForForm($form->fresh(['analysisPackage', 'analysisTypes']));
        $this->assertSame('Color (Apparent Color)', $rows[0]['test']);
        $this->assertSame('Electrical Conductivity (µS/cm)', $rows[2]['test']);
        $this->assertSame('Chloride (mg/L)', $rows[5]['test']);
        $this->assertSame('Argentometric Method'."\n".'4500-Cl- B', $rows[5]['method']);
        $this->assertSame('250 mg/L', $rows[5]['acceptable_values']);
        $this->assertNotSame('% Fat', $rows[0]['test']);
    }

    public function test_ww_fo2_form_seeds_methods_and_matrix_config(): void
    {
        $this->seed();

        $ph = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $this->assertSame('Lab pH', $ph->name);
        $this->assertSame('4500-H+ B – Electrometric Method', $ph->method);

        $chloride = AnalysisType::query()->where('code', 'SA-06')->firstOrFail();
        $this->assertSame('Chloride (mg/L)', $chloride->name);
        $this->assertSame('4500-Cl- B – Argentometric Method', $chloride->method);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $this->assertNull($form->analysis_package_id);
        $this->assertSame(4, (int) $form->analyst_signatory_slots);

        $codes = $form->analysisTypes()->orderByPivot('slot')->pluck('code')->all();
        $this->assertSame(\App\Support\OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes(), $codes);

        $revision = $form->activeRevision();
        $this->assertNotNull($revision);
        $this->assertTrue($revision->hasCanonicalPdf());

        $matrix = $revision->fields()->where('field_type', 'dynamic_test_matrix')->first();
        $this->assertNotNull($matrix);
        $this->assertSame('ww_fo2_matrix', $matrix->name);
        $this->assertEqualsWithDelta(26.425, (float) $matrix->x, 0.01);
        $this->assertEqualsWithDelta(61.621, (float) $matrix->y, 0.01);
        $this->assertEqualsWithDelta(163.05, (float) $matrix->width, 0.01);
        $keys = collect($matrix->table_config['columns'] ?? [])->pluck('key')->all();
        $this->assertSame(['test', 'method', 'result'], $keys);
        $this->assertSame('C', $matrix->table_config['columns'][0]['align'] ?? null);

        $customer = $revision->fields()->where('name', 'results.customer')->first();
        $this->assertNotNull($customer);
        $this->assertEqualsWithDelta(60.0, (float) $customer->x, 0.01);
        $this->assertEqualsWithDelta(32.7, (float) $customer->y, 0.01);

        $certified2 = $revision->fields()->where('name', 'results.analyst_name_4')->first();
        $this->assertNotNull($certified2);
        $this->assertEqualsWithDelta(125.721, (float) $certified2->x, 0.01);
        $this->assertEqualsWithDelta(227.421, (float) $certified2->y, 0.01);

        $rows = \App\Support\DynamicTestMatrix::previewRowsForForm($form->fresh(['analysisPackage', 'analysisTypes']));
        $this->assertSame('Lab pH', $rows[0]['test']);
        $this->assertSame('Time of Analysis:', $rows[0]['test_detail']);
        $this->assertSame('4500-H+ B – Electrometric Method', $rows[0]['method']);
        $this->assertSame('Chloride (mg/L)', $rows[16]['test']);
        $this->assertSame('Date/Time of Analysis:', $rows[16]['test_detail']);
        $this->assertSame('4500-Cl- B – Argentometric Method', $rows[16]['method']);

        // Stale Remarks in stored table_config must not surface in designer/PDF payloads.
        $matrix->table_config = array_merge($matrix->table_config ?? [], [
            'columns' => [
                ...($matrix->table_config['columns'] ?? []),
                ['key' => 'remarks', 'label' => 'REMARKS', 'width_pct' => 14, 'align' => 'C', 'header_align' => 'C'],
            ],
        ]);
        $matrix->save();

        $keys = collect($matrix->fresh()->toDesignerArray()['table_config']['columns'] ?? [])
            ->pluck('key')
            ->all();
        $this->assertSame(['test', 'method', 'result'], $keys);
        $this->assertSame(
            ['test', 'method', 'result'],
            collect($matrix->fresh()->toOverlayArray()['table_config']['columns'] ?? [])->pluck('key')->all(),
        );
    }

    public function test_ww_fo2_subset_of_types_resolves_and_prints_selected_rows_only(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $ph = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $bod = AnalysisType::query()->where('code', 'WW-02')->firstOrFail();
        $chloride = AnalysisType::query()->where('code', 'SA-06')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'WW Physico Customer',
                'customer_email' => 'ww-physico@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    ['description' => 'Effluent', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => [$chloride->id, $bod->id, $ph->id],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $lines = $job->analyses()->get();
        $this->assertCount(3, $lines);

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        foreach ($job->fresh()->analyses as $line) {
            $value = match ((int) $line->analysis_type_id) {
                $ph->id => '7.2',
                $bod->id => '25',
                $chloride->id => '120',
                default => '1',
            };

            $this->actingAs($line->assignee ?? $analyst)
                ->post("/analyst/tasks/{$line->id}/complete", $this->completeResultPayload([
                    'result_value' => $value,
                    'result_method' => \App\Support\DynamicTestMatrix::methodForAnalysisType($line->analysisType) ?? 'Standard Method',
                    'result_remarks' => 'OK',
                ]))
                ->assertRedirect();
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages']);

        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame('LSP-7.8-FO2', $matched->form_code);

        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            null,
            $form->fresh(['analysisPackage', 'analysisTypes']),
        );

        $matrixField = $revision->fields->firstWhere('field_type', 'dynamic_test_matrix');
        $this->assertNotNull($matrixField);
        $rows = $values[$matrixField->name] ?? null;
        $this->assertIsArray($rows);
        $this->assertCount(3, $rows);
        // FO2 print order: Lab pH, … BOD … Chloride
        $this->assertSame('Lab pH', $rows[0]['test']);
        $this->assertSame('7.2 (Passed)', $rows[0]['result']);
        $this->assertSame('4500-H+ B – Electrometric Method', $rows[0]['method']);
        $this->assertStringStartsWith('Time of Analysis:', (string) ($rows[0]['test_detail'] ?? ''));
        $this->assertSame('Biochemical Oxygen Demand (mg/L)', $rows[1]['test']);
        $this->assertSame('25 (Passed)', $rows[1]['result']);
        $this->assertSame('Chloride (mg/L)', $rows[2]['test']);
        $this->assertSame('120 (Passed)', $rows[2]['result']);
        $this->assertStringStartsWith('Date/Time of Analysis:', (string) ($rows[2]['test_detail'] ?? ''));

        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $analyst);
        $this->assertSame('LSP-7.8-FO2', $report->controlledForm?->form_code);
    }

    public function test_dw_old_fo3_subset_of_types_resolves_and_prints_selected_rows_only(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $color = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $chloride = AnalysisType::query()->where('code', 'DW-14')->firstOrFail();
        $iron = AnalysisType::query()->where('code', 'DW-18')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'DW OLD Individual Customer',
                'customer_email' => 'dw-old@example.com',
                'classification' => 'Potability',
                'samples' => [
                    ['description' => 'Well water', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => [$chloride->id, $iron->id, $color->id],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $lines = $job->analyses()->get();
        $this->assertCount(3, $lines);

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        foreach ($job->fresh()->analyses as $line) {
            $value = match ((int) $line->analysis_type_id) {
                $color->id => '3',
                $chloride->id => '40',
                $iron->id => '0.2',
                default => '1',
            };

            $this->actingAs($line->assignee ?? $analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => $value,
                    'result_pass_fail' => 'Passed',
                    'result_method' => \App\Support\DynamicTestMatrix::methodForAnalysisType($line->analysisType) ?? 'Standard Method',
                    'result_remarks' => 'Pass',
                    'result_signatory_name' => $analyst->name,
                    'result_signatory_prc' => '1234567',
                    'result_signatory_name_2' => 'Second Analyst',
                    'result_signatory_prc_2' => '7654321',
                ])
                ->assertRedirect();
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages']);

        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame('LSP-7.8-FO3', $matched->form_code);

        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            null,
            $form->fresh(['analysisPackage', 'analysisTypes']),
        );

        $this->assertIsArray($values['dw_old_fo3_matrix']);
        $this->assertCount(3, $values['dw_old_fo3_matrix']);
        // Issue 11 form order: Color … Chloride … Iron — selected subset keeps form member order.
        $this->assertSame('Color (Apparent Color)', $values['dw_old_fo3_matrix'][0]['test']);
        $this->assertSame('Colorimetric Method (2120 B)', $values['dw_old_fo3_matrix'][0]['method']);
        $this->assertSame('10 color units', $values['dw_old_fo3_matrix'][0]['acceptable_values']);
        $this->assertSame('3 (Passed)', $values['dw_old_fo3_matrix'][0]['result']);
        $this->assertSame('Chloride (mg/L)', $values['dw_old_fo3_matrix'][1]['test']);
        $this->assertSame('Argentometric Method'."\n".'4500-Cl- B', $values['dw_old_fo3_matrix'][1]['method']);
        $this->assertSame('250 mg/L', $values['dw_old_fo3_matrix'][1]['acceptable_values']);
        $this->assertSame('Iron (mg/L)', $values['dw_old_fo3_matrix'][2]['test']);

        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);
        $this->assertSame('combined', $report->kind);
        $this->assertSame('LSP-7.8-FO3', $report->controlledForm?->form_code);

        $pdf = app(ControlledDocumentGenerator::class)->fromResultForm(
            $form,
            $job,
            $admin,
            $report->analyses,
        )['binary'];

        $this->assertStringStartsWith('%PDF', $pdf);
        $this->assertGreaterThan(10_000, strlen($pdf));
    }

    public function test_dw_physico_package_still_resolves_fo37_not_fo3(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $color = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();
        $ph = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'DW Package Customer',
                'customer_email' => 'dw-pkg@example.com',
                'classification' => 'Potability',
                'samples' => [
                    ['description' => 'Bottled water', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [$package->id],
                'analysis_type_ids' => [$color->id, $ph->id],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame('LSP-7.8-FO37', $matched->form_code);
        $this->assertNotSame('LSP-7.8-FO3', $matched->form_code);
    }

    public function test_unrelated_types_do_not_match_dw_old_fo3(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $fat = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();
        $color = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'Mixed Unrelated Customer',
                'customer_email' => 'mixed@example.com',
                'classification' => 'Food',
                'samples' => [
                    ['description' => 'Mixed sample', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => [$fat->id, $color->id],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertTrue(
            $matched === null || $matched->form_code !== 'LSP-7.8-FO3',
            'FO3 must not match when any job type is outside its bound set',
        );
    }

    public function test_form_designer_receives_package_matrix_preview_rows(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $fat = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();
        $protein = AnalysisType::query()->where('code', 'PX-02')->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-MATRIX-PREVIEW',
            'name' => 'Matrix preview package',
            'default_price' => 100,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 101,
        ]);
        $package->syncTypes([$fat->id, $protein->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'PROX-MATRIX-DESIGNER',
                'name' => 'Proximate matrix designer',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'PROX-MATRIX-DESIGNER')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->get("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/designer")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('admin/form-designer')
                ->has('matrix_preview_rows', 2)
                ->where('matrix_preview_rows.0.test', $fat->name)
                ->where('matrix_preview_rows.0.test_method', 'Soxhlet Extraction Method')
                ->where('matrix_preview_rows.1.test', $protein->name));
    }

    private function makeBlankResultPdf(): UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, 'Result sheet');
        $binary = $pdf->Output('', 'S');
        $path = tempnam(sys_get_temp_dir(), 'result-pdf-');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'result.pdf', 'application/pdf', null, true);
    }
}
