<?php

namespace Tests\Unit;

use App\Support\DynamicTestMatrix;
use PHPUnit\Framework\TestCase;

class MatrixDesignerTextEditsTest extends TestCase
{
    public function test_edit_applies_when_test_name_matches_ignoring_case_and_spaces(): void
    {
        $rows = [['test' => 'Sulfate (mg/L)', 'method' => "Turbidimetric Method\n4500 - SO4-2 E"]];
        $config = ['preview_data' => [['test' => '  SULFATE (MG/L) ', 'method' => 'Turbidimetric Method 4500 - SO4-2 E']]];

        $result = DynamicTestMatrix::applyDesignerTextEdits($rows, $config);

        $this->assertSame('Turbidimetric Method 4500 - SO4-2 E', $result[0]['method']);
    }

    public function test_job_data_is_never_overridden(): void
    {
        $rows = [['test' => 'pH', 'result' => '7.2', 'remarks' => 'Passed', 'sample_1' => '7.2']];
        $config = ['preview_data' => [['test' => 'pH', 'result' => '0', 'remarks' => 'Failed', 'sample_1' => '0']]];

        $this->assertSame($rows, DynamicTestMatrix::applyDesignerTextEdits($rows, $config));
    }

    public function test_empty_edit_keeps_original_text(): void
    {
        $rows = [['test' => 'Odor', 'acceptable_values' => 'No objectionable odor', 'method' => 'Threshold Odor Test']];
        $config = ['preview_data' => [['test' => 'Odor', 'acceptable_values' => '  ', 'method' => null]]];

        $this->assertSame($rows, DynamicTestMatrix::applyDesignerTextEdits($rows, $config));
    }

    public function test_rows_without_a_matching_edit_are_unchanged(): void
    {
        $rows = [
            ['test' => 'Iron (mg/L)', 'method' => "Phenanthroline Method\n3500 - Fe B"],
            ['test' => 'Nitrite (mg/L)', 'method' => 'Diazotization Method'],
        ];
        $config = ['preview_data' => [['test' => 'Nitrite (mg/L)', 'method' => 'Diazotization']]];

        $result = DynamicTestMatrix::applyDesignerTextEdits($rows, $config);

        $this->assertSame($rows[0], $result[0]);
        $this->assertSame('Diazotization', $result[1]['method']);
        $this->assertSame($rows, DynamicTestMatrix::applyDesignerTextEdits($rows, []));
    }
}
