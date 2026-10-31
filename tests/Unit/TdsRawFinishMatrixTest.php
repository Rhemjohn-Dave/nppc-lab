<?php

namespace Tests\Unit;

use App\Models\AnalysisType;
use App\Models\JobOrderAnalysis;
use App\Support\DynamicTestMatrix;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Support\Collection;
use Tests\TestCase;

class TdsRawFinishMatrixTest extends TestCase
{
    public function test_catalog_exposes_raw_and_finish_acceptable_values_for_dw_03(): void
    {
        $this->assertTrue(OfficialAnalysisCatalog::usesRawFinishResults('DW-03'));
        $this->assertTrue(OfficialAnalysisCatalog::usesRawFinishResults('DW-02'));
        $this->assertFalse(OfficialAnalysisCatalog::usesRawFinishResults('DW-05'));
        $this->assertTrue(OfficialAnalysisCatalog::usesResidualChlorineResults('DW-05'));
        $this->assertFalse(OfficialAnalysisCatalog::usesResidualChlorineResults('DW-02'));

        $this->assertSame(
            '600 for product that has not undergone reverse osmosis / distillation process',
            OfficialAnalysisCatalog::acceptableValuesForCodeVariant('DW-03', 'raw'),
        );
        $this->assertSame(
            '<10 for product that undergone reverse osmosis/distillation process',
            OfficialAnalysisCatalog::acceptableValuesForCodeVariant('DW-03', 'finish'),
        );
        $this->assertSame(
            "600 for product that has not undergone reverse osmosis / distillation process\n"
            .'<10 for product that undergone reverse osmosis/distillation process',
            OfficialAnalysisCatalog::acceptableValuesForCode('DW-03'),
        );
        $this->assertSame(
            '6.5-8.5',
            OfficialAnalysisCatalog::acceptableValuesForCodeVariant('DW-02', 'raw'),
        );
        $this->assertSame(
            '5-7 for product water that undergone reverse osmosis/distillation process',
            OfficialAnalysisCatalog::acceptableValuesForCodeVariant('DW-02', 'finish'),
        );
        $this->assertSame(
            "6.5-8.5\n5-7 for product water that undergone reverse osmosis/distillation process",
            OfficialAnalysisCatalog::acceptableValuesForCode('DW-02'),
        );
        $this->assertSame(
            'Raw: 450; Finish: 8',
            OfficialAnalysisCatalog::summarizeRawFinishResults('450', '8'),
        );
    }

    public function test_build_rows_expands_dw_03_into_raw_and_finish(): void
    {
        $type = new AnalysisType([
            'code' => 'DW-03',
            'name' => 'Total Dissolved Solids (mg/L)',
            'method' => 'Total Dissolved Solids Dried at 180°C (2540D)',
        ]);
        $line = new JobOrderAnalysis([
            'name' => 'Total Dissolved Solids (mg/L)',
            'result_value' => 'Raw: 450; Finish: 8',
            'result_raw' => '450',
            'result_finish' => '8',
            'result_unit' => 'mg/L',
            'result_method' => 'Total Dissolved Solids Dried at 180°C (2540D)',
            'result_remarks_raw' => 'Raw note',
            'result_remarks_finish' => 'Finish note',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));

        $this->assertCount(2, $rows);
        $this->assertSame('Total Dissolved Solids (mg/L)', $rows[0]['test']);
        $this->assertSame('Total Dissolved Solids (mg/L)', $rows[1]['test']);
        $this->assertTrue(DynamicTestMatrix::rowsFormSpanPair($rows[0], $rows[1]));
        $this->assertSame(
            '600 for product that has not undergone reverse osmosis / distillation process',
            $rows[0]['acceptable_values'],
        );
        $this->assertSame(
            '<10 for product that undergone reverse osmosis/distillation process',
            $rows[1]['acceptable_values'],
        );
        $this->assertStringNotContainsString(
            '<10 for product that undergone reverse osmosis/distillation process',
            (string) $rows[0]['acceptable_values'],
        );
        $this->assertStringNotContainsString(
            '600 for product that has not undergone reverse osmosis / distillation process',
            (string) $rows[1]['acceptable_values'],
        );

        $sanitized = DynamicTestMatrix::sanitizeRawFinishAcceptableValues([
            'test' => 'Total Dissolved Solids (mg/L)',
            'row_span_role' => 'start',
            'acceptable_values' => "600 for product that has not undergone reverse osmosis / distillation process\n"
                .'<10 for product that undergone reverse osmosis/distillation process',
        ]);
        $this->assertSame(
            '600 for product that has not undergone reverse osmosis / distillation process',
            $sanitized['acceptable_values'],
        );
        $this->assertFalse(DynamicTestMatrix::isRowSpanColumnKey('acceptable_values'));
        $this->assertFalse(DynamicTestMatrix::isRowSpanColumnKey('remarks'));
        $this->assertSame('450 mg/L', $rows[0]['result']);
        $this->assertSame('Total Dissolved Solids Dried at 180°C (2540D)', $rows[0]['method']);
        $this->assertSame('Total Dissolved Solids Dried at 180°C (2540D)', $rows[1]['method']);

        $this->assertSame('8 mg/L', $rows[1]['result']);
        $this->assertSame('Raw note', $rows[0]['remarks']);
        $this->assertSame('Finish note', $rows[1]['remarks']);
        $this->assertSame('start', $rows[0]['row_span_role']);
        $this->assertSame('continue', $rows[1]['row_span_role']);
    }

