<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_receive_marks_assigned_without_exclusive_owner_when_type_has_qualifiers(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Shared Customer', 'shared@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->assertNull($line->assigned_to);
        $this->assertSame(JobOrderAnalysisStatus::Assigned, $line->status);
    }

    public function test_two_qualifiers_both_see_and_can_work_the_same_line(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $this->assertTrue($first->analysisTypes()->where('analysis_types.id', $type->id)->exists());
        $this->assertTrue($second->analysisTypes()->where('analysis_types.id', $type->id)->exists());

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Both See Customer', 'both@see.example.com');
        $line = $job->analyses()->firstOrFail();

        foreach ([$first, $second] as $analyst) {
            $this->actingAs($analyst)
                ->get('/analyst')
                ->assertOk()
                ->assertInertia(fn ($page) => $page
                    ->component('analyst/index')
                    ->has('tasks', 1)
                    ->where('tasks.0.id', $line->id)
                    ->where('tasks.0.can_work', true)
                    ->where('tasks.0.is_mine', true));
        }

        $this->actingAs($second)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '12.5',
                'result_unit' => '%',
            ])
            ->assertOk();

        $line->refresh();
        $this->assertSame($second->id, $line->assigned_to);
        $this->assertSame('in_progress', $line->status->value);
    }

    public function test_analyst_sees_only_types_on_their_admin_assignments(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $wwAnalyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $microAnalyst = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $wwType = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $microTypeIds = $package->analysisTypes()->pluck('analysis_types.id')->map(fn ($id) => (int) $id)->all();

        $this->assertNotEmpty($microTypeIds);

        $wwAnalyst->analysisTypes()->sync([$wwType->id]);
        $microAnalyst->analysisTypes()->sync($microTypeIds);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Split Visibility Customer',
            'customer_email' => 'split-visibility@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => array_values(array_unique(array_merge(
                        $microTypeIds,
                        [(int) $wwType->id],
                    ))),
                ],
            ],
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

        $wwLineIds = $job->fresh('analyses')->analyses
            ->filter(fn ($line) => (int) $line->analysis_type_id === (int) $wwType->id)
            ->pluck('id')
            ->all();
        $microLineIds = $job->fresh('analyses')->analyses
            ->filter(fn ($line) => in_array((int) $line->analysis_type_id, $microTypeIds, true))
            ->pluck('id')
            ->all();

        $this->assertNotEmpty($wwLineIds);
        $this->assertNotEmpty($microLineIds);

        $this->actingAs($wwAnalyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks', count($wwLineIds))
                ->where('tasks', function ($tasks) use ($wwLineIds, $microLineIds) {
                    $taskIds = collect($tasks)->pluck('id')->all();

                    return empty(array_diff($wwLineIds, $taskIds))
                        && empty(array_intersect($taskIds, $microLineIds));
                }));

        $this->actingAs($microAnalyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks', count($microLineIds))
                ->where('tasks', function ($tasks) use ($wwLineIds, $microLineIds) {
                    $taskIds = collect($tasks)->pluck('id')->all();

                    return empty(array_diff($microLineIds, $taskIds))
                        && empty(array_intersect($taskIds, $wwLineIds));
                }));
    }

    public function test_unqualified_analyst_cannot_encode_line(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $second->analysisTypes()->detach($type->id);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Locked Customer', 'locked@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->assertNull($line->assigned_to);

        $this->actingAs($second)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks', 0));

        $this->actingAs($second)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '1.0',
            ])
            ->assertSessionHasErrors('analysis');

        $this->assertNull($line->fresh()->assigned_to);
    }

    public function test_line_stays_pending_when_nobody_is_qualified(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $first = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $second = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $first->analysisTypes()->detach($type->id);
        $second->analysisTypes()->detach($type->id);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Orphan Customer', 'orphan@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->assertNull($line->assigned_to);
        $this->assertSame(JobOrderAnalysisStatus::Pending, $line->status);
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
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                ],
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