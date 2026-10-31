<?php

namespace Tests\Feature;

use App\Enums\ControlledFormFieldType;
use App\Services\ControlledPdfFiller;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class InlineBoldFieldWriteTest extends TestCase
{
    public function test_write_field_strips_markers_and_prints_title_and_body(): void
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
            'name' => 'methods',
            'type' => ControlledFormFieldType::Multiline->value,
            'page' => 1,
            'x' => 20.0,
            'y' => 40.0,
            'w' => 160.0,
            'h' => 40.0,
            'font_size' => 10.0,
            'align' => 'L',
            'font_family' => 'helvetica',
            'font_color' => '#000000',
            'options' => [
                'prefer_static' => true,
                'static_text' => "**Test Methods and References:**\nPour Plate Method body line",
            ],
        ];

        $write = new ReflectionMethod($filler, 'writeField');
        $write->setAccessible(true);
        $write->invoke($filler, $pdf, $field, 'mapped ignored', []);

        $raw = $pdf->Output('', 'S');
        // TCPDF emits word-sized text operators; markers must not appear.
        $this->assertStringContainsString('(Test )', $raw);
        $this->assertStringContainsString('(Methods )', $raw);
        $this->assertStringContainsString('(References:)', $raw);
        $this->assertStringContainsString('(Pour )', $raw);
        $this->assertStringContainsString('(Plate )', $raw);
        $this->assertStringContainsString('Helvetica-Bold', $raw);
        $this->assertStringNotContainsString('**', $raw);
        $this->assertStringNotContainsString('mapped ignored', $raw);
    }
}
