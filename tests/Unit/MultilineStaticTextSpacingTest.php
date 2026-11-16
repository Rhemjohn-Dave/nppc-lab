<?php

namespace Tests\Unit;

use App\Services\ControlledPdfFiller;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MultilineStaticTextSpacingTest extends TestCase
{
    public function test_line_height_factor_stretches_vertical_advance(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'writeStyledMultiline');
        $method->setAccessible(true);

        $baseY = $this->drawAndMeasure($method, $filler, "Line one\nLine two\nLine three", 4.2, 0.0);
        $tallY = $this->drawAndMeasure($method, $filler, "Line one\nLine two\nLine three", 8.4, 0.0);

        $this->assertGreaterThan($baseY + 10.0, $tallY);
        $this->assertEqualsWithDelta(($tallY - 40.0) / ($baseY - 40.0), 2.0, 0.05);
    }

    public function test_paragraph_gap_adds_extra_on_blank_lines(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'writeStyledMultiline');
        $method->setAccessible(true);

        $without = $this->drawAndMeasure($method, $filler, "Para one\n\nPara two", 4.2, 0.0);
        $withGap = $this->drawAndMeasure($method, $filler, "Para one\n\nPara two", 4.2, 5.0);

        $this->assertEqualsWithDelta($without + 5.0, $withGap, 0.05);
    }

    public function test_leading_spaces_shift_cursor_without_collapsing(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $split = new ReflectionMethod(ControlledPdfFiller::class, 'splitLeadingIndent');
        $split->setAccessible(true);

        [$units, $body] = $split->invoke($filler, "    Indented label");
        $this->assertSame(4, $units);
        $this->assertSame('Indented label', $body);

        [$tabUnits, $tabBody] = $split->invoke($filler, "\tIndented");
        $this->assertSame(4, $tabUnits);
        $this->assertSame('Indented', $tabBody);

        $pdf = $this->newPdf();
        $indent = new ReflectionMethod(ControlledPdfFiller::class, 'leadingIndentMm');
        $indent->setAccessible(true);
        $pdf->SetFont('times', '', 10);
        $indentMm = (float) $indent->invoke($filler, $pdf, 10.0, 4);
        $this->assertGreaterThan(2.0, $indentMm);

        $write = new ReflectionMethod(ControlledPdfFiller::class, 'writeStyledMultiline');
        $write->setAccessible(true);
        $endY = $write->invoke(
            $filler,
            $pdf,
            '    Indented label',
            20.0,
            40.0,
            160.0,
            40.0,
            4.2,
            'times',
            10.0,
            false,
            0.0,
        );

        $raw = $pdf->Output('', 'S');
        $this->assertStringContainsString('(Indented )', $raw);
        $this->assertStringContainsString('(label)', $raw);
        $this->assertGreaterThan(40.0, $endY);
    }

    public function test_write_field_keeps_tab_indent_from_static_text(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $pdf = $this->newPdf();
        $write = new ReflectionMethod(ControlledPdfFiller::class, 'writeField');
        $write->setAccessible(true);

        $write->invoke($filler, $pdf, [
            'name' => 'fo2_signatory_reviewed',
            'type' => 'multiline',
            'x' => 12.0,
            'y' => 40.0,
            'w' => 95.0,
            'h' => 30.0,
            'font_size' => 9.0,
            'font_family' => 'times',
            'align' => 'L',
            'options' => [
                'signatory_block' => [
                    'label' => 'Reviewed by:',
                    'people' => [[
                        'name_key' => 'results.analyst_name',
                        'fallback_name' => 'MA. ANTONIELA C. LANACA',
                        'lines_after_name' => ['Laboratory Analyst'],
                    ]],
                ],
                'static_text' => "Reviewed by:\n    MA. ANTONIELA C. LANACA\nLaboratory Analyst",
            ],
        ], null, []);

        $raw = $pdf->Output('', 'S');
        $this->assertStringContainsString('(Reviewed )', $raw);
        $this->assertStringContainsString('(MA. )', $raw);
        $this->assertStringContainsString('(Laboratory )', $raw);
    }

    public function test_resolve_helpers_clamp_options(): void
    {
        $filler = app(ControlledPdfFiller::class);
        $factor = new ReflectionMethod(ControlledPdfFiller::class, 'resolveLineHeightFactor');
        $factor->setAccessible(true);
        $gap = new ReflectionMethod(ControlledPdfFiller::class, 'resolveParagraphGapMm');
        $gap->setAccessible(true);

        $this->assertSame(1.0, $factor->invoke($filler, []));
        $this->assertSame(0.75, $factor->invoke($filler, ['line_height_factor' => 0.1]));
        $this->assertSame(2.0, $factor->invoke($filler, ['line_height_factor' => 9]));
        $this->assertSame(0.0, $gap->invoke($filler, []));
        $this->assertSame(12.0, $gap->invoke($filler, ['paragraph_gap_mm' => 99]));
        $this->assertSame(3.5, $gap->invoke($filler, ['paragraph_gap_mm' => 3.5]));
    }

    private function newPdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setCompression(false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        return $pdf;
    }

    private function drawAndMeasure(
        ReflectionMethod $method,
        ControlledPdfFiller $filler,
        string $text,
        float $lineHeight,
        float $paragraphGapMm,
    ): float {
        $pdf = $this->newPdf();

        return (float) $method->invoke(
            $filler,
            $pdf,
            $text,
            20.0,
            40.0,
            160.0,
            120.0,
            $lineHeight,
            'helvetica',
            10.0,
            false,
            $paragraphGapMm,
        );
    }
}
