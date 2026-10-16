<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RevenueRateSafetyAudit extends Command
{
    protected $signature =
        'reports:rate-safety {--month=}';

    protected $description =
        'Audit royalty rate safety without modifying financial data';

    public function handle(): int
    {
        $month = $this->option('month');

        $q = DB::table('report_rows as rr')
            ->leftJoin(
                'labels as l',
                'l.id',
                '=',
                'rr.label_id'
            );

        if ($month) {
            $q->where('rr.sale_month', $month);
        }

        $total = (clone $q)->count();

        $ownerMapped = (clone $q)
            ->whereNotNull('rr.track_id')
            ->whereNotNull('rr.label_id')
            ->count();

        $rateReady = (clone $q)
            ->whereNotNull('rr.track_id')
            ->whereNotNull('rr.label_id')
            ->whereNotNull(
                'l.revenue_share_percentage'
            )
            ->whereBetween(
                'l.revenue_share_percentage',
                [0, 100]
            )
            ->count();

        $rateHold = (clone $q)
            ->whereNotNull('rr.track_id')
            ->whereNotNull('rr.label_id')
            ->whereNull(
                'l.revenue_share_percentage'
            )
            ->count();

        $unmapped = (clone $q)
            ->where(function ($x) {
                $x->whereNull('rr.track_id')
                  ->orWhereNull('rr.label_id');
            })
            ->count();

        $rateReadyRevenue = (float)
            (clone $q)
            ->whereNotNull('rr.track_id')
            ->whereNotNull('rr.label_id')
            ->whereNotNull(
                'l.revenue_share_percentage'
            )
            ->whereBetween(
                'l.revenue_share_percentage',
                [0, 100]
            )
            ->sum('rr.collected_revenue');

        $rateHoldRevenue = (float)
            (clone $q)
            ->whereNotNull('rr.track_id')
            ->whereNotNull('rr.label_id')
            ->whereNull(
                'l.revenue_share_percentage'
            )
            ->sum('rr.collected_revenue');

        $this->line(
            'MONTH='.($month ?: 'ALL')
        );
        $this->line('TOTAL='.$total);
        $this->line(
            'OWNER_MAPPED='.$ownerMapped
        );
        $this->line(
            'RATE_READY='.$rateReady
        );
        $this->line(
            'RATE_HOLD='.$rateHold
        );
        $this->line(
            'UNMAPPED='.$unmapped
        );
        $this->line(
            'RATE_READY_REVENUE='.
            $rateReadyRevenue
        );
        $this->line(
            'RATE_HOLD_REVENUE='.
            $rateHoldRevenue
        );

        return self::SUCCESS;
    }
}
