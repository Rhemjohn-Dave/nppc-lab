<?php

namespace Tests\Feature;

use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisType;
use App\Models\JobOrder;
use App\Models\User;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TdsRawFinishEncodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_analyst_draft_and_complete_accept_raw_and_finish_for_dw_03(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $tds = AnalysisType::query()->where('code', 'DW-03')->firstOrFail();
        $tds->update(['requires_pass_fail' => false]);

        $this->assertTrue(OfficialAnalysisCatalog::usesRawFinishResults($tds->code));

        $job = $this->intakeAndReceive(
            $receiving,
            [$tds->id],
            'TDS Raw Finish Customer',
            'tds-raw-finish@example.com',
        );

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_raw' => '450',
                'result_finish' => '8',
                'result_unit' => 'mg/L',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($tds) ?? 'TDS method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertSame('450', $line->result_raw);
        $this->assertSame('8', $line->result_finish);
        $this->assertSame('Raw: 450; Finish: 8', $line->result_value);
        $this->assertSame(JobOrderAnalysisStatus::InProgress, $line->status);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_raw' => '450',
                'result_finish' => '8',
                'result_unit' => 'mg/L',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($tds) ?? 'TDS method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);
        $this->assertSame('450', $line->result_raw);
        $this->assertSame('8', $line->result_finish);

        $rows = DynamicTestMatrix::buildRows($job->fresh(['analyses.analysisType'])->analyses);
        $this->assertCount(2, $rows);
        $this->assertSame('Total Dissolved Solids (mg/L)', $rows[0]['test']);
        $this->assertSame('Total Dissolved Solids (mg/L)', $rows[1]['test']);
        $this->assertTrue(DynamicTestMatrix::rowsFormSpanPair($rows[0], $rows[1]));
        $this->assertSame('450 mg/L', $rows[0]['result']);
        $this->assertSame('8 mg/L', $rows[1]['result']);
    }

    public function test_complete_accepts_raw_only_or_finish_only_for_dw_03(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $tds = AnalysisType::query()->where('code', 'DW-03')->firstOrFail();
        $tds->update(['requires_pass_fail' => false]);

        $jobRaw = $this->intakeAndReceive(
            $receiving,
            [$tds->id],
            'TDS Raw Only Customer',
            'tds-raw-only@example.com',
        );
        $lineRaw = $jobRaw->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$lineRaw->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_raw' => '450',
                'result_method' => 'TDS method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $lineRaw->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $lineRaw->status);
        $this->assertSame('450', $lineRaw->result_raw);
        $this->assertNull($lineRaw->result_finish);
        $this->assertSame('Raw: 450', $lineRaw->result_value);

        $jobFinish = $this->intakeAndReceive(
            $receiving,
            [$tds->id],
            'TDS Finish Only Customer',
            'tds-finish-only@example.com',
        );
        $lineFinish = $jobFinish->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$lineFinish->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_finish' => '8',
                'result_method' => 'TDS method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $lineFinish->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $lineFinish->status);
        $this->assertNull($lineFinish->result_raw);
        $this->assertSame('8', $lineFinish->result_finish);
        $this->assertSame('Finish: 8', $lineFinish->result_value);

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$lineFinish->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_method' => 'TDS method',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['result_raw', 'result_finish']);
    }

    public function test_analyst_draft_and_complete_accept_finish_and_chlorinated_for_dw_05(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $cl = AnalysisType::query()->where('code', 'DW-05')->firstOrFail();
        $cl->update(['requires_pass_fail' => false]);

        $this->assertTrue(OfficialAnalysisCatalog::usesResidualChlorineResults($cl->code));

        $job = $this->intakeAndReceive(
            $receiving,
            [$cl->id],
            'Cl Residual Customer',
            'cl-residual@example.com',
        );

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_finish' => '0.4',
                'result_chlorinated' => '0.9',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($cl) ?? 'DPD method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertNull($line->result_raw);
        $this->assertSame('0.4', $line->result_finish);
        $this->assertSame('0.9', $line->result_chlorinated);
        $this->assertSame('Finish: 0.4; Chlorinated: 0.9', $line->result_value);
        $this->assertSame(JobOrderAnalysisStatus::InProgress, $line->status);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_finish' => '0.4',
                'result_chlorinated' => '0.9',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($cl) ?? 'DPD method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $rows = DynamicTestMatrix::buildRows($job->fresh(['analyses.analysisType'])->analyses);
        $this->assertCount(3, $rows);
        $this->assertSame('0.4', $rows[0]['result']);
        $this->assertSame('0.9', $rows[1]['result']);
        $this->assertSame('-', $rows[2]['result']);
    }

    public function test_complete_accepts_finish_only_or_chlorinated_only_for_dw_05(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $cl = AnalysisType::query()->where('code', 'DW-05')->firstOrFail();
        $cl->update(['requires_pass_fail' => false]);

        $jobFinish = $this->intakeAndReceive(
            $receiving,
            [$cl->id],
            'Cl Finish Only Customer',
            'cl-finish-only@example.com',
        );
        $lineFinish = $jobFinish->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$lineFinish->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_finish' => '0.4',
                'result_method' => 'DPD method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $lineFinish->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $lineFinish->status);
        $this->assertSame('0.4', $lineFinish->result_finish);
        $this->assertNull($lineFinish->result_chlorinated);
        $this->assertSame('Finish: 0.4', $lineFinish->result_value);

        $jobCl = $this->intakeAndReceive(
            $receiving,
            [$cl->id],
            'Cl Chlorinated Only Customer',
            'cl-chlorinated-only@example.com',
        );
        $lineCl = $jobCl->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$lineCl->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_chlorinated' => '0.9',
                'result_method' => 'DPD method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $lineCl->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $lineCl->status);
        $this->assertNull($lineCl->result_finish);
        $this->assertSame('0.9', $lineCl->result_chlorinated);
        $this->assertSame('Chlorinated: 0.9', $lineCl->result_value);

        $this->actingAs($analyst)
            ->from('/analyst')
            ->post("/analyst/tasks/{$lineCl->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_method' => 'DPD method',
            ])
            ->assertRedirect('/analyst')
            ->assertSessionHasErrors(['result_finish', 'result_chlorinated']);
    }

    public function test_analyst_draft_and_complete_accept_raw_and_finish_for_dw_02(): void
    {
        $this->seed();

        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $ph = AnalysisType::query()->where('code', 'DW-02')->firstOrFail();
        $ph->update(['requires_pass_fail' => false]);

        $this->assertTrue(OfficialAnalysisCatalog::usesRawFinishResults($ph->code));

        $job = $this->intakeAndReceive(
            $receiving,
            [$ph->id],
            'pH Raw Finish Customer',
            'ph-raw-finish@example.com',
        );

        $line = $job->analyses()->firstOrFail();

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/draft", [
                'performed_at' => '2026-07-29T15:00',
                'result_raw' => '7.2',
                'result_finish' => '6.1',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($ph) ?? 'pH method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertSame('7.2', $line->result_raw);
        $this->assertSame('6.1', $line->result_finish);
        $this->assertSame('Raw: 7.2; Finish: 6.1', $line->result_value);
        $this->assertSame(JobOrderAnalysisStatus::InProgress, $line->status);

        $this->actingAs($analyst)
            ->post("/analyst/tasks/{$line->id}/complete", [
                'performed_at' => '2026-07-29T15:00',
                'result_raw' => '7.2',
                'result_finish' => '6.1',
                'result_method' => DynamicTestMatrix::methodForAnalysisType($ph) ?? 'pH method',
            ])
            ->assertRedirect()
            ->assertSessionDoesntHaveErrors();

        $line->refresh();
        $this->assertSame(JobOrderAnalysisStatus::Completed, $line->status);

        $rows = DynamicTestMatrix::buildRows($job->fresh(['analyses.analysisType'])->analyses);
        $this->assertCount(2, $rows);
        $this->assertSame('pH', $rows[0]['test']);
        $this->assertSame('pH', $rows[1]['test']);
        $this->assertTrue(DynamicTestMatrix::rowsFormSpanPair($rows[0], $rows[1]));
        $this->assertSame('7.2', $rows[0]['result']);
        $this->assertSame('6.1', $rows[1]['result']);
        $this->assertSame('6.5-8.5', $rows[0]['acceptable_values']);
    }

    /**
     * @param  list<int>  $typeIds
     */
    private function intakeAndReceive(
        User $receiving,
        array $typeIds,
        string $customer,
        string $email,
    ): JobOrder {
        $this->actingAs($receiving)
            ->post('/intake/job-orders', [
                'customer_name' => $customer,
                'customer_email' => $email,
                'classification' => 'Potability',
                'samples' => [
                    ['description' => 'Bottled water', 'matrix' => 'Liquid'],
                ],
                'package_ids' => [],
                'analysis_type_ids' => $typeIds,
            ])
            ->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
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

        return $job->fresh(['analyses.analysisType']) ?? $job;
    }
}
