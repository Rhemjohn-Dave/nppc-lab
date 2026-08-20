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
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

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

        $this->actingAs($receiving)
            ->post("/receiving/{$job->id}/receive")
            ->assertRedirect('/receiving');

        foreach ($job->analyses()->get() as $line) {
            $line->refresh();
            $worker = $line->assignee ?? $analyst;
            $this->actingAs($worker)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => 'Passed',
                    'result_measurement' => '<1.1',
                    'result_unit' => 'MPN/100ml',
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

        $this->assertSame('Passed', $values['test_1_result']);
        $this->assertSame('Passed', $values['test_2_result']);
        $this->assertSame('-', $values['test_3_result']);
        $this->assertSame('-', $values['test_3_measurement']);
        $this->assertSame('-', $values['test_3_unit']);
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
