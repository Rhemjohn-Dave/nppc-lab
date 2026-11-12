<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;

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
    public static function defaultsFor(?ControlledForm $form, ?JobOrder $jobOrder = null): array
    {
        $code = $form?->form_code;
        $description = trim((string) ($jobOrder?->samples->first()?->description ?? ''));
        $variant = OfficialAnalysisCatalog::fo2FooterVariant(
            $code === 'LSP-7.8-FO2A'
                ? 'Abstracted water'
                : ($code === 'LSP-7.8-FO2B' ? 'Coastal Water' : $description),
        );

        $stored = is_array($jobOrder?->result_footer) ? $jobOrder->result_footer : [];
        $temperature = trim((string) ($stored['temperature_c'] ?? ''));
        $humidity = trim((string) ($stored['humidity_pct'] ?? ''));
        $testReportNo = trim((string) ($stored['test_report_no'] ?? ''));

        if (in_array($code, ['LSP-7.8-FO3', 'LSP-7.8-FO37'], true)) {
            return [
                'temperature_c' => $temperature,
                'humidity_pct' => $humidity,
                'test_report_no' => $testReportNo,
                'lab_conditions' => self::composeLabConditionsText($form, [
                    'temperature_c' => $temperature !== '' ? $temperature : null,
                    'humidity_pct' => $humidity !== '' ? $humidity : null,
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
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'test_report_no' => $testReportNo,
            'lab_conditions' => self::composeLabConditionsText($form, [
                'temperature_c' => $temperature !== '' ? $temperature : null,
                'humidity_pct' => $humidity !== '' ? $humidity : null,
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
    ): array {
        $jobOrder->loadMissing('samples');
        $defaults = self::defaultsFor($form, $jobOrder);
        $stored = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];

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

        $showFieldData = self::formShowsFieldData($form, $jobOrder);
        $fieldData = trim((string) ($jobOrder->field_data ?? ''));
        if ($showFieldData && $fieldData === '') {
            $fieldData = OfficialAnalysisCatalog::coastalFieldDataTemplate();
        }

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'test_report_no' => $testReportNo,
            'test_report_prefix' => self::testReportPrefixFor($form),
            'lab_conditions' => $composed !== '' ? $composed : $value('lab_conditions'),
            'references' => $showNotes ? $value('references') : '',
            'notes' => $showNotes ? $value('notes') : '',
            'field_data' => $showFieldData ? $fieldData : '',
            'requires_atmosphere' => $requiresAtmosphere,
            'requires_field_data' => $showFieldData,
            'atmosphere_complete' => self::isAtmosphereComplete($jobOrder, $form),
            'field_data_complete' => ! $showFieldData || trim((string) ($jobOrder->field_data ?? '')) !== '',
            'show_notes_fields' => $showNotes,
            'show_field_data' => $showFieldData,
            'save_url' => $saveUrl,
            'can_edit' => filled($saveUrl),
        ];
    }

    public static function formShowsFieldData(?ControlledForm $form, ?JobOrder $jobOrder = null): bool
    {
        if ($form?->form_code === 'LSP-7.8-FO2B') {
            return true;
        }

        $jobOrder?->loadMissing('samples');
        $description = trim((string) ($jobOrder?->samples->first()?->description ?? ''));

        return OfficialAnalysisCatalog::isWastewaterCoastalKind($description);
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

    public static function isAtmosphereComplete(JobOrder $jobOrder, ?ControlledForm $form = null): bool
    {
        if ($form !== null && ! self::formRequiresLabAtmosphere($form)) {
            return true;
        }

        $stored = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];
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
