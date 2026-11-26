<?php

namespace App\Support;

/**
 * W201 MIXXTUNE_TRUSTED_HOSTS
 *
 * The app used to accept ANY Host header for building links (password reset, invitation,
 * redirects). A forged Host could put an attacker's address into our own e-mails.
 * This is the allow-list: only these hosts (and the APP_URL host) may reach the app;
 * any other Host answers 400 before a page is built.
 *
 * When the distributor white-label portals arrive, their registered hosts will be added
 * here from the database (Wave B). Until then the list is fixed.
 */
final class TrustedHosts
{
    /** @var string[] plain host names (no scheme, no port) */
    private const FIXED = [
        'mixxtune.com',
        'www.mixxtune.com',
        'staging.mixxtune.com',
        'artist.mixxtune.com',
        'label.mixxtune.com',
        'home.mixxtune.com',
        'admin.mixxtune.com',
        'localhost',
        '127.0.0.1',
    ];

    /** Regex patterns as Symfony's TrustedHosts expects them. */
    public static function patterns(): array
    {
        $hosts = self::FIXED;

        $appHost = parse_url((string) config('app.url'), PHP_URL_HOST);
        if (is_string($appHost) && $appHost !== '') {
            $hosts[] = strtolower($appHost);
        }

        $extra = (string) env('TRUSTED_HOSTS', '');
        foreach (explode(',', $extra) as $h) {
            $h = strtolower(trim($h));
            if ($h !== '') {
                $hosts[] = $h;
            }
        }

        /* W230: <slug>.mixxtune.com of every active distributor */
        foreach (DistributorHost::activeHosts() as $h) {
            $hosts[] = $h;
        }

        $hosts = array_values(array_unique($hosts));

        return array_map(
            fn (string $h) => '^'.preg_quote($h, '/').'$',
            $hosts
        );
    }
}
