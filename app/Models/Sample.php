<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sample extends Model
{
    protected $fillable = [
        'job_order_id',
        'classification',
        'sample_code',
        'description',
        'matrix',
        'quantity',
        'unit',
        'remarks',
        'sort_order',
        'submitted_for_review_at',
        'submitted_form_ids',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'submitted_for_review_at' => 'datetime',
            'submitted_form_ids' => 'array',
            'released_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<JobOrder, $this> */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }

    /** @return HasMany<JobOrderAnalysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(JobOrderAnalysis::class);
    }

    /** @return BelongsToMany<AnalysisPackage, $this> */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(AnalysisPackage::class, 'job_order_packages')
            ->using(JobOrderPackage::class)
            ->withPivot(['job_order_id', 'selected_type_ids', 'waived_type_ids', 'discount_percent'])
            ->withTimestamps();
    }
}
