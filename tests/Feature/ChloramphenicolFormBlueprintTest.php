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

class ChloramphenicolFormBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_chloramphenicol_f016_blueprint_with_matrix(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-CAP')->firstOrFail();
        $this->assertFalse($package->is_active);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-CAP')->firstOrFail();
        $this->assertNull($form->analysis_package_id);
        $this->assertSame(
            OfficialAnalysisCatalog::chloramphenicolTypeCodes(),
            AnalysisType::query()
                ->whereIn('id', $form->orderedTypeIds())
                ->get()
                ->sortBy(fn (AnalysisType $type) => array_search(
                    $type->code,
                    OfficialAnalysisCatalog::chloramphenicolTypeCodes(),
                    true,
                ))
                ->pluck('code')
                ->values()
                ->all(),
        );

        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_chloramphenicol_form_fields');
        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());

        $matrix = $revision->fields()->where('name', 'chloramphenicol_f016_matrix')->firstOrFail();
        $columns = $matrix->table_config['columns'] ?? [];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Results', $columns[2]['label'] ?? null);
        $this->assertSame('(ppb)', $columns[2]['sublabel'] ?? null);

        $this->assertTrue($revision->hasCanonicalPdf());
        $this->assertSame(
            'Chloramphenicol ELISA Assay',
            OfficialAnalysisCatalog::methodForCode('FD-CAP'),
        );
    }

    public function test_chloramphenicol_matrix_enriches_control_no_and_sample_description(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-CAP')->firstOrFail();
        $typeIds = $form->orderedTypeIds();
        $this->assertNotEmpty($typeIds);

        $this->post('/intake/job-orders', [
            'customer_name' => 'CAP Customer',
            'customer_email' => 'cap@example.com',
            'classification' => 'Aqua',
            'samples' => [
                ['description' => 'Prawn sample', 'matrix' => 'Solid'],
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

        $this->assertSame('Chloramphenicol (chemical residue)', $bag['results.test_requested'] ?? null);

        $matrix = $bag['chloramphenicol_f016_matrix'] ?? [];
        $this->assertIsArray($matrix);
        $this->assertNotEmpty($matrix[0]['control_no'] ?? null);
        $this->assertSame('Prawn sample', $matrix[0]['sample_description'] ?? null);
        $this->assertArrayHasKey('result', $matrix[0] ?? []);
    }
}
