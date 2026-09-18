<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Services\FieldValueResolver;
use App\Support\OfficialAnalysisCatalog;
use Database\Seeders\ControlledFormDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NitriteFormBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_nitrite_f016_blueprint_with_matrix(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-FOOD-NITRITE')->firstOrFail();
        $this->assertFalse($package->is_active);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->firstOrFail();
        $this->assertNull($form->analysis_package_id);
        $this->assertSame(
            OfficialAnalysisCatalog::nitriteTypeCodes(),
            AnalysisType::query()
                ->whereIn('id', $form->orderedTypeIds())
                ->get()
                ->sortBy(fn (AnalysisType $type) => array_search(
                    $type->code,
                    OfficialAnalysisCatalog::nitriteTypeCodes(),
                    true,
                ))
                ->pluck('code')
                ->values()
                ->all(),
        );

        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_nitrite_form_fields');
        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());

        $matrix = $revision->fields()->where('name', 'nitrite_f016_matrix')->firstOrFail();
        $columns = $matrix->table_config['columns'] ?? [];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Nitrite (mg/kg)', $columns[2]['label'] ?? null);
        $this->assertSame('Spectrophotometric Method', $columns[2]['sublabel'] ?? null);
        $this->assertFalse($matrix->table_config['stretch_body'] ?? true);

        $this->assertTrue($revision->hasCanonicalPdf());
        $this->assertSame(
            'Spectrophotometric Method',
            OfficialAnalysisCatalog::methodForCode('FD-NO2'),
        );
    }

    public function test_nitrite_matrix_enriches_control_no_and_sample_description(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->firstOrFail();
        $typeIds = $form->orderedTypeIds();
        $this->assertNotEmpty($typeIds);

        $this->post('/intake/job-orders', [
            'customer_name' => 'NO2 Customer',
            'customer_email' => 'no2@example.com',
            'classification' => 'Agriculture',
            'samples' => [
                ['description' => 'Cured meat', 'matrix' => 'Solid'],
            ],
            'package_ids' => [],
            'analysis_type_ids' => $typeIds,
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();

        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType', 'samples']),
            null,
            $form,
        );

        $this->assertSame('Nitrite Content', $bag['results.test_requested'] ?? null);

        $matrix = $bag['nitrite_f016_matrix'] ?? [];
        $this->assertIsArray($matrix);
        $this->assertCount(1, $matrix);
        $this->assertSame((string) $job->reference_no, $matrix[0]['control_no'] ?? null);
        $this->assertSame('Cured meat', $matrix[0]['sample_description'] ?? null);
        $this->assertArrayHasKey('result', $matrix[0] ?? []);
    }

    public function test_nitrite_matrix_prints_one_row_per_sample_with_suffixed_control_numbers(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->firstOrFail();
        $typeIds = $form->orderedTypeIds();
        $this->assertNotEmpty($typeIds);

        $this->post('/intake/job-orders', [
            'customer_name' => 'NO2 Multi Customer',
            'customer_email' => 'no2-multi@example.com',
            'classification' => 'Agriculture',
            'samples' => [
                ['description' => 'Batch A', 'matrix' => 'Solid'],
                ['description' => 'Batch B', 'matrix' => 'Solid'],
                ['description' => 'Batch C', 'matrix' => 'Solid'],
            ],
            'package_ids' => [],
            'analysis_type_ids' => $typeIds,
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $ref = (string) $job->reference_no;

        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType', 'samples']),
            null,
            $form,
        );

        $matrix = $bag['nitrite_f016_matrix'] ?? [];
        $this->assertCount(3, $matrix);
        $this->assertSame($ref.'A', $matrix[0]['control_no'] ?? null);
        $this->assertSame($ref.'B', $matrix[1]['control_no'] ?? null);
        $this->assertSame($ref.'C', $matrix[2]['control_no'] ?? null);
        $this->assertSame('Batch A', $matrix[0]['sample_description'] ?? null);
        $this->assertSame('Batch B', $matrix[1]['sample_description'] ?? null);
        $this->assertSame('Batch C', $matrix[2]['sample_description'] ?? null);
    }
}
