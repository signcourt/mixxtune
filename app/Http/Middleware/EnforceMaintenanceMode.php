<?php

namespace App\Http\Middleware;

use App\Services\V2\SystemSettingService;
use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnforceMaintenanceMode
{
    public function handle(
        Request $request,
        Closure $next
    ): Response {
        /*
         * This middleware intentionally does not block/render a
         * separate page. Maintenance state is shared with Inertia
         * so PanelLayout can preserve the existing Mixx Tune shell.
         */
        return $next($request);
    }

    public static function state(Request $request): array
    {
        $settings = app(SystemSettingService::class);

        $enabled = (bool) $settings->get(
            'maintenance.enabled',
            false
        );

        $date = $settings->get(
            'maintenance.date',
            ''
        );

        $startTime = $settings->get(
            'maintenance.start_time',
            ''
        );

        $endTime = $settings->get(
            'maintenance.end_time',
            ''
        );

        $message = $settings->get(
            'maintenance.message',
            'Mixx Tune is undergoing scheduled maintenance. Please check back shortly.'
        );

        $timezone = 'Asia/Kolkata';

        $now = Carbon::now($timezone);

        $scheduled = false;
        $active = false;
        $startsAt = null;
        $endsAt = null;

        if ($enabled && $date && $startTime && $endTime) {
            try {
                $startsAt = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    "{$date} {$startTime}",
                    $timezone
                );

                $endsAt = Carbon::createFromFormat(
                    'Y-m-d H:i',
                    "{$date} {$endTime}",
                    $timezone
                );

                /* MIXXTUNE_SETTINGS_WIRED_V1: a window like 23:00-02:00 ends the next day */
                if ($endsAt->lte($startsAt)) {
                    $endsAt->addDay();
                }

                $scheduled = $now->lt($startsAt);
                $active = $now->gte($startsAt)
                    && $now->lt($endsAt);
            } catch (\Throwable $e) {
                $scheduled = false;
                $active = false;
            }
        }

        /*
         * Super Admin always bypasses active maintenance.
         */
        $isSuperAdmin = $request->user()
            && ($request->user()->role ?? null) === 'super_admin';

        return [
            'enabled' => $enabled,
            'scheduled' => $scheduled,
            'active' => $active && !$isSuperAdmin,
            'is_super_admin' => $isSuperAdmin,

            'date' => $date,
            'start_time' => $startTime,
            'end_time' => $endTime,

            'message' => $message,
            'timezone' => $timezone,

            'starts_at' => $startsAt?->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
        ];
    }
}
