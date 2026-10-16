<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


/*
|--------------------------------------------------------------------------
| MIXXTUNE_REPORT_IMPORT_PUBLISH_SCHEDULE
|--------------------------------------------------------------------------
|
| Approved revenue imports become visible and wallet-posted only
| when their publish_at timestamp becomes due.
|
*/

use Illuminate\Support\Facades\Schedule;

Schedule::command(
    'reports:publish-due-imports --limit=25'
)
    ->everyMinute()
    ->withoutOverlapping(10);
