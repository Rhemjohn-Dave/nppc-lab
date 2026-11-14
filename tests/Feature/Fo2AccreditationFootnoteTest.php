<?php

namespace Tests\Feature;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fo2AccreditationFootnoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_intake_seeds_catalog_accreditation_footnotes(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $codes = ['WW-08', 'WW-09', 'WW-03'];
        $typeIds = AnalysisType::query()
            ->whereIn('code', $codes)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Asterisk Seed',
                'customer_email' => 'fo2-asterisk-seed@example.com',
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
        $byCode = $job->analyses()
            ->with('analysisType')
            ->get()
            ->keyBy(fn ($line) => $line->analysisType?->code);

        $this->assertNull($byCode->get('WW-08')?->accreditation_footnote);
        $this->assertSame('*', $byCode->get('WW-09')?->accreditation_footnote);
        $this->assertSame('***', $byCode->get('WW-03')?->accreditation_footnote);
    }

    public function test_analyst_can_set_accreditation_footnote_and_matrix_prefixes_test(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'WW-04')->firstOrFail();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Asterisk Encode',
                'customer_email' => 'fo2-asterisk-encode@example.com',
                'classification' => 'Wastewater',
                'samples' => [
                    [
                        'description' => 'Wastewater',
                        'matrix' => 'Liquid',
                        'analysis_type_ids' => [$type->id],
                        'package_ids' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->advanceJobForAnalyst($job, $analyst);
        $line = $job->fresh()->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-10-20T10:00',
                'result_value' => '12',
                'result_method' => '2120 B',
                'accreditation_footnote' => '***',
            ])
            ->assertOk();

        $line->refresh();
        $this->assertSame('***', $line->accreditation_footnote);

        $rows = DynamicTestMatrix::buildRows(collect([$line->fresh('analysisType')]));
        $this->assertSame('***Color (TCU)', $rows[0]['test'] ?? null);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-10-20T10:00',
                'result_value' => '12',
                'result_method' => '2120 B',
                'accreditation_footnote' => 'none',
            ])
            ->assertOk();

        $line->refresh();
        $this->assertSame('', $line->accreditation_footnote);
        $rowsNone = DynamicTestMatrix::buildRows(collect([$line->fresh('analysisType')]));
        $this->assertSame('Color (TCU)', $rowsNone[0]['test'] ?? null);
    }

    public function test_analyst_can_edit_footer_before_assays_complete(): void
    {
        Storage::fake('local');
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $typeIds = AnalysisType::query()
            ->whereIn('code', array_slice(OfficialAnalysisCatalog::wastewaterPhysicoIssue18TypeCodes(), 0, 2))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => 'FO2 Early Footer',
                'customer_email' => 'fo2-early-footer@example.com',
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
        $this->advanceJobForAnalyst($job, $analyst);

        $this->actingAs($analyst)
            ->putJson("/analyst/job-orders/{$job->id}/result-footer", [
                'temperature_c' => '24',
                'humidity_pct' => '60',
                'test_report_no' => 'TR-001',
                'notes' => 'Edited before complete',
            ])
            ->assertOk()
            ->assertJsonPath('footer.notes', 'Edited before complete')
            ->assertJsonPath('footer.atmosphere_complete', true);

        $this->assertSame('Edited before complete', $job->fresh()->result_footer['notes'] ?? null);
    }

    private function advanceJobForAnalyst(JobOrder $job, User $analyst): void
    {
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
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
        }
    }
};
