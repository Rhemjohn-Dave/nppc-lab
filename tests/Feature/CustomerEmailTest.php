<?php

namespace Tests\Feature;

use App\Mail\RequestReceivedMail;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisType;
use App\Models\EmailLog;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\CustomerEmailService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class CustomerEmailTest extends TestCase
{
    use RefreshDatabase;

    private function intake(string $email = 'customer@example.com'): JobOrder
    {
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Email Customer',
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        return JobOrder::query()->latest('id')->firstOrFail();
    }

    public function test_intake_queues_request_received_email_and_logs_it(): void
    {
        $this->seed();
        Mail::fake();

        $job = $this->intake();

        Mail::assertSent(RequestReceivedMail::class, function (RequestReceivedMail $mail) use ($job) {
            return $mail->hasTo('customer@example.com')
                && $mail->jobOrder->is($job);
        });

        $log = EmailLog::query()->where('job_order_id', $job->id)->firstOrFail();
        $this->assertSame(EmailLog::TYPE_REQUEST_RECEIVED, $log->type);
        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        $this->assertSame(1, $log->attempts);
    }

    public function test_request_received_email_renders_reference_and_tests(): void
    {
        $this->seed();
        Mail::fake();

        $job = $this->intake();
        $html = (new RequestReceivedMail($job))->render();

        $this->assertStringContainsString($job->reference_no, $html);
        $this->assertStringContainsString((string) $job->analyses()->value('name'), $html);
    }

    public function test_missing_customer_email_is_logged_as_skipped(): void
    {
        $this->seed();
        Mail::fake();

        $job = $this->intake();
        $job->update(['customer_email' => null]);

        $log = app(CustomerEmailService::class)->queueResultsReady($job);

        $this->assertSame(EmailLog::STATUS_SKIPPED, $log->status);
        Mail::assertNotSent(ResultsReadyMail::class);
    }

    public function test_smtp_failure_is_logged_without_throwing(): void
    {
        $this->seed();
        $job = $this->intake();

        config([
            'mail.default' => 'smtp',
            'mail.mailers.smtp.url' => null,
            'mail.mailers.smtp.host' => '127.0.0.1',
            'mail.mailers.smtp.port' => 1,
            'mail.mailers.smtp.timeout' => 3,
        ]);
        Mail::purge('smtp');

        $log = app(CustomerEmailService::class)->queueResultsReady($job);

        $this->assertSame(EmailLog::STATUS_FAILED, $log->status);
        $this->assertNotEmpty($log->error);
    }

    public function test_receiving_can_resend_customer_email(): void
    {
        $this->seed();
        Mail::fake();
        $job = $this->intake();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $log = EmailLog::query()->where('job_order_id', $job->id)->firstOrFail();

        $this->actingAs($receiving)
            ->post("/email-logs/{$log->id}/resend")
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(RequestReceivedMail::class, 2);
        $this->assertSame(2, EmailLog::query()->where('job_order_id', $job->id)->count());
    }

    public function test_analyst_cannot_resend_customer_email(): void
    {
        $this->seed();
        Mail::fake();
        $job = $this->intake();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $log = EmailLog::query()->where('job_order_id', $job->id)->firstOrFail();

        $this->actingAs($analyst)
            ->post("/email-logs/{$log->id}/resend")
            ->assertForbidden();
    }

    public function test_receiving_show_includes_email_logs(): void
    {
        $this->seed();
        Mail::fake();
        $job = $this->intake();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->actingAs($receiving)
            ->get("/receiving/{$job->id}")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('emailLogs', 1)
                ->where('emailLogs.0.type', EmailLog::TYPE_REQUEST_RECEIVED)
                ->where('emailLogs.0.status', EmailLog::STATUS_SENT));
    }

    public function test_results_ready_mail_is_logged_by_service(): void
    {
        $this->seed();
        Mail::fake();
        $job = $this->intake();

        $log = app(CustomerEmailService::class)->queueResultsReady($job);

        $this->assertSame(EmailLog::STATUS_SENT, $log->status);
        Mail::assertSent(ResultsReadyMail::class);
    }
}
