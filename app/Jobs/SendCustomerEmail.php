<?php

namespace App\Jobs;

use App\Mail\RequestReceivedMail;
use App\Mail\ResultsReadyMail;
use App\Models\EmailLog;
use App\Support\Mail\MailSettings;
use Illuminate\Contracts\Mail\Mailable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendCustomerEmail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var list<int> */
    public array $backoff = [60, 300];

    public function __construct(public int $emailLogId) {}

    public function handle(): void
    {
        $log = EmailLog::query()->with('jobOrder.samples', 'jobOrder.analyses')->find($this->emailLogId);

        if ($log === null || $log->status === EmailLog::STATUS_SENT || blank($log->to)) {
            return;
        }

        $mailable = $this->mailableFor($log);
        if ($mailable === null) {
            $log->markFailed('Unknown email type or missing job order.');

            return;
        }

        // Workers are long-lived; pick up the latest admin SMTP settings each run.
        MailSettings::apply();

        $log->increment('attempts');

        try {
            Mail::to($log->to)->send($mailable);
        } catch (Throwable $e) {
            // Keep the latest error visible while retries are pending.
            $log->update(['error' => mb_substr($e->getMessage(), 0, 2000)]);

            throw $e;
        }

        $log->markSent();
    }

    public function failed(?Throwable $exception): void
    {
        EmailLog::query()->find($this->emailLogId)?->markFailed(
            $exception?->getMessage() ?? 'Email could not be sent.',
        );
    }

    private function mailableFor(EmailLog $log): ?Mailable
    {
        $jobOrder = $log->jobOrder;
        if ($jobOrder === null) {
            return null;
        }

        return match ($log->type) {
            EmailLog::TYPE_RESULTS_READY => new ResultsReadyMail($jobOrder),
            EmailLog::TYPE_REQUEST_RECEIVED => new RequestReceivedMail($jobOrder),
            default => null,
        };
    }
}
