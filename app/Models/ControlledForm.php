<?php

namespace App\Models;

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderVariant;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $form_code
 * @property string $name
 * @property string|null $description
 * @property string|null $department
 * @property ControlledFormCategory $category
 * @property JobOrderVariant|null $job_order_variant
 * @property int|null $current_revision_id
 * @property string|null $combination_key
 * @property int|null $analysis_package_id
 * @property int $analyst_signatory_slots
 * @property bool $analyst_require_prc
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ControlledFormRevision|null $currentRevision
 * @property-read Collection<int, ControlledFormRevision> $revisions
 * @property-read Collection<int, AnalysisType> $analysisTypes
 * @property-read AnalysisPackage|null $analysisPackage
 */
class ControlledForm extends Model
{
    public const RFA_FORM_CODE = 'NPPC-LAB-FRM-001';

    public const RFA_AQUA_FORM_CODE = 'NPPC-LAB-FRM-AQUA';

    protected $fillable = [
        'form_code',
        'name',
        'description',
        'department',
        'category',
        'job_order_variant',
        'current_revision_id',
        'combination_key',
        'analysis_package_id',
        'analyst_signatory_slots',
        'analyst_require_prc',
    ];

    protected function casts(): array
    {
        return [
            'category' => ControlledFormCategory::class,
            'job_order_variant' => JobOrderVariant::class,
            'analyst_signatory_slots' => 'integer',
            'analyst_require_prc' => 'boolean',
        ];
    }

    /** @return HasMany<ControlledFormRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ControlledFormRevision::class)->orderByDesc('id');
    }

    /** @return BelongsTo<ControlledFormRevision, $this> */
    public function currentRevision(): BelongsTo
    {
        return $this->belongsTo(ControlledFormRevision::class, 'current_revision_id');
    }

    /** @return BelongsTo<AnalysisPackage, $this> */
    public function analysisPackage(): BelongsTo
    {
        return $this->belongsTo(AnalysisPackage::class);
    }

    /** @return BelongsToMany<AnalysisType, $this> */
    public function analysisTypes(): BelongsToMany
    {
        return $this->belongsToMany(
            AnalysisType::class,
            'controlled_form_binding_types',
        )->withPivot('slot')->withTimestamps()->orderByPivot('slot');
    }

    public function activeRevision(): ?ControlledFormRevision
    {
        $current = $this->currentRevision;

        if ($current && $current->status === ControlledFormRevisionStatus::Active) {
            return $current;
        }

        return $this->revisions()
            ->where('status', ControlledFormRevisionStatus::Active)
            ->first();
    }

    /**
     * @return array<int, int>
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
     * Default / general Job Order form (LSP 7.1 FO1).
     */
    public static function jobOrderForm(): ?self
    {
        return static::jobOrderFormForVariant(JobOrderVariant::General);
    }

    public static function jobOrderFormFor(?JobOrder $jobOrder): ?self
    {
        return static::jobOrderFormForVariant(
            OfficialAnalysisCatalog::variantForClassification($jobOrder?->classification),
        );
    }

    public static function jobOrderFormForVariant(JobOrderVariant $variant): ?self
    {
        $preferredCode = $variant === JobOrderVariant::Aqua
            ? self::RFA_AQUA_FORM_CODE
            : self::RFA_FORM_CODE;

        $byVariant = static::query()
            ->where('category', ControlledFormCategory::JobOrder)
            ->where('job_order_variant', $variant)
            ->orderBy('id')
            ->first();

        if ($byVariant) {
            return $byVariant;
        }

        $byCode = static::query()
            ->where('category', ControlledFormCategory::JobOrder)
            ->where('form_code', $preferredCode)
            ->first();

        if ($byCode) {
            return $byCode;
        }

        // Aqua must not silently fall back to the general template when misconfigured.
        if ($variant === JobOrderVariant::Aqua) {
            return null;
        }

        return static::query()
            ->where('category', ControlledFormCategory::JobOrder)
            ->where(function ($query) {
                $query->whereNull('job_order_variant')
                    ->orWhere('job_order_variant', JobOrderVariant::General);
            })
            ->orderBy('id')
            ->first();
    }
}
