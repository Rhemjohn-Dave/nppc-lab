<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FoodMatrixHeaderValuesTest extends TestCase
{
    use RefreshDatabase;

    public function test_food_matrix_forms_resolve_control_no_and_sample_description_in_bag(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $typeIds = AnalysisType::query()
            ->whereIn('code', OfficialAnalysisCatalog::milkAnalysisTypeCodes())
            ->pluck('id')
            ->all();
        $this->assertNotEmpty($typeIds);

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'Matrix Header Customer',
                'customer_email' => 'matrix-header@example.com',
                'classification' => 'Others: Other Food Analysis',
                'samples' => [
                    ['description' => 'Fresh cow milk', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => array_slice($typeIds, 0, 2),
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('Fresh cow milk', $job->samples->first()?->description);

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $bag = app(FieldValueResolver::class)->forResult($revision, $job->fresh(['samples', 'analyses.analysisType']), null, $form);

        $this->assertSame($job->reference_no, $bag['results.control_no']);
        $this->assertSame('Fresh cow milk', $bag['results.sample_description']);

        $matrixField = $revision->fields()->where('name', 'milk_f016_matrix')->firstOrFail();
        $config = $matrixField->table_config;
        $resultCol = collect($config['columns'] ?? [])->firstWhere('key', 'result');
        $this->assertIsArray($resultCol);
        $this->assertSame('results.control_no', $resultCol['label_data_source'] ?? null);
        $this->assertSame('results.sample_description', $resultCol['sublabel_data_source'] ?? null);
    }

    public function test_proximate_blueprint_result_column_has_header_data_sources(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-PROX')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $matrix = $revision->fields()
            ->where('field_type', 'dynamic_test_matrix')
            ->firstOrFail();
        $resultCol = collect($matrix->table_config['columns'] ?? [])->firstWhere('key', 'result');

        $this->assertSame('results.control_no', $resultCol['label_data_source'] ?? null);
        $this->assertSame('results.sample_description', $resultCol['sublabel_data_source'] ?? null);
    }
}
