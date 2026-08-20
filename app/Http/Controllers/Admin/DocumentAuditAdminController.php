<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\DocumentAuditLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DocumentAuditAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $logs = DocumentAuditLog::query()
            ->with('user')
            ->when($search !== '', function ($query) use ($search) {
                $query->where('action', 'like', "%{$search}%")
                    ->orWhereHas('user', fn ($q) => $q->where('name', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(40)
            ->withQueryString()
            ->through(fn (DocumentAuditLog $log) => [
                'id' => $log->id,
                'user' => $log->user?->name,
                'action' => $log->action,
                'record' => class_basename($log->auditable_type).'#'.$log->auditable_id,
                'old_value' => $log->old_value,
                'new_value' => $log->new_value,
                'ip_address' => $log->ip_address,
                'created_at' => $log->created_at?->toDayDateTimeString(),
            ]);

        return Inertia::render('admin/document-audit', [
            'logs' => $logs,
            'filters' => ['q' => $search],
        ]);
    }
}
