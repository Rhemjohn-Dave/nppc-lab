<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisPackage;
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
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

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
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);
        Mail::assertNothingSent();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
        Mail::assertNothingSent();

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
        Mail::assertSent(ResultsReadyMail::class);
    }

    public function test_analyst_only_sees_jobs_after_pricing_and_receive(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

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
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks', 1)
                ->where('jobs.total', 1)
                ->where('jobs.from', 1)
                ->where('jobs.to', 1)
                ->has('jobs.links'));
    }

    public function test_intake_requires_customer_email(): void
    {
        $this->seed();

        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

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

    public function test_head_can_batch_sign_finished_jobs(): void
    {
        Mail::fake();
        $this->seed();

        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $first = $this->createFinishedJob('Batch One', 'batch-one@example.com');
        $second = $this->createFinishedJob('Batch Two', 'batch-two@example.com');

        $this->actingAs($head)
            ->post('/head/sign-batch', [
                'job_order_ids' => [$first->id, $second->id],
                'review_notes' => 'End of day batch',
            ])
            ->assertRedirect('/head');

        $first->refresh();
        $second->refresh();
        $this->assertNotNull($first->reviewed_at);
        $this->assertNotNull($second->reviewed_at);
        $this->assertSame($head->id, $first->reviewed_by);
        $this->assertSame($head->id, $second->reviewed_by);
    }

    public function test_head_can_return_a_line_to_the_analyst(): void
    {
        Mail::fake();
        $this->seed();

        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $job = $this->createFinishedJob('Return Customer', 'return@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($head)
            ->post("/head/{$job->id}/return", [
                'analysis_ids' => [$line->id],
                'review_notes' => 'Please recheck the pH reading.',
            ])
            ->assertRedirect('/head');

        $job->refresh();
        $line->refresh();
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);
        $this->assertSame(JobOrderAnalysisStatus::Returned, $line->status);
        $this->assertNull($line->result_value);

        $this->actingAs($analyst)
            ->get('/analyst?status=returned')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('filters.status', 'returned')
                ->has('tasks', 1)
                ->where('tasks.0.status', JobOrderAnalysisStatus::Returned->value)
                ->where('tasks.0.job_order.review_notes', 'Please recheck the pH reading.')
                ->where('jobs.total', 1));
    }

    public function test_completed_analysis_cannot_be_overwritten_without_return(): void
    {
        Mail::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Guard Customer',
            'customer_email' => 'guard@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Guard sample', 'matrix' => 'Liquid'],
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

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '10',
                'result_unit' => 'mg/L',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '99',
                'result_unit' => 'mg/L',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors('analysis');

        $line->refresh();
        $this->assertSame('10', $line->result_value);
    }

    public function test_filtered_queue_links_return_expected_filters(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('dashboard'));

        $this->actingAs($receiving)
            ->get('/receiving?status=draft_submitted')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('receiving/index')
                ->where('filters.status', 'draft_submitted'));

        $this->actingAs($head)
            ->get('/head?tab=unsigned')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('head/index')
                ->where('filters.tab', 'unsigned'));

        $this->actingAs($head)
            ->get('/history?status=unsigned')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('history/index')
                ->where('filters.status', 'unsigned'));

        $this->actingAs($analyst)
            ->get('/analyst?status=returned')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->where('filters.status', 'returned'));
    }

    public function test_completing_tests_does_not_send_to_head_until_designated_analyst_submits(): void
    {
        Mail::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $other = User::query()->create([
            'name' => 'Other Analyst',
            'email' => 'other-analyst@nppc.local',
            'password' => 'password',
            'email_verified_at' => now(),
        ]);
        $other->assignRole('analyst');

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Consolidate Customer',
            'customer_email' => 'consolidate@example.com',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 225,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertSessionHasErrors('job_order');

        $job->refresh();
        $lines = $job->analyses()->with('assignee')->get();

        foreach ($lines as $line) {
            $worker = $line->assignee ?? $analyst;
            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => 'Passed',
                ])
                ->assertRedirect();
        }

        $job->refresh();
        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);

        $this->actingAs($other)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertSessionHasErrors('job_order');

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
    }

    private function createFinishedJob(string $customerName, string $email): JobOrder
    {
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => $customerName,
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Finished sample', 'matrix' => 'Liquid'],
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

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '1.0',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review")
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingReview, $job->status);

        return $job;
    }
}
