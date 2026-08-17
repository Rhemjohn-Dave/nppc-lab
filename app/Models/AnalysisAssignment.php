<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AnalysisAssignment extends Model
{
    protected $fillable = [
        'user_id',
        'analysis_type_id',
    ];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<AnalysisType, $this> */
    public function analysisType(): BelongsTo
    {
        return $this->belongsTo(AnalysisType::class);
    }
}
