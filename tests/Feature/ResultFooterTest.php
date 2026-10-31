<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\FieldValueResolver;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResultFooterTest extends TestCase
{
    use RefreshDatabase;

    public function test_seed_exposes_fo2a_and_abstracted_resolves_to_it(): void
    {
        Storage::fake('local');
        $this->seed();

        $fo2a = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->first();
        $this->assertNotNull($fo2a);
        $this->assertNotNull($fo2a->activeRevision());
        $this->assertSame(3, (int) $fo2a->analyst_signatory_slots);

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = OfficialAnalysisCatalog::wastewaterPhysicoAbstractedTypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2A Resolve',
                'customer_email' => 'fo2a-resolve@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    [
                        'description' => 'Abstracted water',
                        'matrix' => 'Liquid',
                        'analysis_type_ids' => $typeIds,
                        'package_ids' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->advanceFo2JobToReady($job);

        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $resolved = app(\App\Services\AnalysisResultReportResolver::class)->forJobOrder($job->fresh(), $analyst);
        $this->assertSame('LSP-7.8-FO2A', $resolved->controlledForm?->form_code);
    }

    public function test_analyst_can_override_result_footer_notes(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $codes = OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes();
        $typeIds = AnalysisType::query()
            ->whereIn('code', array_slice($codes, 0, 3))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Footer Override',
                'customer_email' => 'fo2-footer-override@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    [
                        'description' => 'Wastewater',
                        'matrix' => 'Liquid',
                        'analysis_type_ids' => $typeIds,
                        'package_ids' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->advanceFo2JobToReady($job);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'lab_conditions' => 'Custom lab conditions',
                'references' => 'Custom references',
                'notes' => 'Custom override notes',
            ])
            ->assertOk()
            ->assertJsonPath('footer.notes', 'Custom override notes')
            ->assertJsonPath('footer.lab_conditions', 'Custom lab conditions');

        $job->refresh();
        $this->assertSame('Custom override notes', $job->result_footer['notes'] ?? null);

        $bag = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job->fresh(['samples', 'analyses.analysisType']),
            null,
            $form->fresh(['analysisPackage', 'analysisTypes']),
            $job->samples()->first(),
        );

        $this->assertSame('Custom lab conditions', $bag['fo2_lab_conditions']);
        $this->assertSame('Custom references', $bag['fo2_references']);
        $this->assertSame('Custom override notes', $bag['fo2_notes']);
    }

    public function test_result_footer_reset_restores_catalog_defaults(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $typeIds = AnalysisType::query()
            ->whereIn('code', array_slice(OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes(), 0, 2))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Footer Reset',
                'customer_email' => 'fo2-footer-reset@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    [
                        'description' => 'River water',
                        'matrix' => 'Liquid',
                        'analysis_type_ids' => $typeIds,
                        'package_ids' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->advanceFo2JobToReady($job);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'notes' => 'Temporary notes',
            ])
            ->assertOk();

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'reset' => true,
            ])
            ->assertOk();

        $this->assertNull($job->fresh()->result_footer);

        $bag = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job->fresh(['samples', 'analyses.analysisType']),
            null,
            $form->fresh(['analysisPackage', 'analysisTypes']),
            $job->samples()->first(),
        );

        $this->assertStringContainsString('***Not PAB and EMB', (string) $bag['fo2_notes']);
    }

    private function advanceFo2JobToReady(JobOrder $job): void
    {
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $lines = $job->analyses()->get();

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

        foreach ($job->fresh()->analyses as $line) {
            $line->update(['assigned_to' => $analyst->id]);

            $this->actingAs($analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'performed_at' => '2026-10-20T10:00',
                    'result_value' => '1.0',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                    'result_unit' => 'mg/L',
                    'result_remarks' => 'Ok',
                ])
                ->assertRedirect();
        }
    }
}
