<?php

namespace App\Support;

use App\Enums\AnalysisPackageReportLayout;
use App\Enums\ControlledFormFieldType;
use App\Models\AnalysisPackage;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Support\Collection;

class DynamicTestMatrix
{
    /**
     * Default matrix matches the Proximate Analysis Result Form
     * (LSP 7.8 F016): TEST | Control Number.
     *
     * @return array<string, mixed>
     */
    public static function defaultConfig(): array
    {
        return [
            'columns' => [
                [
                    'key' => 'test',
                    'label' => 'TEST',
                    'width_pct' => 55,
                    'align' => 'C',
                    'header_align' => 'C',
                ],
                [
                    'key' => 'result',
                    'label' => 'Control Number',
                    'sublabel' => "Sample Description:\nSS:",
                    'sublabel_align' => 'C',
                    'width_pct' => 45,
                    'align' => 'C',
                    'header_align' => 'C',
                ],
            ],
            'row_height_mm' => 9,
            'header_row' => true,
            'header_row_height_mm' => 14,
            'border' => true,
            'preview_rows' => 8,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 7,
        ];
    }

    /**
     * Column/layout preset for a controlled form (package or form_code aware).
     *
     * @return array<string, mixed>
     */
    public static function configForForm(?ControlledForm $form): array
    {
        if ($form === null) {
            return self::defaultConfig();
        }

        $form->loadMissing('analysisPackage');

        return self::configForPackage($form->analysisPackage, $form->form_code);
    }

    /**
     * Column/layout preset for a bound analysis package (or form_code hint).
     *
     * @return array<string, mixed>
     */
    public static function configForPackage(?AnalysisPackage $package, ?string $formCode = null): array
    {
        $code = $package?->code;
        $resolvedFormCode = $formCode ?? $package?->form_code;

        if (
            $code === 'PKG-DW-PHYSICO'
            || $resolvedFormCode === 'LSP-7.8-FO37'
            || $resolvedFormCode === 'LSP-7.8-FO3'
        ) {
            return self::drinkingWaterPhysicoConfig();
        }

        if ($resolvedFormCode === 'LSP-7.8-FO2') {
            return self::wastewaterPhysicoConfig();
        }

        if ($resolvedFormCode === 'LSP-7.8-F016-WA') {
            return self::waterActivityConfig();
        }

        if ($resolvedFormCode === 'LSP-7.8-F016-CAP') {
            return self::chloramphenicolConfig();
        }

        if ($resolvedFormCode === 'LSP-7.8-F016-NO2') {
            return self::nitriteConfig();
        }

        return self::defaultConfig();
    }

    /**
     * FO2 Issue 18 matrix must stay TEST | METHOD | RESULTS (no Remarks).
     * Designer saves can otherwise reintroduce a stale Remarks column from an older session.
     *
     * @param  array<string, mixed>|null  $tableConfig
     * @return array<string, mixed>|null
     */
    public static function normalizeStoredTableConfig(
        string $fieldName,
        ?ControlledFormFieldType $fieldType,
        mixed $tableConfig,
        ?ControlledForm $form = null,
    ): mixed {
        $isMatrix = $fieldType === ControlledFormFieldType::DynamicTestMatrix
            || ($fieldType === null && is_array($tableConfig));

        if (! $isMatrix) {
            return $tableConfig;
        }

        if (is_array($tableConfig)
            && array_key_exists('method_font_size', $tableConfig)
            && (! is_numeric($tableConfig['method_font_size']) || (float) $tableConfig['method_font_size'] <= 0)
        ) {
            unset($tableConfig['method_font_size']);
        }

        $isFo2Matrix = $fieldName === 'ww_fo2_matrix'
            || $form?->form_code === 'LSP-7.8-FO2';
        $isWaMatrix = $fieldName === 'water_activity_f016_matrix'
            || $form?->form_code === 'LSP-7.8-F016-WA';
        $isCapMatrix = $fieldName === 'chloramphenicol_f016_matrix'
            || $form?->form_code === 'LSP-7.8-F016-CAP';
        $isNo2Matrix = $fieldName === 'nitrite_f016_matrix'
            || $form?->form_code === 'LSP-7.8-F016-NO2';
        $isLockedResidueMatrix = $isWaMatrix || $isCapMatrix || $isNo2Matrix;

        if (! $isFo2Matrix && ! $isLockedResidueMatrix) {
            return $tableConfig;
        }

        $preset = match (true) {
            $isNo2Matrix => self::nitriteConfig(),
            $isCapMatrix => self::chloramphenicolConfig(),
            $isWaMatrix => self::waterActivityConfig(),
            default => self::wastewaterPhysicoConfig(),
        };
        if (! is_array($tableConfig)) {
            return $preset;
        }

        $storedByKey = [];
        foreach ($tableConfig['columns'] ?? [] as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }
            $key = (string) $column['key'];
            if ($key === 'remarks') {
                continue;
            }
            $storedByKey[$key] = $column;
        }

