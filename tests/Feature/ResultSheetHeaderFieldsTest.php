<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ResultSheetHeaderFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_analysis_result_catalog_lists_official_header_fields(): void
    {
        $this->seed();

        $keys = collect(FieldValueResolver::catalog(ControlledFormCategory::AnalysisResult->value))
            ->pluck('key');

        $this->assertTrue($keys->contains('results.sample_received_at'));
        $this->assertTrue($keys->contains('results.sampling_datetime'));
        $this->assertTrue($keys->contains('results.analysis_datetime'));
        $this->assertTrue($keys->contains('results.release_date'));
        $this->assertTrue($keys->contains('results.customer'));
        $this->assertTrue($keys->contains('results.collected_by'));
        $this->assertTrue($keys->contains('results.receipt_at'));
        $this->assertTrue($keys->contains('results.ref_no'));
        $this->assertTrue($keys->contains('results.control_no'));
        $this->assertTrue($keys->contains('results.sample_code'));
        $this->assertTrue($keys->contains('results.collection_datetime'));
        $this->assertTrue($keys->contains('results.examination_datetime'));
        $this->assertTrue($keys->contains('results.report_date'));
        $this->assertTrue($keys->contains('results.water_supply'));
        $this->assertTrue($keys->contains('results.sampling_point'));
        $this->assertTrue($keys->contains('results.classification'));
        $this->assertTrue($keys->contains('results.test_requested'));
        $this->assertTrue($keys->contains('results.test_methods_references'));
    }

    public function test_water_activity_types_only_form_prints_test_requested_name(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-WATER-ACTIVITY')->firstOrFail();
        $this->assertFalse($package->is_active);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-WA')->firstOrFail();
        $this->assertNull($form->analysis_package_id);

        $typeIds = $form->orderedTypeIds();
        $this->assertNotEmpty($typeIds);

        $this->post('/intake/job-orders', [
            'customer_name' => 'WA Customer',
            'customer_email' => 'wa@example.com',
            'classification' => 'Agriculture',
            'samples' => [
                ['description' => 'Dried fruit', 'matrix' => 'Solid'],
            ],
            'package_ids' => [],
            'analysis_type_ids' => $typeIds,
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->activeRevision()
            ?? $form->revisions()->first()
            ?? $form->revisions()->create([
                'revision' => '01',
                'status' => 'draft',
                'fill_mode' => 'overlay',
                'created_by' => User::where('email', 'admin@nppc.local')->value('id'),
            ]);

        $bag = app(FieldValueResolver::class)->forResult($revision, $job->fresh(['analyses.analysisType']), null, $form);

        // Types-only F016-WA: Control Number | Sample Description | Water Activity, Aw.
        $matrix = $bag['water_activity_f016_matrix'] ?? [];
        $this->assertIsArray($matrix);
        $this->assertNotEmpty($matrix[0]['control_no'] ?? null);
        $this->assertSame('Dried fruit', $matrix[0]['sample_description'] ?? null);
        $this->assertArrayHasKey('result', $matrix[0] ?? []);
    }

    public function test_fo26_prints_panel_title_as_test_requested(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO26')->firstOrFail();
        $typeIds = array_slice($form->orderedTypeIds(), 0, 3);
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO26 Header Customer',
                'customer_email' => 'fo26-header@example.com',
                'classification' => 'Others: Food Products - Microbiological Test',
                'samples' => [
                    ['description' => 'Meat product', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->activeRevision()
            ?? $form->revisions()->first()
            ?? $form->revisions()->create([
                'revision' => '01',
                'status' => 'draft',
                'fill_mode' => 'overlay',
                'created_by' => User::where('email', 'admin@nppc.local')->value('id'),
            ]);

        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType']),
            null,
            $form,
        );

        $this->assertSame('Microbiological Food Test', $bag['results.test_requested']);

        $sample = app(FieldValueResolver::class)->sampleValues($revision->fresh(['form', 'fields']));
        $this->assertSame('Microbiological Food Test', $sample['results.test_requested']);
        $this->assertNotEmpty($sample['results.test_methods_references'] ?? null);
        $this->assertStringContainsString(' — ', (string) $sample['results.test_methods_references']);
    }

    public function test_fo26_test_methods_references_lists_selected_tests_only_and_prefers_result_method(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO26')->firstOrFail();
        $codes = ['FM-01', 'FM-08', 'FM-11'];
        $typeIds = collect($codes)
            ->map(fn (string $code) => \App\Models\AnalysisType::query()->where('code', $code)->value('id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertCount(3, $typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO26 Methods Customer',
                'customer_email' => 'fo26-methods@example.com',
                'classification' => 'Others: Food Products - Microbiological Test',
                'samples' => [
                    ['description' => 'Meat product', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->where('analysis_type_id', $typeIds[0])->firstOrFail();
        $line->update(['result_method' => 'Custom Analyst Method XYZ']);

        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType']),
            null,
            $form,
        );

        $block = (string) ($bag['results.test_methods_references'] ?? '');
        $this->assertStringContainsString('Custom Analyst Method XYZ', $block);
        $this->assertStringContainsString('Total Coliform', $block);
        $this->assertStringContainsString('Fecal Coliform', $block);
        $this->assertStringNotContainsString('Salmonella', $block);
        $this->assertSame(3, substr_count($block, "\n") + 1);

        $mappedField = $revision->fields()
            ->where('data_source_key', 'results.test_methods_references')
            ->orWhere('name', 'results.test_methods_references')
            ->first();
        $this->assertNotNull($mappedField);
        $this->assertTrue((bool) (($mappedField->options['cover'] ?? false)));
    }

    public function test_fo26_preview_pdf_nests_methods_under_tests_even_when_method_font_size_is_zero(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO26')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $matrix = $revision->fields()
            ->where('name', 'food_micro_fo26_matrix')
            ->firstOrFail();
        $config = is_array($matrix->table_config) ? $matrix->table_config : [];
        $config['method_font_size'] = 0;
        $matrix->update(['table_config' => $config]);

        $normalized = $matrix->fresh()->toOverlayArray()['table_config'] ?? [];
        $this->assertTrue(
            ! array_key_exists('method_font_size', $normalized)
            || (float) $normalized['method_font_size'] > 0,
        );

        $rows = app(FieldValueResolver::class)
            ->sampleValues($revision->fresh(['fields', 'form.analysisTypes']));
        $matrixRows = $rows['food_micro_fo26_matrix'] ?? [];
        $this->assertNotEmpty($matrixRows);
        $this->assertSame('Pour Plate Method', $matrixRows[0]['test_method'] ?? null);

        $overlay = $matrix->fresh()->toOverlayArray();
        $filler = app(\App\Services\ControlledPdfFiller::class);
        $pdf = new \setasign\Fpdi\Tcpdf\Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $measure = new \ReflectionMethod($filler, 'measureMatrixBodyRowHeight');
        $base = (float) ($normalized['row_height_mm'] ?? 6);
        $fontSize = (float) $overlay['font_size'];
        $methodFont = max(6, $fontSize - 1);
        $height = $measure->invoke(
            $filler,
            $pdf,
            $matrixRows[0],
            $normalized['columns'] ?? [],
            (float) $overlay['w'],
            $base,
            $fontSize,
            $methodFont,
            (string) $overlay['font_family'],
            true,
        );

        $this->assertGreaterThan($base, $height);
    }

    public function test_fo27_prints_panel_title_as_test_requested(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO27')->firstOrFail();
        $typeIds = array_slice($form->orderedTypeIds(), 0, 4);
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO27 Header Customer',
                'customer_email' => 'fo27-header@example.com',
                'classification' => 'Others: Food Products - Microbiological Test',
                'samples' => [
                    ['description' => 'Raw sugar', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->activeRevision()
            ?? $form->revisions()->first()
            ?? $form->revisions()->create([
                'revision' => '01',
                'status' => 'draft',
                'fill_mode' => 'overlay',
                'created_by' => User::where('email', 'admin@nppc.local')->value('id'),
            ]);

        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType']),
            null,
            $form,
        );

        $this->assertSame('Food Micro Sugar Test', $bag['results.test_requested']);
    }

    public function test_types_only_catalog_exposes_test_name_slots(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-WA')->firstOrFail();
        $this->assertNull($form->analysis_package_id);

        $keys = collect(FieldValueResolver::catalog(ControlledFormCategory::AnalysisResult->value, $form))
            ->pluck('key');

        $this->assertTrue($keys->contains('results.test_requested'));
        $this->assertTrue($keys->contains('test_1_name'));
    }

    public function test_sample_received_uses_kiosk_submit_datetime_format(): void
    {
        $this->seed();

        $this->travelTo(Carbon::parse('2026-07-29 15:00:00', 'Asia/Manila'));

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Coastal Farms',
            'customer_email' => 'coastal@example.com',
            'customer_address' => 'Bacolod City',
            'classification' => 'Wastewater',
            'sampling_date' => '2026-07-20',
            'sampling_time' => '09:30',
            'sample_collected_by' => 'Juan Cruz',
            'wastewater_source' => 'Faucet',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertSame('Coastal Farms', $bag['results.customer']);
        $this->assertSame('Bacolod City', $bag['results.address']);
        $this->assertSame('July 29, 2026 (3:00PM)', $bag['results.sample_received_at']);
        $this->assertSame('Wastewater', $bag['results.sample_code']);
        $this->assertNull($bag['results.sample_description']);
        $this->assertSame('July 20, 2026 (9:30AM)', $bag['results.sampling_datetime']);
        $this->assertSame('Juan Cruz', $bag['results.collected_by']);
        $this->assertSame($job->reference_no, $bag['results.ref_no']);
        $this->assertSame($job->reference_no, $bag['results.control_no']);
        $this->assertSame($job->reference_no, $bag['control_number_1']);
        $this->assertSame('July 20, 2026 (9:30AM)', $bag['results.collection_datetime']);
        $this->assertSame($bag['results.sampling_datetime'], $bag['results.collection_datetime']);
        $this->assertSame('Faucet', $bag['results.water_supply']);
        $this->assertSame('Faucet', $bag['results.sampling_point']);
        $this->assertNull($bag['results.analysis_datetime']);
        $this->assertNull($bag['results.release_date']);
        $this->assertNull($bag['results.report_date']);
    }

    public function test_others_specified_source_and_classification_print_on_result_header(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Other Source Customer',
            'customer_email' => 'other-source@example.com',
            'classification' => 'Others: Irrigation canal',
            'wastewater_source' => 'Others: Spring box',
            'samples' => [
                ['description' => 'Spring water', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertSame('Irrigation canal', $bag['results.classification']);
        $this->assertSame('Spring box', $bag['results.sampling_point']);
        $this->assertSame('Spring box', $bag['results.water_supply']);
    }

    public function test_analysis_datetime_uses_first_completed_result(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Coastal Farms',
            'customer_email' => 'coastal-analysis@example.com',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->travelTo(Carbon::parse('2026-07-21 10:00:00', 'Asia/Manila'));

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

        $this->travelTo(Carbon::parse('2026-07-29 15:00:00', 'Asia/Manila'));

        $first = $job->analyses()->firstOrFail();
        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/complete", [
                    'result_value' => 'ND',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method'
                ])
            ->assertRedirect();

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh(['analyses']), true);

        $this->assertSame('July 21, 2026 (10:00AM)', $bag['results.receipt_at']);
        $this->assertSame('July 29, 2026 (3:00PM)', $bag['results.analysis_datetime']);
        $this->assertSame($bag['results.analysis_datetime'], $bag['results.examination_datetime']);
        $this->assertNull($bag['results.release_date']);
        $this->assertNull($bag['results.report_date']);
    }

    public function test_receipt_and_examination_print_manila_time_not_utc(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Timezone Customer',
            'customer_email' => 'timezone@example.com',
            'classification' => 'Potability',
            'sampling_date' => '2026-08-20',
            'sampling_time' => '00:05',
            'samples' => [
                ['description' => 'Kitchen faucet', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->travelTo(Carbon::parse('2026-08-19 16:51:00', 'UTC'));

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

        $first = $job->analyses()->firstOrFail();
        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/complete", [
                    'result_value' => 'ND',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method'
                ])
            ->assertRedirect();

        $bag = app(FieldValueResolver::class)->jobOrderBag($job->fresh(['analyses']), true);

        $this->assertSame('August 20, 2026 (12:05AM)', $bag['results.collection_datetime']);
        $this->assertSame('August 20, 2026 (12:51AM)', $bag['results.receipt_at']);
        $this->assertSame('August 20, 2026 (12:51AM)', $bag['results.examination_datetime']);
        $this->assertNull($bag['results.report_date']);
        $this->assertNull($bag['results.release_date']);
    }

    public function test_sterile_bottle_field_data_prints_as_sample_description(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Potability Customer',
            'customer_email' => 'potability@example.com',
            'classification' => 'Potability',
            'field_data' => 'Water in sterile bottle',
            'samples' => [
                [
                    'sample_code' => 'DW-12',
                    'description' => 'Kitchen faucet',
                    'matrix' => 'Liquid',
                ],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $bag = app(FieldValueResolver::class)->jobOrderBag($job, true);

        $this->assertTrue($bag['potability_sterile']);
        $this->assertSame('DW-12', $bag['results.sample_code']);
        $this->assertSame('Water in sterile bottle', $bag['results.sample_description']);
        $this->assertSame($job->reference_no, $bag['results.control_no']);
    }

    public function test_fo4_sample_description_prints_classification(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $form->forceFill(['analysis_package_id' => $package->id])->save();

        $this->post('/intake/job-orders', [
            'customer_name' => 'FO4 Classification Customer',
            'customer_email' => 'fo4-class@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Plant effluent', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->fresh()->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());

        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses', 'packages', 'samples']),
            null,
            $form->fresh(['analysisPackage']),
        );

        $this->assertSame('Wastewater', $bag['results.sample_description']);
        $this->assertSame('Wastewater', $bag['results.classification']);
    }
}
