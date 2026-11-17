<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\Sample;
use Illuminate\Support\Carbon;

class ReportDate
{
    public const STORAGE_KEY = 'report_date';

    /**
     * @return array{mode: 'calendar'|'working', days: int}
     */
    public static function ruleFor(?ControlledForm $form): array
    {
        $code = trim((string) ($form?->form_code ?? ''));

        return match ($code) {
            'LSP-7.8-FO4', 'LSP-7.8-FO5' => ['mode' => 'calendar', 'days' => 4],
            'LSP-7.8-FO3', 'LSP-7.8-FO37' => ['mode' => 'working', 'days' => 7],
            'LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B' => ['mode' => 'calendar', 'days' => 7],
            default => ['mode' => 'calendar', 'days' => 7],
        };
    }

    /**
     * TAT date from Date/Time Submitted (`received_at`), or null when not yet received.
     */
    public static function computeDefault(JobOrder $jobOrder, ?ControlledForm $form = null): ?Carbon
    {
        if ($jobOrder->received_at === null) {
            return null;
        }

        $tz = (string) config('app.lab_timezone', 'Asia/Manila');
        $anchor = Carbon::instance($jobOrder->received_at)->timezone($tz)->startOfDay();
        $rule = self::ruleFor($form);

        return $rule['mode'] === 'working'
            ? WorkingDays::addWorkingDays($anchor, $rule['days'])
            : WorkingDays::addCalendarDays($anchor, $rule['days']);
    }

    /**
     * Analyst override stored as Y-m-d in result_footer by_sheet, when present.
     */
    public static function override(
        JobOrder $jobOrder,
        ?Sample $sample = null,
        ?ControlledForm $form = null,
    ): ?Carbon {
        $bucket = ResultFooter::bucketForSheet(
            is_array($jobOrder->result_footer) ? $jobOrder->result_footer : null,
            $sample?->id,
            $form,
        );
        $raw = trim((string) ($bucket[self::STORAGE_KEY] ?? ''));
        if ($raw === '') {
            return null;
        }

        try {
            $tz = (string) config('app.lab_timezone', 'Asia/Manila');

            return Carbon::parse($raw, $tz)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Effective report date: override wins, else form TAT from received_at.
     */
    public static function resolve(
        JobOrder $jobOrder,
        ?ControlledForm $form = null,
        ?Sample $sample = null,
    ): ?Carbon {
        return self::override($jobOrder, $sample, $form)
            ?? self::computeDefault($jobOrder, $form);
    }

    /**
     * Persist or clear per-sheet report_date override without wiping lab footer fields.
     *
     * @return array{by_sheet: array<string, array<string, mixed>>}|null
     */
    public static function mergeOverrideIntoStorage(
        ?array $existing,
        int $sampleId,
        ControlledForm $form,
        ?string $reportDateYmd,
    ): ?array {
        $key = ResultFooter::sheetKey($sampleId, $form);
        if ($key === null) {
            return is_array($existing) ? $existing : null;
        }

        $bySheet = ResultFooter::bySheetMap($existing);
        if ($bySheet === [] && ResultFooter::isLegacyFlatFooter($existing)) {
            $bySheet[$key] = array_intersect_key(
                $existing ?? [],
                array_flip([
                    'temperature_c',
                    'humidity_pct',
                    'test_report_no',
                    'lab_conditions',
                    'references',
                    'notes',
                    self::STORAGE_KEY,
                ]),
            );
        }

        $bucket = $bySheet[$key] ?? [];
        $normalized = $reportDateYmd !== null && trim($reportDateYmd) !== ''
            ? trim($reportDateYmd)
            : null;

        if ($normalized === null) {
            unset($bucket[self::STORAGE_KEY]);
        } else {
            $bucket[self::STORAGE_KEY] = $normalized;
        }

        if ($bucket === []) {
            unset($bySheet[$key]);
        } else {
            $bySheet[$key] = $bucket;
        }

        return $bySheet === [] ? null : ['by_sheet' => $bySheet];
    }

    /**
     * @return array{
     *     value: string|null,
     *     display: string|null,
     *     default_value: string|null,
     *     default_display: string|null,
     *     is_override: bool,
     *     sample_id: int|null,
     *     form_id: int|null,
     *     form_code: string|null,
     *     can_edit: bool,
     *     save_url: string|null
     * }
     */
    public static function manifestPayload(
        JobOrder $jobOrder,
        ?ControlledForm $form,
        ?Sample $sample = null,
        ?string $saveUrl = null,
        bool $canEdit = false,
    ): array {
        $tz = (string) config('app.lab_timezone', 'Asia/Manila');
        $override = self::override($jobOrder, $sample, $form);
        $default = self::computeDefault($jobOrder, $form);
        $effective = $override ?? $default;

        $format = static function (?Carbon $date) use ($tz): ?string {
            return $date?->copy()->timezone($tz)->format('F j, Y');
        };
        $ymd = static function (?Carbon $date) use ($tz): ?string {
            return $date?->copy()->timezone($tz)->format('Y-m-d');
        };

        return [
            'value' => $ymd($effective),
            'display' => $format($effective),
            'default_value' => $ymd($default),
            'default_display' => $format($default),
            'is_override' => $override !== null,
            'sample_id' => $sample?->id,
            'form_id' => $form?->id,
            'form_code' => $form?->form_code,
            'can_edit' => $canEdit && filled($saveUrl) && $form !== null && $sample !== null,
            'save_url' => $canEdit ? $saveUrl : null,
        ];
    }
}
