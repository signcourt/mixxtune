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

/*
| MIXXTUNE_INVITATION_REMINDERS_V1
| 10:05 India time every day: remind people who have not accepted their invitation.
*/
Schedule::command('invitations:send-reminders')
    ->dailyAt('04:35')
    ->timezone('UTC')
    ->withoutOverlapping(30);

/*
| W91 MIXXTUNE_PENDING_AUDIO_CLEANUP
| Every hour: delete background WAV uploads that no track used (older than 26 h).
*/
Schedule::command('tracks:clean-pending-audio')
    ->hourly()
    ->withoutOverlapping(30);

/* W95 MIXXTUNE_PANEL_MAIL: new mail of the domain mailboxes into the panel */
Schedule::command('mail:sync')
    ->everyTwoMinutes()
    ->withoutOverlapping(15)
    ->runInBackground();

/*
| W96 MIXXTUNE_STAFF_ALERTS (India time)
| 09:05 agreements to renew (e-mail at 90/60/30/15/7/3/1/0 days, weekly after the end)
| 09:10 did the backups run? (failed / missing / too old -> bell + e-mail to Super Admins)
*/
Schedule::command('agreements:remind')
    ->dailyAt('09:05')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);

Schedule::command('backups:check --quiet-ok')
    ->dailyAt('09:10')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);
