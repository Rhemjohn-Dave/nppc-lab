<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class JobOrderSpecimenTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_stores_specimen_for_food_proximate_and_resolves_result_key(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Food Specimen Customer',
            'customer_email' => 'specimen@example.com',
            'classification' => 'Academic/Research',
            'specimen' => 'Dried fish fillet',
            'samples' => [
                ['description' => 'Fish sample', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertSame('Dried fish fillet', $job->specimen);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertSame('Dried fish fillet', $bag['results.specimen']);
        $this->assertSame('Dried fish fillet', $bag['job_orders.specimen']);
    }

    public function test_water_only_intake_leaves_specimen_null(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Water Only Customer',
            'customer_email' => 'water-only@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Effluent', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertNull($job->specimen);

        $bag = app(FieldValueResolver::class)->jobOrderBag($job);
        $this->assertNull($bag['results.specimen'] ?? null);
    }
}
