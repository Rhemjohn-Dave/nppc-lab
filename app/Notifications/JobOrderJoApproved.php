<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Support\SyncBroadcastMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class JobOrderJoApproved extends Notification
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
            'type' => 'job_order_jo_approved',
            'job_order_id' => $this->jobOrder->id,
            'reference_no' => $this->jobOrder->reference_no,
            'customer_name' => $this->jobOrder->customer_name,
            'message' => "Job order {$this->jobOrder->reference_no} was approved by Head. You can print JO copies and send to analysts.",
            'href' => "/receiving/{$this->jobOrder->id}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return SyncBroadcastMessage::make($this->toArray($notifiable));
    }
}