        $columns = [];
        foreach ($preset['columns'] as $presetColumn) {
            $key = (string) $presetColumn['key'];
            $stored = $storedByKey[$key] ?? [];

            if ($isLockedResidueMatrix) {
                // Hard-lock official headers; Milk-style result labels must not overwrite.
                $mergedColumn = array_merge($presetColumn, array_intersect_key($stored, array_flip([
                    'align',
                    'header_align',
                    'font_size',
                    'header_font_size',
                    'width_pct',
                ])));
                unset($mergedColumn['label_data_source'], $mergedColumn['sublabel_data_source']);
                $columns[] = $mergedColumn;

                continue;
            }

            // Keep designer alignment / fonts; force FO2 keys and default labels/widths.
            $columns[] = array_merge($presetColumn, array_intersect_key($stored, array_flip([
                'align',
                'header_align',
                'sublabel_align',
                'sublabel',
                'sublabels',
                'font_size',
                'header_font_size',
                'width_pct',
                'label',
            ])));
        }

        $merged = array_merge($tableConfig, [
            'columns' => $columns,
            'preview_rows' => $preset['preview_rows'],
        ]);

        // Residue sheets: keep official headers; do not force designer preview_data placeholders.
        if ($isLockedResidueMatrix && array_key_exists('stretch_body', $preset)) {
            $merged['stretch_body'] = $preset['stretch_body'];
        }

        if ($isLockedResidueMatrix) {
            unset($merged['preview_data']);
        }

