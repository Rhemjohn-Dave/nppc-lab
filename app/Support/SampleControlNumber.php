<?php

namespace App\Support;

use App\Models\JobOrder;
use Illuminate\Support\Collection;

/**
 * Per-sample control numbers printed on RFA Job Order forms.
 *
 * Rule: one sample → reference_no only; two or more → reference_no + A/B/C…
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

        if ($indexZeroBased > 25) {
            return $referenceNo.($indexZeroBased + 1);
        }

        return $referenceNo.chr(ord('A') + $indexZeroBased);
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
