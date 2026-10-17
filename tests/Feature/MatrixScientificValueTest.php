<?php

namespace Tests\Feature;

use App\Enums\ControlledFormFieldType;
use App\Services\ControlledPdfFiller;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MatrixScientificValueTest extends TestCase
{
    public function test_scientific_result_in_narrow_column_is_never_written_with_caret(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $field = [
            'name' => 'test_matrix',
            'type' => ControlledFormFieldType::DynamicTestMatrix->value,
            'page' => 1,
            'x' => 20.0,
            'y' => 40.0,
            'w' => 120.0,
            'h' => 60.0,
            'font_size' => 11.0,
            'align' => 'L',
            'font_family' => 'helvetica',
            'font_color' => '#000000',
            'table_config' => [
                'columns' => [
                    ['key' => 'test', 'label' => 'Test', 'width_pct' => 80],
                    ['key' => 'result', 'label' => 'Result', 'width_pct' => 20],
                ],
                'row_height_mm' => 7,
                'header_row' => true,
                'border' => true,
                'stretch_body' => false,
            ],
        ];

        $rows = [
            ['test' => 'Aerobic Plate Count', 'result' => '120×10³ CFU/mL'],
            ['test' => 'Yeast and Molds', 'result' => '230×10⁻⁶'],
        ];

        $write = new ReflectionMethod($filler, 'writeDynamicTestMatrix');
        $write->setAccessible(true);
        $write->invoke($filler, $pdf, $field, $rows, []);

        $output = $pdf->Output('', 'S');

        $this->assertStringContainsString('CFU/mL', $output);
        $this->assertStringNotContainsString('^3', $output);
        $this->assertStringNotContainsString('^-6', $output);
    }

    public function test_plain_text_field_draws_scientific_power_as_ascii_superscript(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        $field = [
            'name' => 'test_1_measurement',
            'type' => ControlledFormFieldType::Text->value,
            'page' => 1,
            'x' => 120.0,
            'y' => 80.0,
            'w' => 18.0,
            'h' => 5.0,
            'font_size' => 11.0,
            'align' => 'C',
            'font_family' => 'helvetica',
            'font_color' => '#000000',
        ];

        $write = new ReflectionMethod($filler, 'writeField');
        $write->setAccessible(true);
        $write->invoke($filler, $pdf, $field, '230×10⁻⁶', []);

        $output = $pdf->Output('', 'S');

        $this->assertStringContainsString('(-6)', $output);
        $this->assertStringNotContainsString('⁻', $output);
        $this->assertStringNotContainsString('^-6', $output);
    }

    public function test_plain_scientific_flattens_superscripts_inside_sentences(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $plain = new ReflectionMethod($filler, 'plainScientific');
        $plain->setAccessible(true);

        $this->assertSame('Count 2.3×10-6 g', $plain->invoke($filler, 'Count 2.3×10⁻⁶ g'));
        $this->assertSame('No power here', $plain->invoke($filler, 'No power here'));
    }
}
