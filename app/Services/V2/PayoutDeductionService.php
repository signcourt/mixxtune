<?php

namespace App\Services\V2;

use App\Models\Finance\PayoutProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/*
 * MIXXTUNE_TDS_V1
 * One place that decides GST and TDS for a payout, so the Mark Paid
 * document, the admin screen and the bank payout file always agree.
 *
 * Settings (Master Settings -> Finance & Invoice), all default 0 = off:
 *   invoice.tds_percent         normal TDS rate on the payout amount
 *   invoice.tds_no_pan_percent  rate used when the payee has no PAN (0 = use normal rate)
 *   invoice.tds_threshold       yearly limit (Indian financial year, April-March) per payee;
 *                               TDS starts on the payout that takes the year's total above it
 *                               (0 = deduct on every payout)
 * The rates and the limit are decided by the company's CA; the code only applies them.
 */
class PayoutDeductionService
{
    public function __construct(
        private readonly WithdrawalTaxDocumentPolicyService $taxPolicy
    ) {
    }

    public static function financialYear(?Carbon $date = null): array
    {
        $date = ($date ?? now())->copy();
        $startYear = $date->month >= 4 ? $date->year : $date->year - 1;

        return [
            'label' => $startYear.'-'.substr((string) ($startYear + 1), -2),
            'start' => Carbon::create($startYear, 4, 1)->startOfDay(),
            'end' => Carbon::create($startYear + 1, 3, 31)->endOfDay(),
        ];
    }

    public function settings(): array
    {
        $rows = DB::table('system_settings')
            ->whereIn('key', ['invoice.tds_percent', 'invoice.tds_no_pan_percent', 'invoice.tds_threshold'])
            ->pluck('value', 'key');

        $clamp = fn ($value) => max(0.0, min(100.0, (float) $value));

        return [
            'rate' => $clamp($rows['invoice.tds_percent'] ?? 0),
            'no_pan_rate' => $clamp($rows['invoice.tds_no_pan_percent'] ?? 0),
            'threshold' => max(0.0, (float) ($rows['invoice.tds_threshold'] ?? 0)),
        ];
    }

    /**
     * Amount already paid to this payee in the financial year (from payout documents).
     */
    public function paidThisYear(int $userId, ?int $exceptWithdrawalId = null, ?Carbon $date = null): float
    {
        $fy = self::financialYear($date);

        $query = DB::table('invoices')
            ->where('user_id', $userId)
            ->whereBetween('invoice_date', [$fy['start']->toDateString(), $fy['end']->toDateString()]);

        if ($exceptWithdrawalId) {
            $query->where(function ($inner) use ($exceptWithdrawalId) {
                $inner->whereNull('withdrawal_id')->orWhere('withdrawal_id', '!=', $exceptWithdrawalId);
            });
        }

        return round((float) $query->sum('subtotal'), 2);
    }

    public function preview(User $payee, float $gross, ?int $withdrawalId = null, ?PayoutProfile $profile = null): array
    {
        $profile ??= PayoutProfile::query()->where('user_id', $payee->id)->first();
        $policy = $this->taxPolicy->policyFor($payee, $profile);
        $settings = $this->settings();
        $fy = self::financialYear();

        $gross = round($gross, 2);
        $hasPan = $profile && filled($profile->pan_number);

        $gstPercent = $policy['gst_registered']
            ? max(0.0, min(100.0, (float) $policy['configured_gst_percent']))
            : 0.0;
        $gstAmount = round($gross * $gstPercent / 100, 2);

        $paidBefore = $this->paidThisYear((int) $payee->id, $withdrawalId);
        $rate = (!$hasPan && $settings['no_pan_rate'] > 0) ? $settings['no_pan_rate'] : $settings['rate'];

        if ($rate <= 0) {
            $reason = 'tds_off';
            $rate = 0.0;
        } elseif ($settings['threshold'] > 0 && ($paidBefore + $gross) <= $settings['threshold']) {
            $reason = 'below_threshold';
            $rate = 0.0;
        } else {
            $reason = (!$hasPan && $settings['no_pan_rate'] > 0) ? 'no_pan_rate' : 'normal_rate';
        }

        $tdsAmount = round($gross * $rate / 100, 2);
        $total = round($gross + $gstAmount, 2);

        return [
            'gross' => $gross,
            'gst_percent' => $gstPercent,
            'gst_amount' => $gstAmount,
            'tds_percent' => $rate,
            'tds_amount' => $tdsAmount,
            'total' => $total,
            'net' => round($total - $tdsAmount, 2),
            'tds_reason' => $reason,
            'has_pan' => $hasPan,
            'pan' => $hasPan ? strtoupper(trim((string) $profile->pan_number)) : null,
            'financial_year' => $fy['label'],
            'paid_this_year_before' => $paidBefore,
            'threshold' => $settings['threshold'],
        ];
    }

    public static function reasonText(string $reason): string
    {
        return match ($reason) {
            'below_threshold' => 'No TDS: yearly total is within the TDS limit',
            'no_pan_rate' => 'Higher TDS: payee has no PAN',
            'normal_rate' => 'TDS at normal rate',
            default => 'TDS is switched off (rate 0)',
        };
    }
}
