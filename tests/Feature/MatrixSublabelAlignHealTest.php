<?php

namespace Tests\Feature;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormFieldType;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MatrixSublabelAlignHealTest extends TestCase
{
    use RefreshDatabase;

    public function test_heal_centers_left_aligned_result_sublabels(): void
    {
        Storage::fake('local');
        $this->seed();

        $admin = User::where('email', 'admin@nppc.local')->firstOrFail();
        $type = AnalysisType::query()->where('code', 'PX-01')->firstOrFail();

        $package = AnalysisPackage::query()->create([
            'code' => 'PKG-ALIGN-HEAL',
            'name' => 'Align heal package',
            'default_price' => 100,
            'report_layout' => AnalysisPackageReportLayout::DynamicMatrix,
            'is_active' => true,
            'sort_order' => 120,
        ]);
        $package->syncTypes([$type->id]);

        $this->actingAs($admin)
            ->post('/admin/controlled-forms', [
                'form_code' => 'ALIGN-HEAL-01',
                'name' => 'Align heal form',
                'category' => ControlledFormCategory::AnalysisResult->value,
                'revision' => '01',
                'file' => $this->makeBlankResultPdf(),
                'activate' => 1,
                'analysis_package_id' => $package->id,
            ])
            ->assertRedirect();

        $form = ControlledForm::query()->where('form_code', 'ALIGN-HEAL-01')->firstOrFail();
        $revision = $form->activeRevision();
        $this->assertNotNull($revision);

        $field = ControlledFormField::query()->create([
            'controlled_form_revision_id' => $revision->id,
            'name' => 'prox_matrix_align_test',
            'label' => 'Matrix',
            'field_type' => ControlledFormFieldType::DynamicTestMatrix,
            'page_number' => 1,
            'x' => 10,
            'y' => 40,
            'width' => 180,
            'height' => 90,
            'font_size' => 8,
            'z_order' => 1,
            'table_config' => [
                'columns' => [
                    ['key' => 'test', 'label' => 'TEST', 'header_align' => 'C'],
                    [
                        'key' => 'result',
                        'label' => 'Control Number',
                        'sublabel' => "Sample Description:\nSS:",
                        'sublabel_align' => 'L',
                        'header_align' => 'C',
                        'align' => 'C',
                    ],
                ],
            ],
        ]);

        $migration = require database_path('migrations/2026_09_07_160000_heal_matrix_sublabel_align_center.php');
        $migration->up();

        $field->refresh();
        $this->assertSame('C', $field->table_config['columns'][1]['sublabel_align']);
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
