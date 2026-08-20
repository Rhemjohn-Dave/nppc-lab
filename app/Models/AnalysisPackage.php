<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string|null $description
 * @property int|null $category_id
 * @property string $default_price
 * @property list<string>|null $classifications
 * @property string|null $form_code
 * @property int|null $signatory_user_id
 * @property bool $is_active
 * @property int $sort_order
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $signatory
 * @property-read AnalysisCategory|null $category
 * @property-read Collection<int, AnalysisType> $analysisTypes
 */
class AnalysisPackage extends Model
{
    protected $fillable = [
        'code',
        'name',
        'description',
        'category_id',
        'default_price',
        'classifications',
        'form_code',
        'signatory_user_id',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'default_price' => 'decimal:2',
            'classifications' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function signatory(): BelongsTo
    {
        return $this->belongsTo(User::class, 'signatory_user_id');
    }

    /** @return BelongsTo<AnalysisCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AnalysisCategory::class, 'category_id');
    }

    /** @return BelongsToMany<AnalysisType, $this> */
    public function analysisTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            AnalysisType::class,
            'analysis_package_types',
        )->withPivot('slot')->withTimestamps()->orderByPivot('slot');
    }

    /** @return BelongsToMany<JobOrder, $this> */
    public function jobOrders(): BelongsToMany
    {
        return $this->belongsToMany(JobOrder::class, 'job_order_packages')
            ->using(JobOrderPackage::class)
            ->withPivot(['selected_type_ids', 'waived_type_ids'])
            ->withTimestamps();
    }

    /** @return HasOne<ControlledForm, $this> */
    public function resultForm(): HasOne
    {
        return $this->hasOne(ControlledForm::class, 'analysis_package_id');
    }

    /**
     * @return list<int>
     */
    public function orderedTypeIds(): array
    {
        return $this->analysisTypes()
            ->orderByPivot('slot')
            ->pluck('analysis_types.id')
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>  $typeIds
     */
    public function syncTypes(array $typeIds): void
    {
        $ids = array_values(array_unique(array_map('intval', $typeIds)));
        $payload = [];

        foreach ($ids as $slot => $typeId) {
            $payload[$typeId] = ['slot' => $slot + 1];
        }

        $this->analysisTypes()->sync($payload);
    }

    public function matchesClassification(?string $classification): bool
    {
        $tags = $this->classifications ?? [];
        if ($tags === []) {
            return true;
        }

        $value = mb_strtolower(trim((string) $classification));
        if ($value === '') {
            return false;
        }

        foreach ($tags as $tag) {
            if (str_contains($value, mb_strtolower((string) $tag))) {
                return true;
            }
        }

        return false;
    }
}
