<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
| Calibration / maintenance reminder stubs for future LIMS modules.
| Enable concrete job classes as those modules land.
*/
Schedule::call(function () {
    // Placeholder: dispatch overdue calibration and maintenance reminder jobs.
})->dailyAt('07:00')->name('lims-reminder-stub')->withoutOverlapping();
