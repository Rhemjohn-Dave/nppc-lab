<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\DynamicTestMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SamplePreparationLineTest extends TestCase
{
    use RefreshDatabase;

    public function test_sample_preparation_needs_no_result_and_is_left_off_the_result_sheet(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $ash = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();
        $prep = AnalysisType::query()->where('code', 'PX-11')->firstOrFail();
        $this->assertTrue($prep->isSamplePreparation());
        $this->assertFalse($ash->isSamplePreparation());

        $this->post('/intake/job-orders', [
            'customer_name' => 'Prep Customer',
            'customer_email' => 'prep@example.com',
            'samples' => [
                ['description' => 'Feed sample', 'matrix' => 'Solid'],
            ],
            'analysis_type_ids' => [$ash->id, $prep->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$ash->id, $prep->id],
            $job->analyses()->pluck('analysis_type_id')->all(),
        );

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $job->analyses()->get()->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 500,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();
        $this->approveJobOrder($job);
        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $ashLine = $job->analyses()->where('analysis_type_id', $ash->id)->firstOrFail();
        $prepLine = $job->analyses()->where('analysis_type_id', $prep->id)->firstOrFail();
        $worker = $ashLine->assignee ?? $analyst;

        $this->actingAs($prepLine->assignee ?? $analyst)
            ->post("/analyst/tasks/{$prepLine->id}/complete", [])
            ->assertSessionHasNoErrors()
            ->assertRedirect();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $prepLine->fresh()->status);
        $this->assertNull($prepLine->fresh()->result_value);

        $this->actingAs($worker)
            ->post("/analyst/tasks/{$ashLine->id}/complete", $this->completeResultPayload([
                'result_value' => '0.000234',
            ]))
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-PROX')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $bag = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job->fresh(['analyses.analysisType', 'analyses.assignee', 'samples']),
            null,
            $form,
        );

        $matrixField = $revision->fields->firstWhere('field_type', \App\Enums\ControlledFormFieldType::DynamicTestMatrix);
        $this->assertNotNull($matrixField);
        $tests = collect($bag[$matrixField->name] ?? [])->pluck('test')->all();
        $this->assertContains($ash->name, $tests);
        $this->assertNotContains('Sample Preparation', $tests);
        $this->assertStringNotContainsString('Sample Preparation', (string) ($bag['results.test_methods_references'] ?? ''));

        $previewTests = collect(DynamicTestMatrix::previewRowsForForm($form))->pluck('test')->all();
        $this->assertNotContains('Sample Preparation', $previewTests);

        $this->actingAs($worker)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'signatories' => [
                    ['name' => 'Test Analyst', 'prc_id' => '445566'],
                    ['name' => 'Second Analyst', 'prc_id' => '778899'],
                    ['name' => 'Third Analyst', 'prc_id' => '112233'],
                    ['name' => 'Fourth Analyst', 'prc_id' => '334455'],
                ],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $job->refresh();
        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
        $this->assertTrue(
            $job->analyses()->where('analysis_type_id', $prep->id)->exists(),
            'Sample Preparation stays on the JO as a billable line.',
        );
    }
}
