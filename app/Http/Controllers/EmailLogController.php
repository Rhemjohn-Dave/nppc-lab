<?php

namespace App\Http\Controllers;

use App\Models\EmailLog;
use App\Services\CustomerEmailService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class EmailLogController extends Controller
{
    public function resend(Request $request, EmailLog $emailLog, CustomerEmailService $emails): RedirectResponse
    {
        abort_unless(in_array($emailLog->type, EmailLog::RESENDABLE_TYPES, true), 404);

        $log = $emails->resend($emailLog, $request->user());

        return match ($log->status) {
            EmailLog::STATUS_SKIPPED => back()->with('error', 'No customer email on file — nothing was sent.'),
            EmailLog::STATUS_FAILED => back()->with('error', 'Email could not be sent: '.($log->error ?? 'unknown error')),
            default => back()->with('success', "Email queued to {$log->to}."),
        };
    }
}
