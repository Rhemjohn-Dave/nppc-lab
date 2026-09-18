<?php

namespace Tests\Unit;

use App\Models\AnalysisType;
use App\Models\JobOrderAnalysis;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProximateDynamicTestMatrixTest extends TestCase
{
    public function test_default_config_matches_proximate_two_column_sheet(): void
    {
        $config = DynamicTestMatrix::defaultConfig();

        $this->assertSame('TEST', $config['columns'][0]['label']);
        $this->assertSame('Control Number', $config['columns'][1]['label']);
        $this->assertSame('result', $config['columns'][1]['key']);
        $this->assertStringContainsString('Sample Description:', (string) $config['columns'][1]['sublabel']);
        $this->assertStringContainsString('SS:', (string) $config['columns'][1]['sublabel']);
        $this->assertSame('C', $config['columns'][1]['sublabel_align']);
        $this->assertCount(2, $config['columns']);
    }

    public function test_proximate_methods_are_catalogued(): void
    {
        $this->assertSame('Soxhlet Extraction Method', OfficialAnalysisCatalog::methodForCode('PX-01'));
        $this->assertSame('FLAME-AES', OfficialAnalysisCatalog::methodForCode('PX-07'));
        $this->assertSame('Refractometer', OfficialAnalysisCatalog::methodForCode('PX-08'));
        $this->assertNull(OfficialAnalysisCatalog::methodForCode('MB-01'));
    }

    public function test_build_rows_include_test_methods_and_result_column(): void
    {
        $type = new AnalysisType(['code' => 'PX-01', 'name' => 'Fats']);
        $line = new JobOrderAnalysis([
            'name' => '% Fat',
            'result_value' => '0.68',
            'result_unit' => '%',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));

        $this->assertSame('% Fat', $rows[0]['test']);
        $this->assertSame('Soxhlet Extraction Method', $rows[0]['test_method']);
        $this->assertSame('0.68 %', $rows[0]['result']);
    }

    public function test_build_rows_prefer_stored_analysis_type_method(): void
    {
        $type = new AnalysisType([
            'code' => 'PX-01',
            'name' => 'Fats',
            'method' => 'Custom lab method',
        ]);
        $line = new JobOrderAnalysis([
            'name' => '% Fat',
            'result_value' => '1.0',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));

        $this->assertSame('Custom lab method', $rows[0]['test_method']);
    }

    public function test_method_falls_back_to_catalog_when_db_method_empty(): void
    {
        $type = new AnalysisType(['code' => 'PX-07', 'name' => 'Sodium', 'method' => null]);

        $this->assertSame('FLAME-AES', DynamicTestMatrix::methodForAnalysisType($type));
    }

    public function test_sample_rows_cover_all_eight_proximate_parameters(): void
    {
        $rows = DynamicTestMatrix::sampleRows();

        $this->assertCount(8, $rows);
        $this->assertSame('% Sodium', $rows[6]['test']);
        $this->assertSame('FLAME-AES', $rows[6]['test_method']);
        $this->assertSame('Sugar, Bx', $rows[7]['test']);
    }

    public function test_normalized_column_widths_fill_field_when_pct_sum_is_incomplete(): void
    {
        $columns = [
            ['key' => 'test', 'label' => 'TEST', 'width_pct' => 41],
            ['key' => 'result', 'label' => 'Control Number', 'width_pct' => 45],
        ];
        $totalWidth = 153.807;

        $widths = DynamicTestMatrix::normalizedColumnWidths($columns, $totalWidth);
        $percentages = DynamicTestMatrix::normalizedWidthPercentages($columns);

        $this->assertCount(2, $widths);
        $this->assertEqualsWithDelta($totalWidth, array_sum($widths), 0.001);
        $this->assertEqualsWithDelta(100.0, array_sum($percentages), 0.001);
        $this->assertEqualsWithDelta($totalWidth * (41 / 86), $widths[0], 0.001);
        $this->assertEqualsWithDelta($totalWidth * (45 / 86), $widths[1], 0.001);
    }

    public function test_normalized_column_widths_keep_full_sum_unchanged(): void
    {
        $columns = DynamicTestMatrix::defaultConfig()['columns'];
        $totalWidth = 160.0;

        $widths = DynamicTestMatrix::normalizedColumnWidths($columns, $totalWidth);

        $this->assertEqualsWithDelta(88.0, $widths[0], 0.001);
        $this->assertEqualsWithDelta(72.0, $widths[1], 0.001);
        $this->assertEqualsWithDelta($totalWidth, array_sum($widths), 0.001);
    }

    public function test_normalized_column_widths_equal_split_when_pct_missing(): void
    {
        $columns = [
            ['key' => 'test', 'label' => 'TEST'],
            ['key' => 'result', 'label' => 'Result'],
            ['key' => 'remarks', 'label' => 'Remarks'],
        ];

        $widths = DynamicTestMatrix::normalizedColumnWidths($columns, 90.0);

        $this->assertCount(3, $widths);
        $this->assertEqualsWithDelta(30.0, $widths[0], 0.001);
        $this->assertEqualsWithDelta(30.0, $widths[1], 0.001);
        $this->assertEqualsWithDelta(30.0, $widths[2], 0.001);
        $this->assertEqualsWithDelta(90.0, array_sum($widths), 0.001);
    }
}
