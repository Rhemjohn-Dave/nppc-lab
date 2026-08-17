<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TaskAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public JobOrder $jobOrder,
        public JobOrderAnalysis $analysis,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'task_assigned',
            'job_order_id' => $this->jobOrder->id,
            'analysis_id' => $this->analysis->id,
            'reference_no' => $this->jobOrder->reference_no,
            'analysis_name' => $this->analysis->name,
            'message' => "You were assigned {$this->analysis->name} on {$this->jobOrder->reference_no}.",
        ];
    }
}
