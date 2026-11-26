<?php

namespace App\Services\V2;

use App\Support\ReportDataStamp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * W225 - the Analytics totals are the same until report data changes, so each one is remembered.
 * Key = method + the exact SQL and bindings of the (already scoped and filtered) query + the other
 * arguments + the "owner money" switch + the report data fingerprint. Another user, filter or role gives
 * another SQL, so nobody can ever get somebody else's numbers. Any problem -> the plain calculation.
 */
class CachedReportAnalyticsService extends ReportAnalyticsService
{
    private const TTL = 900; /* seconds - a safety net; the fingerprint already drops stale entries */

    private function remember(string $method, array $args, callable $compute)
    {
        try {
            $parts = [$method, self::$ownerMoney ? 1 : 0, ReportDataStamp::value()];
            foreach ($args as $arg) {
                if ($arg instanceof Builder || $arg instanceof \Illuminate\Database\Query\Builder) {
                    $parts[] = [$arg->toSql(), $arg->getBindings()];
                } elseif (is_scalar($arg) || $arg === null || is_array($arg)) {
                    $parts[] = $arg;
                } else {
                    return $compute(); /* something we cannot fingerprint safely */
                }
            }
            $key = 'w225:an:'.sha1(json_encode($parts, JSON_PARTIAL_OUTPUT_ON_ERROR));
        } catch (\Throwable $e) {
            report($e);

            return $compute();
        }

        try {
            return Cache::remember($key, self::TTL, $compute);
        } catch (\Throwable $e) {
            report($e);

            return $compute();
        }
    }

    public function summary(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::summary(...$a)); }

    public function monthlyTrend(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::monthlyTrend(...$a)); }

    public function topPlatforms(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topPlatforms(...$a)); }

    public function topCountries(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topCountries(...$a)); }

    public function topTracks(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topTracks(...$a)); }

    public function topArtists(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topArtists(...$a)); }

    public function topAlbums(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topAlbums(...$a)); }

    public function topLabels(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::topLabels(...$a)); }

    public function saleTypeSummary(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::saleTypeSummary(...$a)); }

    public function currencySummary(...$a): array { return $this->remember(__FUNCTION__, $a, fn () => parent::currencySummary(...$a)); }

    public function cmsSummary(...$a): Collection { return $this->remember(__FUNCTION__, $a, fn () => parent::cmsSummary(...$a)); }
}
