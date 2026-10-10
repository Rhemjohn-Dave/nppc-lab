<?php

namespace App\Services;

use App\Jobs\SendCustomerEmail;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Queues customer-facing emails and records each attempt in email_logs.
 * Sending happens in SendCustomerEmail so SMTP problems never block lab workflow.
 */
class CustomerEmailService
{
    public function queueResultsReady(JobOrder $jobOrder, ?User $actor = null): EmailLog
    {
        return $this->queue($jobOrder, EmailLog::TYPE_RESULTS_READY, $actor);
    }

    public function queueRequestReceived(JobOrder $jobOrder, ?User $actor = null): EmailLog
    {
        return $this->queue($jobOrder, EmailLog::TYPE_REQUEST_RECEIVED, $actor);
    }

    public function resend(EmailLog $log, User $actor): EmailLog
    {
        if (! in_array($log->type, EmailLog::RESENDABLE_TYPES, true) || $log->jobOrder === null) {
            throw ValidationException::withMessages([
                'email' => 'This email cannot be resent.',
            ]);
        }

        return $this->queue($log->jobOrder, $log->type, $actor);
    }

    private function queue(JobOrder $jobOrder, string $type, ?User $actor): EmailLog
    {
        $to = filled($jobOrder->customer_email) ? trim((string) $jobOrder->customer_email) : null;

        $log = EmailLog::create([
            'job_order_id' => $jobOrder->id,
            'type' => $type,
            'to' => $to,
            'status' => $to === null ? EmailLog::STATUS_SKIPPED : EmailLog::STATUS_QUEUED,
            'created_by' => $actor?->id,
        ]);

        if ($to !== null) {
            try {
                SendCustomerEmail::dispatch($log->id)->afterCommit();
            } catch (\Throwable $e) {
                // The sync queue runs the job inline and rethrows; the job already marked the log failed.
                report($e);
            }
        }

        return $log->fresh() ?? $log;
    }
}
