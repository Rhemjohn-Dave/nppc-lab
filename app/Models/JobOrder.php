<?php

namespace App\Models;

use App\Enums\JobOrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $reference_no
 * @property string $customer_name
 * @property string|null $customer_email
 * @property string|null $customer_contact
 * @property string|null $customer_address
 * @property string|null $company_name
 * @property string|null $ownership_type
 * @property string|null $classification
 * @property Carbon|null $sampling_date
 * @property string|null $sampling_time
 * @property string|null $sample_collected_by
 * @property string|null $field_data
 * @property string|null $sample_storage_temp
 * @property string|null $wastewater_source
 * @property string|null $sampling_point
 * @property string|null $other_tests
 * @property JobOrderStatus $status
 * @property string $total_cost
 * @property int|null $received_by
 * @property Carbon|null $received_at
 * @property int|null $reviewed_by
 * @property Carbon|null $reviewed_at
 * @property string|null $review_notes
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Collection<int, Sample> $samples
 * @property-read Collection<int, JobOrderAnalysis> $analyses
 * @property-read User|null $receiver
 * @property-read User|null $reviewer
 */
class JobOrder extends Model
{
    protected $fillable = [
        'reference_no',
        'customer_name',
        'customer_email',
        'customer_contact',
        'customer_address',
        'company_name',
        'ownership_type',
        'classification',
        'sampling_date',
        'sampling_time',
        'sample_collected_by',
        'field_data',
        'sample_storage_temp',
        'wastewater_source',
        'sampling_point',
        'other_tests',
        'status',
        'total_cost',
        'received_by',
        'received_at',
        'reviewed_by',
        'reviewed_at',
        'review_notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => JobOrderStatus::class,
            'total_cost' => 'decimal:2',
            'sampling_date' => 'date',
            'received_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return HasMany<Sample, $this> */
    public function samples(): HasMany
    {
        return $this->hasMany(Sample::class)->orderBy('sort_order');
    }

    /** @return HasMany<JobOrderAnalysis, $this> */
    public function analyses(): HasMany
    {
        return $this->hasMany(JobOrderAnalysis::class);
    }

    /** @return BelongsTo<User, $this> */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** @return BelongsToMany<AnalysisPackage, $this> */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(AnalysisPackage::class, 'job_order_packages')
            ->using(JobOrderPackage::class)
            ->withPivot(['selected_type_ids', 'waived_type_ids'])
            ->withTimestamps();
    }

    /**
     * Package member analysis types the customer unchecked (print as "-").
     *
     * @return list<int>
     */
    public function waivedTypeIds(): array
    {
        $this->loadMissing('packages');

        $ids = [];
        foreach ($this->packages as $package) {
            $raw = $package->pivot->waived_type_ids ?? null;
            if (! is_array($raw)) {
                continue;
            }
            foreach ($raw as $id) {
                $ids[] = (int) $id;
            }
        }

        return array_values(array_unique($ids));
    }

    public function recalculateTotal(): void
    {
        $this->update([
            'total_cost' => $this->analyses()->sum('total_cost'),
        ]);
    }
}