    public function test_build_rows_expands_dw_02_into_raw_and_finish(): void
    {
        $type = new AnalysisType([
            'code' => 'DW-02',
            'name' => 'pH',
            'method' => 'Electrometric Method (4500-H+ B)',
        ]);
        $line = new JobOrderAnalysis([
            'name' => 'pH',
            'result_value' => 'Raw: 7.2; Finish: 6.1',
            'result_raw' => '7.2',
            'result_finish' => '6.1',
            'result_method' => 'Electrometric Method (4500-H+ B)',
            'result_remarks_raw' => 'Raw pH note',
            'result_remarks_finish' => 'Finish pH note',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));

        $this->assertCount(2, $rows);
        $this->assertSame('pH', $rows[0]['test']);
        $this->assertSame('pH', $rows[1]['test']);
        $this->assertTrue(DynamicTestMatrix::rowsFormSpanPair($rows[0], $rows[1]));
        $this->assertSame('6.5-8.5', $rows[0]['acceptable_values']);
        $this->assertSame(
            '5-7 for product water that undergone reverse osmosis/distillation process',
            $rows[1]['acceptable_values'],
        );
        $this->assertSame('7.2', $rows[0]['result']);
        $this->assertSame('6.1', $rows[1]['result']);
        $this->assertSame('Raw pH note', $rows[0]['remarks']);
        $this->assertSame('Finish pH note', $rows[1]['remarks']);
        $this->assertSame('rawfinish-dw-02-0', $rows[0]['row_span_group']);

        $sanitized = DynamicTestMatrix::sanitizeRawFinishAcceptableValues([
            'test' => 'pH',
            'row_span_role' => 'start',
            'acceptable_values' => "6.5-8.5\n5-7 for product water that undergone reverse osmosis/distillation process",
        ]);
        $this->assertSame('6.5-8.5', $sanitized['acceptable_values']);
    }

