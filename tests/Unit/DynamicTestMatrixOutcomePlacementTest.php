<?php

namespace Tests\Unit;

use App\Support\DynamicTestMatrix;
use Tests\TestCase;

class DynamicTestMatrixOutcomePlacementTest extends TestCase
{
    /** @return array<string, mixed> */
    private function row(?string $remarks = null): array
    {
        return [
            'test' => 'Color (Apparent Color)',
            'result' => '5 (Passed)',
            'result_value' => '5',
            'pass_fail' => 'Passed',
            'sample_1' => '5 (Passed)',
            'remarks' => $remarks,
        ];
    }

    public function test_outcome_moves_to_remarks_when_sheet_has_remarks_column(): void
    {
        $rows = DynamicTestMatrix::placeOutcomeForColumns(
            [$this->row()],
            DynamicTestMatrix::drinkingWaterPhysicoColumns(),
        );

        $this->assertSame('5', $rows[0]['result']);
        $this->assertSame('5', $rows[0]['sample_1']);
        $this->assertSame('Passed', $rows[0]['remarks']);
    }

    public function test_outcome_stays_with_result_without_remarks_column(): void
    {
        $rows = DynamicTestMatrix::placeOutcomeForColumns(
            [$this->row()],
            [['key' => 'test'], ['key' => 'method'], ['key' => 'result']],
        );

        $this->assertSame('5 (Passed)', $rows[0]['result']);
        $this->assertNull($rows[0]['remarks']);
    }

    public function test_analyst_remarks_are_kept_after_outcome(): void
    {
        $rows = DynamicTestMatrix::placeOutcomeForColumns(
            [$this->row('Retested')],
            DynamicTestMatrix::drinkingWaterPhysicoColumns(),
        );

        $this->assertSame('Passed; Retested', $rows[0]['remarks']);
    }

    public function test_rows_without_outcome_are_unchanged(): void
    {
        $row = ['test' => 'pH', 'result' => '7.1', 'result_value' => '7.1', 'pass_fail' => null, 'remarks' => null];

        $rows = DynamicTestMatrix::placeOutcomeForColumns([$row], DynamicTestMatrix::drinkingWaterPhysicoColumns());

        $this->assertSame([$row], $rows);
    }
}
