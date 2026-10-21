<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;

/*
 * MIXXTUNE_INVOICES_V2 (W42)
 * One place that picks the next free invoice number "PREFIX-PERIOD-000001".
 * Before: statement invoices used (highest invoice id + 1) and payout
 * documents used (count of this year's invoices + 1) - numbers jumped and
 * two invoices made at the same moment could get the same number (unique
 * key error). Now: highest number already used this year for this prefix,
 * plus one, skipping any number that exists.
 */
class InvoiceNumber
{
    public static function next(string $period, ?string $prefix = null): string
    {
        $prefix = $prefix ?: PlatformRules::invoicePrefix();
        $head = $prefix.'-'.$period.'-';
        $year = substr($period, 0, 4);

        /*
         * One running number per year for every invoice of this prefix
         * (statement invoices PREFIX-YYYY-n and payout documents
         * PREFIX-YYYYMM-n share it, as before), so numbers never repeat.
         */
        $used = DB::table('invoices')
            ->where('invoice_number', 'like', str_replace(['%', '_'], ['\%', '\_'], $prefix.'-'.$year).'%')
            ->pluck('invoice_number');

        $pattern = '/^'.preg_quote($prefix.'-'.$year, '/').'\d{0,2}-(\d+)$/';
        $max = 0;
        foreach ($used as $number) {
            if (preg_match($pattern, (string) $number, $m)) {
                $max = max($max, (int) $m[1]);
            }
        }

        $n = $max + 1;
        while (DB::table('invoices')->where('invoice_number', sprintf('%s%06d', $head, $n))->exists()) {
            $n++;
        }

        return sprintf('%s%06d', $head, $n);
    }
}
