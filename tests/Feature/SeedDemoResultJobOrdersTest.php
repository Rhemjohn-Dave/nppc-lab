<?php

namespace Tests\Feature;

use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class SeedDemoResultJobOrdersTest extends TestCase
{
    use RefreshDatabase;

    public function test_command_creates_demo_job_resolving_to_fo26(): void
    {
        $this->seed();

        $exit = Artisan::call('demo:seed-result-jobs', [
            '--form' => ['LSP-7.8-FO26'],
            '--fresh' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());

        $job = JobOrder::query()
            ->where('customer_email', 'demo-fo26-result@nppc.local')
            ->latest('id')
            ->first();

        $this->assertNotNull($job);
        $this->assertNotEmpty($job->reference_no);
        $job->load(['samples', 'analyses']);
        $this->assertCount(2, $job->samples);
        $this->assertSame(1, $job->analyses->pluck('analysis_type_id')->unique()->count());
        $this->assertCount(2, $job->analyses);
        $this->assertSame(
            $job->analyses()->count(),
            $job->analyses()->where('status', 'completed')->count(),
        );

        $analyst = User::query()->where('email', 'analyst@nppc.local')->firstOrFail();
        $resolver = app(AnalysisResultReportResolver::class);
        $loaded = $job->fresh(['samples', 'analyses.analysisType', 'analyses.assignee', 'packages', 'packageLinks']) ?? $job;

        $this->assertSame(
            'LSP-7.8-FO26',
            $resolver->matchingControlledForm($loaded)?->form_code,
            'Multi-sample lean demos must resolve a job-level form so Signatories can edit.',
        );

        $resolved = $resolver->forJobOrder($loaded, $analyst);
        $this->assertSame('LSP-7.8-FO26', $resolved->controlledForm?->form_code);

        $this->actingAs($analyst)
            ->get('/analyst')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('analyst/index')
                ->has('consolidations', fn ($consolidations) => $consolidations
                    ->where('0.id', $job->id)
                    ->where('0.signatory.can_edit', true)
                    ->etc()));
    }

    public function test_command_is_idempotent_without_fresh(): void
    {
        $this->seed();

        Artisan::call('demo:seed-result-jobs', [
            '--form' => ['LSP-7.8-FO26'],
            '--fresh' => true,
        ]);

        $firstId = JobOrder::query()
            ->where('customer_email', 'demo-fo26-result@nppc.local')
            ->value('id');
        $this->assertNotNull($firstId);

        Artisan::call('demo:seed-result-jobs', [
            '--form' => ['LSP-7.8-FO26'],
        ]);

        $this->assertSame(
            1,
            JobOrder::query()->where('customer_email', 'demo-fo26-result@nppc.local')->count(),
        );
        $this->assertSame(
            $firstId,
            JobOrder::query()->where('customer_email', 'demo-fo26-result@nppc.local')->value('id'),
        );
    }

    public function test_full_flag_selects_all_bound_types_for_fo26(): void
    {
        $this->seed();

        $exit = Artisan::call('demo:seed-result-jobs', [
            '--form' => ['LSP-7.8-FO26'],
            '--full' => true,
            '--fresh' => true,
        ]);

        $this->assertSame(0, $exit, Artisan::output());

        $job = JobOrder::query()
            ->where('customer_email', 'demo-fo26-full-result@nppc.local')
            ->latest('id')
            ->first();

        $this->assertNotNull($job);
        $job->load(['samples', 'analyses']);
        $this->assertCount(2, $job->samples);
        $this->assertGreaterThan(1, $job->analyses->pluck('analysis_type_id')->unique()->count());
        $this->assertSame(
            $job->analyses()->count(),
            $job->analyses()->where('status', 'completed')->count(),
        );

        $analyst = User::query()->where('email', 'analyst@nppc.local')->firstOrFail();
        $resolved = app(AnalysisResultReportResolver::class)->forJobOrder(
            $job->fresh(['samples', 'analyses.analysisType', 'analyses.assignee', 'packages', 'packageLinks']) ?? $job,
            $analyst,
        );

        $this->assertSame('LSP-7.8-FO26', $resolved->controlledForm?->form_code);
    }
}
