<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Notifications\AnalysisReturned;
use App\Notifications\ResultsReleased;
use App\Notifications\TaskAssigned;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificationCorrectnessTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_assigns_without_task_assigned_notification(): void
    {
        $this->seed();

        Notification::fake();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakePriceApproveReceive($receiving, [$type->id], 'Suggest Customer', 'suggest@example.com');
        $line = $job->analyses()->firstOrFail();

        Notification::assertNotSentTo($analyst, TaskAssigned::class);
        $this->assertSame($analyst->id, $line->assigned_to);

        $notification = new TaskAssigned($job, $line);
        $message = (string) ($notification->toArray($analyst)['message'] ?? '');

        $this->assertTrue(str_contains($message, 'suggested for you'));
        $this->assertTrue(str_contains($message, 'Any qualified analyst may encode it'));
    }

    public function test_return_sends_analysis_returned_not_task_assigned(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakePriceApproveReceive($receiving, [$type->id], 'Return Customer', 'return@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '10.0',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => $this->fo2Signatories(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('pending_review', $job->fresh()->status->value);

        Notification::fake();

        $this->actingAs($head)
            ->post("/head/{$job->id}/return", [
                'analysis_ids' => [$line->id],
                'review_notes' => 'Please recheck moisture',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($analyst, AnalysisReturned::class);
        Notification::assertNotSentTo($analyst, TaskAssigned::class);
    }

    public function test_release_notifies_analyst_and_receiving_and_mails_customer(): void
    {
        $this->seed();

        Mail::fake();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakePriceApproveReceive($receiving, [$type->id], 'Release Customer', 'release@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '11.0',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => $this->fo2Signatories(),
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame('pending_review', $job->fresh()->status->value);

        Notification::fake();

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign", [
                'review_notes' => 'Released',
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($analyst, ResultsReleased::class, function (ResultsReleased $notification) use ($analyst) {
            $payload = $notification->toArray($analyst);

            return ($payload['audience'] ?? null) === ResultsReleased::AUDIENCE_ANALYST
                && str_contains((string) $payload['message'], 'wet sign')
                && ($payload['href'] ?? null) === '/analyst?job='.$notification->jobOrder->id;
        });

        Notification::assertSentTo($receiving, ResultsReleased::class, function (ResultsReleased $notification) use ($receiving) {
            $payload = $notification->toArray($receiving);

            return ($payload['audience'] ?? null) === ResultsReleased::AUDIENCE_RECEIVING
                && str_contains((string) $payload['message'], 'reprint')
                && ($payload['href'] ?? null) === '/receiving/'.$notification->jobOrder->id;
        });

        Mail::assertSent(\App\Mail\ResultsReadyMail::class);
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function intakePriceApproveReceive(User $receiving, array $typeIds, string $name, string $email): JobOrder
    {
        $this->post('/intake/job-orders', [
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'samples' => [
                ['description' => 'Sample', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => $typeIds,
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses->map(fn ($line) => [
            'id' => $line->id,
            'unit_price' => 100,
            'quantity' => 1,
        ])->all();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", ['lines' => $lines])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        return $job->fresh(['analyses']) ?? $job;
    }

    /**
     * @return list<array{name: string, prc_id: string}>
     */
    private function fo2Signatories(): array
    {
        return [
            ['name' => 'Test Analyst', 'prc_id' => '445566'],
            ['name' => 'Second Analyst', 'prc_id' => '778899'],
            ['name' => 'Third Analyst', 'prc_id' => '112233'],
            ['name' => 'Fourth Analyst', 'prc_id' => '334455'],
        ];
    }
}
