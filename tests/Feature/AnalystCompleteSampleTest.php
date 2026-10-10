<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystCompleteSampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_complete_sample_marks_all_drafted_assays_complete(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $moisture = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $ash = AnalysisType::query()->where('code', 'WW-12')->firstOrFail();

        $moisture->update(['requires_pass_fail' => false]);
        $ash->update(['requires_pass_fail' => false]);

        $job = $this->intakeAndReceive(
            $receiving,
            [$moisture->id, $ash->id],
            'Complete Sample Customer',
            'complete-sample@example.com',
        );

        $lines = $job->analyses()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $sampleId = $lines->first()?->sample_id;

        foreach ($lines as $index => $line) {
            $this->actingAs($analyst)
                ->post("/analyst/tasks/{$line->id}/draft", [
                    'performed_at' => '2026-07-29T15:00',
                    'result_value' => (string) (10 + $index),
                    'result_method' => 'Standard Method',
                ])
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/complete-sample", array_filter([
                'sample_id' => $sampleId,
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('success');

        foreach ($lines as $line) {
            $fresh = $line->fresh();
            $this->assertSame(JobOrderAnalysisStatus::Completed, $fresh?->status);
            $this->assertNotNull($fresh?->completed_at);
            $this->assertNull($fresh?->result_pass_fail);
        }

        $this->assertSame('in_analysis', $job->fresh()?->status->value);
    }

    public function test_complete_sample_rejects_when_an_assay_is_missing_a_value(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $moisture = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $ash = AnalysisType::query()->where('code', 'WW-12')->firstOrFail();

        $moisture->update(['requires_pass_fail' => false]);
        $ash->update(['requires_pass_fail' => false]);

        $job = $this->intakeAndReceive(
            $receiving,
            [$moisture->id, $ash->id],
            'Incomplete Sample Customer',
            'incomplete-sample@example.com',
        );

        $lines = $job->analyses()->orderBy('id')->get();
        $first = $lines->firstOrFail();
        $sampleId = $first->sample_id;

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '11.2',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/job-orders/{$job->id}/complete-sample", array_filter([
                'sample_id' => $sampleId,
            ]))
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['assays']);

        $this->assertNotSame(
            JobOrderAnalysisStatus::Completed,
            $first->fresh()?->status,
        );
    }

    public function test_complete_sample_requires_pass_fail_when_procedure_flag_is_on(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => true]);

        $job = $this->intakeAndReceive(
            $receiving,
            [$type->id],
            'Pass Fail Sample Customer',
            'pf-sample@example.com',
        );

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '9.9',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/job-orders/{$job->id}/complete-sample", array_filter([
                'sample_id' => $line->sample_id,
            ]))
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['assays']);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '9.9',
                'result_pass_fail' => 'Passed',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/complete-sample", array_filter([
                'sample_id' => $line->sample_id,
            ]))
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('Passed', $line->fresh()?->result_pass_fail);
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->fresh()?->status);
    }

    public function test_complete_job_order_without_sample_id_marks_all_samples_complete(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);

        $job = $this->intakeAndReceive(
            $receiving,
            [$type->id],
            'Multi Sample Complete Customer',
            'multi-complete@example.com',
            [
                ['description' => 'Sample A', 'matrix' => 'Solid'],
                ['description' => 'Sample B', 'matrix' => 'Solid'],
            ],
        );

        $lines = $job->analyses()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $this->assertNotSame(
            $lines[0]->sample_id,
            $lines[1]->sample_id,
        );

        foreach ($lines as $index => $line) {
            $this->actingAs($analyst)
                ->post("/analyst/tasks/{$line->id}/draft", [
                    'performed_at' => '2026-07-29T15:00',
                    'result_value' => (string) (20 + $index),
                    'result_method' => 'Standard Method',
                ])
                ->assertRedirect()
                ->assertSessionDoesntHaveErrors();
        }

        $this->actingAs($analyst)
            ->post("/analyst/job-orders/{$job->id}/complete-sample", [])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors()
            ->assertSessionHas('success');

        foreach ($lines as $line) {
            $fresh = $line->fresh();
            $this->assertSame(JobOrderAnalysisStatus::Completed, $fresh?->status);
            $this->assertNotNull($fresh?->completed_at);
        }
    }

    public function test_complete_job_order_without_sample_id_rejects_when_another_sample_is_incomplete(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);

        $job = $this->intakeAndReceive(
            $receiving,
            [$type->id],
            'Multi Sample Incomplete Customer',
            'multi-incomplete@example.com',
            [
                ['description' => 'Sample A', 'matrix' => 'Solid'],
                ['description' => 'Sample B', 'matrix' => 'Solid'],
            ],
        );

        $lines = $job->analyses()->orderBy('id')->get();
        $this->assertCount(2, $lines);
        $first = $lines->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$first->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '11.2',
            ])
            ->assertRedirect();

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/job-orders/{$job->id}/complete-sample", [])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['assays']);

        $this->assertNotSame(
            JobOrderAnalysisStatus::Completed,
            $first->fresh()?->status,
        );
    }

    /**
     * @param  list<int>  $typeIds
     * @param  list<array{description: string, matrix?: string}>|null  $samples
     */
    private function intakeAndReceive(
        User $receiving,
        array $typeIds,
        string $name,
        string $email,
        ?array $samples = null,
    ): JobOrder {
        $this->post('/intake/job-orders', [
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'samples' => $samples ?? [
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
}
