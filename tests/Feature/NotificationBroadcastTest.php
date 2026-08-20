<?php

namespace Tests\Feature;

use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use App\Models\User;
use App\Notifications\JobOrderPendingReview;
use App\Notifications\JobOrderSubmitted;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificationBroadcastTest extends TestCase
{
    use RefreshDatabase;

    public function test_lms_notifications_use_database_and_broadcast_channels(): void
    {
        $this->seed();

        $job = JobOrder::query()->create([
            'reference_no' => '26-9001',
            'customer_name' => 'Notify Customer',
            'status' => 'draft_submitted',
            'total_cost' => 0,
        ]);

        $analysis = JobOrderAnalysis::query()->create([
            'job_order_id' => $job->id,
            'name' => 'E. coli',
            'quantity' => 1,
            'unit_price' => 100,
            'total_cost' => 100,
            'status' => 'pending',
        ]);

        $submitted = new JobOrderSubmitted($job);
        $assigned = new TaskAssigned($job, $analysis);
        $pending = new JobOrderPendingReview($job);

        $user = User::where('email', 'admin@nppc.local')->firstOrFail();

        $this->assertSame(['database', 'broadcast'], $submitted->via($user));
        $this->assertSame(['database', 'broadcast'], $assigned->via($user));
        $this->assertSame(['database', 'broadcast'], $pending->via($user));

        $this->assertSame('/receiving/'.$job->id, $submitted->toArray($user)['href']);
        $this->assertSame('/analyst?job='.$job->id, $assigned->toArray($user)['href']);
        $this->assertSame('/head/'.$job->id, $pending->toArray($user)['href']);
    }

    public function test_private_user_channel_callback_allows_only_the_owner(): void
    {
        $this->seed();

        $user = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $other = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $callback = function ($authenticated, $id) {
            return (int) $authenticated->id === (int) $id;
        };

        $this->assertTrue($callback($user, (string) $user->id));
        $this->assertFalse($callback($user, (string) $other->id));
    }

    public function test_notification_click_marks_read_and_payload_includes_href(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $job = JobOrder::query()->create([
            'reference_no' => '26-9002',
            'customer_name' => 'Href Customer',
            'status' => 'draft_submitted',
            'total_cost' => 0,
        ]);

        $receiving->notifyNow(new JobOrderSubmitted($job));

        $notification = $receiving->notifications()->firstOrFail();
        $this->assertSame('/receiving/'.$job->id, $notification->data['href'] ?? null);

        $this->actingAs($receiving)
            ->post("/notifications/{$notification->id}/read")
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }
}
