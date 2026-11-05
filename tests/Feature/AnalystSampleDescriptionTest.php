<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalystSampleDescriptionTest extends TestCase
{
    use RefreshDatabase;

    public function test_qualified_analyst_can_update_sample_description_until_release(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $other = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();

        $job = $this->intakeAndReceive($receiving, $type, 'Leaves', 'leaves@example.com');
        $sample = $job->samples()->firstOrFail();
        $line = $job->analyses()->firstOrFail();
        $line->update(['assigned_to' => $analyst->id]);
        $other->analysisTypes()->detach($type->id);

        $this->actingAs($analyst)
            ->withHeader('referer', 'http://127.0.0.1:8000/analyst?job='.$job->id)
            ->patch("/analyst/job-orders/{$job->id}/samples/{$sample->id}", [
                'description' => 'Herbanext leaves',
            ])
            ->assertRedirect('/analyst?job='.$job->id)
            ->assertSessionDoesntHaveErrors();

        $sample->refresh();
        $this->assertSame('Herbanext leaves', $sample->description);
        $this->assertSame('Solid', $sample->matrix);

        $this->actingAs($other)
            ->patch("/analyst/job-orders/{$job->id}/samples/{$sample->id}", [
                'description' => 'Should not save',
            ])
            ->assertForbidden();

        $this->assertSame('Herbanext leaves', $sample->fresh()->description);

        $sample->update(['released_at' => now()]);

        $this->actingAs($analyst)
            ->patch("/analyst/job-orders/{$job->id}/samples/{$sample->id}", [
                'description' => 'After release',
            ])
            ->assertForbidden();

        $this->assertSame('Herbanext leaves', $sample->fresh()->description);
    }

    public function test_wastewater_description_stays_one_of_the_three_kinds(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();

        $job = $this->intakeAndReceive(
            $receiving,
            $type,
            'Plant effluent',
            'effluent@example.com',
            'Wastewater',
            'Wastewater',
            'Liquid',
        );
        $sample = $job->samples()->firstOrFail();
        $job->analyses()->firstOrFail()->update(['assigned_to' => $analyst->id]);

        $this->actingAs($analyst)
            ->from('/analyst')
            ->patch("/analyst/job-orders/{$job->id}/samples/{$sample->id}", [
                'description' => 'Kitchen tap',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors('description');

        $this->assertSame('Wastewater', $sample->fresh()->description);

        $this->actingAs($analyst)
            ->patch("/analyst/job-orders/{$job->id}/samples/{$sample->id}", [
                'description' => 'River water',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $this->assertSame('River water', $sample->fresh()->description);
    }

    private function intakeAndReceive(
        User $receiving,
        AnalysisType $type,
        string $name,
        string $email,
        ?string $classification = null,
        string $description = 'Sample',
        string $matrix = 'Solid',
    ): JobOrder {
        $this->post('/intake/job-orders', [
            'customer_name' => $name,
            'customer_email' => $email,
            'customer_contact' => '09170000000',
            'classification' => $classification,
            'samples' => [
                ['description' => $description, 'matrix' => $matrix],
            ],
            'analysis_type_ids' => [$type->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $job->analyses->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 100,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        return $job->fresh(['analyses', 'samples']) ?? $job;
    }
}
