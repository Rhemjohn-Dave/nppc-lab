<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Mail\ResultsReadyMail;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class PerSampleSubmitForReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_submit_one_sample_while_other_stays_encodable(): void
    {
        Mail::fake();
        $this->seed();

        [$job, $sampleA, $sampleB, $lineA, $lineB] = $this->createTwoSampleInAnalysisJob();

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();

        $this->completeLine($analyst, $lineA);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'sample_id' => $sampleA->id,
                'signatories' => $this->resultSignatories(),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $sampleA->refresh();
        $sampleB->refresh();

        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);
        $this->assertNotNull($sampleA->submitted_for_review_at);
        $this->assertNull($sampleB->submitted_for_review_at);
        $this->assertNull($job->reviewed_at);

        // Sample B can still be encoded on the same JO.
        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$lineB->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '9.1',
                'result_method' => 'Standard Method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame(
            JobOrderAnalysisStatus::InProgress,
            $lineB->fresh()?->status,
        );

        $this->actingAs($head)
            ->get('/head/results')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('head/results')
                ->has('orders.data', fn ($orders) => $orders
                    ->where('0.id', $job->id)
                    ->etc()));

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign", [
                'sample_id' => $sampleA->id,
                'review_notes' => 'Sample A released',
            ])
            ->assertRedirect();

        $job->refresh();
        $sampleA->refresh();

        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);
        $this->assertNotNull($sampleA->released_at);
        $this->assertNull($job->reviewed_at);
        Mail::assertNotSent(ResultsReadyMail::class);

        $this->completeLine($analyst, $lineB);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'sample_id' => $sampleB->id,
                'signatories' => $this->resultSignatories(),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $sampleB->refresh();

        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
        $this->assertNotNull($sampleB->submitted_for_review_at);

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign", [
                'sample_id' => $sampleB->id,
            ])
            ->assertRedirect();

        $job->refresh();
        $sampleB->refresh();

        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        $this->assertNotNull($job->reviewed_at);
        $this->assertNotNull($sampleB->released_at);
        Mail::assertSent(ResultsReadyMail::class);
    }

    public function test_whole_jo_submit_still_works_for_single_sample(): void
    {
        Mail::fake();
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $head = User::where('email', 'head@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Single Sample Submit',
            'customer_email' => 'single-submit@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                ],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $line->id, 'unit_price' => 100, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $this->completeLine($analyst, $line);

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => $this->resultSignatories(),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $sample = $job->samples()->firstOrFail();

        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
        $this->assertNotNull($sample->submitted_for_review_at);

        $this->actingAs($head)
            ->post("/head/{$job->id}/sign")
            ->assertRedirect();

        $job->refresh();
        $sample->refresh();

        $this->assertSame(JobOrderStatus::ReadyForPickup, $job->status);
        $this->assertNotNull($sample->released_at);
        Mail::assertSent(ResultsReadyMail::class);
    }

    /**
     * @return array{0: JobOrder, 1: \App\Models\Sample, 2: \App\Models\Sample, 3: \App\Models\JobOrderAnalysis, 4: \App\Models\JobOrderAnalysis}
     */
    private function createTwoSampleInAnalysisJob(): array
    {
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Per Sample Submit Customer',
            'customer_email' => 'per-sample-submit@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'analysis_type_ids' => [$type->id],
                ],
                [
                    'description' => 'River water',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'analysis_type_ids' => [$type->id],
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load(['samples', 'analyses']);
        $this->assertCount(2, $job->samples);
        $this->assertCount(2, $job->analyses);

        $samples = $job->samples->sortBy('id')->values();
        $sampleA = $samples[0];
        $sampleB = $samples[1];
        $lineA = $job->analyses->firstWhere('sample_id', $sampleA->id);
        $lineB = $job->analyses->firstWhere('sample_id', $sampleB->id);
        $this->assertNotNull($lineA);
        $this->assertNotNull($lineB);

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => [
                    ['id' => $lineA->id, 'unit_price' => 100, 'quantity' => 1],
                    ['id' => $lineB->id, 'unit_price' => 100, 'quantity' => 1],
                ],
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job = $job->fresh(['samples', 'analyses']) ?? $job;

        return [
            $job,
            $job->samples->firstWhere('id', $sampleA->id) ?? $sampleA,
            $job->samples->firstWhere('id', $sampleB->id) ?? $sampleB,
            $job->analyses->firstWhere('id', $lineA->id) ?? $lineA,
            $job->analyses->firstWhere('id', $lineB->id) ?? $lineB,
        ];
    }

    private function completeLine(User $analyst, $line): void
    {
        $jobId = (int) $line->job_order_id;

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$jobId}/result-footer", [
                'temperature_c' => '25',
                'humidity_pct' => '55',
                'test_report_no' => 'TR-001',
            ])
            ->assertOk();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", $this->completeResultPayload([
                'result_value' => '12.5',
                'result_pass_fail' => null,
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();
    }

    /**
     * @return list<array{name: string, prc_id: string}>
     */
    private function resultSignatories(): array
    {
        return [
            ['name' => 'Test Analyst', 'prc_id' => '445566'],
            ['name' => 'Second Analyst', 'prc_id' => '778899'],
            ['name' => 'Third Analyst', 'prc_id' => '112233'],
            ['name' => 'Fourth Analyst', 'prc_id' => '334455'],
        ];
    }
}
