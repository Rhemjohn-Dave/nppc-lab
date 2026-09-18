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
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

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
        $moisture = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $ash = AnalysisType::query()->where('code', 'WW-12')->firstOrFail();

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
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $second->analysisTypes()->detach($type->id);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Solo Customer', 'solo@example.com');

        $this->assertSame($first->id, $job->analyses()->firstOrFail()->assigned_to);
    }

    public function test_qualified_teammate_can_encode_suggested_line_and_takes_ownership(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Shared PC Customer', 'shared@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->assertSame($first->id, $line->assigned_to);
        $this->assertTrue($second->analysisTypes()->where('analysis_types.id', $type->id)->exists());

        $this->actingAs($second)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks')
                ->where('tasks.0.id', $line->id)
                ->where('tasks.0.can_work', true)
                ->where('tasks.0.is_mine', false));

        $this->actingAs($second)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'result_value' => '12.5',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $line->refresh();
        $this->assertSame($second->id, $line->assigned_to);
        $this->assertSame('in_progress', $line->status->value);

        $this->actingAs($second)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'result_value' => '12.5',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                'result_unit' => '%',
            ])
            ->assertRedirect();

        $this->assertSame($second->id, $line->fresh()->assigned_to);
        $this->assertSame('completed', $line->fresh()->status->value);
    }

    public function test_unqualified_analyst_cannot_encode_teammate_line(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $second->analysisTypes()->detach($type->id);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Locked Customer', 'locked@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->assertSame($first->id, $line->assigned_to);

        $this->actingAs($second)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'result_value' => '1.0',
            ])
            ->assertSessionHasErrors('analysis');

        $this->assertSame($first->id, $line->fresh()->assigned_to);
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

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        return $job->fresh(['analyses']) ?? $job;
    }
}
