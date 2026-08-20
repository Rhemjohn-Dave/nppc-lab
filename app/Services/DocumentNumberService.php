<?php

namespace App\Services;

use App\Models\DocumentNumberCounter;
use Illuminate\Support\Facades\DB;

class DocumentNumberService
{
    public function next(): string
    {
        $year = (int) now()->year;

        $number = DB::transaction(function () use ($year) {
            $counter = DocumentNumberCounter::query()
                ->where('year', $year)
                ->lockForUpdate()
                ->first();

            if (! $counter) {
                $counter = DocumentNumberCounter::query()->create([
                    'year' => $year,
                    'last_number' => 0,
                ]);
                $counter = DocumentNumberCounter::query()
                    ->where('year', $year)
                    ->lockForUpdate()
                    ->firstOrFail();
            }

            $counter->last_number++;
            $counter->save();

            return $counter->last_number;
        });

        return sprintf('DOC-%d-%06d', $year, $number);
    }
}
