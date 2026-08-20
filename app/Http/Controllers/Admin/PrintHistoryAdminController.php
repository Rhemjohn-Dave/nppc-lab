<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PrintLog;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PrintHistoryAdminController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim($request->string('q')->toString());

        $logs = PrintLog::query()
            ->with(['document.form', 'document.revision', 'printer'])
            ->when($search !== '', function ($query) use ($search) {
                $query->whereHas('document', function ($inner) use ($search) {
                    $inner->where('document_number', 'like', "%{$search}%")
                        ->orWhereHas('form', fn ($q) => $q->where('form_code', 'like', "%{$search}%"));
                });
            })
            ->latest('printed_at')
            ->paginate(30)
            ->withQueryString()
            ->through(fn (PrintLog $log) => [
                'id' => $log->id,
                'document_number' => $log->document->document_number,
                'form_code' => $log->document->form->form_code,
                'revision' => $log->document->revision->revision,
                'user' => $log->printer?->name,
                'printed_at' => $log->printed_at?->toDayDateTimeString(),
                'copies' => $log->number_of_copies,
                'printer_name' => $log->printer_name,
                'ip_address' => $log->ip_address,
            ]);

        return Inertia::render('admin/print-history', [
            'logs' => $logs,
            'filters' => ['q' => $search],
        ]);
    }
}
