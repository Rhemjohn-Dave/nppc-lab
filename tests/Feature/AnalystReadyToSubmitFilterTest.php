<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystReadyToSubmitFilterTest extends TestCase
{
    use RefreshDatabase;

    public function test_fully_encoded_job_appears_in_ready_to_submit_until_sent_to_head(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Ready For Head Customer',
            'customer_email' => 'ready-for-head@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                ],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $line->refresh();
        $worker = $line->assignee ?? $analyst;

        $this->actingAs($worker)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '25',
                'humidity_pct' => '55',
                'test_report_no' => 'TR-001',
            ])
            ->assertOk();

        $this->actingAs($worker)
            ->post("/analyst/tasks/{$line->id}/complete", $this->completeResultPayload([
                'result_value' => '7.1',
            ]))
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);

        $this->actingAs($worker)
            ->get('/analyst?status=ready_to_submit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('filters.status', 'ready_to_submit')
                ->where('counts.ready_to_submit', fn ($count) => (int) $count >= 1)
                ->where('tasks.0.job_order.id', $job->id)
                ->where('tasks.0.status', 'completed'));

        $this->actingAs($worker)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'Test Analyst', 'prc_id' => '445566'],
                    ['name' => 'Second Analyst', 'prc_id' => '778899'],
                    ['name' => 'Third Analyst', 'prc_id' => '112233'],
                    ['name' => 'Fourth Analyst', 'prc_id' => '334455'],
                ],
            ])
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingReview, $job->status);

        $this->actingAs($worker)
            ->get('/analyst?status=ready_to_submit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('filters.status', 'ready_to_submit')
                ->has('tasks', 0)
                ->where('counts.ready_to_submit', 0));
    }
}
