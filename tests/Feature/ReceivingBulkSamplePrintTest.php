<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReceivingBulkSamplePrintTest extends TestCase
{
    use RefreshDatabase;

    public function test_receiving_can_print_bulk_sample_details(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/receiving/bulk-orders', [
                'customer_name' => 'Bayawan Water District',
                'customer_email' => 'bulk-print@example.com',
                'classification' => 'Potability',
                'see_attached_file' => true,
                'analysis_type_ids' => [$type->id],
                'samples' => [
                    [
                        'sample_code' => 'A',
                        'description' => 'KALAMTUKAN SOURCE',
                        'location' => 'BRGY. KALAMTUKAN',
                        'sample_point' => 'SPRING',
                        'collected_time' => '12:04 PM',
                        'matrix' => 'Water',
                    ],
                    [
                        'sample_code' => 'B',
                        'description' => 'VILLASOL SOURCE',
                        'location' => 'BRGY. VILLASOL',
                        'sample_point' => 'TAP',
                        'collected_time' => '12:13 PM',
                        'matrix' => 'Water',
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue((bool) $job->is_receiving_bulk);

        $this->withoutVite();

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/samples/print")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/print-sample-details')
                ->where('jobOrder.reference_no', $job->reference_no)
                ->where('samples.0.index', 1)
                ->where('samples.0.sample_code', 'A')
                ->where('samples.0.description', 'KALAMTUKAN SOURCE')
                ->where('samples.0.location', 'BRGY. KALAMTUKAN')
                ->where('samples.0.sample_point', 'SPRING')
                ->where('samples.0.collected_time', '12:04 PM')
                ->where('samples.1.sample_code', 'B')
                ->where('samples.1.sample_point', 'TAP'));
    }

    public function test_non_bulk_job_cannot_print_sample_details(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'DW-01')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Single Sample',
            'customer_email' => 'single-print@example.com',
            'classification' => 'Potability',
            'samples' => [
                [
                    'classification' => 'Potability',
                    'description' => 'Kitchen tap',
                    'matrix' => 'Liquid',
                    'analysis_type_ids' => [$type->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertFalse((bool) $job->is_receiving_bulk);

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/samples/print")
            ->assertNotFound();
    }
}
