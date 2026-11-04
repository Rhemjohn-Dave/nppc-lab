<?php

namespace App\Support;

use App\Models\JobOrder;
use Illuminate\Support\Collection;

/**
 * Per-sample control numbers printed on RFA Job Order forms.
 *
 * Rule: one sample → reference_no only; two or more → reference_no + A/B/C…Z then AA, AB, AC…
 */
class SampleControlNumber
{
    /**
     * @param  Collection<int, mixed>|null  $samples
     */
    public static function forIndex(
        string $referenceNo,
        int $indexZeroBased,
        int $sampleCount,
    ): ?string {
        $referenceNo = trim($referenceNo);

        if ($referenceNo === '' || $sampleCount < 1 || $indexZeroBased < 0 || $indexZeroBased >= $sampleCount) {
            return null;
        }

        if ($sampleCount === 1) {
            return $referenceNo;
        }

        return $referenceNo.self::letterSuffix($indexZeroBased);
    }

    /**
     * Excel-style column letters: 0→A … 25→Z, 26→AA, 27→AB, …
     */
    public static function letterSuffix(int $indexZeroBased): string
    {
        if ($indexZeroBased < 0) {
            return '';
        }

        $n = $indexZeroBased;
        $suffix = '';

        while ($n >= 0) {
            $suffix = chr(ord('A') + ($n % 26)).$suffix;
            $n = intdiv($n, 26) - 1;
        }

        return $suffix;
    }

    public static function forJobOrder(JobOrder $jobOrder, int $indexZeroBased): ?string
    {
        $jobOrder->loadMissing('samples');
        $count = $jobOrder->samples->count();

        return self::forIndex((string) $jobOrder->reference_no, $indexZeroBased, $count);
    }

    /**
     * @param  Collection<int, mixed>  $samples
     * @return list<string|null>
     */
    public static function listForReference(string $referenceNo, Collection $samples): array
    {
        $count = $samples->count();
        $out = [];

        for ($i = 0; $i < $count; $i++) {
            $out[] = self::forIndex($referenceNo, $i, $count);
        }

        return $out;
    }
}
