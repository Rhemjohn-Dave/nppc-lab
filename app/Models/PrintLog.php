<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $generated_document_id
 * @property int|null $printed_by
 * @property Carbon $printed_at
 * @property int $number_of_copies
 * @property string|null $printer_name
 * @property string|null $ip_address
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read GeneratedDocument $document
 * @property-read User|null $printer
 */
class PrintLog extends Model
{
    protected $fillable = [
        'generated_document_id',
        'printed_by',
        'printed_at',
        'number_of_copies',
        'printer_name',
        'ip_address',
    ];

    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<GeneratedDocument, $this> */
    public function document(): BelongsTo
    {
        return $this->belongsTo(GeneratedDocument::class, 'generated_document_id');
    }

    /** @return BelongsTo<User, $this> */
    public function printer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
