<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\Sample;

class ResultFooter
{
    public const ATMOSPHERE_FORM_CODES = [
        'LSP-7.8-FO2',
        'LSP-7.8-FO2A',
        'LSP-7.8-FO2B',
        'LSP-7.8-FO3',
        'LSP-7.8-FO37',
    ];

    public const PAGE_STAMP_FORM_CODES = [
        'LSP-7.8-FO2',
        'LSP-7.8-FO2A',
        'LSP-7.8-FO2B',
        'LSP-7.8-FO3',
        'LSP-7.8-FO37',
        'LSP-7.8-FO28',
    ];

    public const TEST_REPORT_PREFIX_WW = 'CHE WW –';

    public const TEST_REPORT_PREFIX_DW = 'CHE DW –';

    public const FO37_MDL_LINE = 'Method Detection Limit: Arsenic=6x10-5 mg/L, Lead=0.003 mg/L, Cadmium=0.002 mg/L';

    public static function sheetKey(?int $sampleId, ?ControlledForm $form): ?string
    {
        $code = trim((string) ($form?->form_code ?? ''));
        if ($sampleId === null || $sampleId <= 0 || $code === '') {
            return null;
        }

        return $sampleId.':'.$code;
    }

    /**
     * @param  array<mixed>|null  $stored
     * @return array<string, array<string, mixed>>
     */
    public static function bySheetMap(?array $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return [];
        }

        if (! isset($stored['by_sheet']) || ! is_array($stored['by_sheet'])) {
            return [];
        }

        $map = [];
        foreach ($stored['by_sheet'] as $key => $bucket) {
            if (! is_string($key) || $key === '' || ! is_array($bucket)) {
                continue;
            }
            $map[$key] = $bucket;
        }

