<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Support\SyncBroadcastMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class JobOrderPendingReview extends Notification
{
    public function __construct(public JobOrder $jobOrder) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_order_ready_to_sign',
            'job_order_id' => $this->jobOrder->id,
            'reference_no' => $this->jobOrder->reference_no,
            'message' => "Job order {$this->jobOrder->reference_no} is ready for Head review.",
            'href' => "/head/{$this->jobOrder->id}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return SyncBroadcastMessage::make($this->toArray($notifiable));
    }
}
