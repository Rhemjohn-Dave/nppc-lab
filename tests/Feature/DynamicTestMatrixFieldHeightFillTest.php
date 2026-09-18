<?php

namespace Tests\Feature;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use App\Services\ControlledPdfFiller;
use App\Support\DynamicTestMatrix;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class DynamicTestMatrixFieldHeightFillTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_dynamic_test_matrix_stretches_body_to_field_box_bottom(): void
    {
        $this->seed();

        $filler = app(ControlledPdfFiller::class);
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $y = 70.0;
        $h = 90.0;
        $field = [
            'name' => 'test_matrix',
            'type' => ControlledFormFieldType::DynamicTestMatrix->value,
            'page' => 1,
            'x' => 40.0,
            'y' => $y,
            'w' => 140.0,
            'h' => $h,
            'font_size' => 11.0,
            'align' => 'L',
            'font_family' => 'helvetica',
            'font_color' => '#000000',
            'table_config' => [
                'columns' => DynamicTestMatrix::defaultConfig()['columns'],
                'row_height_mm' => 9,
                'header_row_height_mm' => 15,
                'header_row' => true,
                'border' => true,
                'header_bold' => true,
                'test_name_bold' => true,
                'method_font_size' => 9,
            ],
        ];

        $rows = [
            ['test' => 'Test A', 'test_method' => 'Method A', 'result' => '1.0'],
            ['test' => 'Test B', 'test_method' => 'Method B', 'result' => '2.0'],
            ['test' => 'Test C', 'test_method' => null, 'result' => ''],
            ['test' => 'Test D', 'test_method' => 'Method D', 'result' => '4.0'],
            ['test' => 'Test E', 'test_method' => 'Method E', 'result' => '5.0'],
        ];

        $write = new ReflectionMethod($filler, 'writeDynamicTestMatrix');
        $write->setAccessible(true);
        $write->invoke($filler, $pdf, $field, $rows, []);

        // Recompute the stretch plan the writer uses so we assert the bottom edge.
        $measureHeader = new ReflectionMethod($filler, 'measureMatrixHeaderRowHeight');
        $measureHeader->setAccessible(true);
        $headerHeight = $measureHeader->invoke(
            $filler,
            $field['table_config']['columns'],
            9.0,
            11.0,
            15.0,
        );

        $measureBody = new ReflectionMethod($filler, 'measureMatrixBodyRowHeight');
        $measureBody->setAccessible(true);
        $naturals = [];
        foreach ($rows as $row) {
            $naturals[] = $measureBody->invoke(
                $filler,
                $pdf,
                $row,
                $field['table_config']['columns'],
                140.0,
                9.0,
                11.0,
                9.0,
                'helvetica',
                true,
            );
        }

        $stretched = DynamicTestMatrix::stretchBodyRowHeights($naturals, $h - $headerHeight);
        $drawnBottom = $y + $headerHeight + array_sum($stretched);

        $this->assertEqualsWithDelta($y + $h, $drawnBottom, 0.05);
        $this->assertCount(5, $stretched);
        $this->assertStringStartsWith('%PDF', $pdf->Output('', 'S'));
    }

    public function test_milk_matrix_field_height_heal_updates_existing_revision_without_pdf_change(): void
    {
        $this->seed();

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->firstOrFail();
        $revision = $form->activeRevision() ?? $form->revisions()->firstOrFail();
        $pathBefore = $revision->canonical_pdf_path;

        $matrix = $revision->fields()
            ->where('name', 'milk_f016_matrix')
            ->first();

        if (! $matrix) {
            $matrix = ControlledFormField::query()->create([
                'controlled_form_revision_id' => $revision->id,
                'name' => 'milk_f016_matrix',
                'label' => 'Dynamic test matrix',
                'field_type' => ControlledFormFieldType::DynamicTestMatrix,
                'page_number' => 1,
                'x' => 39,
                'y' => 73,
                'width' => 140,
                'height' => 50,
                'font_size' => 11,
                'z_order' => 1,
            ]);
        } else {
            $matrix->update(['height' => 50]);
        }

        $migration = require database_path('migrations/2026_09_16_170000_heal_milk_matrix_field_box_height.php');
        $migration->up();

        $matrix->refresh();
        $revision->refresh();

        $this->assertEqualsWithDelta(92.0, (float) $matrix->height, 0.01);
        $this->assertSame($pathBefore, $revision->canonical_pdf_path);
    }
}
