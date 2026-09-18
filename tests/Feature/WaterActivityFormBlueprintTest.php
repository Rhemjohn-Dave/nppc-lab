<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Support\OfficialAnalysisCatalog;
use Database\Seeders\ControlledFormDefaultsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WaterActivityFormBlueprintTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_imports_water_activity_f016_blueprint_with_matrix(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-WA')->firstOrFail();
        $this->assertNull($form->analysis_package_id);
        $this->assertSame(
            OfficialAnalysisCatalog::waterActivityTypeCodes(),
            AnalysisType::query()
                ->whereIn('id', $form->orderedTypeIds())
                ->get()
                ->sortBy(fn (AnalysisType $type) => array_search(
                    $type->code,
                    OfficialAnalysisCatalog::waterActivityTypeCodes(),
                    true,
                ))
                ->pluck('code')
                ->values()
                ->all(),
        );

        $revision = $form->revisions()
            ->where('revision', ControlledFormDefaultsSeeder::DEFAULT_REVISION)
            ->firstOrFail();

        $blueprint = config('result_water_activity_form_fields');
        $this->assertSame(count($blueprint['fields']), $revision->fields()->count());

        $matrix = $revision->fields()->where('name', 'water_activity_f016_matrix')->firstOrFail();
        $columns = $matrix->table_config['columns'] ?? [];
        $this->assertSame(
            ['control_no', 'sample_description', 'result'],
            array_values(array_map(static fn (array $col): string => (string) $col['key'], $columns)),
        );
        $this->assertSame('Water Activity Meter', $columns[2]['sublabel'] ?? null);

        $this->assertTrue($revision->hasCanonicalPdf());
        $this->assertSame(
            'Decagon AquaLab Series 3TE',
            OfficialAnalysisCatalog::methodForCode('WA-01'),
        );
    }
}
