<?php

namespace App\Models;

use App\Enums\JobOrderAnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property int|null $analysis_type_id
 * @property string $name
 * @property string|null $category
 * @property string|null $category_label
 * @property int $quantity
 * @property string $unit_price
 * @property string $total_cost
 * @property JobOrderAnalysisStatus $status
 * @property int|null $assigned_to
 * @property string|null $result_value
 * @property string|null $result_measurement
 * @property string|null $result_unit
 * @property string|null $result_remarks
 * @property Carbon|null $completed_at
 * @property-read JobOrder $jobOrder
 * @property-read AnalysisType|null $analysisType
 * @property-read User|null $assignee
 */
class JobOrderAnalysis extends Model
{
    protected $fillable = [
        'job_order_id',
        'analysis_type_id',
        'name',
        'category',
        'category_label',
        'quantity',
        'unit_price',
        'total_cost',
        'status',
        'assigned_to',
        'result_value',
        'result_measurement',
        'result_unit',
        'result_remarks',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobOrderAnalysisStatus::class,
            'unit_price' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'completed_at' => 'datetime',
        ];
    }

    public function resolvedCategoryLabel(): string
    {
        return $this->category_label
            ?: $this->category
            ?: 'Other';
    }

    /** @return BelongsTo<JobOrder, $this> */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /** @return BelongsTo<AnalysisType, $this> */
    public function analysisType(): BelongsTo
    {
        return $this->belongsTo(AnalysisType::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
