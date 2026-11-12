<?php

namespace App\Support;

use App\Models\AnalysisPackage;
use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\JobOrderAnalysis;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class ResultSignatories
{
    /**
     * @param  list<array{name?: mixed, prc_id?: mixed}>  $signatories
     * @return list<array{name: string, prc_id: string|null}>
     */
    public static function normalizeForStorage(
        array $signatories,
        ControlledForm $form,
        ?JobOrder $jobOrder = null,
    ): array {
        $slots = self::slotsFor($form, $jobOrder);
        $requirePrc = self::requirePrcFor($form);
        $normalized = [];

        for ($i = 0; $i < $slots; $i++) {
            $row = is_array($signatories[$i] ?? null) ? $signatories[$i] : [];
            $name = trim((string) ($row['name'] ?? ''));
            $prc = trim((string) ($row['prc_id'] ?? ''));

            if ($name === '') {
                throw ValidationException::withMessages([
                    "signatories.{$i}.name" => 'Analyst name is required.',
                ]);
            }

            if ($requirePrc && $prc === '') {
                throw ValidationException::withMessages([
                    "signatories.{$i}.prc_id" => 'PRC ID is required for this form.',
                ]);
            }

            $normalized[] = [
                'name' => $name,
                'prc_id' => $requirePrc || $prc !== '' ? ($prc !== '' ? $prc : null) : null,
            ];
        }

        return $normalized;
    }

    /**
     * Merge a normalized slot list into JO storage keyed by form_code.
     * Legacy flat lists are preserved under other form codes when migrating.
     *
     * @param  array<mixed>|null  $existing
     * @param  list<array{name: string, prc_id: string|null}>  $normalized
     * @return array{by_form: array<string, list<array{name: string, prc_id: string|null}>>}
     */
    public static function mergeIntoStorage(?array $existing, ControlledForm $form, array $normalized): array
    {
        $code = (string) ($form->form_code ?? '');
        $byForm = self::byFormMap($existing);

        if ($code !== '') {
            $byForm[$code] = array_values($normalized);
        }

        return ['by_form' => $byForm];
    }

    /**
     * @param  array<mixed>|null  $stored
     * @return array<string, list<array{name: string, prc_id: string|null}>>
     */
    public static function byFormMap(?array $stored): array
    {
        if (! is_array($stored) || $stored === []) {
            return [];
        }

        if (isset($stored['by_form']) && is_array($stored['by_form'])) {
            $map = [];
            foreach ($stored['by_form'] as $code => $rows) {
                if (! is_string($code) || $code === '' || ! is_array($rows)) {
                    continue;
                }
                $map[$code] = array_values(array_filter(
                    $rows,
                    fn ($row): bool => is_array($row),
                ));
            }

            return $map;
        }

        // Legacy flat list — no form key yet; caller resolves via rowsForForm.
        return [];
    }

    /**
     * Slot rows for a form: by_form[code], else legacy flat list, else empty.
     *
     * @param  array<mixed>|null  $stored
     * @return list<array{name?: mixed, prc_id?: mixed}>
     */
    public static function rowsForForm(?array $stored, ?ControlledForm $form): array
    {
        if (! is_array($stored) || $stored === []) {
            return [];
        }

        $code = (string) ($form?->form_code ?? '');
        $byForm = self::byFormMap($stored);

        if ($code !== '' && isset($byForm[$code]) && $byForm[$code] !== []) {
            return $byForm[$code];
        }

        // Legacy flat list (numeric keys, first row has name).
        if (self::isLegacyFlatList($stored)) {
            return array_values($stored);
        }

        // Single bucket in by_form when form_code missing / unknown.
        if ($code === '' && count($byForm) === 1) {
            return array_values($byForm)[0];
        }

        return [];
    }

    /**
     * @param  array<mixed>  $stored
     */
    public static function isLegacyFlatList(array $stored): bool
    {
        if (isset($stored['by_form'])) {
            return false;
        }

        $first = $stored[0] ?? null;

        return is_array($first) && array_key_exists('name', $first);
    }

    public static function isConfirmedForForm(?array $stored, ?ControlledForm $form): bool
    {
        return self::rowsForForm($stored, $form) !== [];
    }

    /**
     * @param  Collection<int, ControlledForm|null>|iterable<int, ControlledForm|null>  $forms
     */
    public static function missingFormCodes(?array $stored, iterable $forms): array
    {
        $missing = [];

        foreach ($forms as $form) {
            if (! $form instanceof ControlledForm) {
                continue;
            }
            $code = (string) ($form->form_code ?? '');
            if ($code === '') {
                continue;
            }
            if (! self::isConfirmedForForm($stored, $form)) {
                $missing[] = $code;
            }
        }

        return array_values(array_unique($missing));
    }

    public static function slotsFor(?ControlledForm $form, ?JobOrder $jobOrder = null): int
    {
        if ($form?->form_code === 'LSP-7.8-FO2B') {
            return 2;
        }

        if ($form?->form_code === 'LSP-7.8-FO2A') {
            return 3;
        }

        $slots = (int) ($form?->analyst_signatory_slots ?? 1);

        return max(1, min(4, $slots));
    }

    /**
     * Dialog / UI labels for each analyst slot (form-aware).
     *
     * @return list<string>
     */
    public static function slotLabels(?ControlledForm $form, ?JobOrder $jobOrder = null): array
    {
        $slots = self::slotsFor($form, $jobOrder);

        if ($form?->form_code === 'LSP-7.8-FO2B') {
            return array_slice([
                'Reviewed by',
                'Certified Correct',
            ], 0, $slots);
        }

        if ($form?->form_code === 'LSP-7.8-FO2A') {
            return array_slice([
                'Reviewed by',
                'Certified Correct',
                'Noted By',
            ], 0, $slots);
        }

        if ($form?->form_code === 'LSP-7.8-FO2') {
            return array_slice([
                'Reviewed by',
                'Noted By',
                'Certified Correct (1)',
                'Certified Correct (2)',
            ], 0, $slots);
        }

        if ($form?->form_code === 'LSP-7.8-FO28') {
            return array_slice([
                'Analyzed by',
                'Approved by',
            ], 0, $slots);
        }

        return array_map(
            fn (int $index): string => 'Analyst '.($index + 1),
            range(0, $slots - 1),
        );
    }

    public static function requirePrcFor(?ControlledForm $form): bool
    {
        return (bool) ($form?->analyst_require_prc ?? false);
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>|null  $orderedAnalyses
     * @return list<array{name: string, prc_id: string|null}>
     */
    public static function defaults(JobOrder $jobOrder, ?ControlledForm $form = null, ?Collection $orderedAnalyses = null): array
    {
        $slots = self::slotsFor($form, $jobOrder);
        $designer = self::designerStaticSlots($form, $slots);

        $fo28Fallback = $form?->form_code === 'LSP-7.8-FO28'
            ? array_slice([
                ['name' => 'Ma. Antoniela C. Lanaca, RChT', 'prc_id' => '0000761'],
                ['name' => 'Wendy A. Tayson, RCh, MEnE', 'prc_id' => '15131'],
            ], 0, $slots)
            : null;

        if ($fo28Fallback !== null) {
            $merged = [];
            for ($i = 0; $i < $slots; $i++) {
                $merged[] = [
                    'name' => $designer[$i]['name'] !== ''
                        ? $designer[$i]['name']
                        : ($fo28Fallback[$i]['name'] ?? ''),
                    'prc_id' => $designer[$i]['prc_id'] !== null
                        ? $designer[$i]['prc_id']
                        : ($fo28Fallback[$i]['prc_id'] ?? null),
                ];
            }

            return $merged;
        }

        $names = self::candidateNames($jobOrder, $orderedAnalyses);
        $defaults = [];

        if ($slots === 1) {
            $assigneeName = $names === []
                ? ''
                : (count($names) === 1 ? $names[0] : implode(', ', $names));
            $defaults[] = [
                'name' => $designer[0]['name'] !== '' ? $designer[0]['name'] : $assigneeName,
                'prc_id' => $designer[0]['prc_id'],
            ];

            return $defaults;
        }

        for ($i = 0; $i < $slots; $i++) {
            $defaults[] = [
                'name' => $designer[$i]['name'] !== ''
                    ? $designer[$i]['name']
                    : ($names[$i] ?? ''),
                'prc_id' => $designer[$i]['prc_id'],
            ];
        }

        return $defaults;
    }

    /**
     * Name/PRC static text from the active Form Designer revision (results.analyst_*).
     *
     * @return list<array{name: string, prc_id: string|null}>
     */
    public static function designerStaticSlots(?ControlledForm $form, ?int $slots = null): array
    {
        $count = max(1, min(4, $slots ?? self::slotsFor($form)));
        $empty = array_fill(0, $count, ['name' => '', 'prc_id' => null]);

        if ($form === null) {
            return $empty;
        }

        $revision = $form->activeRevision();
        if ($revision === null) {
            return $empty;
        }

        $revision->loadMissing('fields');
        $byKey = [];
        foreach ($revision->fields as $field) {
            $key = trim((string) ($field->data_source_key ?: $field->name));
            if ($key === '') {
                continue;
            }
            $byKey[$key] = $field;
        }

        $nameKeys = [
            'results.analyst_name',
            'results.analyst_name_2',
            'results.analyst_name_3',
            'results.analyst_name_4',
        ];
        $prcKeys = [
            'results.analyst_prc',
            'results.analyst_prc_2',
            'results.analyst_prc_3',
            'results.analyst_prc_4',
        ];
        $licenseKeys = self::fo2LicenseKeysForForm($form);

        $rows = [];
        for ($i = 0; $i < $count; $i++) {
            $name = self::fieldStaticText($byKey[$nameKeys[$i]] ?? null);
            $prc = self::normalizePrcStatic(self::fieldStaticText($byKey[$prcKeys[$i]] ?? null));
            if ($prc === null && isset($licenseKeys[$i])) {
                $prc = self::parseLicenseNumber(
                    self::fieldStaticText($byKey[$licenseKeys[$i]] ?? null),
                );
            }

            $rows[] = [
                'name' => $name,
                'prc_id' => $prc,
            ];
        }

        return $rows;
    }

    /**
     * FO2 / FO2A license chrome fields mapped to dialog slot order.
     *
     * @return list<string>
     */
    private static function fo2LicenseKeysForForm(?ControlledForm $form): array
    {
        return match ($form?->form_code) {
            'LSP-7.8-FO2B' => [
                'fo2_lic_reviewed',
                'fo2_lic_certified',
            ],
            'LSP-7.8-FO2A' => [
                'fo2_lic_reviewed',
                'fo2_lic_certified',
                'fo2_lic_noted',
            ],
            'LSP-7.8-FO2' => [
                'fo2_lic_reviewed',
                'fo2_lic_noted',
                'fo2_lic_certified_1',
                'fo2_lic_certified_2',
            ],
            default => [],
        };
    }

    private static function fieldStaticText(mixed $field): string
    {
        if ($field === null) {
            return '';
        }

        $options = is_array($field->options ?? null) ? $field->options : [];
        $raw = trim((string) ($options['static_text'] ?? ''));
        if ($raw === '') {
            return '';
        }

        return trim(InlineBoldMarkup::strip($raw));
    }

    private static function normalizePrcStatic(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        // Pure / mostly-numeric PRC stored on results.analyst_prc*.
        if (preg_match('/^\d{4,}$/', $text) === 1) {
            return $text;
        }

        return self::parseLicenseNumber($text);
    }

    private static function parseLicenseNumber(string $text): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        if (preg_match('/License\s*#\s*(\d+)/i', $text, $match) === 1) {
            return $match[1];
        }

        if (preg_match('/\bPRC(?:\s+License)?(?:\s+No\.?)?\s*[#:]?\s*(\d+)/i', $text, $match) === 1) {
            return $match[1];
        }

        return null;
    }

    /**
     * @param  Collection<int, JobOrderAnalysis>|null  $orderedAnalyses
     * @return list<string>
     */
    public static function candidateNames(JobOrder $jobOrder, ?Collection $orderedAnalyses = null): array
    {
        $jobOrder->loadMissing(['packages.signatory', 'analyses.assignee']);

        $signatories = $jobOrder->packages
            ->map(fn (AnalysisPackage $package) => $package->signatory?->name)
            ->filter()
            ->unique()
            ->values();

        if ($signatories->count() === 1) {
            return [(string) $signatories->first()];
        }

        $ordered = $orderedAnalyses?->values() ?? $jobOrder->analyses->values();

        return $ordered
            ->map(fn (JobOrderAnalysis $line) => $line->assignee?->name)
            ->filter()
            ->unique()
            ->values()
            ->map(fn ($name) => (string) $name)
            ->all();
    }

    /**
     * Printed analyst name only — PRC goes on results.analyst_prc*.
     *
     * @param  list<array{name?: string|null, prc_id?: string|null}>|null  $signatories
     */
    public static function formatLine(?array $signatory): ?string
    {
        if ($signatory === null) {
            return null;
        }

        $name = trim((string) ($signatory['name'] ?? ''));

        return $name !== '' ? $name : null;
    }

    /**
     * @return array{
     *     name: string|null,
     *     name_2: string|null,
     *     name_3: string|null,
     *     name_4: string|null,
     *     prc: string|null,
     *     prc_2: string|null,
     *     prc_3: string|null,
     *     prc_4: string|null,
     *     line: string|null,
     *     line_2: string|null,
     *     line_3: string|null,
     *     line_4: string|null
     * }
     */
    public static function bagValues(JobOrder $jobOrder, ?ControlledForm $form = null, ?Collection $orderedAnalyses = null): array
    {
        $stored = $jobOrder->result_signatories;
        $rows = self::rowsForForm(is_array($stored) ? $stored : null, $form);
        if ($rows === []) {
            $rows = self::defaults($jobOrder, $form, $orderedAnalyses);
        }

        $slot = static function (int $index) use ($rows): ?array {
            return is_array($rows[$index] ?? null) ? $rows[$index] : null;
        };

        $first = $slot(0);
        $second = $slot(1);
        $third = $slot(2);
        $fourth = $slot(3);

        return [
            'name' => filled($first['name'] ?? null) ? (string) $first['name'] : null,
            'name_2' => filled($second['name'] ?? null) ? (string) $second['name'] : null,
            'name_3' => filled($third['name'] ?? null) ? (string) $third['name'] : null,
            'name_4' => filled($fourth['name'] ?? null) ? (string) $fourth['name'] : null,
            'prc' => filled($first['prc_id'] ?? null) ? (string) $first['prc_id'] : null,
            'prc_2' => filled($second['prc_id'] ?? null) ? (string) $second['prc_id'] : null,
            'prc_3' => filled($third['prc_id'] ?? null) ? (string) $third['prc_id'] : null,
            'prc_4' => filled($fourth['prc_id'] ?? null) ? (string) $fourth['prc_id'] : null,
            'line' => self::formatLine($first),
            'line_2' => self::formatLine($second),
            'line_3' => self::formatLine($third),
            'line_4' => self::formatLine($fourth),
        ];
    }

    /**
     * Payload for the result report preview / export dialog.
     *
     * @return array<string, mixed>
     */
    public static function manifestPayload(
        JobOrder $jobOrder,
        ?ControlledForm $form,
        ?string $saveUrl = null,
        ?Collection $orderedAnalyses = null,
        ?int $sampleId = null,
    ): array {
        $stored = is_array($jobOrder->result_signatories) ? $jobOrder->result_signatories : null;
        $confirmed = self::isConfirmedForForm($stored, $form);
        $canEdit = filled($saveUrl);
        $rows = $confirmed
            ? self::rowsForForm($stored, $form)
            : self::defaults($jobOrder, $form, $orderedAnalyses);

        return [
            'slots' => self::slotsFor($form, $jobOrder),
            'slot_labels' => self::slotLabels($form, $jobOrder),
            'require_prc' => self::requirePrcFor($form),
            'confirmed' => $confirmed,
            'can_edit' => $canEdit,
            'signatories' => array_values($rows),
            'form_code' => $form?->form_code,
            'form_id' => $form?->id,
            'sample_id' => $sampleId,
            'save_url' => $canEdit ? $saveUrl : null,
        ];
    }
}
