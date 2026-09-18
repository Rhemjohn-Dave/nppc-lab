<?php

namespace App\Notifications;

use App\Models\JobOrder;
use App\Support\SyncBroadcastMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Notification;

class ResultsReleased extends Notification
{
    public const AUDIENCE_ANALYST = 'analyst';

    public const AUDIENCE_RECEIVING = 'receiving';

    public function __construct(
        public JobOrder $jobOrder,
        public string $audience = self::AUDIENCE_ANALYST,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $ref = $this->jobOrder->reference_no;
        $isReceiving = $this->audience === self::AUDIENCE_RECEIVING;

        return [
            'type' => 'results_released',
            'job_order_id' => $this->jobOrder->id,
            'reference_no' => $ref,
            'audience' => $this->audience,
            'message' => $isReceiving
                ? "Results for {$ref} were released. You may reprint JO/RFA if needed."
                : "Results for {$ref} were released. Print the dated result form for wet sign.",
            'href' => $isReceiving
                ? "/receiving/{$this->jobOrder->id}"
                : '/analyst?job='.$this->jobOrder->id,
        ];
    }

    public function toBroadcast(object $notifiable): BroadcastMessage
    {
        return SyncBroadcastMessage::make($this->toArray($notifiable));
    }
}
