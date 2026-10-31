<?php

namespace App\Services\V2\Office;

use App\Models\Office\ActivityPing;
use App\Models\Office\Attendance;
use App\Models\Office\OfficeDevice;
use App\Models\Office\Screenshot;
use App\Models\User;
use App\Services\V2\FinanceAudit;
use App\Services\V2\StaffAlerts;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/*
 * W128 MIXXTUNE_OFFICE_MONITOR
 * Pairing the desktop agent, taking its heartbeats and screenshots, and the idle alert.
 * A heartbeat adds computer active / idle time to the day; "active" = the keyboard/mouse moved
 * in the last minute. Nothing here reads keystrokes, the screen content, camera or mic.
 */
class OfficeMonitor
{
    public const HEARTBEAT_CAP = 90;

    public const TZ = 'Asia/Kolkata';

    /** staff asks to connect this computer: a short code the agent will use once */
    public function makePairCode(User $user, ?string $deviceName = null, ?string $platform = null): array
    {
        $code = strtoupper(Str::random(3).'-'.Str::random(3));
        $device = OfficeDevice::query()->create([
            'public_id' => (string) Str::ulid(),
            'user_id' => $user->id,
            'platform' => in_array($platform, ['windows', 'mac'], true) ? $platform : null,
            'device_name' => $deviceName ? mb_substr($deviceName, 0, 190) : null,
            'pair_code' => $code,
            'pair_expires_at' => now()->addMinutes(15),
            'approved' => false,
        ]);

        return ['code' => $code, 'expires_in' => 900, 'device_id' => $device->id];
    }

    /** the agent sends the code: it gets a long token (shown once) + the current settings */
    public function pair(string $code, array $info): array
    {
        $device = OfficeDevice::query()->whereRaw('UPPER(pair_code) = ?', [strtoupper(trim($code))])
            ->where('pair_expires_at', '>', now())->whereNull('token_hash')->first();
        if (!$device) {
            throw ValidationException::withMessages(['code' => 'This code is wrong or has expired. Make a new one in My Office.']);
        }
        $token = Str::random(48);
        $device->forceFill([
            'token_hash' => hash('sha256', $token),
            'pair_code' => null,
            'pair_expires_at' => null,
            'approved' => true,
            'platform' => in_array($info['platform'] ?? null, ['windows', 'mac'], true) ? $info['platform'] : $device->platform,
            'device_name' => isset($info['device_name']) ? mb_substr((string) $info['device_name'], 0, 190) : $device->device_name,
            'app_version' => isset($info['app_version']) ? mb_substr((string) $info['app_version'], 0, 30) : null,
            'rustdesk_id' => isset($info['rustdesk_id']) ? mb_substr((string) $info['rustdesk_id'], 0, 40) : null,
            'last_seen_at' => now(),
        ])->save();
        FinanceAudit::log('office.device_paired', 'Office computer paired for '.optional($device->user)->name.' ('.$device->platform.').', ['device_id' => $device->id]);

        return ['token' => $token, 'settings' => OfficeSettings::all()];
    }

    /** one heartbeat from the agent (about once a minute) */
    public function heartbeat(OfficeDevice $device, array $data): array
    {
        $idle = max(0, (int) ($data['idle_seconds'] ?? 0));
        $active = $idle < 60;
        $app = isset($data['foreground_app']) ? mb_substr((string) $data['foreground_app'], 0, 190) : null;

        $device->forceFill([
            'last_seen_at' => now(),
            'last_active_at' => $active ? now() : $device->last_active_at,
            'idle_seconds' => $idle,
            'foreground_app' => $app,
            'rustdesk_id' => isset($data['rustdesk_id']) ? mb_substr((string) $data['rustdesk_id'], 0, 40) : $device->rustdesk_id,
            'app_version' => isset($data['app_version']) ? mb_substr((string) $data['app_version'], 0, 30) : $device->app_version,
        ])->save();

        $date = Carbon::now(self::TZ)->toDateString();
        ActivityPing::query()->create([
            'device_id' => $device->id, 'user_id' => $device->user_id, 'work_date' => $date,
            'pinged_at' => now(), 'idle_seconds' => $idle, 'active' => $active, 'foreground_app' => $app,
        ]);

        /* add to the day only while the staff member is clocked in */
        $att = Attendance::query()->where('user_id', $device->user_id)->whereDate('work_date', $date)->first();
        $clockedIn = $att && $att->clock_in_at && !$att->clock_out_at;
        if ($clockedIn && !$att->on_break) {
            $add = self::HEARTBEAT_CAP;
            if ($att->getAttribute('comp_active_seconds') === null) {
                $att->comp_active_seconds = 0;
            }
            if ($active) {
                $att->comp_active_seconds += $add;
            } else {
                $att->comp_idle_seconds += $add;
            }
            $att->save();
        }

        return ['settings' => OfficeSettings::all(), 'clocked_in' => (bool) $clockedIn];
    }

    public function storeScreenshot(OfficeDevice $device, UploadedFile $file): ?Screenshot
    {
        if (!OfficeSettings::monitoringEnabled()) {
            return null;
        }
        $date = Carbon::now(self::TZ)->toDateString();
        $path = $file->store('office/screenshots/'.$device->user_id.'/'.$date, 'local');

        return Screenshot::query()->create([
            'device_id' => $device->id, 'user_id' => $device->user_id, 'work_date' => $date,
            'path' => $path, 'foreground_app' => $device->foreground_app, 'taken_at' => now(), 'created_at' => now(),
        ]);
    }

    /** the scheduled idle check: a clocked-in staff member with no activity for the set minutes -> tell the Super Admins (once) */
    public function idleCheck(): int
    {
        if (!OfficeSettings::monitoringEnabled()) {
            return 0;
        }
        $mins = OfficeSettings::idleMinutes();
        $date = Carbon::now(self::TZ)->toDateString();
        $supers = app(StaffAlerts::class)->superAdmins();
        $n = 0;
        $rows = Attendance::query()->whereDate('work_date', $date)
            ->whereNotNull('clock_in_at')->whereNull('clock_out_at')->where('on_break', false)->get();
        foreach ($rows as $att) {
            $device = OfficeDevice::query()->where('user_id', $att->user_id)->where('approved', true)->orderByDesc('last_seen_at')->first();
            if (!$device || !$device->last_active_at) {
                continue; /* no agent / never active: that is "Unknown", not idle */
            }
            if ($device->last_active_at->gt(now()->subMinutes($mins))) {
                continue; /* active recently */
            }
            /* already alerted for this idle stretch? */
            if ($att->getAttribute('idle_alerted_at') && $att->idle_alerted_at->gt($device->last_active_at)) {
                continue;
            }
            $user = User::query()->find($att->user_id);
            $since = $device->last_active_at->timezone(self::TZ)->format('h:i A');
            try {
                app(StaffAlerts::class)->bell($supers, 'office.idle', $user?->name.' looks idle', 'No keyboard/mouse since '.$since.' ('.$mins.'+ min). Last app: '.($device->foreground_app ?: '—').'.', '/office/admin/activity', 'warning');
            } catch (\Throwable) {
            }
            $att->forceFill(['idle_alerted_at' => now()])->save();
            $n++;
        }

        return $n;
    }
}
