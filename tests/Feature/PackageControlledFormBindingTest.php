<?php

namespace Tests\Feature;

use App\Enums\ControlledFormCategory;
use App\Enums\JobOrderAnalysisStatus;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class PackageControlledFormBindingTest extends TestCase
{
    use RefreshDatabase;

    public function test_only_one_analysis_result_form_may_bind_a_package(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-NDW')->firstOrFail();

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO4')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO4',
                'name' => 'FO4 first',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO4-B',
                'name' => 'FO4 duplicate package',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'analysis_package_id' => $package->id,
            ])
            ->assertSessionHasErrors('analysis_package_id');

        $package->refresh();
        $this->assertSame('LSP-7.8-FO4', $package->form_code);
        $this->assertNotNull($package->resultForm);
        $this->assertSame('LSP-7.8-FO4', $package->resultForm->form_code);
    }

    public function test_package_subset_still_resolves_package_form_and_fills_dashes(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $package->update(['is_active' => true]);
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO5',
                'name' => 'FO5 drinking water',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();
        $this->assertSame($package->id, $form->analysis_package_id);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Partial Package Customer',
            'customer_email' => 'partial@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Water in sterile bottle', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
            'analysis_type_ids' => [$total->id, $thermo->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages()->where('analysis_packages.id', $package->id)->exists());
        $this->assertEqualsCanonicalizing([$hpc->id], $job->waivedTypeIds());
        $this->assertEqualsCanonicalizing(
            [$total->id, $thermo->id],
            $job->analyses()->pluck('analysis_type_id')->all(),
        );

        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame($form->id, $matched->id);

        $lines = $job->analyses()->get();
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

        foreach ($job->analyses()->get() as $line) {
            $line->refresh();
            $worker = $line->assignee ?? $analyst;
            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => '<1.1',
                    'result_pass_fail' => 'Passed',
                    'result_method' => 'Standard Method',
                    'result_unit' => 'MPN/100ml'
                ])
                ->assertRedirect();
        }

        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages', 'samples']),
            null,
            $form->fresh(['analysisTypes', 'analysisPackage.analysisTypes']),
        );

        $this->assertSame('Passed', $values['test_1_result'] ?? null);
        $this->assertSame('<1.1', $values['test_1_measurement'] ?? null);
        $this->assertSame('Passed', $values['test_2_result'] ?? null);
        $this->assertSame('<1.1', $values['test_2_measurement'] ?? null);
        $this->assertSame('-', $values['test_3_result'] ?? '-');
        $this->assertSame('-', $values['test_3_measurement'] ?? '-');
        $this->assertSame('-', $values['test_3_unit'] ?? '-');
        $this->assertCount(3, $form->fresh()->orderedTypeIds());
    }

    public function test_standalone_result_form_matches_exact_type_set_without_package(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'HPC-SOLO',
                'name' => 'HPC standalone result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_type_ids' => [$hpc->id],
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'HPC-SOLO')->firstOrFail();
        $this->assertNull($form->analysis_package_id);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Standalone Customer',
            'customer_email' => 'solo@example.com',
            'samples' => [
                ['description' => 'Water', 'matrix' => 'Liquid'],
            ],
            'analysis_type_ids' => [$hpc->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertTrue($job->packages->isEmpty());

        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame($form->id, $matched->id);
    }

    public function test_package_form_with_empty_type_pivot_still_fills_analyst_results(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();
        $package = AnalysisPackage::query()->where('code', 'PKG-MIC-DW')->firstOrFail();
        $package->update(['is_active' => true]);
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $existing = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->first();
        if ($existing) {
            foreach ($existing->revisions as $revision) {
                $revision->fields()->delete();
                $revision->delete();
            }
            $existing->delete();
        }

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'LSP-7.8-FO5',
                'name' => 'FO5 drinking water',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO5')->firstOrFail();

        // Simulate seeded shells that set analysis_package_id but never synced the pivot.
        $form->analysisTypes()->detach();
        $form->forceFill(['combination_key' => null])->save();
        $this->assertSame([], $form->fresh()->orderedTypeIds());
        $this->assertSame($package->id, $form->fresh()->analysis_package_id);

        $this->post('/intake/job-orders', [
            'customer_name' => 'Empty Pivot Customer',
            'customer_email' => 'empty-pivot@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Drinking water', 'matrix' => 'Liquid'],
            ],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing(
            [$total->id, $thermo->id, $hpc->id],
            $job->analyses()->pluck('analysis_type_id')->all(),
        );

        $matched = app(AnalysisResultReportResolver::class)->matchingControlledForm($job);
        $this->assertNotNull($matched);
        $this->assertSame($form->id, $matched->id);

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

        $encoded = [
            $total->id => $this->completeResultPayload([
                'result_value' => '3',
                'result_pass_fail' => 'Failed',
                'result_unit' => 'MPN/100ml',
            ]),
            $thermo->id => $this->completeResultPayload([
                'result_value' => '1',
                'result_pass_fail' => 'Passed',
                'result_unit' => 'MPN/100ml',
            ]),
            $hpc->id => $this->completeResultPayload([
                'result_value' => '2',
                'result_pass_fail' => 'Passed',
                'result_unit' => 'CFU/ml',
            ]),
        ];

        foreach ($job->analyses()->get() as $line) {
            $line->refresh();
            $payload = $encoded[(int) $line->analysis_type_id];
            $worker = $line->assignee ?? $analyst;
            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", $payload)
                ->assertRedirect();
            $this->assertSame(JobOrderAnalysisStatus::Completed, $line->fresh()->status);
        }

        $report = app(AnalysisResultReportResolver::class)->forJobOrder(
            $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages', 'samples']),
            $analyst,
        );

        $this->assertSame('combined', $report->kind);
        $this->assertCount(3, $report->analyses);
        $this->assertSame('Failed', $report->values['test_1_result'] ?? null);
        $this->assertSame('Passed', $report->values['test_2_result'] ?? null);
        $this->assertSame('Passed', $report->values['test_3_result'] ?? null);

        // Raw bag (before PDF field map) still carries measurements for any mapped keys.
        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);
        $revision->setRelation('fields', collect());
        $bag = app(FieldValueResolver::class)->forResult(
            $revision,
            $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages', 'samples']),
            $report->analyses,
            $form->fresh(['analysisTypes', 'analysisPackage.analysisTypes']),
        );
        $this->assertSame('3', $bag['test_1_measurement'] ?? null);
        $this->assertSame('1', $bag['test_2_measurement'] ?? null);
        $this->assertSame('2', $bag['test_3_measurement'] ?? null);

        // Heal restores the missing pivot for future designer/source lists.
        $this->assertSame(1, \App\Services\ControlledFormService::healEmptyPackageResultBindings());
        $this->assertCount(3, $form->fresh()->orderedTypeIds());
        $this->assertSame(0, \App\Services\ControlledFormService::healEmptyPackageResultBindings());
    }

    private function makeBlankResultPdf(): UploadedFile
    {
        $pdf = new Fpdi;
        $pdf->AddPage();
        $pdf->SetFont('Helvetica', '', 12);
        $pdf->Cell(40, 10, 'Result sheet');
        $binary = $pdf->Output('', 'S');
        $path = tempnam(sys_get_temp_dir(), 'result-pdf-');
        file_put_contents($path, $binary);

        return new UploadedFile($path, 'result.pdf', 'application/pdf', null, true);
    }
}
