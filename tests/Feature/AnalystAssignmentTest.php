<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_job_goes_to_the_idle_analyst_when_the_other_already_has_open_work(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $this->assertTrue($first->id < $second->id);

        $busy = $this->intakeAndReceive($receiving, [$type->id], 'Busy Customer', 'busy@example.com');
        $this->assertSame($first->id, $busy->analyses()->firstOrFail()->assigned_to);

        $idle = $this->intakeAndReceive($receiving, [$type->id], 'Idle Customer', 'idle@example.com');
        $this->assertSame($second->id, $idle->analyses()->firstOrFail()->assigned_to);
    }

    public function test_one_receive_spreads_lines_across_idle_analysts_who_can_do_the_tests(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $moisture = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();
        $ash = AnalysisType::query()->where('code', 'PC-08')->firstOrFail();

        $job = $this->intakeAndReceive(
            $receiving,
            [$moisture->id, $ash->id],
            'Split Customer',
            'split@example.com',
        );

        $assignees = $job->analyses()->orderBy('id')->pluck('assigned_to')->all();

        $this->assertEqualsCanonicalizing([$first->id, $second->id], $assignees);
        $this->assertCount(2, array_unique($assignees));
    }

    public function test_only_qualified_analyst_receives_the_line(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PC-07')->firstOrFail();

        $second->analysisTypes()->detach($type->id);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Solo Customer', 'solo@example.com');

        $this->assertSame($first->id, $job->analyses()->firstOrFail()->assigned_to);
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function intakeAndReceive(User $receiving, array $typeIds, string $name, string $email): JobOrder
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

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        return $job->fresh(['analyses']) ?? $job;
    }
}
