<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property int $category_id
 * @property string $default_price
 * @property bool $is_active
 * @property int $sort_order
 * @property-read AnalysisCategory $category
 */
class AnalysisType extends Model
{
    protected $fillable = [
        'code',
        'name',
        'category_id',
        'default_price',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<AnalysisCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AnalysisCategory::class, 'category_id');
    }

    /** @return BelongsToMany<User, $this> */
    public function analysts(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'analysis_assignments')->withTimestamps();
    }

    /** @return HasMany<JobOrderAnalysis, $this> */
    public function jobOrderAnalyses(): HasMany
    {
        return $this->hasMany(JobOrderAnalysis::class);
    }
}
