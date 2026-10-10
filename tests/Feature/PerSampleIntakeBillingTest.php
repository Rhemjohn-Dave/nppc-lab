<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Support\SampleControlNumber;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PerSampleIntakeBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_two_samples_same_package_bill_twice(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $memberIds = $package->orderedTypeIds();
        $memberPriceSum = (float) AnalysisType::query()
            ->whereIn('id', $memberIds)
            ->sum('default_price');

        $this->post('/intake/job-orders', [
            'customer_name' => 'Multi Sample Customer',
            'customer_email' => 'multi@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
                [
                    'description' => 'Sample B',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load(['samples', 'analyses', 'packageLinks']);

        $this->assertCount(2, $job->samples);
        $this->assertCount(2, $job->packageLinks);
        $this->assertCount(count($memberIds) * 2, $job->analyses);
        $this->assertEqualsWithDelta($memberPriceSum * 2, (float) $job->total_cost, 0.01);

        $this->assertSame(
            SampleControlNumber::forIndex($job->reference_no, 0, 2),
            $job->reference_no.'A',
        );
        $this->assertSame(
            SampleControlNumber::forIndex($job->reference_no, 1, 2),
            $job->reference_no.'B',
        );
    }

    public function test_reports_for_job_order_emits_one_form_per_sample(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $memberIds = $package->orderedTypeIds();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Report Customer',
            'customer_email' => 'report@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
                [
                    'description' => 'Sample B',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load([
            'samples',
            'analyses',
            'packageLinks.analysisPackage',
            'packages',
        ]);
        $admin = User::role('admin')->firstOrFail();

        $reports = app(AnalysisResultReportResolver::class)
            ->reportsForJobOrder($job, $admin, withValues: false);

        $this->assertCount(2, $reports);
        $this->assertSame($job->reference_no.'A', $reports[0]->controlNumber);
        $this->assertSame($job->reference_no.'B', $reports[1]->controlNumber);
        $this->assertNotNull($reports[0]->controlledForm);
        $this->assertNotNull($reports[1]->controlledForm);
        $this->assertSame(
            (int) $reports[0]->controlledForm->id,
            (int) $reports[1]->controlledForm->id,
        );
    }

    public function test_result_report_manifest_lists_loadable_sample_tabs(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $memberIds = $package->orderedTypeIds();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Preview Tabs Customer',
            'customer_email' => 'preview-tabs@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                [
                    'description' => 'Sample A',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
                [
                    'description' => 'Sample B',
                    'matrix' => 'Liquid',
                    'package_ids' => [$package->id],
                    'analysis_type_ids' => $memberIds,
                ],
            ],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail()->load(['samples', 'analyses']);
        $head = User::role('head_analysis')->firstOrFail();
        $analyst = User::role('analyst')->firstOrFail();
        $sampleB = $job->samples->values()->get(1);
        $this->assertNotNull($sampleB);

        $job->analyses()->update(['assigned_to' => $analyst->id]);

        $headManifest = $this->actingAs($head)
            ->getJson("/head/{$job->id}/result-report")
            ->assertOk()
            ->assertJsonCount(2, 'reports')
            ->assertJsonPath('reports.0.control_number', $job->reference_no.'A')
            ->assertJsonPath('reports.1.control_number', $job->reference_no.'B')
            ->assertJsonPath('reports.0.label', 'Control no. '.$job->reference_no.'A')
            ->assertJsonPath('reports.1.label', 'Control no. '.$job->reference_no.'B')
            ->json();

        $this->assertStringContainsString(
            "/head/{$job->id}/result-report",
            (string) data_get($headManifest, 'reports.0.report_url'),
        );
        $this->assertStringContainsString(
            'sample_id='.$sampleB->id,
            (string) data_get($headManifest, 'reports.1.report_url'),
        );

        $this->actingAs($head)
            ->getJson("/head/{$job->id}/result-report?sample_id={$sampleB->id}")
            ->assertOk()
            ->assertJsonPath('sample_id', $sampleB->id)
            ->assertJsonPath('control_number', $job->reference_no.'B');

        $analystManifest = $this->actingAs($analyst)
            ->getJson("/analyst/job-orders/{$job->id}/result-report")
            ->assertOk()
            ->assertJsonCount(2, 'reports')
            ->assertJsonPath('reports.0.label', 'Control no. '.$job->reference_no.'A')
            ->json();

        $this->assertStringContainsString(
            "/analyst/job-orders/{$job->id}/result-report",
            (string) data_get($analystManifest, 'reports.1.report_url'),
        );

        $this->actingAs($analyst)
            ->getJson("/analyst/job-orders/{$job->id}/result-report?sample_id={$sampleB->id}")
            ->assertOk()
            ->assertJsonPath('sample_id', $sampleB->id)
            ->assertJsonPath('control_number', $job->reference_no.'B');
    }
}
