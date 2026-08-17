<?php

namespace App\Notifications;

use App\Models\JobOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class JobOrderPendingReview extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public JobOrder $jobOrder) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'job_order_ready_to_sign',
            'job_order_id' => $this->jobOrder->id,
            'reference_no' => $this->jobOrder->reference_no,
            'message' => "Job order {$this->jobOrder->reference_no} is finished and awaiting end-of-day signature.",
        ];
    }
}
