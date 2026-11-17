<?php

namespace App\Support;

use Illuminate\Support\Carbon;

class WorkingDays
{
    /**
     * Add N calendar days (includes weekends).
     */
    public static function addCalendarDays(Carbon $from, int $days): Carbon
    {
        return $from->copy()->startOfDay()->addDays($days);
    }

    /**
     * Add N working days (Monday–Friday). Weekends are skipped; holidays are not.
     */
    public static function addWorkingDays(Carbon $from, int $days): Carbon
    {
        $cursor = $from->copy()->startOfDay();
        $remaining = max(0, $days);

        while ($remaining > 0) {
            $cursor->addDay();
            if ($cursor->isWeekday()) {
                $remaining--;
            }
        }

        return $cursor;
    }
}
