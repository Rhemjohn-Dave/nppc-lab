<?php

namespace Tests\Unit;

use App\Services\ControlledPdfFiller;
use ReflectionMethod;
use setasign\Fpdi\Tcpdf\Fpdi;
use Tests\TestCase;

class MatrixFontFitTest extends TestCase
{
    private const LONG_METHOD = 'Standard Methods for the Examination of Water and Wastewater, 24th Edition, 2023 — Section 5210 B (5-Day BOD Test)';

    /** FO2 body box: 107.595 mm field minus 5 mm header row. */
    private const FO2_BODY_HEIGHT = 102.595;

    public function test_fo2_rows_with_time_of_analysis_fit_at_designer_sizes(): void
    {
        $rows = array_fill(0, 17, $this->row('2540 D – Total Suspended Solids Dried at 103-105°C'));

        [$columns, $bodySize, $methodSize] = $this->fit($rows, self::FO2_BODY_HEIGHT);

        $this->assertSame($this->columns(), $columns);
        $this->assertSame(6.5, $bodySize);
        $this->assertSame(6.0, $methodSize);
        $this->assertLessThanOrEqual(self::FO2_BODY_HEIGHT + 0.01, $this->measure($rows, $columns, $bodySize, $methodSize));
    }

    public function test_method_column_shrinks_first_and_other_columns_keep_designer_size(): void
    {
        $rows = array_fill(0, 17, $this->row(self::LONG_METHOD));
        $designerHeight = $this->measure($rows, $this->columns(), 6.5, 6.0);

        [$columns, $bodySize] = $this->fit($rows, $designerHeight - 5.0);

        $this->assertSame(8.0, (float) $columns[0]['font_size']);
        $this->assertSame(8.0, (float) $columns[2]['font_size']);
        $this->assertLessThan(8.0, (float) $columns[1]['font_size']);
        $this->assertGreaterThanOrEqual(6.0, (float) $columns[1]['font_size']);
        $this->assertSame(6.5, $bodySize);
    }

    public function test_rows_that_fit_keep_every_size(): void
    {
        $rows = array_fill(0, 3, $this->row(self::LONG_METHOD));

        [$columns, $bodySize, $methodSize] = $this->fit($rows, 108.0);

        $this->assertSame($this->columns(), $columns);
        $this->assertSame(6.5, $bodySize);
        $this->assertSame(6.0, $methodSize);
    }

    public function test_falls_back_to_uniform_shrink_but_keeps_a_readable_floor(): void
    {
        $rows = array_fill(0, 17, $this->row(self::LONG_METHOD));

        [$columns] = $this->fit($rows, 40.0);

        $this->assertLessThan(8.0, (float) $columns[0]['font_size']);
        $this->assertLessThan(8.0, (float) $columns[2]['font_size']);
        $this->assertGreaterThanOrEqual(5.0, (float) $columns[0]['font_size']);
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}
     */
    private function fit(array $rows, float $availableHeight): array
    {
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'fitMatrixBodyFonts');

        return $method->invoke(
            app(ControlledPdfFiller::class),
            $this->pdf(),
            $rows,
            $this->columns(),
            163.05,
            5.0,
            6.5,
            6.0,
            'calibri',
            true,
            $availableHeight,
        );
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @param  list<array<string, mixed>>  $columns
     */
    private function measure(array $rows, array $columns, float $bodySize, float $methodSize): float
    {
        $pdf = $this->pdf();
        $method = new ReflectionMethod(ControlledPdfFiller::class, 'measureMatrixBodyRowHeight');
        $filler = app(ControlledPdfFiller::class);

        $total = 0.0;
        foreach ($rows as $row) {
            $total += $method->invoke($filler, $pdf, $row, $columns, 163.05, 5.0, $bodySize, $methodSize, 'calibri', true);
        }

        return $total;
    }

    private function pdf(): Fpdi
    {
        $pdf = new Fpdi('P', 'mm', [215.9, 279.4], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->AddPage();

        return $pdf;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function columns(): array
    {
        return [
            ['key' => 'test', 'label' => 'TEST', 'width_pct' => 34, 'font_size' => 8],
            ['key' => 'method', 'label' => 'METHOD', 'width_pct' => 48, 'font_size' => 8],
            ['key' => 'result', 'label' => 'RESULTS', 'width_pct' => 18, 'font_size' => 8],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function row(string $method): array
    {
        return [
            'test' => 'Biochemical Oxygen Demand (mg/L)',
            'test_detail' => 'Time/Date of Analysis: October 10, 2026 (10:59AM)',
            'method' => $method,
            'result' => '12.5',
        ];
    }
}
