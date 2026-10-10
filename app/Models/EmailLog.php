<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $job_order_id
 * @property string $type
 * @property string|null $to
 * @property string $status
 * @property string|null $error
 * @property int $attempts
 * @property \Carbon\CarbonImmutable|null $sent_at
 * @property int|null $created_by
 * @property \Carbon\CarbonImmutable|null $created_at
 */
class EmailLog extends Model
{
    public const TYPE_RESULTS_READY = 'results_ready';

    public const TYPE_REQUEST_RECEIVED = 'request_received';

    public const TYPE_TEST = 'test';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_FAILED = 'failed';

    public const STATUS_SKIPPED = 'skipped';

    /** Customer email types that can be resent from Receiving / History. */
    public const RESENDABLE_TYPES = [
        self::TYPE_RESULTS_READY,
        self::TYPE_REQUEST_RECEIVED,
    ];

    protected $fillable = [
        'job_order_id',
        'type',
        'to',
        'status',
        'error',
        'attempts',
        'sent_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<JobOrder, $this> */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function markSent(): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'sent_at' => now(),
            'error' => null,
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'error' => mb_substr($error, 0, 2000),
        ]);
    }

    /**
     * Customer email history for one job order (newest first).
     *
     * @return list<array<string, mixed>>
     */
    public static function summariesFor(JobOrder $jobOrder): array
    {
        return self::query()
            ->where('job_order_id', $jobOrder->id)
            ->whereIn('type', self::RESENDABLE_TYPES)
            ->latest('id')
            ->limit(20)
            ->get()
            ->map(fn (self $log) => $log->toSummary())
            ->all();
    }

    public static function typeLabel(string $type): string
    {
        return match ($type) {
            self::TYPE_RESULTS_READY => 'Results ready',
            self::TYPE_REQUEST_RECEIVED => 'Request received',
            self::TYPE_TEST => 'Test email',
            default => ucfirst(str_replace('_', ' ', $type)),
        };
    }

    /**
     * @return array{
     *     id: int,
     *     type: string,
     *     type_label: string,
     *     to: string|null,
     *     status: string,
     *     error: string|null,
     *     sent_at: string|null,
     *     created_at: string|null,
     *     reference_no: string|null
     * }
     */
    public function toSummary(): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => self::typeLabel($this->type),
            'to' => $this->to,
            'status' => $this->status,
            'error' => $this->error,
            'sent_at' => $this->sent_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'reference_no' => $this->relationLoaded('jobOrder')
                ? $this->jobOrder?->reference_no
                : null,
        ];
    }
}
