<?php

namespace App\Models;

use App\Enums\GeneratedDocumentStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property string $document_number
 * @property int $controlled_form_id
 * @property int $controlled_form_revision_id
 * @property string $source_type
 * @property int $source_id
 * @property int|null $generated_by
 * @property Carbon $generated_at
 * @property string $pdf_path
 * @property GeneratedDocumentStatus $status
 * @property string|null $sha256
 * @property string|null $template_sha256
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ControlledForm $form
 * @property-read ControlledFormRevision $revision
 * @property-read User|null $generator
 * @property-read Model $source
 * @property-read Collection<int, PrintLog> $printLogs
 */
class GeneratedDocument extends Model
{
    protected $fillable = [
        'document_number',
        'controlled_form_id',
        'controlled_form_revision_id',
        'source_type',
        'source_id',
        'generated_by',
        'generated_at',
        'pdf_path',
        'status',
        'sha256',
        'template_sha256',
    ];

    protected function casts(): array
    {
        return [
            'status' => GeneratedDocumentStatus::class,
            'generated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ControlledForm, $this> */
    public function form(): BelongsTo
    {
        return $this->belongsTo(ControlledForm::class, 'controlled_form_id');
    }

    /** @return BelongsTo<ControlledFormRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ControlledFormRevision::class, 'controlled_form_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /** @return MorphTo<Model, $this> */
    public function source(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return HasMany<PrintLog, $this> */
    public function printLogs(): HasMany
    {
        return $this->hasMany(PrintLog::class);
    }

    public function fileExists(): bool
    {
        return $this->pdf_path !== '' && Storage::disk('local')->exists($this->pdf_path);
    }

    public function absolutePath(): string
    {
        return Storage::disk('local')->path($this->pdf_path);
    }
}
