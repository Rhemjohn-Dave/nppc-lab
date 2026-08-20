<?php

namespace App\Notifications;

use App\Models\JobOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class JobOrderSubmitted extends Notification implements ShouldQueue
{
    use Queueable;

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
            'type' => 'job_order_submitted',
            'job_order_id' => $this->jobOrder->id,
            'reference_no' => $this->jobOrder->reference_no,
            'customer_name' => $this->jobOrder->customer_name,
            'message' => "New job order {$this->jobOrder->reference_no} submitted by {$this->jobOrder->customer_name}.",
            'href' => "/receiving/{$this->jobOrder->id}",
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return new BroadcastMessage($this->toArray($notifiable));
    }
}
