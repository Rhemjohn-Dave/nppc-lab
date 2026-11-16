<?php

namespace Tests\Unit;

use App\Enums\ControlledFormFieldType;
use App\Support\DynamicTestMatrix;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class DynamicTestMatrixDesignerPreviewParityTest extends TestCase
{
    #[Test]
    public function preview_rows_for_designer_config_caps_at_preview_rows(): void
    {
        $base = [
            ['test' => 'A', 'method' => 'M1'],
            ['test' => 'B', 'method' => 'M2'],
            ['test' => 'C', 'method' => 'M3'],
        ];
        $config = [
            'preview_rows' => 2,
            'preview_data' => [
                ['test' => 'A', 'method' => 'edited'],
            ],
        ];

        $rows = DynamicTestMatrix::previewRowsForDesignerConfig($config, $base);

        $this->assertCount(2, $rows);
        $this->assertSame('edited', $rows[0]['method']);
        $this->assertSame('B', $rows[1]['test']);
    }

    #[Test]
    public function preview_rows_for_designer_config_merges_non_empty_preview_data_overrides(): void
    {
        $base = [
            ['test' => 'Color', 'acceptable_values' => '10'],
        ];
        $config = [
            'preview_rows' => 1,
            'preview_data' => [
                ['test' => 'Color', 'acceptable_values' => '', 'method' => 'Colorimetric'],
            ],
        ];

        $rows = DynamicTestMatrix::previewRowsForDesignerConfig($config, $base);

        $this->assertCount(1, $rows);
        $this->assertSame('10', $rows[0]['acceptable_values']);
        $this->assertSame('Colorimetric', $rows[0]['method']);
    }

    #[Test]
    public function fo2_normalize_preserves_designer_preview_rows(): void
    {
        $normalized = DynamicTestMatrix::normalizeStoredTableConfig(
            'ww_fo2b_matrix',
            ControlledFormFieldType::DynamicTestMatrix,
            [
                'columns' => DynamicTestMatrix::wastewaterPhysicoConfig()['columns'],
                'preview_rows' => 7,
                'stretch_body' => false,
            ],
        );

        $this->assertIsArray($normalized);
        $this->assertSame(7, (int) ($normalized['preview_rows'] ?? 0));
    }
}