        return $map;
    }

    /**
     * Flat legacy footer (no by_sheet) used before per-sheet storage.
     *
     * @param  array<mixed>|null  $stored
     */
    public static function isLegacyFlatFooter(?array $stored): bool
    {
        if (! is_array($stored) || $stored === []) {
            return false;
        }

        if (isset($stored['by_sheet'])) {
            return false;
        }

        return array_key_exists('temperature_c', $stored)
            || array_key_exists('humidity_pct', $stored)
            || array_key_exists('test_report_no', $stored)
            || array_key_exists('lab_conditions', $stored)
            || array_key_exists('notes', $stored)
            || array_key_exists('references', $stored);
    }

    /**
     * @param  array<mixed>|null  $stored
     * @return array<string, mixed>
     */
    public static function bucketForSheet(
        ?array $stored,
        ?int $sampleId,
        ?ControlledForm $form,
    ): array {
        $key = self::sheetKey($sampleId, $form);
        $bySheet = self::bySheetMap($stored);

        if ($key !== null && isset($bySheet[$key]) && is_array($bySheet[$key])) {
            return $bySheet[$key];
        }

        if (self::isLegacyFlatFooter($stored)) {
            return $stored;
        }

        return [];
    }

    /**
     * @param  array<mixed>|null  $existing
     * @param  array{
     *     temperature_c?: string|null,
     *     humidity_pct?: string|null,
     *     test_report_no?: string|null,
     *     lab_conditions?: string|null,
     *     references?: string|null,
     *     notes?: string|null
     * }  $normalized
     * @return array{by_sheet: array<string, array<string, mixed>>}
     */
    public static function mergeIntoStorage(
        ?array $existing,
        ?int $sampleId,
        ControlledForm $form,
        array $normalized,
    ): array {
        $key = self::sheetKey($sampleId, $form);
        $bySheet = self::bySheetMap($existing);

        // Promote legacy flat footer into a sheet bucket when first keyed save happens.
        if ($bySheet === [] && self::isLegacyFlatFooter($existing) && $key !== null) {
            $bySheet[$key] = array_intersect_key(
                $existing ?? [],
                array_flip([
                    'temperature_c',
                    'humidity_pct',
                    'test_report_no',
                    'lab_conditions',
                    'references',
                    'notes',
                ]),
            );
        }

        if ($key !== null) {
            $bySheet[$key] = $normalized;
        }

        return ['by_sheet' => $bySheet];
    }

    /**
     * FIELD DATA text for a sheet: sample column first, else JO legacy.
     */
    public static function fieldDataTextForSample(?Sample $sample, ?JobOrder $jobOrder): string
    {
        $fromSample = trim((string) ($sample?->field_data ?? ''));
        if ($fromSample !== '') {
            return $fromSample;
        }

        return trim((string) ($jobOrder?->field_data ?? ''));
    }

    /**
     * @return array{
     *     temperature_c: string,
     *     humidity_pct: string,
     *     test_report_no: string,
     *     lab_conditions: string,
     *     references: string,
     *     notes: string
     * }
     */
    public static function defaultsFor(
        ?ControlledForm $form,
        ?JobOrder $jobOrder = null,
        ?Sample $sample = null,
    ): array {
        $code = $form?->form_code;
        $description = trim((string) ($sample?->description
            ?? $jobOrder?->samples->first()?->description
            ?? ''));
        $variant = OfficialAnalysisCatalog::fo2FooterVariant(
            $code === 'LSP-7.8-FO2A'
                ? 'Abstracted water'
                : ($code === 'LSP-7.8-FO2B' ? 'Coastal Water' : $description),
        );

        if (in_array($code, ['LSP-7.8-FO3', 'LSP-7.8-FO37'], true)) {
            return [
                'temperature_c' => '',
                'humidity_pct' => '',
                'test_report_no' => '',
                'lab_conditions' => self::composeLabConditionsText($form, [
                    'temperature_c' => null,
                    'humidity_pct' => null,
                ]),
                'references' => '',
                'notes' => '',
            ];
        }

        $notes = match ($variant) {
            'abstracted' => OfficialAnalysisCatalog::fo2AbstractedNotesText(),
            'coastal' => OfficialAnalysisCatalog::fo2CoastalNotesText(),
            default => OfficialAnalysisCatalog::fo2FullNotesText(),
        };

        return [
            'temperature_c' => '',
            'humidity_pct' => '',
            'test_report_no' => '',
            'lab_conditions' => self::composeLabConditionsText($form, [
                'temperature_c' => null,
                'humidity_pct' => null,
            ], $variant),
            'references' => OfficialAnalysisCatalog::fo2ReferencesText(),
            'notes' => $notes,
        ];
    }

    /**
     * @return array{
     *     temperature_c: string,
     *     humidity_pct: string,
     *     test_report_no: string,
     *     test_report_prefix: string,
     *     lab_conditions: string,
     *     references: string,
     *     notes: string,
     *     sample_id: int|null,
     *     form_id: int|null,
     *     form_code: string|null,
     *     requires_atmosphere: bool,
     *     atmosphere_complete: bool,
     *     show_notes_fields: bool,
     *     save_url: string|null,
     *     can_edit: bool
     * }
     */
    public static function manifestPayload(
        JobOrder $jobOrder,
        ?ControlledForm $form,
        ?string $saveUrl = null,
        ?Sample $sample = null,
    ): array {
        $jobOrder->loadMissing('samples');
        if ($sample === null && $jobOrder->samples->isNotEmpty()) {
            $sample = $jobOrder->samples->first();
        }

        $defaults = self::defaultsFor($form, $jobOrder, $sample);
        $storedRoot = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];
        $stored = self::bucketForSheet($storedRoot, $sample?->id, $form);

        $temperature = trim((string) ($stored['temperature_c'] ?? ''));
        $humidity = trim((string) ($stored['humidity_pct'] ?? ''));
        $testReportNo = trim((string) ($stored['test_report_no'] ?? ''));

        $value = static function (string $key) use ($stored, $defaults): string {
            $override = trim((string) ($stored[$key] ?? ''));

            return $override !== '' ? $override : $defaults[$key];
        };

        $code = $form?->form_code;
        $showNotes = in_array($code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'], true);
        $requiresAtmosphere = self::formRequiresLabAtmosphere($form);

        $composed = self::composeLabConditionsText($form, [
            'temperature_c' => $temperature !== '' ? $temperature : null,
            'humidity_pct' => $humidity !== '' ? $humidity : null,
            'lab_conditions' => $stored['lab_conditions'] ?? null,
        ]);

        $showFieldData = self::formShowsFieldData($form, $jobOrder, $sample);
        $fieldDataRaw = self::fieldDataTextForSample($sample, $jobOrder);
        $fieldComplete = self::isCoastalFieldDataComplete($fieldDataRaw);
        $emptyParts = [
            'temperature' => '',
            'dissolved_oxygen' => '',
            'time_of_sampling' => '',
            'time_of_sampling_from' => '',
            'time_of_sampling_to' => '',
        ];
        $fieldParts = ($showFieldData && $fieldDataRaw !== '')
            ? self::parseCoastalFieldData($fieldDataRaw)
            : $emptyParts;
        $fieldDataPreview = $showFieldData
            ? ($fieldDataRaw !== ''
                ? $fieldDataRaw
                : OfficialAnalysisCatalog::coastalFieldDataTemplate())
            : '';

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'test_report_no' => $testReportNo,
            'test_report_prefix' => self::testReportPrefixFor($form),
            'lab_conditions' => $composed !== '' ? $composed : $value('lab_conditions'),
            'references' => $showNotes ? $value('references') : '',
            'notes' => $showNotes ? $value('notes') : '',
            'field_data' => $fieldDataPreview,
            'field_data_temperature' => $fieldParts['temperature'],
            'field_data_dissolved_oxygen' => $fieldParts['dissolved_oxygen'],
            'field_data_time_of_sampling' => $fieldParts['time_of_sampling'],
            'field_data_time_of_sampling_from' => $fieldParts['time_of_sampling_from'],
            'field_data_time_of_sampling_to' => $fieldParts['time_of_sampling_to'],
            'sample_id' => $sample?->id,
            'form_id' => $form?->id,
            'form_code' => $form?->form_code,
            'requires_atmosphere' => $requiresAtmosphere,
            'requires_field_data' => $showFieldData,
            'atmosphere_complete' => self::isAtmosphereComplete($jobOrder, $form, $sample),
            'field_data_complete' => ! $showFieldData || $fieldComplete,
            'show_notes_fields' => $showNotes,
            'show_field_data' => $showFieldData,
            'save_url' => $saveUrl,
            'can_edit' => filled($saveUrl),
        ];
    }

    public static function formShowsFieldData(
        ?ControlledForm $form,
        ?JobOrder $jobOrder = null,
        ?Sample $sample = null,
    ): bool {
        if ($sample !== null) {
            $description = trim((string) ($sample->description ?? ''));
            if ($description !== '' && in_array($description, OfficialAnalysisCatalog::wastewaterKinds(), true)) {
                return OfficialAnalysisCatalog::isWastewaterCoastalKind($description);
            }

            return $form?->form_code === 'LSP-7.8-FO2B'
                || OfficialAnalysisCatalog::isWastewaterCoastalKind($description);
        }

        $jobOrder?->loadMissing('samples');
        $descriptions = collect($jobOrder?->samples ?? [])
            ->map(static fn ($row): string => trim((string) ($row->description ?? '')))
            ->filter(static fn (string $description): bool => $description !== '')
            ->values();

        if ($descriptions->contains(
            static fn (string $description): bool => OfficialAnalysisCatalog::isWastewaterCoastalKind($description),
        )) {
            return true;
        }

        if ($descriptions->contains(
            static fn (string $description): bool => in_array(
                $description,
                OfficialAnalysisCatalog::wastewaterKinds(),
                true,
            ),
        )) {
            return false;
        }

        return $form?->form_code === 'LSP-7.8-FO2B';
    }

    /**
     * @return array{
     *     temperature: string,
     *     dissolved_oxygen: string,
     *     time_of_sampling: string,
     *     time_of_sampling_from: string,
     *     time_of_sampling_to: string
     * }
     */
    public static function parseCoastalFieldData(?string $text): array
    {
        $raw = (string) ($text ?? '');

        $extract = static function (string $label) use ($raw): string {
            if ($raw === '') {
                return '';
            }

            $pattern = '/'.preg_quote($label, '/').'\s*[–\-—:]\s*(.*)$/miu';
            if (! preg_match($pattern, $raw, $matches)) {
                return '';
            }

            return trim((string) ($matches[1] ?? ''));
        };

        $timeOfSampling = $extract('Time of Sampling');
        $range = self::parseTimeOfSamplingRange($timeOfSampling);

        return [
            'temperature' => $extract('Temperature'),
            'dissolved_oxygen' => $extract('Dissolved Oxygen'),
            'time_of_sampling' => $timeOfSampling,
            'time_of_sampling_from' => $range['from'],
            'time_of_sampling_to' => $range['to'],
        ];
    }

    /**
     * @return array{from: string, to: string}
     */
    public static function parseTimeOfSamplingRange(?string $text): array
    {
        $raw = trim((string) $text);
        if ($raw === '') {
            return ['from' => '', 'to' => ''];
        }

        if (preg_match('/^(.+?)\s*[–\-—]\s*(.+)$/u', $raw, $matches) === 1) {
            return [
                'from' => trim((string) $matches[1]),
                'to' => trim((string) $matches[2]),
            ];
        }

        return ['from' => $raw, 'to' => ''];
    }

    public static function composeTimeOfSamplingRange(?string $from, ?string $to): string
    {
        $start = trim((string) $from);
        $end = trim((string) $to);

        if ($start !== '' && $end !== '') {
            return "{$start} – {$end}";
        }

        return $start !== '' ? $start : $end;
    }

    public static function composeCoastalFieldData(
        ?string $temperature,
        ?string $dissolvedOxygen,
        ?string $timeOfSampling,
    ): string {
        $temp = trim((string) $temperature);
        $do = trim((string) $dissolvedOxygen);
        $time = trim((string) $timeOfSampling);

        return "FIELD DATA:\n"
            ."a) Temperature – {$temp}\n"
            ."b) Dissolved Oxygen - {$do}\n"
            ."c) Time of Sampling - {$time}\n"
            ."d) Other field data will be available upon request\n"
            .'(Field Sampling Logbook)';
    }

    public static function isCoastalFieldDataComplete(?string $text): bool
    {
        $parts = self::parseCoastalFieldData($text);

        return $parts['temperature'] !== ''
            && $parts['dissolved_oxygen'] !== ''
            && $parts['time_of_sampling_from'] !== ''
            && $parts['time_of_sampling_to'] !== '';
    }

    /**
     * @param  array{
     *     temperature_c?: mixed,
     *     humidity_pct?: mixed,
     *     test_report_no?: mixed,
     *     lab_conditions?: mixed,
     *     references?: mixed,
     *     notes?: mixed
     * }  $payload
     * @return array{
     *     temperature_c: string|null,
     *     humidity_pct: string|null,
     *     test_report_no: string|null,
     *     lab_conditions: string|null,
     *     references: string|null,
     *     notes: string|null
     * }
     */
    public static function normalizeForStorage(array $payload, ?ControlledForm $form = null): array
    {
        $normalize = static function (mixed $value): ?string {
            $text = trim((string) ($value ?? ''));

            return $text !== '' ? $text : null;
        };

        $temperature = $normalize($payload['temperature_c'] ?? null);
        $humidity = $normalize($payload['humidity_pct'] ?? null);
        $testReportNo = $normalize($payload['test_report_no'] ?? null);

        $composed = self::composeLabConditionsText($form, [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'lab_conditions' => $payload['lab_conditions'] ?? null,
        ]);

        $showNotes = in_array($form?->form_code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'], true);

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'test_report_no' => $testReportNo,
            'lab_conditions' => $composed !== '' ? $composed : $normalize($payload['lab_conditions'] ?? null),
            'references' => $showNotes ? $normalize($payload['references'] ?? null) : null,
            'notes' => $showNotes ? $normalize($payload['notes'] ?? null) : null,
        ];
    }

    public static function formSupportsFooter(?ControlledForm $form): bool
    {
        return self::formRequiresLabAtmosphere($form);
    }

    public static function formRequiresLabAtmosphere(?ControlledForm $form): bool
    {
        return in_array($form?->form_code, self::ATMOSPHERE_FORM_CODES, true);
    }

    public static function formUsesPageStamp(?ControlledForm $form): bool
    {
        return in_array($form?->form_code, self::PAGE_STAMP_FORM_CODES, true);
    }

    public static function testReportPrefixFor(?ControlledForm $form): string
    {
        $code = $form?->form_code;

        if (in_array($code, ['LSP-7.8-FO3', 'LSP-7.8-FO37'], true)) {
            return self::TEST_REPORT_PREFIX_DW;
        }

        return self::TEST_REPORT_PREFIX_WW;
    }

    public static function isAtmosphereComplete(
        JobOrder $jobOrder,
        ?ControlledForm $form = null,
        ?Sample $sample = null,
    ): bool {
        if ($form !== null && ! self::formRequiresLabAtmosphere($form)) {
            return true;
        }

        $storedRoot = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];
        $stored = self::bucketForSheet($storedRoot, $sample?->id, $form);
        $temperature = trim((string) ($stored['temperature_c'] ?? ''));
        $humidity = trim((string) ($stored['humidity_pct'] ?? ''));
        $testReportNo = trim((string) ($stored['test_report_no'] ?? ''));

        return $temperature !== '' && $humidity !== '' && $testReportNo !== '';
    }

    /**
     * @param  array{temperature_c?: string|null, humidity_pct?: string|null, lab_conditions?: mixed}|null  $footer
     */
    public static function composeLabConditionsText(
        ?ControlledForm $form,
        ?array $footer = null,
        ?string $variant = null,
    ): string {
        $code = $form?->form_code;
        $temperature = trim((string) ($footer['temperature_c'] ?? ''));
        $humidity = trim((string) ($footer['humidity_pct'] ?? ''));
        $tempDisplay = $temperature !== '' ? $temperature : '     ';
        $humDisplay = $humidity !== '' ? $humidity : '     ';

        if ($code === 'LSP-7.8-FO37') {
            return "Laboratory Test Conditions during the conduct of the test:   Temp: {$tempDisplay}   Humidity: {$humDisplay}\n"
                .self::FO37_MDL_LINE;
        }

        if ($code === 'LSP-7.8-FO3') {
            return "Laboratory Test Conditions during the conduct of the test:   Temp: {$tempDisplay}   Humidity: {$humDisplay}";
        }

        if (in_array($code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'], true)) {
            return "Laboratory Conditions during the conduct of test: Temp = {$tempDisplay} °C ; Humidity = {$humDisplay} %\n"
                .'NA – Not Applicable for specified water body .';
        }

        $legacy = trim((string) ($footer['lab_conditions'] ?? ''));

        return $legacy;
    }

    /**
     * @param  bool|'abstracted'|'coastal'|'full'  $variantOrAbstracted
     */
    public static function composeFo2FormMeta(?string $testReportNo, bool|string $variantOrAbstracted = false): string
    {
        $suffix = trim((string) $testReportNo);
        $variant = is_string($variantOrAbstracted)
            ? $variantOrAbstracted
            : ($variantOrAbstracted ? 'abstracted' : 'full');
        $codeLine = match ($variant) {
            'abstracted' => 'LSP 7.8_FO2',
            'coastal' => 'LSP 7.8_FO2B',
            default => 'LSP 7.8  FO2',
        };

        return "Test Report#: ".self::TEST_REPORT_PREFIX_WW.($suffix !== '' ? ' '.$suffix : '')."\n"
            ."NPPC-ADL\n"
            ."{$codeLine}\n"
            ."Rev. 17/Issue 18\n"
            .'Effectivity Date: 09/16/2026';
    }

    public static function composeFo3FormMeta(?string $testReportNo): string
    {
        $suffix = trim((string) $testReportNo);

        return "Test Report #: ".self::TEST_REPORT_PREFIX_DW.($suffix !== '' ? ' '.$suffix : '')."\n"
            ."Laboratory DOH Accreditation # # DOH052581\n"
            ."NPPC-ADL\n"
            ."LSP 7.8 FO3\n"
            .'Rev. 10 / Issue 11 Effectivity Date:  09/01/2026';
    }

    public static function composeFo37FormMeta(?string $testReportNo): string
    {
        $suffix = trim((string) $testReportNo);

        return "Test Report #: ".self::TEST_REPORT_PREFIX_DW.($suffix !== '' ? ' '.$suffix : '')."\n"
            ."Laboratory DOH Accreditation # DOH052581\n"
            ."NPPC-ADL\n"
            ."LSP 7.8 FO37\n"
            ."Rev. 06 / Issue 07\n"
            .'Effectivity Date:  09/01/2026';
    }

    public static function pageStampText(int $pageNo, int $pageCount): string
    {
        return '- page '.$pageNo.'/'.$pageCount.' -';
    }
}
