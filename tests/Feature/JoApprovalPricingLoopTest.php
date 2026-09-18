<?php

namespace Tests\Feature;

use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\JobOrderJoApproved;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class JoApprovalPricingLoopTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_queues_head_once_and_reprice_after_approve_does_not_loop(): void
    {
        Notification::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Loop Customer',
            'customer_email' => 'loop@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Loop sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        Notification::fake();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 250, 'quantity' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pricing saved and sent to Head for Job Order approval.');

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingJoApproval, $job->status);
        $this->assertNull($job->jo_approved_at);

        Notification::assertNothingSent();

        $this->actingAs($head)
            ->get('/head/jo?tab=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('head/jo')
                ->where('counts.pending', 1));

        $this->approveJobOrder($job);

        $job->refresh();
        $this->assertSame(JobOrderStatus::JoApproved, $job->status);
        $this->assertNotNull($job->jo_approved_at);

        Notification::assertSentTo(
            [$receiving],
            JobOrderJoApproved::class,
        );

        Notification::fake();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 300, 'quantity' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pricing updated.');

        $job->refresh();
        $this->assertSame(JobOrderStatus::JoApproved, $job->status);
        $this->assertNotNull($job->jo_approved_at);
        $this->assertSame('300.00', number_format((float) $line->fresh()->unit_price, 2));

        Notification::assertNothingSent();

        $this->actingAs($head)
            ->get('/head/jo?tab=pending')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('head/jo')
                ->where('counts.pending', 0));

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}/print")
            ->assertOk();

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->assertSame(JobOrderStatus::InAnalysis, $job->fresh()->status);
    }

    public function test_reprice_while_awaiting_head_keeps_pending_without_new_queue_status(): void
    {
        Notification::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Pending Reprice',
            'customer_email' => 'pending-reprice@example.com',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        Notification::fake();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 150, 'quantity' => 1],
                ],
            ])
            ->assertRedirect()
            ->assertSessionHas('success', 'Pricing updated (still awaiting Head JO approval).');

        $this->assertSame(JobOrderStatus::PendingJoApproval, $job->fresh()->status);
        Notification::assertNothingSent();
    }

    public function test_heal_restores_desynced_jo_approved_rows(): void
    {
        $this->seed();

        $job = JobOrder::query()->create([
            'reference_no' => '26-HEAL1',
            'customer_name' => 'Heal Customer',
            'status' => JobOrderStatus::PendingJoApproval,
            'total_cost' => 100,
            'jo_approved_at' => now(),
            'jo_approved_by' => User::where('email', 'head@nppc.local')->value('id'),
        ]);

        $healed = \App\Services\JobOrderService::healJoApprovalStatusDesync();

        $this->assertGreaterThanOrEqual(1, $healed);
        $this->assertSame(JobOrderStatus::JoApproved, $job->fresh()->status);
    }
}
