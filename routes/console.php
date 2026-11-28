<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

/*
| W230 MIXXTUNE_DISTRIBUTOR_HOST
| distributor:hosts                 -> prints the address of every active distributor, one per line
| distributor:hosts --ready=a,b     -> marks which of them have a working certificate (set by the server script)
*/
Artisan::command('distributor:hosts {--ready=__none__}', function () {
    if (!\App\Services\V2\DistributorService::ready()) {
        return 0;
    }
    $rows = \Illuminate\Support\Facades\DB::table('distributors')->where('status', 'active')->orderBy('id')->get(['id', 'slug', 'host', 'host_verified_at']);
    $ready = (string) $this->option('ready');
    if ($ready === '__none__') {
        foreach ($rows as $r) {
            $h = \App\Support\DistributorHost::hostOf((string) $r->slug);
            if (\App\Support\DistributorHost::slugFromHost($h) !== null) {
                $this->line($h);
            }
        }
        $this->line(\App\Services\V2\ReleaseShareService::shareHost()); /* W238 share-host-list */

        return 0;
    }
    $list = array_filter(array_map('trim', explode(',', strtolower($ready))));
    /* W238 share-host-ready: links go to share.mixxtune.com only once its certificate works */
    try {
        $shareReady = in_array(\App\Services\V2\ReleaseShareService::shareHost(), $list, true);
        $settings = app(\App\Services\V2\SystemSettingService::class);
        if ((bool) $settings->get('share.host_ready', false) !== $shareReady) {
            $settings->set('share.host_ready', $shareReady, 'share', 'boolean');
            $this->line('share address ready: '.($shareReady ? 'yes' : 'no'));
        }
    } catch (\Throwable $e) {
        report($e);
    }
    $n = 0;
    foreach ($rows as $r) {
        $h = \App\Support\DistributorHost::hostOf((string) $r->slug);
        $ok = in_array($h, $list, true);
        if ($ok && (!$r->host_verified_at || $r->host !== $h)) {
            \Illuminate\Support\Facades\DB::table('distributors')->where('id', $r->id)->update(['host' => $h, 'host_verified_at' => now(), 'updated_at' => now()]);
            $n++;
        } elseif (!$ok && $r->host_verified_at) {
            \Illuminate\Support\Facades\DB::table('distributors')->where('id', $r->id)->update(['host_verified_at' => null, 'updated_at' => now()]);
            $n++;
        }
    }
    \App\Support\DistributorHost::forgetCache();
    \App\Services\V2\DistributorService::forget();
    $this->line('changed: '.$n);

    return 0;
})->purpose('Addresses of active distributors (used by the server script that sets up their certificates)');

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

/* W115 MIXXTUNE_RAZORPAY_PAYOUTS: follow bank transfers that Razorpay has not finished (also if a webhook was missed) */
Schedule::command('payouts:sync')
    ->everyFiveMinutes()
    ->withoutOverlapping(15);

/* W117 MIXXTUNE_MAIL_SCHEDULE_SEND: send the scheduled mails whose time has come */
Schedule::command('mail:send-scheduled')
    ->everyMinute()
    ->withoutOverlapping(10);

/* W120 MIXXTUNE_RELEASE_TRASH: releases in the Trash for 90 days are removed for good */
Schedule::command('releases:purge-trash')
    ->dailyAt('03:30')
    ->withoutOverlapping(60);

/* W127 MIXXTUNE_OFFICE: task reminders (due soon / overdue) */
Schedule::command('office:reminders')
    ->everyFifteenMinutes()
    ->withoutOverlapping(10);

/* W128 MIXXTUNE_OFFICE_MONITOR: idle alerts (uses the panel's "idle minutes" setting) */
Schedule::command('office:idle-check')
    ->everyFiveMinutes()
    ->withoutOverlapping(10);


/* W239 MIXXTUNE_LIVE_LINKS_AGENT: every hour - find the store links of releases whose release date has come */
Artisan::command('promo:agent {--limit=8} {--older=25}', function () {
    if (! \App\Services\V2\ReleaseLinkAgent::ready()) {
        $this->line('not set up');

        return 0;
    }
    $agent = app(\App\Services\V2\ReleaseLinkAgent::class);
    if (! $agent->enabled()) {
        $this->line('switched off');

        return 0;
    }
    $n = 0;
    foreach ($agent->due(max(1, min(20, (int) $this->option('limit')))) as $release) {
        try {
            $this->line('#'.$release->id.' '.$release->title.': '.$agent->check($release));
        } catch (\Throwable $e) {
            report($e);
            $this->line('#'.$release->id.' failed: '.$e->getMessage());
        }
        $n++;
        sleep(2);
    }
    $this->line('looked at: '.$n);

    /* W245 MIXXTUNE_AGENT_OLDER_SONGS: then a batch of older live songs - nobody is told about those */
    if ($agent->backfill()) {
        $m = 0;
        foreach ($agent->backlog(max(1, min(60, (int) $this->option('older')))) as $release) {
            try {
                $this->line('older #'.$release->id.' '.$release->title.': '.$agent->check($release, false, true));
            } catch (\Throwable $e) {
                report($e);
                $this->line('older #'.$release->id.' failed: '.$e->getMessage());
            }
            $m++;
            sleep(3);
        }
        $this->line('older songs looked at: '.$m);
    }

    return 0;
})->purpose('Live Links Agent: store links for releases that just went live');

Schedule::command('promo:agent')
    ->hourlyAt(17)
    ->withoutOverlapping(30);


/* W240 MIXXTUNE_WARM: open the Super Admin dashboard and Analytics from the inside, so their totals are ready before a person asks */
Artisan::command('perf:warm', function () {
    $user = \App\Models\User::query()->where('role', 'super_admin')->where('account_status', 'active')->orderBy('id')->first();
    if (! $user) {
        $this->line('no super admin');

        return 0;
    }
    config(['session.driver' => 'array']);
    $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'localhost';
    foreach (['/super-admin/dashboard', '/analytics'] as $path) {
        try {
            auth()->guard('web')->setUser($user);
            $t = microtime(true);
            $request = \Illuminate\Http\Request::create('https://'.$host.$path, 'GET');
            $response = app(\Illuminate\Contracts\Http\Kernel::class)->handle($request);
            $this->line($path.' '.$response->getStatusCode().' '.round((microtime(true) - $t) * 1000).'ms');
        } catch (\Throwable $e) {
            report($e);
            $this->line($path.' failed: '.$e->getMessage());
        }
    }

    return 0;
})->purpose('Warm the heavy dashboard / analytics totals');

Schedule::command('perf:warm')
    ->everyTenMinutes()
    ->withoutOverlapping(8);
