<?php

namespace Tests\Feature;

use App\Models\AnalysisPackage;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\ReportDate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportDateTatTest extends TestCase
{
    use RefreshDatabase;

    public function test_fo5_report_date_is_four_calendar_days_after_received(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-BACT')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'TAT FO5',
            'customer_email' => 'tat-fo5@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Kitchen faucet', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->travelTo(Carbon::parse('2026-07-21 10:00:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
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

        $job = $job->fresh(['analyses', 'samples']);
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job,
            null,
            $form,
            $job->samples->first(),
        );

        $this->assertSame('July 25, 2026', $bag['results.report_date']);
        $this->assertSame('July 25, 2026', $bag['results.release_date']);
        $this->assertSame('July 25, 2026', $bag['results.issued_date']);
        $this->assertNull($job->reviewed_at);
    }

    public function test_fo37_report_date_is_seven_working_days_after_received(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-DW-PHYSICO')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO37')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'TAT FO37',
            'customer_email' => 'tat-fo37@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'DW physico', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->travelTo(Carbon::parse('2026-07-21 10:00:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
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

        $job = $job->fresh(['analyses', 'samples']);
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job,
            null,
            $form,
            $job->samples->first(),
        );

        $this->assertSame('July 30, 2026', $bag['results.report_date']);
    }

    public function test_analyst_can_override_report_date_per_sheet(): void
    {
        $this->seed();

        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $this->post('/intake/job-orders', [
            'customer_name' => 'TAT Override',
            'customer_email' => 'tat-override@example.com',
            'classification' => 'Wastewater',
            'samples' => [
                ['description' => 'Wastewater', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $lines = $job->analyses()->get();

        $this->travelTo(Carbon::parse('2026-07-21 10:00:00', 'Asia/Manila'));

        $this->actingAs($receiving)
            ->patch("/receiving/{$job->id}/pricing", [
                'lines' => $lines->map(fn ($line) => [
                    'id' => $line->id,
                    'unit_price' => 225,
                    'quantity' => 1,
                ])->all(),
            ])
            ->assertRedirect();

        $this->approveJobOrder($job);

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        $job = $job->fresh(['analyses', 'samples']);
        $sample = $job->samples->firstOrFail();

        $this->actingAs($analyst)
            ->patch("/analyst/job-orders/{$job->id}/report-date", [
                'sample_id' => $sample->id,
                'form_id' => $form->id,
                'report_date' => '2026-08-01',
            ])
            ->assertRedirect();

        $job = $job->fresh(['analyses', 'samples']);
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $revision->setRelation('fields', collect());
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job,
            null,
            $form,
            $sample->fresh(),
        );

        $this->assertSame('August 1, 2026', $bag['results.report_date']);
        $this->assertNotNull(ReportDate::override($job, $sample, $form));

        $this->actingAs($analyst)
            ->patch("/analyst/job-orders/{$job->id}/report-date", [
                'sample_id' => $sample->id,
                'form_id' => $form->id,
                'reset' => true,
            ])
            ->assertRedirect();

        $job = $job->fresh(['analyses', 'samples']);
        $revision->setRelation('fields', collect());
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job,
            null,
            $form,
            $sample->fresh(),
        );

        $this->assertSame('July 25, 2026', $bag['results.report_date']);
        $this->assertNull(ReportDate::override($job, $sample, $form));
    }
}
