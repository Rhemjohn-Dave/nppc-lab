<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Sample extends Model
{
    protected $fillable = [
        'job_order_id',
        'sample_code',
        'description',
        'matrix',
        'quantity',
        'unit',
        'remarks',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
        ];
    }

    /** @return BelongsTo<JobOrder, $this> */
    public function jobOrder(): BelongsTo
    {
        return $this->belongsTo(JobOrder::class);
    }
}
