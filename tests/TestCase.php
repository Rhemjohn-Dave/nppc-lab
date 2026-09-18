<?php

namespace Tests;

use App\Models\JobOrder;
use App\Models\User;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }

    protected function approveJobOrder(JobOrder|int $job): void
    {
        $id = $job instanceof JobOrder ? $job->id : $job;
        $head = User::where('email', 'head@nppc.local')->firstOrFail();

        $this->actingAs($head)
            ->post("/head/{$id}/approve")
            ->assertRedirect('/head/jo?tab=pending');
    }

    /**
     * Payload for POST /analyst/tasks/{id}/complete under the unified encode rules.
     *
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function completeResultPayload(array $overrides = []): array
    {
        return array_merge([
            'result_value' => '1.0',
            'result_pass_fail' => 'Passed',
            'result_method' => 'Standard Method',
            'result_unit' => null,
            'result_remarks' => null,
        ], $overrides);
    }
}
