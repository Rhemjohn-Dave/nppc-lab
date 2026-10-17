<?php

namespace Tests\Unit;

use App\Support\DynamicTestMatrix;
use PHPUnit\Framework\TestCase;

class MatrixRowHeightOverrideTest extends TestCase
{
    public function test_override_applies_only_to_its_row(): void
    {
        $config = ['row_heights_mm' => ['odor' => 12.5]];

        $this->assertSame(12.5, DynamicTestMatrix::rowMinHeightMm($config, ['test' => 'Odor'], 7.0));
        $this->assertSame(7.0, DynamicTestMatrix::rowMinHeightMm($config, ['test' => 'Sulfate (mg/L)'], 7.0));
    }

    public function test_keys_ignore_case_and_surrounding_spaces(): void
    {
        $config = ['row_heights_mm' => ['sulfate (mg/l)' => 9]];

        $this->assertSame('sulfate (mg/l)', DynamicTestMatrix::rowHeightKey(['test' => '  Sulfate (mg/L) ']));
        $this->assertSame(9.0, DynamicTestMatrix::rowMinHeightMm($config, ['test' => '  SULFATE (MG/L) '], 7.0));
    }

    public function test_override_can_be_shorter_than_table_row_height(): void
    {
        $config = ['row_heights_mm' => ['odor' => 4]];

        $this->assertSame(4.0, DynamicTestMatrix::rowMinHeightMm($config, ['test' => 'Odor'], 7.0));
    }

    public function test_missing_or_invalid_overrides_keep_table_row_height(): void
    {
        $this->assertSame(7.0, DynamicTestMatrix::rowMinHeightMm([], ['test' => 'Odor'], 7.0));
        $this->assertSame(7.0, DynamicTestMatrix::rowMinHeightMm(['row_heights_mm' => 'x'], ['test' => 'Odor'], 7.0));
        $this->assertSame(7.0, DynamicTestMatrix::rowMinHeightMm(['row_heights_mm' => ['odor' => 'tall']], ['test' => 'Odor'], 7.0));
        $this->assertSame(7.0, DynamicTestMatrix::rowMinHeightMm(['row_heights_mm' => ['' => 20]], ['test' => ''], 7.0));
    }

    public function test_row_extra_spacing_is_never_negative(): void
    {
        $this->assertSame(0.0, DynamicTestMatrix::rowExtraMm([]));
        $this->assertSame(2.5, DynamicTestMatrix::rowExtraMm(['row_extra_mm' => 2.5]));
        $this->assertSame(1.0, DynamicTestMatrix::rowExtraMm(['row_extra_mm' => '1']));
        $this->assertSame(0.0, DynamicTestMatrix::rowExtraMm(['row_extra_mm' => -3]));
        $this->assertSame(0.0, DynamicTestMatrix::rowExtraMm(['row_extra_mm' => 'wide']));
    }
}
