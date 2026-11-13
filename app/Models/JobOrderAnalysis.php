<?php

namespace App\Models;

use App\Enums\JobOrderAnalysisStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $job_order_id
 * @property int|null $sample_id
 * @property int|null $analysis_type_id
 * @property string $name
 * @property string|null $category
 * @property string|null $category_label
 * @property int $quantity
 * @property string $unit_price
 * @property string $discount_percent
 * @property string $total_cost
 * @property JobOrderAnalysisStatus $status
 * @property int|null $assigned_to
 * @property string|null $result_value
 * @property string|null $result_raw
 * @property string|null $result_finish
 * @property string|null $result_chlorinated
 * @property string|null $result_pass_fail
 * @property string|null $result_measurement
 * @property string|null $result_unit
 * @property string|null $result_remarks
 * @property string|null $result_remarks_raw
 * @property string|null $result_remarks_finish
 * @property string|null $result_remarks_chlorinated
 * @property string|null $result_method
 * @property string|null $accreditation_footnote
 * @property Carbon|null $performed_at
 * @property Carbon|null $completed_at
 * @property-read JobOrder $jobOrder
 * @property-read Sample|null $sample
 * @property-read AnalysisType|null $analysisType
 * @property-read User|null $assignee
 */
class JobOrderAnalysis extends Model
{
    protected $fillable = [
        'job_order_id',
        'sample_id',
        'analysis_type_id',
        'name',
        'category',
        'category_label',
        'quantity',
        'unit_price',
        'discount_percent',
        'total_cost',
        'status',
        'assigned_to',
        'result_value',
        'result_raw',
        'result_finish',
        'result_chlorinated',
        'result_pass_fail',
        'result_measurement',
        'result_unit',
        'result_remarks',
        'result_remarks_raw',
        'result_remarks_finish',
        'result_remarks_chlorinated',
        'result_method',
        'accreditation_footnote',
        'performed_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobOrderAnalysisStatus::class,
            'unit_price' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'total_cost' => 'decimal:2',
            'performed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public const LABORATORY_SERVICES_CATEGORY = 'laboratory_services';

    public function isSamplePreparation(): bool
    {
        return $this->analysisType?->isSamplePreparation()
            ?? AnalysisType::isSamplePreparationName($this->name);
    }

    public function isLaboratoryServiceLine(): bool
    {
        return ($this->category ?? '') === self::LABORATORY_SERVICES_CATEGORY;
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

    /** @return BelongsTo<Sample, $this> */
    public function sample(): BelongsTo
    {
        return $this->belongsTo(Sample::class);
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
