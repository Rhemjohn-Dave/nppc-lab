<?php

namespace Tests\Feature;

use App\Events\LabQueueUpdated;
use App\Models\User;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabQueueUpdatedTest extends TestCase
{
    use RefreshDatabase;

    public function test_event_broadcasts_immediately_on_scoped_private_channels(): void
    {
        $event = new LabQueueUpdated(
            [LabQueueUpdated::SCOPE_RECEIVING, LabQueueUpdated::SCOPE_ANALYST],
            42,
        );

        $this->assertInstanceOf(ShouldBroadcastNow::class, $event);
        $this->assertSame('LabQueueUpdated', $event->broadcastAs());
        $this->assertSame([
            'scopes' => ['receiving', 'analyst'],
            'job_order_id' => 42,
        ], $event->broadcastWith());

        $channels = collect($event->broadcastOn())
            ->map(fn (PrivateChannel $channel) => $channel->name)
            ->all();

        $this->assertSame([
            'private-lab.queue.receiving',
            'private-lab.queue.analyst',
        ], $channels);
    }

    public function test_all_scope_targets_every_lab_queue_channel(): void
    {
        $event = new LabQueueUpdated([LabQueueUpdated::SCOPE_ALL]);

        $channels = collect($event->broadcastOn())
            ->map(fn (PrivateChannel $channel) => $channel->name)
            ->all();

        $this->assertSame([
            'private-lab.queue.receiving',
            'private-lab.queue.analyst',
            'private-lab.queue.head',
        ], $channels);
    }

    public function test_queue_channels_authorize_expected_roles(): void
    {
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();

        $this->assertTrue($receiving->hasRole(['receiving', 'admin']));
        $this->assertFalse($analyst->hasRole(['receiving', 'admin']));
        $this->assertTrue($admin->hasRole(['receiving', 'admin']));

        $this->assertTrue($analyst->hasRole(['analyst', 'admin']));
        $this->assertFalse($receiving->hasRole(['analyst', 'admin']));

        $this->assertTrue($head->hasRole(['head_analysis', 'admin']));
        $this->assertFalse($analyst->hasRole(['head_analysis', 'admin']));
        $this->assertTrue($admin->hasRole(['head_analysis', 'admin']));
    }
}
