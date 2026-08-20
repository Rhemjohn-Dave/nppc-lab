<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class LabQueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public const SCOPE_RECEIVING = 'receiving';

    public const SCOPE_ANALYST = 'analyst';

    public const SCOPE_HEAD = 'head';

    public const SCOPE_ALL = 'all';

    /**
     * @param  list<string>  $scopes
     */
    public function __construct(
        public array $scopes,
        public ?int $jobOrderId = null,
    ) {
        $this->scopes = array_values(array_unique(array_map('strval', $scopes)));
    }

    /**
     * @param  list<string>|string  $scopes
     */
    public static function notify(array|string $scopes, ?int $jobOrderId = null): void
    {
        $list = is_array($scopes) ? $scopes : [$scopes];

        event(new self($list, $jobOrderId));
    }

    /**
     * @return array<int, PrivateChannel>
     */
    public function broadcastOn(): array
    {
        $channels = [];

        if ($this->includes(self::SCOPE_RECEIVING) || $this->includes(self::SCOPE_ALL)) {
            $channels[] = new PrivateChannel('lab.queue.receiving');
        }

        if ($this->includes(self::SCOPE_ANALYST) || $this->includes(self::SCOPE_ALL)) {
            $channels[] = new PrivateChannel('lab.queue.analyst');
        }

        if ($this->includes(self::SCOPE_HEAD) || $this->includes(self::SCOPE_ALL)) {
            $channels[] = new PrivateChannel('lab.queue.head');
        }

        return $channels;
    }

    public function broadcastAs(): string
    {
        return 'LabQueueUpdated';
    }

    /**
     * @return array{scopes: list<string>, job_order_id: int|null}
     */
    public function broadcastWith(): array
    {
        return [
            'scopes' => $this->scopes,
            'job_order_id' => $this->jobOrderId,
        ];
    }

    private function includes(string $scope): bool
    {
        return in_array($scope, $this->scopes, true);
    }
}
