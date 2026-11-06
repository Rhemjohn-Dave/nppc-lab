<?php

namespace App\Support;

use App\Models\ControlledFormRevision;
use App\Models\JobOrder;
use App\Models\Sample;

/**
 * Intake facts an analyst may correct when the open result form plots them.
 */
class ResultSheetFacts
{
    /**
     * @var array<string, array{keys: list<string>, type: 'text'|'datetime'}>
     */
    private const GROUPS = [
        'wastewater_source' => [
            'keys' => ['results.water_supply', 'results.sampling_point'],
            'type' => 'text',
        ],
        'specimen' => [
            'keys' => ['results.specimen'],
            'type' => 'text',
        ],
        'sample_collected_by' => [
            'keys' => ['results.collected_by'],
            'type' => 'text',
        ],
        'sampling_datetime' => [
            'keys' => ['results.sampling_datetime', 'results.collection_datetime'],
            'type' => 'datetime',
        ],
    ];

    /**
     * Receiving-bulk location, sample point, and time stay on the open sample.
     *
     * @var array<string, array{keys: list<string>, type: 'text'|'time'}>
     */
    private const BULK_SAMPLE_GROUPS = [
        'location' => [
            'keys' => ['results.water_supply'],
            'type' => 'text',
        ],
        'sample_point' => [
            'keys' => ['results.sampling_point'],
            'type' => 'text',
        ],
        'collected_time' => [
            'keys' => ['results.sampling_datetime', 'results.collection_datetime'],
            'type' => 'time',
        ],
    ];

    /**
     * @var list<string>
     */
    private const BULK_REPLACED_KEYS = [
        'wastewater_source',
        'sampling_datetime',
    ];

    /**
     * @return list<array{key: string, label: string, type: string, value: string, time: string|null}>
     */
    public static function forReport(AnalysisResultReport $report): array
    {
        $revision = $report->controlledRevision;
        if (! $revision instanceof ControlledFormRevision) {
            return [];
        }

        $revision->loadMissing('fields');
        $labelsByName = [];
        foreach ($revision->fields as $field) {
            $labelsByName[(string) $field->name] = trim((string) ($field->label ?: $field->name));
        }

        $sample = self::reportSample($report);
        $bulk = (bool) $report->jobOrder->is_receiving_bulk && $sample instanceof Sample;
        $groups = self::GROUPS;
        if ($bulk) {
            foreach (self::BULK_REPLACED_KEYS as $replaced) {
                unset($groups[$replaced]);
            }
            $groups = self::BULK_SAMPLE_GROUPS + $groups;
        }

        $facts = [];
        foreach ($groups as $key => $group) {
            $label = null;
            foreach ($group['keys'] as $fieldName) {
                if (isset($labelsByName[$fieldName]) && $labelsByName[$fieldName] !== '') {
                    $label = $labelsByName[$fieldName];
                    break;
                }
            }
            if ($label === null) {
                continue;
            }

            $facts[] = self::present($key, $group['type'], $label, $report->jobOrder, $bulk ? $sample : null);
        }

        return $facts;
    }

    private static function reportSample(AnalysisResultReport $report): ?Sample
    {
        if ($report->sampleId === null) {
            return null;
        }

        $report->jobOrder->loadMissing('samples');

        $sample = $report->jobOrder->samples->first(
            fn ($row) => (int) $row->id === (int) $report->sampleId,
        );

        return $sample instanceof Sample ? $sample : null;
    }

    /**
     * @return array{key: string, label: string, type: string, value: string, time: string|null}
     */
    private static function present(string $key, string $type, string $label, JobOrder $jobOrder, ?Sample $sample): array
    {
        if ($type === 'time' && $sample instanceof Sample) {
            $parsed = BulkSampleRemarks::parse($sample->remarks);

            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'value' => BulkSampleRemarks::timeForInput($parsed['collected_time']),
                'time' => null,
            ];
        }

        if ($type === 'datetime') {
            $time = trim((string) ($jobOrder->sampling_time ?? ''));

            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'value' => $jobOrder->sampling_date?->format('Y-m-d') ?? '',
                'time' => $time === '' ? '' : substr($time, 0, 5),
            ];
        }

        if ($sample instanceof Sample && in_array($key, ['location', 'sample_point'], true)) {
            $parsed = BulkSampleRemarks::parse($sample->remarks);

            return [
                'key' => $key,
                'label' => $label,
                'type' => $type,
                'value' => $parsed[$key],
                'time' => null,
            ];
        }

        $stored = match ($key) {
            'wastewater_source' => $jobOrder->wastewater_source,
            'specimen' => $jobOrder->specimen,
            'sample_collected_by' => $jobOrder->sample_collected_by,
            default => null,
        };

        return [
            'key' => $key,
            'label' => $label,
            'type' => $type,
            'value' => self::printedText($stored),
            'time' => null,
        ];
    }

    private static function printedText(mixed $value): string
    {
        $trimmed = trim((string) $value);
        if (preg_match('/^others\s*:\s*(.+)$/i', $trimmed, $matches) === 1) {
            return trim($matches[1]);
        }

        return $trimmed;
    }
}
