<?php

namespace Tests\Feature;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\AnalysisResultReportResolver;
use App\Services\ControlledDocumentGenerator;
use App\Services\FieldValueResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DynamicTestMatrixReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_dynamic_matrix_package_prints_only_selected_test_rows(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $receiving = User::where('email', 'receiving@nppc.local')->firstOrFail();
        $analyst = User::where('email', 'analyst@nppc.local')->firstOrFail();

        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();
        $thermo = AnalysisType::query()->where('code', 'MB-02B')->firstOrFail();
        $hpc = AnalysisType::query()->where('code', 'MB-01')->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-FOOD-DEMO',
            'name' => 'Food analysis demo',
            'default_price' => 500,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 99,
        ]);
        $package->syncTypes([$total->id, $thermo->id, $hpc->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'FOOD-MATRIX-01',
                'name' => 'Food matrix result',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'FOOD-MATRIX-01')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $this->actingAs($admin)
            ->put("/admin/controlled-forms/{$form->id}/revisions/{$revision->id}/fields", [
                'fields' => [
                    [
                        'name' => 'food_matrix',
                        'label' => 'Test results',
                        'field_type' => 'dynamic_test_matrix',
                        'page_number' => 1,
                        'x' => 15,
                        'y' => 60,
                        'width' => 180,
                        'height' => 80,
                        'font_size' => 8,
                        'table_config' => [
                            'columns' => [
                                ['key' => 'test', 'label' => 'TEST', 'width_pct' => 55],
                                ['key' => 'result', 'label' => 'Result', 'width_pct' => 25],
                                ['key' => 'remarks', 'label' => 'Remarks', 'width_pct' => 20],
                            ],
                            'row_height_mm' => 5,
                            'header_row' => true,
                            'border' => true,
                        ],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'Matrix Customer',
            'customer_email' => 'matrix@example.com',
            'classification' => 'Potability',
            'samples' => [
                ['description' => 'Food sample', 'matrix' => 'Solid'],
            ],
            'package_ids' => [$package->id],
            'analysis_type_ids' => [$total->id, $thermo->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $this->assertEqualsCanonicalizing([$hpc->id], $job->waivedTypeIds());

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

        foreach ($job->fresh()->analyses as $line) {
            $this->actingAs($line->assignee ?? $analyst)
                ->post("/analyst/tasks/{$line->id}/complete", [
                    'result_value' => 'Passed',
                    'result_measurement' => '<1.1',
                    'result_unit' => 'MPN/100ml',
                    'result_remarks' => 'Pass',
                ])
                ->assertRedirect();
        }

        $job = $job->fresh(['analyses.analysisType', 'analyses.assignee', 'packages']);
        foreach ($job->analyses as $line) {
            $this->assertSame('completed', $line->status->value);
        }
        $revision = $form->fresh()->activeRevision();
        $this->assertNotNull($revision);

        $values = app(FieldValueResolver::class)->forResult(
            $revision->load('fields'),
            $job,
            null,
            $form->fresh(['analysisPackage']),
        );

        $this->assertArrayNotHasKey('test_1_result', $values);
        $this->assertArrayNotHasKey('test_3_result', $values);
        $this->assertIsArray($values['food_matrix']);
        $this->assertCount(2, $values['food_matrix']);
        $this->assertSame($total->name, $values['food_matrix'][0]['test']);

        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);
        $this->assertSame('combined', $report->kind);
        $this->assertCount(2, $report->analyses);

        $pdf = app(ControlledDocumentGenerator::class)->fromResultForm(
            $form,
            $job,
            $admin,
            $report->analyses,
        )['binary'];

        $this->assertStringStartsWith('%PDF', $pdf);

        $previewLine = $job->analyses->firstOrFail();
        $this->actingAs($admin)
            ->get("/analyst/tasks/{$previewLine->id}/combined-pdf")
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_matrix_package_without_matrix_field_is_unavailable(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $total = AnalysisType::query()->where('code', 'MB-02A')->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-MATRIX-NOFIELD',
            'name' => 'Matrix missing field',
            'default_price' => 100,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 100,
        ]);
        $package->syncTypes([$total->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'MATRIX-NOFIELD',
                'name' => 'No matrix region',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $this->post('/intake/job-orders', [
            'customer_name' => 'No matrix field',
            'customer_email' => 'nomatrix@example.com',
            'samples' => [['description' => 'Sample', 'matrix' => 'Solid']],
            'package_ids' => [$package->id],
        ])->assertRedirect();

        $job = JobOrder::query()->latest('id')->firstOrFail();
        $report = app(AnalysisResultReportResolver::class)->forJobOrder($job, $admin);

        $this->assertSame('unavailable', $report->kind);
        $this->assertStringContainsString('Dynamic test matrix', (string) $report->message);
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