        return $merged;
    }

    /**
     * Replace matrix column header labels/sublabels from fill-value data sources.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $values
     * @return list<array<string, mixed>>
     */
    public static function resolveHeaderSources(array $columns, array $values): array
    {
        return array_map(function (array $column) use ($values): array {
            $labelKey = isset($column['label_data_source']) ? trim((string) $column['label_data_source']) : '';
            if ($labelKey !== '' && array_key_exists($labelKey, $values)) {
                $resolved = trim((string) ($values[$labelKey] ?? ''));
                if ($resolved !== '') {
                    $column['label'] = $resolved;
                }
            }

            $sublabelKey = isset($column['sublabel_data_source']) ? trim((string) $column['sublabel_data_source']) : '';
            if ($sublabelKey !== '' && array_key_exists($sublabelKey, $values)) {
                $resolved = trim((string) ($values[$sublabelKey] ?? ''));
                if ($resolved !== '') {
                    $column['sublabel'] = $resolved;
                    unset($column['sublabels']);
                }
            }

            if (isset($column['sublabels']) && is_array($column['sublabels'])) {
                $column['sublabels'] = array_map(function ($line) use ($values) {
                    if (! is_array($line)) {
                        return $line;
                    }
                    $source = isset($line['data_source']) ? trim((string) $line['data_source']) : '';
                    if ($source === '' || ! array_key_exists($source, $values)) {
                        return $line;
                    }
                    $resolved = trim((string) ($values[$source] ?? ''));
                    if ($resolved === '') {
                        return $line;
                    }

                    return array_merge($line, ['text' => $resolved]);
                }, $column['sublabels']);
            }

            return $column;
        }, $columns);
    }

    /**
     * Designer / sample preview rows from the form's bound package or types.
     *
     * @return list<array{test: string, test_method?: string|null, method?: string|null, acceptable_values?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function previewRowsForForm(?ControlledForm $form): array
    {
        if ($form === null) {
            return self::sampleRows();
        }

        $form->loadMissing(['analysisPackage.analysisTypes', 'analysisTypes']);

        if ($form->analysisPackage) {
            return self::previewRowsForPackage($form->analysisPackage);
        }

        return self::previewRowsForTypeIds($form->orderedTypeIds(), $form->analysisTypes);
    }

    /**
     * Whether a types-only form should render as a dynamic matrix (no package layout flag).
     */
    public static function formUsesMatrix(?ControlledForm $form, ?ControlledFormRevision $revision = null): bool
    {
        if ($form === null) {
            return false;
        }

        $form->loadMissing('analysisPackage');
        if (self::packageUsesMatrix($form->analysisPackage)) {
            return true;
        }

        $revision ??= $form->activeRevision();
        $revision?->loadMissing('fields');

        return $form->analysis_package_id === null
            && self::revisionUsesMatrix($revision);
    }

    /**
     * Selected job lines in the form's bound type print order (types-only matrix).
     *
     * @return Collection<int, JobOrderAnalysis>
     */
    public static function orderedSelectedAnalysesForForm(JobOrder $jobOrder, ControlledForm $form): Collection
    {
        $jobOrder->loadMissing(['analyses.analysisType', 'analyses.assignee']);
        $form->loadMissing('analysisPackage');

        if ($form->analysisPackage) {
            return self::orderedSelectedAnalyses($jobOrder, $form->analysisPackage);
        }

        $byType = $jobOrder->analyses->keyBy(
            fn (JobOrderAnalysis $line): int => (int) $line->analysis_type_id,
        );

        return collect($form->orderedTypeIds())
            ->map(fn (int $typeId) => $byType->get($typeId))
            ->filter(fn ($line): bool => $line instanceof JobOrderAnalysis)
            ->values();
    }

    /**
     * @param  list<int>  $orderedTypeIds
     * @param  Collection<int, AnalysisType>|iterable<int, AnalysisType>  $types
     * @return list<array{test: string, test_method?: string|null, method?: string|null, acceptable_values?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function previewRowsForTypeIds(array $orderedTypeIds, iterable $types): array
    {
        $byId = collect($types)->keyBy(
            fn (AnalysisType $type): int => (int) $type->id,
        );

        $rows = collect($orderedTypeIds)
            ->map(function (int $typeId) use ($byId): ?array {
                $type = $byId->get($typeId);
                if (! $type instanceof AnalysisType) {
                    return null;
                }

                $method = self::methodForAnalysisType($type);

                return [
                    'test' => (string) $type->name,
                    'test_method' => $method,
                    'test_detail' => self::testDetailForType($type),
                    'method' => $method,
                    'acceptable_values' => self::acceptableValuesForAnalysisType($type),
                    'result' => '',
                    'sample_1' => '',
                    'sample_2' => null,
                    'remarks' => null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $rows !== [] ? $rows : self::sampleRows();
    }

    /**
     * Distribute leftover field-box body height across drawn rows (Designer WYSIWYG).
     * Does not invent empty rows — only stretches rows that will print.
     *
     * @param  list<float>  $naturalHeightsMm
     * @return list<float>
     */
    public static function stretchBodyRowHeights(array $naturalHeightsMm, float $availableBodyMm): array
    {
        $count = count($naturalHeightsMm);
        if ($count === 0) {
            return [];
        }

        $naturalTotal = array_sum($naturalHeightsMm);
        if ($availableBodyMm <= 0 || $naturalTotal <= 0) {
            return array_values($naturalHeightsMm);
        }

        if ($naturalTotal >= $availableBodyMm - 0.05) {
            return array_values($naturalHeightsMm);
        }

        $extraEach = ($availableBodyMm - $naturalTotal) / $count;

        return array_map(
            static fn (float $natural): float => $natural + $extraEach,
            array_values($naturalHeightsMm),
        );
    }

    /**
     * Normalize column width_pct values so they sum to 100.
     * Designer HTML tables always fill the field box; PDF must do the same
     * even when stored percentages are incomplete (e.g. 41+45=86).
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<float>
     */
    public static function normalizedWidthPercentages(array $columns): array
    {
        $count = count($columns);
        if ($count === 0) {
            return [];
        }

        $raw = [];
        $sum = 0.0;
        foreach ($columns as $index => $column) {
            $pct = is_array($column) ? (float) ($column['width_pct'] ?? 0) : 0.0;
            $raw[$index] = $pct > 0 ? $pct : 0.0;
            $sum += $raw[$index];
        }

        if ($sum <= 0) {
            $equal = 100 / $count;

            return array_fill(0, $count, $equal);
        }

        $normalized = [];
        $running = 0.0;
        $lastPositiveIndex = null;
        foreach ($raw as $index => $pct) {
            if ($pct <= 0) {
                $normalized[$index] = 0.0;

                continue;
            }

            $lastPositiveIndex = $index;
            $value = 100 * ($pct / $sum);
            $normalized[$index] = $value;
            $running += $value;
        }

        if ($lastPositiveIndex !== null) {
            $normalized[$lastPositiveIndex] += 100 - $running;
        }

        return array_values($normalized);
    }

    /**
     * Absolute column widths in the same unit as $totalWidth (typically mm).
     *
     * @param  list<array<string, mixed>>  $columns
     * @return list<float>
     */
    public static function normalizedColumnWidths(array $columns, float $totalWidth): array
    {
        $percentages = self::normalizedWidthPercentages($columns);
        $count = count($percentages);
        if ($count === 0 || $totalWidth <= 0) {
            return array_fill(0, $count, 0.0);
        }

        $widths = [];
        $running = 0.0;
        $lastPositiveIndex = null;
        foreach ($percentages as $index => $pct) {
            if ($pct <= 0) {
                $widths[$index] = 0.0;

                continue;
            }

            $lastPositiveIndex = $index;
            $width = $totalWidth * ($pct / 100);
            $widths[$index] = $width;
            $running += $width;
        }

        if ($lastPositiveIndex !== null) {
            $widths[$lastPositiveIndex] += $totalWidth - $running;
        }

        return array_values($widths);
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

    public static function methodForAnalysisType(?AnalysisType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $stored = trim((string) ($type->method ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return OfficialAnalysisCatalog::methodForCode($type->code);
    }

    /**
     * Printed Result cell: measured value (+ unit) and Pass/Fail when both exist.
     */
    public static function formatPrintedResult(
        ?string $measured,
        ?string $unit = null,
        ?string $passFail = null,
    ): ?string {
        $value = trim((string) ($measured ?? ''));
        if ($value !== '' && filled($unit)) {
            $value = trim($value.' '.trim((string) $unit));
        }

        $outcome = trim((string) ($passFail ?? ''));
        if ($value !== '' && $outcome !== '') {
            return $value.' ('.$outcome.')';
        }
        if ($value !== '') {
            return $value;
        }
        if ($outcome !== '') {
            return $outcome;
        }

        return null;
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>  $ordered
     * @return list<array{test: string, test_method?: string|null, method?: string|null, acceptable_values?: string|null, result?: string|null, pass_fail?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function buildRows(Collection $ordered): array
    {
        return $ordered
            ->map(function (JobOrderAnalysis $line): array {
                $passFail = filled($line->result_pass_fail) ? (string) $line->result_pass_fail : null;
                $value = self::formatPrintedResult(
                    $line->result_value,
                    $line->result_unit,
                    $passFail,
                );
                $method = filled($line->result_method)
                    ? (string) $line->result_method
                    : self::methodForAnalysisType($line->analysisType);
                $acceptable = self::acceptableValuesForAnalysisType($line->analysisType);

                return [
                    'test' => (string) $line->name,
                    'test_method' => $method,
                    'test_detail' => self::testDetailForAnalysis($line),
                    'method' => $method,
                    'acceptable_values' => $acceptable,
                    'result' => $value,
                    'pass_fail' => $passFail,
                    'sample_1' => $value,
                    'sample_2' => null,
                    'remarks' => filled($line->result_remarks) ? (string) $line->result_remarks : null,
                ];
            })
            ->all();
    }

    /**
     * Residue sheets (e.g. F016-NO2) print one matrix row per JO sample.
     * Control numbers follow SampleControlNumber; result comes from the bound analysis line.
     *
     * @param  Collection<int, JobOrderAnalysis>  $ordered
     * @return list<array<string, mixed>>
     */
    public static function buildSampleResidueRows(JobOrder $jobOrder, Collection $ordered): array
    {
        // Same rule as other dynamic matrices: no selected parameter → no matrix rows.
        if ($ordered->isEmpty()) {
            return [];
        }

        $jobOrder->loadMissing('samples');
        $samples = $jobOrder->samples->values();
        $template = self::buildRows($ordered)[0] ?? [
            'test' => '',
            'test_method' => null,
            'result' => null,
            'pass_fail' => null,
            'sample_1' => null,
            'sample_2' => null,
            'remarks' => null,
        ];

        $referenceNo = trim((string) ($jobOrder->reference_no ?? ''));
        $count = $samples->count();

        // No sample lines: one control-number row from the JO reference (single sample).
        if ($count === 0) {
            return [[
                ...$template,
                'control_no' => $referenceNo,
                'sample_description' => '',
            ]];
        }

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $sample = $samples->get($i);
            $rows[] = [
                ...$template,
                'control_no' => SampleControlNumber::forIndex($referenceNo, $i, $count) ?? $referenceNo,
                'sample_description' => trim((string) ($sample?->description ?? '')),
            ];
        }

        return $rows;
    }

    /**
     * Optional second line under the test name (e.g. FO2 Time/Date of Analysis).
     */
    public static function testDetailForAnalysis(JobOrderAnalysis $line): ?string
    {
        $code = $line->analysisType?->code;
        $caption = OfficialAnalysisCatalog::wastewaterPhysicoIssue18AnalysisCaption($code);
        if ($caption === null) {
            return null;
        }

        $stamp = self::formatAnalysisStamp($line->completed_at, $caption);

        return $stamp !== null ? "{$caption}: {$stamp}" : "{$caption}:";
    }

    public static function testDetailForType(?AnalysisType $type): ?string
    {
        $caption = OfficialAnalysisCatalog::wastewaterPhysicoIssue18AnalysisCaption($type?->code);
        if ($caption === null) {
            return null;
        }

        return "{$caption}:";
    }

    public static function formatAnalysisStamp(mixed $completedAt, string $caption): ?string
    {
        if ($completedAt === null) {
            return null;
        }

        $carbon = $completedAt instanceof \Illuminate\Support\Carbon
            ? $completedAt
            : \Illuminate\Support\Carbon::parse($completedAt);

        $carbon = $carbon->timezone(config('app.lab_timezone', config('app.timezone', 'Asia/Manila')));

        if (str_starts_with($caption, 'Date/Time')) {
            return $carbon->format('F j, Y (g:iA)');
        }

        return $carbon->format('g:iA');
    }

    public static function acceptableValuesForAnalysisType(?AnalysisType $type): ?string
    {
        if ($type === null) {
            return null;
        }

        $stored = trim((string) ($type->acceptable_values ?? ''));
        if ($stored !== '') {
            return $stored;
        }

        return OfficialAnalysisCatalog::acceptableValuesForCode($type->code);
    }

    /**
     * Designer / empty-preview rows from the bound package members (print order).
     *
     * @return list<array{test: string, test_method?: string|null, method?: string|null, acceptable_values?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function previewRowsForPackage(?AnalysisPackage $package): array
    {
        if ($package === null) {
            return self::sampleRows();
        }

        $package->loadMissing('analysisTypes');

        $byId = $package->analysisTypes->keyBy(
            fn (AnalysisType $type): int => (int) $type->id,
        );

        $rows = collect($package->orderedTypeIds())
            ->map(function (int $typeId) use ($byId): ?array {
                $type = $byId->get($typeId);
                if (! $type instanceof AnalysisType) {
                    return null;
                }

                $method = self::methodForAnalysisType($type);

                return [
                    'test' => (string) $type->name,
                    'test_method' => $method,
                    'test_detail' => self::testDetailForType($type),
                    'method' => $method,
                    'acceptable_values' => self::acceptableValuesForAnalysisType($type),
                    'result' => '',
                    'sample_1' => '',
                    'sample_2' => null,
                    'remarks' => null,
                ];
            })
            ->filter()
            ->values()
            ->all();

        return $rows !== [] ? $rows : self::sampleRows();
    }

    /**
     * Default column set for Drinking Water Physico-Chemical style sheets.
     *
     * @return list<array<string, mixed>>
     */
    public static function drinkingWaterPhysicoColumns(): array
    {
        return [
            [
                'key' => 'test',
                'label' => 'Tests Performed',
                'width_pct' => 23,
                'align' => 'L',
                'header_align' => 'C',
            ],
            [
                'key' => 'method',
                'label' => 'Method',
                'width_pct' => 33,
                'align' => 'L',
                'header_align' => 'C',
            ],
            [
                'key' => 'result',
                'label' => 'Results',
                'width_pct' => 10,
                'align' => 'C',
                'header_align' => 'C',
            ],
            [
                'key' => 'acceptable_values',
                'label' => 'Acceptable Values',
                'width_pct' => 23,
                'align' => 'L',
                'header_align' => 'C',
            ],
            [
                'key' => 'remarks',
                'label' => 'Remarks',
                'width_pct' => 11,
                'align' => 'C',
                'header_align' => 'C',
            ],
        ];
    }

    /**
     * Matrix config for LSP 7.8 F016-NO2 nitrite blank shell.
     * Columns: Control Number | Sample Description | Nitrite (mg/kg).
     *
     * @return array<string, mixed>
     */
    public static function nitriteConfig(): array
    {
        return [
            'columns' => self::nitriteColumns(),
            'row_height_mm' => 12,
            'header_row' => true,
            'header_row_height_mm' => 15,
            'border' => true,
            // Same idea as other dynamic matrices: designer shows bound types only (1).
            // Runtime PDF rows come from JO samples (1 sample → 1 row, N → N).
            'preview_rows' => 1,
            'stretch_body' => false,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 9,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function nitriteColumns(): array
    {
        return [
            [
                'key' => 'control_no',
                'label' => 'Control Number',
                'width_pct' => 28,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'sample_description',
                'label' => 'Sample Description',
                'width_pct' => 36,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'result',
                'label' => 'Nitrite (mg/kg)',
                'sublabel' => 'Spectrophotometric Method',
                'sublabel_align' => 'C',
                'width_pct' => 36,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
        ];
    }

    /**
     * Matrix config for LSP 7.8 F016-CAP chloramphenicol blank shell.
     * Columns: Control Number | Sample Description | Results (ppb).
     *
     * @return array<string, mixed>
     */
    public static function chloramphenicolConfig(): array
    {
        return [
            'columns' => self::chloramphenicolColumns(),
            'row_height_mm' => 12,
            'header_row' => true,
            'header_row_height_mm' => 14,
            'border' => true,
            'preview_rows' => 1,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 9,
            'preview_data' => [
                [
                    'control_no' => 'CAP-2026-001',
                    'sample_description' => 'Prawn sample',
                    'result' => null,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function chloramphenicolColumns(): array
    {
        return [
            [
                'key' => 'control_no',
                'label' => 'Control Number',
                'width_pct' => 30,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'sample_description',
                'label' => 'Sample Description',
                'width_pct' => 40,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'result',
                'label' => 'Results',
                'sublabel' => '(ppb)',
                'sublabel_align' => 'C',
                'width_pct' => 30,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
        ];
    }

    /**
     * Matrix config for LSP 7.8 F016-WA water activity blank shell.
     * Columns: Control Number | Sample Description | Water Activity, Aw.
     *
     * @return array<string, mixed>
     */
    public static function waterActivityConfig(): array
    {
        return [
            'columns' => self::waterActivityColumns(),
            'row_height_mm' => 12,
            'header_row' => true,
            'header_row_height_mm' => 15,
            'border' => true,
            'preview_rows' => 1,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 9,
            'preview_data' => [
                [
                    'control_no' => 'WA-2026-001',
                    'sample_description' => 'Dried fruit',
                    'result' => null,
                ],
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function waterActivityColumns(): array
    {
        return [
            [
                'key' => 'control_no',
                'label' => 'Control Number',
                'width_pct' => 28,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'sample_description',
                'label' => 'Sample Description',
                'width_pct' => 36,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
            [
                'key' => 'result',
                'label' => 'Water Activity, Aw',
                'sublabel' => 'Water Activity Meter',
                'sublabel_align' => 'C',
                'width_pct' => 36,
                'align' => 'C',
                'header_align' => 'C',
                'font_size' => 11,
            ],
        ];
    }

    /**
     * Matrix config for LSP 7.8 FO2 Issue 18 wastewater blank shell.
     * Columns: Test (name + Time/Date of Analysis) | Method | Results.
     *
     * @return array<string, mixed>
     */
    public static function wastewaterPhysicoConfig(): array
    {
        return [
            'columns' => self::wastewaterPhysicoColumns(),
            'row_height_mm' => 5,
            'header_row' => true,
            'header_row_height_mm' => 5,
            'border' => true,
            'preview_rows' => 17,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 6,
            'header_font_size' => 8,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public static function wastewaterPhysicoColumns(): array
    {
        return [
            [
                'key' => 'test',
                'label' => 'TEST',
                'width_pct' => 34,
                'align' => 'C',
                'header_align' => 'C',
            ],
            [
                'key' => 'method',
                'label' => 'METHOD',
                'width_pct' => 48,
                'align' => 'C',
                'header_align' => 'C',
            ],
            [
                'key' => 'result',
                'label' => 'RESULTS',
                'width_pct' => 18,
                'align' => 'C',
                'header_align' => 'C',
            ],
        ];
    }

    /**
     * Matrix config for LSP 7.8 FO37 Issue 7 blank shell (matrix draws headers + grid).
     *
     * @return array<string, mixed>
     */
    public static function drinkingWaterPhysicoConfig(): array
    {
        return [
            'columns' => self::drinkingWaterPhysicoColumns(),
            'row_height_mm' => 10,
            'header_row' => true,
            'header_row_height_mm' => 8,
            'border' => true,
            'preview_rows' => 9,
            'test_name_bold' => true,
            'header_bold' => true,
            'method_font_size' => 7,
            'header_font_size' => 8,
        ];
    }

    /**
     * @return list<array{test: string, test_method?: string|null, method?: string|null, acceptable_values?: string|null, result?: string|null, sample_1?: string|null, sample_2?: string|null, remarks?: string|null}>
     */
    public static function sampleRows(): array
    {
        return [
            ['test' => '% Fat', 'test_method' => 'Soxhlet Extraction Method', 'method' => 'Soxhlet Extraction Method', 'result' => '0.68'],
            ['test' => '% Protein', 'test_method' => 'Kjeldahl Method', 'method' => 'Kjeldahl Method', 'result' => '18.2'],
            ['test' => '% Moisture', 'test_method' => 'Gravimetric Oven Drying at 105°C', 'method' => 'Gravimetric Oven Drying at 105°C', 'result' => '65.0'],
            ['test' => '% Fiber', 'test_method' => 'Weende Method', 'method' => 'Weende Method', 'result' => '2.1'],
            ['test' => '% Carbohydrates', 'test_method' => 'Phenol Sulfuric Acid Method', 'method' => 'Phenol Sulfuric Acid Method', 'result' => '12.4'],
            ['test' => '% Ash', 'test_method' => 'Oxidation at 550°C', 'method' => 'Oxidation at 550°C', 'result' => '1.2'],
            ['test' => '% Sodium', 'test_method' => 'FLAME-AES', 'method' => 'FLAME-AES', 'result' => '0.45'],
            ['test' => 'Sugar, Bx', 'test_method' => 'Refractometer', 'method' => 'Refractometer', 'result' => '4.5'],
        ];
    }
}
