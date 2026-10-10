<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * @property int $id
 * @property int $job_order_id
 * @property int|null $sample_id
 * @property int $analysis_package_id
 * @property list<int>|null $selected_type_ids
 * @property list<int>|null $waived_type_ids
 */
class JobOrderPackage extends Pivot
{
    protected $table = 'job_order_packages';

    public $incrementing = true;

    protected $fillable = [
        'job_order_id',
        'sample_id',
        'analysis_package_id',
        'selected_type_ids',
        'waived_type_ids',
    ];

    protected function casts(): array
    {
        return [
            'selected_type_ids' => 'array',
            'waived_type_ids' => 'array',
        ];
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

    /** @return BelongsTo<AnalysisPackage, $this> */
    public function analysisPackage(): BelongsTo
    {
        return $this->belongsTo(AnalysisPackage::class);
    }
}
