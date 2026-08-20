<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $controlled_form_revision_id
 * @property string|null $from_status
 * @property string $to_status
 * @property int|null $user_id
 * @property string|null $comment
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read ControlledFormRevision $revision
 * @property-read User|null $user
 */
class DocumentApproval extends Model
{
    protected $fillable = [
        'controlled_form_revision_id',
        'from_status',
        'to_status',
        'user_id',
        'comment',
    ];

    /** @return BelongsTo<ControlledFormRevision, $this> */
    public function revision(): BelongsTo
    {
        return $this->belongsTo(ControlledFormRevision::class, 'controlled_form_revision_id');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
