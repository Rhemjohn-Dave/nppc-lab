<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class IntakeTypePresetTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_create_exposes_type_presets(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $expected = [
            [
                'key' => 'ww_physico_fo2',
                'form_code' => 'LSP-7.8-FO2',
                'classifications' => ['Wastewater'],
                'codes' => OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes(),
            ],
            [
                'key' => 'proximate_f016',
                'form_code' => 'LSP-7.8-F016-PROX',
                'classifications' => ['Proximate Analysis'],
                'codes' => OfficialAnalysisCatalog::proximateAnalysisTypeCodes(),
            ],
            [
                'key' => 'milk_f016',
                'form_code' => 'LSP-7.8-F016-MILK',
                'classifications' => ['Other Food Analysis'],
                'codes' => OfficialAnalysisCatalog::milkAnalysisTypeCodes(),
            ],
            [
                'key' => 'water_activity_f016',
                'form_code' => 'LSP-7.8-F016-WA',
                'classifications' => ['Other Food Analysis'],
                'codes' => OfficialAnalysisCatalog::waterActivityTypeCodes(),
            ],
            [
                'key' => 'chloramphenicol_f016',
                'form_code' => 'LSP-7.8-F016-CAP',
                'classifications' => ['Other Food Analysis', 'Aqua'],
                'codes' => OfficialAnalysisCatalog::chloramphenicolTypeCodes(),
            ],
            [
                'key' => 'nitrite_f016',
                'form_code' => 'LSP-7.8-F016-NO2',
                'classifications' => ['Other Food Analysis'],
                'codes' => OfficialAnalysisCatalog::nitriteTypeCodes(),
            ],
            [
                'key' => 'food_micro_fo26',
                'form_code' => 'LSP-7.8-FO26',
                'classifications' => ['Food Products - Microbiological Test'],
                'codes' => OfficialAnalysisCatalog::foodMicroFo26TypeCodes(),
            ],
            [
                'key' => 'food_micro_sugar_fo27',
                'form_code' => 'LSP-7.8-FO27',
                'classifications' => ['Food Products - Microbiological Test'],
                'codes' => OfficialAnalysisCatalog::foodMicroSugarTypeCodes(),
            ],
            [
                'key' => 'dw_physico_old_fo3',
                'form_code' => 'LSP-7.8-FO3',
                'classifications' => ['Potability'],
                'codes' => OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes(),
            ],
            [
                'key' => 'dw_micro_fo5',
                'form_code' => 'LSP-7.8-FO5',
                'classifications' => ['Potability'],
                'codes' => ControlledForm::query()
                    ->where('form_code', 'LSP-7.8-FO5')
                    ->firstOrFail()
                    ->analysisTypes
                    ->pluck('code')
                    ->all(),
            ],
        ];

        $assert = $this->actingAs($receiving)
            ->get('/intake/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('intake/wizard')
                ->has('type_presets', count($expected))
                ->where('type_presets', fn ($presets): bool => collect($presets)
                    ->pluck('form_code')
                    ->doesntContain('LSP-7.8-F016-PHYTO')));

        foreach ($expected as $index => $preset) {
            $ids = collect($preset['codes'])
                ->map(fn (string $code) => AnalysisType::query()
                    ->where('code', $code)
                    ->where('is_active', true)
                    ->where('show_on_kiosk', true)
                    ->value('id'))
                ->filter()
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();

            $assert->assertInertia(fn (Assert $page) => $page
                ->where("type_presets.{$index}.key", $preset['key'])
                ->where("type_presets.{$index}.form_code", $preset['form_code'])
                ->where("type_presets.{$index}.classifications", $preset['classifications'])
                ->where("type_presets.{$index}.analysis_type_ids", $ids)
                ->has("type_presets.{$index}.tests", count($ids)));
        }
    }

    public function test_active_types_only_result_form_becomes_intake_preset(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $source = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $this->assertNotNull($source->activeRevision());

        $types = AnalysisType::query()
            ->whereIn('code', OfficialAnalysisCatalog::proximateAnalysisTypeCodes())
            ->orderBy('id')
            ->take(2)
            ->get();
        $this->assertCount(2, $types);

        $form = $source->replicate(['combination_key']);
        $form->form_code = 'LSP-TEST-PANEL';
        $form->name = 'Custom Panel Test Result Form';
        $form->save();
        $form->analysisTypes()->attach([
            $types[1]->id => ['slot' => 1],
            $types[0]->id => ['slot' => 2],
        ]);

        $findPreset = fn ($presets) => collect($presets)->firstWhere('form_code', 'LSP-TEST-PANEL');

        $this->actingAs($receiving)
            ->get('/intake/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('type_presets', function ($presets) use ($findPreset, $types): bool {
                    $preset = $findPreset($presets);

                    return $preset !== null
                        && $preset['key'] === 'lsp_test_panel'
                        && $preset['label'] === 'Custom Panel'
                        && $preset['analysis_type_ids'] === [(int) $types[1]->id, (int) $types[0]->id];
                }));

        AnalysisType::query()->whereKey($types->pluck('id'))->update(['show_on_kiosk' => false]);

        $this->actingAs($receiving)
            ->get('/intake/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('type_presets', fn ($presets): bool => $findPreset($presets) === null));
    }

    public function test_seeded_fo3_matrix_keeps_natural_row_heights(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $matrix = $form->activeRevision()?->fields()->where('name', 'dw_old_fo3_matrix')->first();

        $this->assertNotNull($matrix);
        $this->assertFalse($matrix->table_config['stretch_body'] ?? true);
    }

    public function test_fo3_preset_type_ids_resolve_fo3(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO3')->firstOrFail();
        $this->assertNotNull($form->activeRevision());

        $codes = OfficialAnalysisCatalog::drinkingWaterOldIssue11TypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO3 Preset Customer',
                'customer_email' => 'fo3-preset@example.com',
                'classification' => 'Potability',
                'samples' => [
                    ['description' => 'Tap water', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
        $this->assertSame('LSP-7.8-FO3', $resolved->controlledForm?->form_code);
    }

    public function test_new_mandatory_package_plus_sulfate_gives_fo37_and_fo3_sulfate_only(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $memberIds = $package->orderedTypeIds();
        $sulfate = AnalysisType::query()->where('code', 'DW-20')->firstOrFail();
        $this->assertNotContains((int) $sulfate->id, $memberIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'Overlap Customer',
                'customer_email' => 'overlap@example.com',
                'classification' => 'Potability',
                'samples' => [
                    [
                        'description' => 'Tap water',
                        'matrix' => 'Liquid',
                        'package_ids' => [$package->id],
                        'analysis_type_ids' => [...$memberIds, (int) $sulfate->id],
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertCount(count($memberIds) + 1, $job->analyses);

        $reports = app(AnalysisResultReportResolver::class)->reportsForJobOrder($job, $receiving, withValues: false);
        $this->assertSame(
            ['LSP-7.8-FO37', 'LSP-7.8-FO3'],
            $reports->map(fn ($report) => $report->controlledForm?->form_code)->all(),
        );

        $fo3 = $reports->first(fn ($report) => $report->controlledForm?->form_code === 'LSP-7.8-FO3');
        $this->assertSame(
            [(int) $sulfate->id],
            $fo3->analyses->pluck('analysis_type_id')->map(fn ($id): int => (int) $id)->values()->all(),
        );

        $control = preg_replace('/^\d{2}-/', '', (string) $job->reference_no);
        $base = "Result_DW_{$job->reference_no}_{$control}_OverlapCustomer_{$job->created_at->format('m-d-y')}";
        $this->assertSame("{$base}_FO37.pdf", $reports[0]->filename);
        $this->assertSame("{$base}_FO3.pdf", $fo3->filename);

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $manifest = $this->actingAs($admin)
            ->getJson("/analyst/job-orders/{$job->id}/result-report")
            ->assertOk()
            ->json();

        $tabFormIds = collect($manifest['reports'])->pluck('form_id')->all();
        $this->assertCount(2, $tabFormIds);
        $this->assertSame(
            [$reports[0]->controlledForm->id, $fo3->controlledForm->id],
            $tabFormIds,
        );
        $this->assertSame($reports[0]->controlledForm->id, $manifest['form_id']);

        $fo3Tab = collect($manifest['reports'])->firstWhere('form_id', $fo3->controlledForm->id);
        $this->actingAs($admin)
            ->getJson($fo3Tab['report_url'])
            ->assertOk()
            ->assertJsonPath('form_id', $fo3->controlledForm->id);
    }

    public function test_fo2_preset_type_ids_create_individual_lines_and_resolve_fo2(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $this->assertNotNull($form->activeRevision());

        $typeIds = AnalysisType::query()
            ->whereIn('code', OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes())
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search(
                $type->code,
                OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes(),
                true,
            ))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Preset Customer',
                'customer_email' => 'fo2-preset@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    ['description' => 'Effluent', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
        $this->assertSame('LSP-7.8-FO2', $resolved->controlledForm?->form_code);
    }

    public function test_proximate_preset_type_ids_create_individual_lines(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::proximateAnalysisTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'Proximate Preset Customer',
                'customer_email' => 'prox-preset@example.com',
                'classification' => 'Others: Proximate Analysis',
                'samples' => [
                    ['description' => 'Feed sample', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
                'specimen' => 'Fish meal',
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-PROX')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-F016-PROX', $resolved->controlledForm?->form_code);
        }
    }

    public function test_milk_preset_type_ids_create_individual_lines_and_resolve_f016_milk(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::milkAnalysisTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'Milk Preset Customer',
                'customer_email' => 'milk-preset@example.com',
                'classification' => 'Others: Other Food Analysis',
                'samples' => [
                    ['description' => 'Raw milk', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => array_slice($typeIds, 0, 3),
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(3, $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-F016-MILK', $resolved->controlledForm?->form_code);
        }
    }

    public function test_water_activity_preset_type_ids_create_individual_lines_and_resolve_f016_wa(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::waterActivityTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'WA Preset Customer',
                'customer_email' => 'wa-preset@example.com',
                'classification' => 'Others: Other Food Analysis',
                'samples' => [
                    ['description' => 'Dried fruit', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-WA')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-F016-WA', $resolved->controlledForm?->form_code);
        }
    }

    public function test_chloramphenicol_preset_type_ids_create_individual_lines_and_resolve_f016_cap(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::chloramphenicolTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'CAP Preset Customer',
                'customer_email' => 'cap-preset@example.com',
                'classification' => 'Aqua',
                'samples' => [
                    ['description' => 'Prawn sample', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-CAP')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-F016-CAP', $resolved->controlledForm?->form_code);
        }
    }

    public function test_nitrite_preset_type_ids_create_individual_lines_and_resolve_f016_no2(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::nitriteTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'NO2 Preset Customer',
                'customer_email' => 'no2-preset@example.com',
                'classification' => 'Others: Other Food Analysis',
                'samples' => [
                    ['description' => 'Cured meat', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(count($typeIds), $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-F016-NO2', $resolved->controlledForm?->form_code);
        }
    }

    public function test_fo26_preset_type_ids_create_individual_lines_and_resolve_fo26(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::foodMicroFo26TypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);
        $this->assertContains('FM-11', $codes);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO26 Preset Customer',
                'customer_email' => 'fo26-preset@example.com',
                'classification' => 'Others: Food Products - Microbiological Test',
                'samples' => [
                    ['description' => 'Meat product', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => array_slice($typeIds, 0, 3),
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(3, $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO26')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-FO26', $resolved->controlledForm?->form_code);
        }
    }

    public function test_fo27_sugar_types_do_not_resolve_to_fo26(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::foodMicroSugarTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->get()
            ->sortBy(fn (AnalysisType $type) => array_search($type->code, $codes, true))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        $this->assertNotEmpty($typeIds);
        $this->assertTrue(
            collect($codes)->every(fn (string $code) => str_starts_with($code, 'SM-')),
        );

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO27 Preset Customer',
                'customer_email' => 'fo27-preset@example.com',
                'classification' => 'Others: Food Products - Microbiological Test',
                'samples' => [
                    ['description' => 'Raw sugar', 'matrix' => 'Solid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => array_slice($typeIds, 0, 4),
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->doesntExist());
        $this->assertCount(4, $job->analyses);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO27')->first();
        if ($form?->activeRevision()) {
            $resolved = app(AnalysisResultReportResolver::class)->forJobOrder($job, $receiving);
            $this->assertSame('LSP-7.8-FO27', $resolved->controlledForm?->form_code);
            $this->assertNotSame('LSP-7.8-FO26', $resolved->controlledForm?->form_code);
        }
    }
}
