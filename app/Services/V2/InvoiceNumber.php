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
    /*
     * MIXXTUNE_LABEL_INVOICE_PREFIX_V1 (W180)
     * Each label raises its own invoice series, so the number carries a prefix
     * made from the label's name: "Sanatan Records" -> "SR", "Zee Music" ->
     * "ZM". Single-word names take their first two letters ("Believe" -> "BE").
     * Anything non-alphanumeric is dropped; the result is 2-12 upper-case
     * letters/digits, falling back to the global prefix when a name gives
     * nothing usable. The running serial is still per-prefix (see next()), so
     * every label gets its own independent sequence.
     */
    public static function labelPrefix(?string $labelName, ?string $fallback = null): string
    {
        $words = preg_split('/\s+/', trim((string) $labelName)) ?: [];
        $initials = '';
        foreach ($words as $w) {
            $w = preg_replace('/[^A-Za-z0-9]/', '', $w);
            if ($w !== '') {
                $initials .= strtoupper($w[0]);
            }
        }

        if (strlen($initials) < 2) {
            // single usable word (or none): take its first two letters
            $flat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $labelName));
            $initials = substr($flat, 0, 2);
        }

        $initials = substr($initials, 0, 12);

        if (strlen($initials) < 2) {
            return $fallback ?: PlatformRules::invoicePrefix();
        }

        return $initials;
    }

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
