<?php

namespace App\Models;

use App\Enums\ControlledFormRevisionStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int $controlled_form_id
 * @property string $revision
 * @property ControlledFormRevisionStatus $status
 * @property Carbon|null $effective_date
 * @property string|null $notes
 * @property string|null $original_name
 * @property string|null $original_path
 * @property string|null $canonical_pdf_path
 * @property string|null $original_mime
 * @property int $page_count
 * @property string|null $page_width_mm
 * @property string|null $page_height_mm
 * @property string $fill_mode
 * @property string|null $sha256
 * @property int|null $created_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ControlledForm $form
 * @property-read User|null $creator
 * @property-read User|null $approver
 * @property-read Collection<int, ControlledFormField> $fields
 */
class ControlledFormRevision extends Model
{
    public const FILL_MODE_OVERLAY = 'overlay';

    public const FILL_MODE_NAMED = 'named';

    protected $fillable = [
        'controlled_form_id',
        'revision',
        'status',
        'effective_date',
        'notes',
        'original_name',
        'original_path',
        'canonical_pdf_path',
        'original_mime',
        'page_count',
        'page_width_mm',
        'page_height_mm',
        'fill_mode',
        'sha256',
        'created_by',
        'approved_by',
        'approved_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ControlledFormRevisionStatus::class,
            'effective_date' => 'date',
            'approved_at' => 'datetime',
            'page_width_mm' => 'decimal:2',
            'page_height_mm' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<ControlledForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(ControlledForm::class, 'controlled_form_id');
    }

    /** @return HasMany<ControlledFormField, $this> */
    public function fields(): HasMany
    {
        return $this->hasMany(ControlledFormField::class)->orderBy('z_order')->orderBy('id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function hasCanonicalPdf(): bool
    {
        return is_string($this->canonical_pdf_path)
            && $this->canonical_pdf_path !== ''
            && Storage::disk('local')->exists($this->canonical_pdf_path);
    }

    public function canonicalAbsolutePath(): string
    {
        return Storage::disk('local')->path((string) $this->canonical_pdf_path);
    }

    public function originalAbsolutePath(): ?string
    {
        if (! is_string($this->original_path) || $this->original_path === '') {
            return null;
        }

        if (! Storage::disk('local')->exists($this->original_path)) {
            return null;
        }

        return Storage::disk('local')->path($this->original_path);
    }

    public function isOverlay(): bool
    {
        return $this->fill_mode !== self::FILL_MODE_NAMED;
    }

    public function isNamed(): bool
    {
        return $this->fill_mode === self::FILL_MODE_NAMED;
    }

    /**
     * @return array{width: float, height: float, unit: string}
     */
    public function page(): array
    {
        return [
            'width' => (float) ($this->page_width_mm ?: 215.9),
            'height' => (float) ($this->page_height_mm ?: 330.2),
            'unit' => 'mm',
        ];
    }
}
