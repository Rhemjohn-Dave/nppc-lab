<?php

namespace App\Support;

/**
 * Per-sample location, sample point, and collection time stored on receiving-bulk remarks.
 */
class BulkSampleRemarks
{
    /**
     * @return array{location: string, sample_point: string, collected_time: string}
     */
    public static function parse(?string $remarks): array
    {
        $out = [
            'location' => '',
            'sample_point' => '',
            'collected_time' => '',
        ];

        foreach (explode(' | ', (string) $remarks) as $part) {
            $part = trim($part);
            if (str_starts_with($part, 'Location: ')) {
                $out['location'] = trim(substr($part, 10));
            } elseif (str_starts_with($part, 'Sample point: ')) {
                $out['sample_point'] = trim(substr($part, 14));
            } elseif (str_starts_with($part, 'Collected: ')) {
                $out['collected_time'] = trim(substr($part, 11));
            }
        }

        return $out;
    }

    /**
     * @param  array{location?: string, sample_point?: string, collected_time?: string}  $parts
     */
    public static function compose(array $parts): ?string
    {
        $chunks = [];
        $location = trim((string) ($parts['location'] ?? ''));
        $point = trim((string) ($parts['sample_point'] ?? ''));
        $time = trim((string) ($parts['collected_time'] ?? ''));

        if ($location !== '') {
            $chunks[] = 'Location: '.$location;
        }
        if ($point !== '') {
            $chunks[] = 'Sample point: '.$point;
        }
        if ($time !== '') {
            $chunks[] = 'Collected: '.$time;
        }

        return $chunks === [] ? null : implode(' | ', $chunks);
    }

    /**
     * Normalize a stored time ("08:15" or "12:04 PM") for an HTML time input.
     */
    public static function timeForInput(?string $value): string
    {
        $raw = trim((string) $value);
        if ($raw === '') {
            return '';
        }

        if (preg_match('/^([01]?\d|2[0-3]):([0-5]\d)(?::[0-5]\d)?$/', $raw, $matches) === 1) {
            return sprintf('%02d:%s', (int) $matches[1], $matches[2]);
        }

        if (preg_match('/^(\d{1,2}):([0-5]\d)\s*([AaPp][Mm])$/', $raw, $matches) !== 1) {
            return '';
        }

        $hour = (int) $matches[1];
        $meridiem = strtoupper($matches[3]);
        if ($meridiem === 'AM') {
            $hour = $hour === 12 ? 0 : $hour;
        } else {
            $hour = $hour === 12 ? 12 : $hour + 12;
        }

        return sprintf('%02d:%s', $hour, $matches[2]);
    }
}
