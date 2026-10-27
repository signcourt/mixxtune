<?php

namespace App\Services\V2;

use Carbon\Carbon;
use Throwable;

/*
 * MIXXTUNE_SETTINGS_WIRED_V1
 *
 * The Master Settings values that the app really enforces, in one place.
 * Fallbacks equal the limits that were hard-coded before, so nothing
 * changes until a Super Admin saves a different value.
 */
class PlatformRules
{
    private static function setting(string $key, mixed $default): mixed
    {
        try {
            return app(SystemSettingService::class)->get($key, $default);
        } catch (Throwable $exception) {
            return $default;
        }
    }

    private static function int(string $key, int $default, int $min, int $max): int
    {
        $value = self::setting($key, $default);

        if (!is_numeric($value)) {
            return $default;
        }

        return max($min, min($max, (int) $value));
    }

    public static function audioMaxMb(): int
    {
        return self::int('upload.audio_max_mb', 300, 1, 2048);
    }

    public static function artworkMaxMb(): int
    {
        return self::int('upload.artwork_max_mb', 20, 1, 100);
    }

    public static function reportMaxMb(): int
    {
        return self::int('upload.report_max_mb', 500, 1, 2048);
    }

    /*
     * W118 MIXXTUNE_RELEASE_DATE_RULE (owner, 27 Oct 2026): a label or artist may pick today or any
     * later day - no minimum number of days any more (the old "Minimum days before release" setting
     * is no longer used). Super Admin / Manager may pick any date, also a past one.
     */
    public static function releaseMinDays(): int
    {
        return 0;
    }

    /* today in India (the panel's day), as the earliest date a label / artist may pick */
    public static function earliestReleaseDate(): Carbon
    {
        return Carbon::now('Asia/Kolkata')->startOfDay();
    }

    public static function staffDates(?\App\Models\User $user): bool
    {
        if (!$user) {
            return false;
        }
        try {
            return in_array(app(PermissionService::class)->role($user), ['super_admin', 'admin'], true);
        } catch (Throwable) {
            return in_array((string) $user->role, ['super_admin', 'admin'], true);
        }
    }

    public static function requireArtwork(): bool
    {
        return (bool) self::setting('release.require_artwork', true);
    }

    public static function allowExplicit(): bool
    {
        return (bool) self::setting('release.allow_explicit', true);
    }

    public static function invoicePrefix(): string
    {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) self::setting('invoice.prefix', 'INV')));

        return $prefix !== '' ? substr($prefix, 0, 12) : 'INV';
    }

    public static function releaseDateMessage(): string
    {
        $days = self::releaseMinDays();

        return $days === 0
            ? 'Release date cannot be in the past.'
            : 'Release date must be on or after '.self::earliestReleaseDate()->format('d M Y')
                .' (at least '.$days.' day'.($days === 1 ? '' : 's').' from today).';
    }

    public static function shared(): array
    {
        return [
            'audio_max_mb' => self::audioMaxMb(),
            'artwork_max_mb' => self::artworkMaxMb(),
            'report_max_mb' => self::reportMaxMb(),
            'release_min_days' => self::releaseMinDays(),
            'earliest_release_date' => self::earliestReleaseDate()->toDateString(),
            'allow_explicit' => self::allowExplicit(),
        ];
    }
}
