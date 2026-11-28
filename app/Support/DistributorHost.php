<?php

namespace App\Support;

use App\Models\Distributor;
use App\Models\User;
use App\Services\V2\DistributorService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * W230 - a distributor's own address: <slug>.mixxtune.com (on staging: <slug>-stg.mixxtune.com).
 * The address only decides which brand is shown before login and who may log in there. It is the same
 * application, the same code and the same security; nothing of ours is handed to the distributor.
 */
final class DistributorHost
{
    public const BASE = 'mixxtune.com';

    /** names that are ours and can never be a distributor address */
    private const OURS = ['share', /* W238 */ 'www', 'mixxtune', 'admin', 'artist', 'label', 'home', 'staging', 'mail', 'api', 'app', 'panel', 'support', 'ftp', 'smtp', 'imap', 'pop', 'webmail', 'cpanel', 'ns1', 'ns2', 'autodiscover', 'autoconfig'];

    public static function suffix(): string
    {
        return (string) config('app.env') === 'production' ? '' : '-stg';
    }

    public static function hostOf(string $slug): string
    {
        return strtolower($slug).self::suffix().'.'.self::BASE;
    }

    /** the distributor slug a host name stands for in THIS installation, or null when it is not such a host */
    public static function slugFromHost(?string $host): ?string
    {
        $host = strtolower(trim((string) $host));
        $tail = '.'.self::BASE;
        if ($host === '' || !str_ends_with($host, $tail)) {
            return null;
        }
        $label = substr($host, 0, -strlen($tail));
        if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $label)) {
            return null; /* also refuses anything with a dot (deeper sub-domains) */
        }
        $suffix = self::suffix();
        if ($suffix !== '') {
            if (!str_ends_with($label, $suffix)) {
                return null;
            }
            $label = substr($label, 0, -strlen($suffix));
        } elseif (str_ends_with($label, '-stg')) {
            return null;
        }
        if (strlen($label) < 3 || strlen($label) > 40 || in_array($label, self::OURS, true)) {
            return null;
        }

        return $label;
    }

    /** host names of every active distributor (plain strings, remembered for a minute) */
    public static function activeHosts(): array
    {
        try {
            if (!DistributorService::ready()) {
                return [];
            }
            $list = Cache::remember('w230:hosts:'.self::suffix(), 60, function () {
                return DB::table('distributors')->where('status', 'active')->pluck('slug')
                    ->map(fn ($s) => self::hostOf((string) $s))
                    ->filter(fn ($h) => self::slugFromHost($h) !== null)
                    ->values()->all();
            });

            return is_array($list) ? array_values(array_filter($list, 'is_string')) : [];
        } catch (\Throwable $e) {
            return [];
        }
    }

    public static function forgetCache(): void
    {
        try {
            Cache::forget('w230:hosts:'.self::suffix());
        } catch (\Throwable $e) {
            // nothing to forget
        }
    }

    public static function resolve(?string $host): ?Distributor
    {
        $slug = self::slugFromHost($host);
        if ($slug === null || !DistributorService::ready()) {
            return null;
        }

        return Distributor::query()->where('slug', $slug)->where('status', 'active')->first();
    }

    /** the distributor whose address this request came in on (set by DistributorHostContext), or null */
    public static function current(): ?Distributor
    {
        try {
            return app()->bound('mixxtune.host_distributor') ? app('mixxtune.host_distributor') : null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Where links in a mail to this person point: his distributor's own address once its certificate is
     * ready (host_verified_at), otherwise null = the normal Mixx Tune address. Sub-labels and tree staff only.
     */
    public static function urlFor($user): ?string
    {
        try {
            if (!$user instanceof User || !DistributorService::ready()) {
                return null;
            }
            $svc = app(DistributorService::class);
            $d = $svc->forUser($user);
            if (!$d || (string) $d->status !== 'active' || !$d->host_verified_at) {
                return null;
            }

            return 'https://'.self::hostOf((string) $d->slug);
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }
}
