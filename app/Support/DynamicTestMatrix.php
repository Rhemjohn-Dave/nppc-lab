<?php

namespace App\Support;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormFieldType;
use App\Models\AnalysisPackage;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Support\Collection;

class DynamicTestMatrix
{
    /**
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'columns' => [
                ['key' => 'test', 'label' => 'TEST', 'width_pct' => 42, 'align' => 'L'],
                ['key' => 'sample_1', 'label' => 'Sample 1', 'sublabel' => 'SS: 18g', 'width_pct' => 29, 'align' => 'C'],
                ['key' => 'sample_2', 'label' => 'Sample 2', 'sublabel' => 'SS: 18g', 'width_pct' => 29, 'align' => 'C'],
            ],
            'row_height_mm' => 7,
            'header_row' => true,
            'border' => true,
            'preview_rows' => 8,
        ];
    }

    public static function revisionUsesMatrix(ControlledFormRevision $revision): bool
    {
        $revision->loadMissing('fields');

        return $revision->fields->contains(
            fn ($field) => $field->field_type === ControlledFormFieldType::DynamicTestMatrix,
        );
    }

    public static function packageUsesMatrix(?AnalysisPackage $package): bool
    {
        return $package !== null
            && $package->report_layout === AnalysisPackageReportLayout::DynamicMatrix;
    }

    /**
     * @return Collection<int, JobOrderAnalysis>
     */
    public static function orderedSelectedAnalyses(JobOrder $jobOrder, AnalysisPackage $package): Collection
    {
        $jobOrder->loadMissing(['analyses.analysisType', 'analyses.assignee']);

        $byType = $jobOrder->analyses->keyBy(
            fn (JobOrderAnalysis $line): int => (int) $line->analysis_type_id,
        );

        return collect($package->orderedTypeIds())
            ->map(fn (int $typeId) => $byType->get($typeId))
            ->filter(fn ($line): bool => $line instanceof JobOrderAnalysis)
            ->values();
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>  $ordered
     * @return list<array{test: string, test_method?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function buildRows(Collection $ordered): array
    {
        return $ordered
            ->map(function (JobOrderAnalysis $line): array {
                $result = trim((string) ($line->result_value ?? ''));
                if ($result !== '' && filled($line->result_unit)) {
                    $result = trim($result.' '.$line->result_unit);
                }

                return [
                    'test' => (string) $line->name,
                    'test_method' => null,
                    'result' => $result !== '' ? $result : null,
                    'sample_1' => $result !== '' ? $result : null,
                    'sample_2' => null,
                    'remarks' => filled($line->result_remarks) ? (string) $line->result_remarks : null,
                ];
            })
            ->all();
    }

    /**
     * @return list<array{test: string, test_method?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function sampleRows(): array
    {
        return [
            ['test' => '% Fat', 'test_method' => 'Soxhlet Extraction Method', 'sample_1' => '0.68', 'sample_2' => '0.59'],
            ['test' => '% Protein', 'test_method' => 'Kjeldahl Method', 'sample_1' => '18.2', 'sample_2' => '18.0'],
            ['test' => '% Moisture', 'test_method' => 'Gravimetric Oven Drying at 105°C', 'sample_1' => '65.0', 'sample_2' => '64.8'],
            ['test' => '% Fiber', 'test_method' => 'Weende Method', 'sample_1' => '2.1', 'sample_2' => '2.0'],
        ];
    }
}