    public function test_build_rows_expands_dw_05_into_finish_chlorinated_raw(): void
    {
        $type = new AnalysisType([
            'code' => 'DW-05',
            'name' => 'Residual Chlorine (mg/L)',
            'method' => 'DPD Colorimetric Method',
        ]);
        $line = new JobOrderAnalysis([
            'name' => 'Residual Chlorine (mg/L)',
            'result_value' => 'Finish: 0.4; Chlorinated: 0.9',
            'result_finish' => '0.4',
            'result_chlorinated' => '0.9',
            'result_method' => 'DPD Colorimetric Method',
            'result_remarks_finish' => 'Finish note',
            'result_remarks_chlorinated' => 'Cl note',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));

        $this->assertCount(3, $rows);
        $this->assertSame('Residual Chlorine (mg/L)', $rows[0]['test']);
        $this->assertSame('Residual Chlorine (mg/L)', $rows[1]['test']);
        $this->assertSame('Residual Chlorine (mg/L)', $rows[2]['test']);
        $this->assertSame(3, DynamicTestMatrix::spanGroupLength($rows, 0));
        $this->assertTrue(DynamicTestMatrix::rowContinuesSpan($rows[0], $rows[1]));
        $this->assertTrue(DynamicTestMatrix::rowContinuesSpan($rows[0], $rows[2]));
        $this->assertSame('<1.0 mg/L for RO water', $rows[0]['acceptable_values']);
        $this->assertSame(
            '0.3 - 1.5 mg/L for chlorinated drinking water',
            $rows[1]['acceptable_values'],
        );
        $this->assertSame('Not applicable to Raw Water', $rows[2]['acceptable_values']);
        $this->assertSame('0.4', $rows[0]['result']);
        $this->assertSame('0.9', $rows[1]['result']);
        $this->assertSame('-', $rows[2]['result']);
        $this->assertSame('Finish note', $rows[0]['remarks']);
        $this->assertSame('Cl note', $rows[1]['remarks']);
        $this->assertNull($rows[2]['remarks']);
        $this->assertSame('finish', $rows[0]['row_variant']);
        $this->assertSame('chlorinated', $rows[1]['row_variant']);
        $this->assertSame('raw', $rows[2]['row_variant']);

        $this->assertSame(
            'Finish: 0.4; Chlorinated: 0.9',
            OfficialAnalysisCatalog::summarizeResidualChlorineResults('0.4', '0.9'),
        );
        $this->assertSame(
            '<1.0 mg/L for RO water',
            OfficialAnalysisCatalog::acceptableValuesForCodeVariant('DW-05', 'finish'),
        );
    }

    public function test_place_outcome_puts_pass_fail_in_remarks_for_dw_02_raw_finish(): void
    {
        $type = new AnalysisType([
            'code' => 'DW-02',
            'name' => 'pH',
            'method' => 'Electrometric Method (4500-H+ B)',
            'requires_pass_fail' => true,
        ]);
        $line = new JobOrderAnalysis([
            'name' => 'pH',
            'result_raw' => '7.2',
            'result_finish' => null,
            'result_pass_fail' => 'Passed',
            'result_method' => 'Electrometric Method (4500-H+ B)',
            'result_remarks_raw' => 'Raw note',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));
        $this->assertSame('Passed', $rows[0]['pass_fail']);
        $this->assertNull($rows[1]['pass_fail']);

        $placed = DynamicTestMatrix::placeOutcomeForColumns($rows, [
            ['key' => 'result'],
            ['key' => 'remarks'],
        ]);

        $this->assertSame('Passed; Raw note', $placed[0]['remarks']);
        $this->assertNull($placed[1]['remarks']);
        $this->assertSame('7.2', $placed[0]['result']);
    }

    public function test_place_outcome_puts_pass_fail_in_remarks_for_dw_05_finish_only(): void
    {
        $type = new AnalysisType([
            'code' => 'DW-05',
            'name' => 'Residual Chlorine (mg/L)',
            'method' => 'DPD Colorimetric Method',
            'requires_pass_fail' => true,
        ]);
        $line = new JobOrderAnalysis([
            'name' => 'Residual Chlorine (mg/L)',
            'result_finish' => '0.4',
            'result_chlorinated' => null,
            'result_pass_fail' => 'Passed',
            'result_method' => 'DPD Colorimetric Method',
            'result_remarks_finish' => 'Finish note',
        ]);
        $line->setRelation('analysisType', $type);

        $rows = DynamicTestMatrix::buildRows(new Collection([$line]));
        $this->assertSame('Passed', $rows[0]['pass_fail']);
        $this->assertNull($rows[1]['pass_fail']);
        $this->assertNull($rows[2]['pass_fail']);

        $placed = DynamicTestMatrix::placeOutcomeForColumns($rows, [
            ['key' => 'result'],
            ['key' => 'remarks'],
        ]);

        $this->assertSame('Passed; Finish note', $placed[0]['remarks']);
        $this->assertNull($placed[1]['remarks']);
        $this->assertNull($placed[2]['remarks']);
        $this->assertSame('-', $placed[2]['result']);
    }
}
