<?php

namespace App\Services;

use App\Models\DocumentAuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class DocumentAuditLogger
{
    /**
     * @param  array<int|string, mixed>|null  $old
     * @param  array<int|string, mixed>|null  $new
     */
    public function record(
        string $action,
        Model $record,
        ?User $user = null,
        ?array $old = null,
        ?array $new = null,
        ?Request $request = null,
    ): DocumentAuditLog {
        $request ??= request();

        return DocumentAuditLog::query()->create([
            'user_id' => $user?->id ?? $request?->user()?->id,
            'action' => $action,
            'auditable_type' => $record->getMorphClass(),
            'auditable_id' => $record->getKey(),
            'old_value' => $old,
            'new_value' => $new,
            'ip_address' => $request?->ip(),
        ]);
    }
}
