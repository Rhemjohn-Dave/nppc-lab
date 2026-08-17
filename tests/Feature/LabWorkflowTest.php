<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class LabWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_to_pickup_happy_path(): void
    {
        Mail::fake();

        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Jane Farmer',
            'customer_email' => 'jane@example.com',
            'customer_contact' => '09171234567',
            'samples' => [
                ['description' => 'Fertilizer lot A', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $this->assertSame(JobOrderStatus::DraftSubmitted, $job->status);

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 500, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job->refresh();
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);

        $line->refresh();
        $this->assertSame($analyst->id, $line->assigned_to);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'result_value' => '12.4',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::InProgress, $line->status);
        $this->assertSame('12.4', $line->result_value);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '12.5',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $job->refresh();
        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        Mail::assertSent(ResultsReadyMail::class);

        $this->actingAs($head)
            ->get('/history')
            ->assertOk();

        $this->actingAs($analyst)
            ->get('/history')
            ->assertForbidden();

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign", [
                'review_notes' => 'Signed end of day',
            ])
            ->assertRedirect('/head');

        $job->refresh();
        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        $this->assertNotNull($job->reviewed_at);
        $this->assertSame($head->id, $job->reviewed_by);
    }

    public function test_analyst_only_sees_jobs_after_pricing_and_receive(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Sam Customer',
            'customer_email' => 'sam@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Water sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertSessionHasErrors('job_order');

        $this->actingAs($analyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks', 0));

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '1.0',
            ])
            ->assertSessionHasErrors('analysis');

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tasks', 0));

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('tasks', 1));
    }

    public function test_intake_requires_customer_email(): void
    {
        $this->seed();

        $type = AnalysisType::query()->firstOrFail();

        $this->from('/intake/create')
            ->post('/intake/job-orders', [
                'customer_name' => 'No Email Customer',
                'customer_contact' => '09171234567',
                'samples' => [
                    ['description' => 'Sample A', 'matrix' => 'Solid'],
                ],
                'analysis_type_ids' => [$type->id],
            ])
            ->assertRedirect('/intake/create')
            ->assertSessionHasErrors('customer_email');
    }
}
