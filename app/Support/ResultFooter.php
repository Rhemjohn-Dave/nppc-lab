<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;

class ResultFooter
{
    public const ATMOSPHERE_FORM_CODES = [
        'LSP-7.8-FO2',
        'LSP-7.8-FO2A',
        'LSP-7.8-FO3',
        'LSP-7.8-FO37',
    ];

    public const FO37_MDL_LINE = 'Method Detection Limit: Arsenic=6x10-5 mg/L, Lead=0.003 mg/L, Cadmium=0.002 mg/L';

    /**
     * @return array{
     *     temperature_c: string,
     *     humidity_pct: string,
     *     lab_conditions: string,
     *     references: string,
     *     notes: string
     * }
     */
    public static function defaultsFor(?ControlledForm $form, ?JobOrder $jobOrder = null): array
    {
        $code = $form?->form_code;
        $description = trim((string) ($jobOrder?->samples->first()?->description ?? ''));
        $variant = ($code === 'LSP-7.8-FO2A' || OfficialAnalysisCatalog::isWastewaterAbstractedKind($description))
            ? 'abstracted'
            : 'full';

        $temperature = '';
        $humidity = '';
        $stored = is_array($jobOrder?->result_footer) ? $jobOrder->result_footer : [];
        $temperature = trim((string) ($stored['temperature_c'] ?? ''));
        $humidity = trim((string) ($stored['humidity_pct'] ?? ''));

        if (in_array($code, ['LSP-7.8-FO3', 'LSP-7.8-FO37'], true)) {
            return [
                'temperature_c' => $temperature,
                'humidity_pct' => $humidity,
                'lab_conditions' => self::composeLabConditionsText($form, [
                    'temperature_c' => $temperature !== '' ? $temperature : null,
                    'humidity_pct' => $humidity !== '' ? $humidity : null,
                ]),
                'references' => '',
                'notes' => '',
            ];
        }

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'lab_conditions' => self::composeLabConditionsText($form, [
                'temperature_c' => $temperature !== '' ? $temperature : null,
                'humidity_pct' => $humidity !== '' ? $humidity : null,
            ], $variant),
            'references' => OfficialAnalysisCatalog::fo2ReferencesText(),
            'notes' => $variant === 'abstracted'
                ? OfficialAnalysisCatalog::fo2AbstractedNotesText()
                : OfficialAnalysisCatalog::fo2FullNotesText(),
        ];
    }

    /**
     * @return array{
     *     temperature_c: string,
     *     humidity_pct: string,
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

        $value = static function (string $key) use ($stored, $defaults): string {
            $override = trim((string) ($stored[$key] ?? ''));

            return $override !== '' ? $override : $defaults[$key];
        };

        $code = $form?->form_code;
        $showNotes = in_array($code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A'], true);
        $requiresAtmosphere = self::formRequiresLabAtmosphere($form);

        $composed = self::composeLabConditionsText($form, [
            'temperature_c' => $temperature !== '' ? $temperature : null,
            'humidity_pct' => $humidity !== '' ? $humidity : null,
            'lab_conditions' => $stored['lab_conditions'] ?? null,
        ]);

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'lab_conditions' => $composed !== '' ? $composed : $value('lab_conditions'),
            'references' => $showNotes ? $value('references') : '',
            'notes' => $showNotes ? $value('notes') : '',
            'requires_atmosphere' => $requiresAtmosphere,
            'atmosphere_complete' => self::isAtmosphereComplete($jobOrder, $form),
            'show_notes_fields' => $showNotes,
            'save_url' => $saveUrl,
            'can_edit' => filled($saveUrl),
        ];
    }

    /**
     * @param  array{
     *     temperature_c?: mixed,
     *     humidity_pct?: mixed,
     *     lab_conditions?: mixed,
     *     references?: mixed,
     *     notes?: mixed
     * }  $payload
     * @return array{
     *     temperature_c: string|null,
     *     humidity_pct: string|null,
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

        $composed = self::composeLabConditionsText($form, [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
            'lab_conditions' => $payload['lab_conditions'] ?? null,
        ]);

        $showNotes = in_array($form?->form_code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A'], true);

        return [
            'temperature_c' => $temperature,
            'humidity_pct' => $humidity,
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

    public static function isAtmosphereComplete(JobOrder $jobOrder, ?ControlledForm $form = null): bool
    {
        if ($form !== null && ! self::formRequiresLabAtmosphere($form)) {
            return true;
        }

        $stored = is_array($jobOrder->result_footer) ? $jobOrder->result_footer : [];
        $temperature = trim((string) ($stored['temperature_c'] ?? ''));
        $humidity = trim((string) ($stored['humidity_pct'] ?? ''));

        return $temperature !== '' && $humidity !== '';
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

        if (in_array($code, ['LSP-7.8-FO2', 'LSP-7.8-FO2A'], true)) {
            return "Laboratory Conditions during the conduct of test: Temp = {$tempDisplay} °C ; Humidity = {$humDisplay} %\n"
                .'NA – Not Applicable for specified water body .';
        }

        $legacy = trim((string) ($footer['lab_conditions'] ?? ''));

        return $legacy;
    }
}
