<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\EmailLog;
use App\Support\Mail\MailSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EmailSettingsAdminController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/email-settings', [
            'settings' => MailSettings::forDisplay(),
            'env_mailer' => (string) config('mail.default'),
            'recent' => EmailLog::query()
                ->with('jobOrder:id,reference_no')
                ->latest('id')
                ->limit(20)
                ->get()
                ->map(fn (EmailLog $log) => $log->toSummary())
                ->values(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $enabled = $request->boolean('enabled');

        $data = $request->validate([
            'enabled' => ['boolean'],
            'host' => [Rule::requiredIf($enabled), 'nullable', 'string', 'max:255'],
            'port' => [Rule::requiredIf($enabled), 'nullable', 'integer', 'min:1', 'max:65535'],
            'encryption' => ['required', Rule::in(MailSettings::ENCRYPTIONS)],
            'username' => ['nullable', 'string', 'max:255'],
            'password' => ['nullable', 'string', 'max:255'],
            'from_address' => [Rule::requiredIf($enabled), 'nullable', 'email', 'max:255'],
            'from_name' => ['nullable', 'string', 'max:255'],
        ]);

        MailSettings::save([...$data, 'enabled' => $enabled]);

        return back()->with('success', $enabled
            ? 'Email settings saved. Send a test email to confirm delivery.'
            : 'Email settings saved. Admin SMTP is disabled; .env mail settings are in effect.');
    }

    public function test(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'to' => ['required', 'email', 'max:255'],
        ]);

        MailSettings::apply();

        $log = EmailLog::create([
            'type' => EmailLog::TYPE_TEST,
            'to' => $data['to'],
            'status' => EmailLog::STATUS_QUEUED,
            'created_by' => $request->user()?->id,
        ]);

        try {
            Mail::raw(
                'This is a test email from the NPPC Laboratory Management System. If you received it, outgoing email is working.',
                function (Message $message) use ($data): void {
                    $message->to($data['to'])->subject('NPPC Lab — test email');
                },
            );
        } catch (\Throwable $e) {
            $log->markFailed($e->getMessage());
            MailSettings::recordTest(false, $e->getMessage());

            return back()->with('error', 'Test email failed: '.$e->getMessage());
        }

        $log->markSent();
        MailSettings::recordTest(true);

        return back()->with('success', "Test email sent to {$data['to']}.");
    }
}
