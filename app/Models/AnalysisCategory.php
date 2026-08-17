<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $slug
 * @property string $name
 * @property int $sort_order
 * @property bool $is_active
 */
class AnalysisCategory extends Model
{
    protected $fillable = [
        'slug',
        'name',
        'sort_order',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /** @return HasMany<AnalysisType, $this> */
    public function analysisTypes(): HasMany
    {
        return $this->hasMany(AnalysisType::class, 'category_id');
    }
}
