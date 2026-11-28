<?php

namespace App\Services\V2;

use App\Support\ReportDataStamp;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * W225 - the Analytics totals are the same until report data changes, so each one is remembered.
 * Key = method + the exact SQL and bindings of the (already scoped and filtered) query + the other
 * arguments + the "owner money" switch + the report data fingerprint. Another user, filter or role gives
 * another SQL, so nobody can ever get somebody else's numbers. Any problem -> the plain calculation.
 *
 * W226 - a sub-label under a distributor sees its OWN share: every earning figure is multiplied with its
 * share % after it is read (the remembered value itself is never changed).
 */
class CachedReportAnalyticsService extends ReportAnalyticsService
{
    private const TTL = 21600; /* W240: 6 hours (was 15 minutes - the page went cold four times an hour) */ /* seconds - a safety net; the fingerprint already drops stale entries */

    /* W226: figures that are money of the account */
    private const W226_MONEY = ['earnings', 'collected_revenue', 'current_earnings', 'previous_earnings', 'earnings_change'];

    /** @var array<int, ?float> */
    private static array $w226Factor = [];

    private function w226Factor(): ?float
    {
        try {
            $user = auth()->user();
            if (!$user || (string) $user->role !== 'label') {
                return null;
            }
            $uid = (int) $user->id;
            if (!array_key_exists($uid, self::$w226Factor)) {
                self::$w226Factor[$uid] = app(DistributorMoneyService::class)->factor($user);
            }

            return self::$w226Factor[$uid];
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    private function w226Scale($value, float $f)
    {
        if ($value instanceof Collection) {
            return $value->map(fn ($v) => $this->w226Scale($v, $f));
        }
        if (is_array($value)) {
            foreach ($value as $k => $v) {
                if (is_array($v) || $v instanceof Collection) {
                    $value[$k] = $this->w226Scale($v, $f);
                } elseif (is_string($k) && in_array($k, self::W226_MONEY, true) && is_numeric($v)) {
                    $value[$k] = round((float) $v * $f, 8);
                }
            }
            /* never a store gross amount for a sub-label: "collected" is its own earning */
            if (isset($value['earnings'], $value['collected_revenue']) && is_numeric($value['earnings']) && is_numeric($value['collected_revenue'])) {
                $value['collected_revenue'] = $value['earnings'];
            }
        }

        return $value;
    }

    private function remember(string $method, array $args, callable $compute)
    {
        $factor = $this->w226Factor();
        if ($factor !== null) {
            /* a sub-label never gets the store's gross amount: its "collected" figure is its own earning */
            $before = self::$ownerMoney;
            self::$ownerMoney = true;
            try {
                return $this->w226Scale($this->rememberPlain($method, $args, $compute), $factor);
            } finally {
                self::$ownerMoney = $before;
            }
        }

        return $this->rememberPlain($method, $args, $compute);
    }

    private function rememberPlain(string $method, array $args, callable $compute)
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
            $key = 'an:'.sha1(json_encode($parts, JSON_PARTIAL_OUTPUT_ON_ERROR));
        } catch (\Throwable $e) {
            report($e);

            return $compute();
        }

        /* W225b: kept as one serialized string and rebuilt exactly (arrays, objects, collections) */
        return ReportDataStamp::remember($key, self::TTL, $compute);
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
