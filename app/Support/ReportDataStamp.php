<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * W225 - one short fingerprint of the report data. It changes whenever a report is imported, published,
 * a row is edited / re-mapped or a month is moved, so every cached total keyed with it is thrown away
 * by itself. Reading it costs four small queries and is remembered for 20 seconds.
 */
class ReportDataStamp
{
    private static ?string $memo = null;

    public static function value(): string
    {
        if (self::$memo !== null) {
            return self::$memo;
        }

        try {
            return self::$memo = (string) Cache::remember('w225:report-stamp', 20, function () {
                $rows = DB::table('report_rows')->selectRaw('COUNT(*) c, COALESCE(MAX(id), 0) m, MAX(updated_at) u')->first();
                $imports = DB::table('report_imports')->selectRaw('COUNT(*) c, COALESCE(MAX(id), 0) m, MAX(updated_at) u')->first();

                /* owner % changes also move the profit / earning figures */
                $labels = DB::table('labels')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();
                $artists = DB::table('artists')->selectRaw('COUNT(*) c, MAX(updated_at) u')->first();

                return substr(sha1(json_encode([$rows, $imports, $labels, $artists])), 0, 16);
            });
        } catch (\Throwable $e) {
            report($e);

            return self::$memo = 'x'.time(); /* no caching rather than a wrong number */
        }
    }
}
