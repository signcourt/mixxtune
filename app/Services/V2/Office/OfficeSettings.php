<?php

namespace App\Services\V2\Office;

use App\Services\V2\SystemSettingService;
use Throwable;

/*
 * W128 MIXXTUNE_OFFICE_MONITOR: the office monitoring settings the Super Admin sets in the panel.
 * Stored in system_settings under office.* with safe defaults.
 */
class OfficeSettings
{
    public const KEYS = [
        'monitoring_enabled' => false,
        'screenshot_minutes' => 10,
        'idle_minutes' => 15,
        'work_start' => '09:30',
        'work_end' => '19:00',
        'rustdesk_host' => '',
    ];

    private static function get(string $key, mixed $default): mixed
    {
        try {
            return app(SystemSettingService::class)->get('office.'.$key, $default);
        } catch (Throwable) {
            return $default;
        }
    }

    public static function monitoringEnabled(): bool
    {
        return (bool) self::get('monitoring_enabled', self::KEYS['monitoring_enabled']);
    }

    public static function screenshotMinutes(): int
    {
        return max(1, min(120, (int) self::get('screenshot_minutes', self::KEYS['screenshot_minutes'])));
    }

    public static function idleMinutes(): int
    {
        return max(1, min(180, (int) self::get('idle_minutes', self::KEYS['idle_minutes'])));
    }

    public static function rustdeskHost(): string
    {
        return (string) self::get('rustdesk_host', '');
    }

    /** everything, for the panel and the agent */
    public static function all(): array
    {
        return [
            'monitoring_enabled' => self::monitoringEnabled(),
            'screenshot_minutes' => self::screenshotMinutes(),
            'idle_minutes' => self::idleMinutes(),
            'work_start' => (string) self::get('work_start', self::KEYS['work_start']),
            'work_end' => (string) self::get('work_end', self::KEYS['work_end']),
            'rustdesk_host' => self::rustdeskHost(),
        ];
    }
}
