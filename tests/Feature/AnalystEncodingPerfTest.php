<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Events\LabQueueUpdated;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class AnalystEncodingPerfTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_returns_json_task_payload_without_inertia_rebuild(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);
        $analyst->analysisTypes()->syncWithoutDetaching([$type->id]);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Encoding Perf Customer', 'encoding-perf@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->postJson("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '12.5',
                'result_unit' => '%',
                'result_method' => 'Standard Method',
            ])
            ->assertOk()
            ->assertJsonPath('task.id', $line->id)
            ->assertJsonPath('task.status', JobOrderAnalysisStatus::InProgress->value)
            ->assertJsonPath('task.result_value', '12.5')
            ->assertJsonPath('task.result_unit', '%')
            ->assertJsonMissing(['component'])
            ->assertJsonMissing(['props']);

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::InProgress, $line->status);
        $this->assertSame('12.5', $line->result_value);
    }

    public function test_value_only_draft_does_not_broadcast_lab_queue_updated(): void
    {
        $this->seed();

        Event::fake([LabQueueUpdated::class]);

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);
        $analyst->analysisTypes()->syncWithoutDetaching([$type->id]);

        $job = $this->intakeAndReceive($receiving, [$type->id], 'Encoding Broadcast Customer', 'encoding-broadcast@example.com');
        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->postJson("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '1.0',
                'result_method' => 'Standard Method',
            ])
            ->assertOk();

        Event::assertDispatched(LabQueueUpdated::class);
        Event::fake([LabQueueUpdated::class]);

        $this->actingAs($analyst)
            ->postJson("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_value' => '2.0',
                'result_method' => 'Standard Method',
            ])
            ->assertOk()
            ->assertJsonPath('task.result_value', '2.0');

        Event::assertNotDispatched(LabQueueUpdated::class);
    }

    public function test_analyst_index_still_returns_core_props(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $type->update(['requires_pass_fail' => false]);
        $analyst->analysisTypes()->syncWithoutDetaching([$type->id]);

        $this->intakeAndReceive($receiving, [$type->id], 'Encoding Index Customer', 'encoding-index@example.com');

        $this->actingAs($analyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('tasks')
                ->has('counts')
                ->has('jobs')
                ->has('consolidations')
                ->has('releasedPrints')
                ->has('filters'));
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
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                ],
            ],
            'analysis_type_ids' => $typeIds,
        ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

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
