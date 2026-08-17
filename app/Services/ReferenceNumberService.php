<?php

namespace App\Services;

use App\Models\JobOrder;
use App\Models\ReferenceCounter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReferenceNumberService
{
    public function next(): string
    {
        return DB::transaction(function () {
            $year = now()->format('y');
            $counter = $this->lockedCounterForYear($year);

            do {
                $counter->last_number++;
                $reference = $this->format($year, $counter->last_number);
            } while (JobOrder::query()->where('reference_no', $reference)->exists());

            $counter->save();

            return $reference;
        });
    }

    /**
     * @return array{
     *     year: string,
     *     year_full: int,
     *     last_number: int,
     *     next_number: int,
     *     next_reference: string,
     *     min_next: int,
     *     highest_issued: int
     * }
     */
    public function status(?string $year = null): array
    {
        $year ??= now()->format('y');
        $counter = ReferenceCounter::query()->where('year', $year)->first();
        $lastNumber = (int) ($counter?->last_number ?? 0);
        $highestIssued = $this->highestIssuedNumber($year);
        $minNext = max(1, $highestIssued + 1);
        $nextNumber = max($lastNumber + 1, $minNext);

        return [
            'year' => $year,
            'year_full' => (int) ('20'.$year),
            'last_number' => $lastNumber,
            'next_number' => $nextNumber,
            'next_reference' => $this->format($year, $nextNumber),
            'min_next' => $minNext,
            'highest_issued' => $highestIssued,
        ];
    }

    public function setNextNumber(int $nextNumber, ?string $year = null): string
    {
        return DB::transaction(function () use ($nextNumber, $year) {
            $year ??= now()->format('y');
            $minNext = max(1, $this->highestIssuedNumber($year) + 1);

            if ($nextNumber < $minNext) {
                throw ValidationException::withMessages([
                    'next_number' => "Next control number must be at least {$minNext} for {$year}.",
                ]);
            }

            $reference = $this->format($year, $nextNumber);

            if (JobOrder::query()->where('reference_no', $reference)->exists()) {
                throw ValidationException::withMessages([
                    'next_number' => "Control number {$reference} is already in use.",
                ]);
            }

            $counter = $this->lockedCounterForYear($year);
            $counter->last_number = $nextNumber - 1;
            $counter->save();

            return $reference;
        });
    }

    private function lockedCounterForYear(string $year): ReferenceCounter
    {
        $counter = ReferenceCounter::query()
            ->where('year', $year)
            ->lockForUpdate()
            ->first();

        if (! $counter) {
            ReferenceCounter::create([
                'year' => $year,
                'last_number' => 0,
            ]);

            $counter = ReferenceCounter::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();
        }

        return $counter;
    }

    private function highestIssuedNumber(string $year): int
    {
        $prefix = $year.'-';

        return (int) (JobOrder::query()
            ->where('reference_no', 'like', $prefix.'%')
            ->pluck('reference_no')
            ->map(function (string $reference) use ($prefix) {
                $suffix = substr($reference, strlen($prefix));

                return ctype_digit($suffix) ? (int) $suffix : 0;
            })
            ->max() ?? 0);
    }

    private function format(string $year, int $number): string
    {
        return sprintf('%s-%04d', $year, $number);
    }
}
