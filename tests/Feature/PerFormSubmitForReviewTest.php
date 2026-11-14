<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Enums\JobOrderStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerFormSubmitForReviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_can_submit_one_result_sheet_while_sibling_sheet_stays_open(): void
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
        $package->forceFill(['signatory_user_id' => $microAnalyst->id])->save();

        $selectedTypeIds = array_values(array_unique(array_merge($microTypeIds, [(int) $wwType->id])));

        $this->post('/intake/job-orders', [
            'customer_name' => 'Multi Sheet Customer',
            'customer_email' => 'multi-sheet@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $selectedTypeIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $sample = $job->samples()->firstOrFail();

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

        $job->refresh();
        $wwLine = $job->analyses->first(
            fn ($line) => (int) $line->analysis_type_id === (int) $wwType->id,
        );
        $microLines = $job->analyses
            ->filter(fn ($line) => in_array((int) $line->analysis_type_id, $microTypeIds, true))
            ->values();

        $this->assertNotNull($wwLine);
        $this->assertNotEmpty($microLines);

        $resolver = app(AnalysisResultReportResolver::class);
        $reports = $resolver->reportsForJobOrder($job->fresh(['samples', 'analyses', 'packages', 'packageLinks']), $microAnalyst, withValues: false);
        $this->assertGreaterThanOrEqual(2, $reports->count());

        $microReport = $reports->first(
            fn ($report) => $report->analyses->contains(
                fn ($line) => (int) $line->id === (int) $microLines->first()->id,
            ),
        );
        $this->assertNotNull($microReport?->controlledForm);
        $microFormId = (int) $microReport->controlledForm->id;

        foreach ($microLines as $line) {
            $this->actingAs($microAnalyst)
                ->post("/analyst/tasks/{$line->id}/complete", $this->completeResultPayload([
                    'result_value' => '<1.1',
                    'result_pass_fail' => null,
                ]))
                ->assertRedirect();
            $this->assertSame(JobOrderAnalysisStatus::Completed, $line->fresh()->status);
        }

        $this->actingAs($microAnalyst)
            ->getJson("/analyst/job-orders/{$job->id}/result-report?sample_id={$sample->id}&form_id={$microFormId}")
            ->assertOk()
            ->assertJsonPath('can_preview', true);

        $this->actingAs($microAnalyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'sample_id' => $sample->id,
                'form_id' => $microFormId,
                'signatories' => $this->fo4Signatories(),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $sample->refresh();

        $this->assertSame(JobOrderStatus::InAnalysis, $job->status);
        $this->assertNotNull($sample->submitted_for_review_at);
        $this->assertContains($microFormId, array_map('intval', $sample->submitted_form_ids ?? []));
        $this->assertSame(JobOrderAnalysisStatus::Assigned, $wwLine->fresh()->status);

        $this->actingAs($wwAnalyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '25',
                'humidity_pct' => '55',
                'test_report_no' => 'TR-001',
            ])
            ->assertOk();

        $this->actingAs($wwAnalyst)
            ->post("/analyst/tasks/{$wwLine->id}/complete", $this->completeResultPayload([
                'result_value' => '7.1',
            ]))
            ->assertRedirect();

        $wwReport = $reports->first(
            fn ($report) => $report->analyses->contains(
                fn ($line) => (int) $line->id === (int) $wwLine->id,
            ),
        );
        // Re-resolve after completes so waiting sheets become combined.
        $reportsAfter = $resolver->reportsForJobOrder($job->fresh(['samples', 'analyses', 'packages', 'packageLinks']), $wwAnalyst, withValues: false);
        $wwReport = $reportsAfter->first(
            fn ($report) => $report->analyses->contains(
                fn ($line) => (int) $line->id === (int) $wwLine->id,
            ),
        );
        $this->assertNotNull($wwReport?->controlledForm);
        $wwFormId = (int) $wwReport->controlledForm->id;
        $this->assertNotSame($microFormId, $wwFormId);

        $this->actingAs($wwAnalyst)
            ->post("/analyst/job-orders/{$job->id}/submit-for-review", [
                'sample_id' => $sample->id,
                'form_id' => $wwFormId,
                'signatories' => $this->fo2Signatories(),
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $job->refresh();
        $sample->refresh();

        $this->assertSame(JobOrderStatus::PendingReview, $job->status);
        $this->assertContains($wwFormId, array_map('intval', $sample->submitted_form_ids ?? []));
        $this->assertContains($microFormId, array_map('intval', $sample->submitted_form_ids ?? []));
    }

    public function test_complete_sample_with_form_id_skips_sibling_discipline_lines(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $wwAnalyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $microAnalyst = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $wwType = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $microTypeIds = $package->analysisTypes()->pluck('analysis_types.id')->map(fn ($id) => (int) $id)->all();

        $wwAnalyst->analysisTypes()->sync([$wwType->id]);
        $microAnalyst->analysisTypes()->sync($microTypeIds);
        $package->forceFill(['signatory_user_id' => $microAnalyst->id])->save();

        $selectedTypeIds = array_values(array_unique(array_merge($microTypeIds, [(int) $wwType->id])));

        $this->post('/intake/job-orders', [
            'customer_name' => 'Complete Sheet Customer',
            'customer_email' => 'complete-sheet@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $selectedTypeIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $sample = $job->samples()->firstOrFail();

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

        $job->refresh();
        $wwLine = $job->analyses->first(
            fn ($line) => (int) $line->analysis_type_id === (int) $wwType->id,
        );
        $microLines = $job->analyses
            ->filter(fn ($line) => in_array((int) $line->analysis_type_id, $microTypeIds, true))
            ->values();

        $this->assertNotNull($wwLine);
        $this->assertNotEmpty($microLines);

        foreach ($microLines as $line) {
            $this->actingAs($microAnalyst)
                ->post("/analyst/tasks/{$line->id}/draft", $this->completeResultPayload([
                    'result_value' => '<1.1',
                    'result_pass_fail' => null,
                ]))
                ->assertOk()
                ->assertSessionDoesntHaveErrors();
        }

        $resolver = app(AnalysisResultReportResolver::class);
        $reports = $resolver->reportsForJobOrder(
            $job->fresh(['samples', 'analyses', 'packages', 'packageLinks']),
            $microAnalyst,
            withValues: false,
        );
        $microReport = $reports->first(
            fn ($report) => $report->analyses->contains(
                fn ($line) => (int) $line->id === (int) $microLines->first()->id,
            ),
        );
        $this->assertNotNull($microReport?->controlledForm);
        $microFormId = (int) $microReport->controlledForm->id;

        // Without form_id this used to assert WW lines and fail "not qualified".
        $this->actingAs($microAnalyst)
            ->post("/analyst/job-orders/{$job->id}/complete-sample", [
                'sample_id' => $sample->id,
                'form_id' => $microFormId,
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        foreach ($microLines as $line) {
            $this->assertSame(JobOrderAnalysisStatus::Completed, $line->fresh()->status);
        }

        $this->assertNotSame(JobOrderAnalysisStatus::Completed, $wwLine->fresh()->status);
    }

    public function test_ww_analyst_can_save_result_footer_on_mixed_job_when_fo4_lists_first(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $wwAnalyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $microAnalyst = User::where('email', 'analyst2@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $wwType = AnalysisType::query()->where('code', 'WW-08')->firstOrFail();
        $microTypeIds = $package->analysisTypes()->pluck('analysis_types.id')->map(fn ($id) => (int) $id)->all();

        $wwAnalyst->analysisTypes()->sync([$wwType->id]);
        $microAnalyst->analysisTypes()->sync($microTypeIds);
        $package->forceFill(['signatory_user_id' => $microAnalyst->id])->save();

        $selectedTypeIds = array_values(array_unique(array_merge($microTypeIds, [(int) $wwType->id])));

        $this->post('/intake/job-orders', [
            'customer_name' => 'Footer Mixed Customer',
            'customer_email' => 'footer-mixed@example.com',
            'customer_contact' => '09170000000',
            'samples' => [
                [
                    'description' => 'Wastewater',
                    'matrix' => 'Liquid',
                    'classification' => 'Wastewater',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $selectedTypeIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $sample = $job->samples()->firstOrFail();

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

        $job->refresh();
        $wwLine = $job->analyses->first(
            fn ($line) => (int) $line->analysis_type_id === (int) $wwType->id,
        );
        $microLines = $job->analyses
            ->filter(fn ($line) => in_array((int) $line->analysis_type_id, $microTypeIds, true))
            ->values();

        $this->assertNotNull($wwLine);

        foreach ($microLines as $line) {
            $this->actingAs($microAnalyst)
                ->post("/analyst/tasks/{$line->id}/complete", $this->completeResultPayload([
                    'result_value' => '<1.1',
                    'result_pass_fail' => null,
                ]))
                ->assertRedirect();
        }

        $this->actingAs($wwAnalyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '25',
                'humidity_pct' => '55',
                'test_report_no' => 'TR-MIX-1',
            ])
            ->assertOk();

        $this->actingAs($wwAnalyst)
            ->post("/analyst/tasks/{$wwLine->id}/complete", $this->completeResultPayload([
                'result_value' => '7.1',
            ]))
            ->assertRedirect();

        $resolver = app(AnalysisResultReportResolver::class);
        $reports = $resolver->reportsForJobOrder(
            $job->fresh(['samples', 'analyses', 'packages', 'packageLinks']),
            $wwAnalyst,
            withValues: false,
        );
        $this->assertSame('unavailable', $reports->first()?->kind);

        $wwReport = $reports->first(
            fn ($report) => $report->analyses->contains(
                fn ($line) => (int) $line->id === (int) $wwLine->id,
            ),
        );
        $this->assertNotNull($wwReport?->controlledForm);

        // After every sheet is complete, FO4 still lists first — footer must target FO2.
        $this->actingAs($wwAnalyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '26',
                'humidity_pct' => '60',
                'test_report_no' => 'TR-MIX-2',
                'sample_id' => $sample->id,
                'form_id' => $wwReport->controlledForm->id,
            ])
            ->assertOk()
            ->assertJsonPath('footer.test_report_no', 'TR-MIX-2');

        $this->actingAs($wwAnalyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '27',
                'humidity_pct' => '61',
                'test_report_no' => 'TR-MIX-3',
            ])
            ->assertOk()
            ->assertJsonPath('footer.test_report_no', 'TR-MIX-3');
    }

    /**
     * @return list<array{name: string, prc_id: string}>
     */
    private function fo4Signatories(): array
    {
        return [
            ['name' => 'Micro Analyst', 'prc_id' => '111111'],
        ];
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