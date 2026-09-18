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
    public static function normalizeForStorage(array $signatories, ControlledForm $form): array
    {
        $slots = self::slotsFor($form);
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

    public static function slotsFor(?ControlledForm $form): int
    {
        $slots = (int) ($form?->analyst_signatory_slots ?? 1);

        return max(1, min(4, $slots));
    }

    /**
     * Dialog / UI labels for each analyst slot (form-aware).
     *
     * @return list<string>
     */
    public static function slotLabels(?ControlledForm $form): array
    {
        $slots = self::slotsFor($form);

        if ($form?->form_code === 'LSP-7.8-FO2') {
            $labels = [
                'Reviewed by',
                'Noted By',
                'Certified Correct (1)',
                'Certified Correct (2)',
            ];

            return array_slice($labels, 0, $slots);
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
        $slots = self::slotsFor($form);
        $names = self::candidateNames($jobOrder, $orderedAnalyses);
        $defaults = [];

        if ($slots === 1) {
            $defaults[] = [
                'name' => $names === [] ? '' : (count($names) === 1 ? $names[0] : implode(', ', $names)),
                'prc_id' => null,
            ];

            return $defaults;
        }

        for ($i = 0; $i < $slots; $i++) {
            $defaults[] = [
                'name' => $names[$i] ?? '',
                'prc_id' => null,
            ];
        }

        return $defaults;
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
        $rows = is_array($stored) && $stored !== []
            ? $stored
            : self::defaults($jobOrder, $form, $orderedAnalyses);

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
    ): array {
        $confirmed = is_array($jobOrder->result_signatories) && $jobOrder->result_signatories !== [];
        $canEdit = filled($saveUrl);

        return [
            'slots' => self::slotsFor($form),
            'slot_labels' => self::slotLabels($form),
            'require_prc' => self::requirePrcFor($form),
            'confirmed' => $confirmed,
            'can_edit' => $canEdit,
            'signatories' => $confirmed
                ? array_values($jobOrder->result_signatories)
                : self::defaults($jobOrder, $form, $orderedAnalyses),
            'save_url' => $canEdit ? $saveUrl : null,
        ];
    }
}
