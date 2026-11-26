<?php

namespace App\Services\V2;

use App\Models\Distributor;
use App\Models\User;
use App\Support\ReportDataStamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * W226 - money inside a distributor's tree.
 *
 * Mixx Tune credits the distributor for every song of his tree (at the distributor's %). What a sub-label
 * gets is between the distributor and the sub-label:  sub-label share = distributor's earning on that
 * sub-label's songs x the sub-label's share %.  The % is frozen per report month the first time the month
 * is seen (and just before the % is changed), so an old month never moves when the % changes later.
 * Payments are the distributor's own register - no money moves inside Mixx Tune.
 */
class DistributorMoneyService
{
    private static ?bool $ready = null;

    /** @var array<int, ?array> */
    private static array $ctx = [];

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = DistributorService::ready()
                    && Schema::hasTable('distributor_sub_label_rates')
                    && Schema::hasTable('distributor_sub_label_payments');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    /**
     * Is this login a SUB-LABEL of a distributor (its owner or its staff)?
     * Returns ['distributor' => Distributor, 'label_id' => int, 'label_name' => string] or null.
     * The distributor himself and his own staff are never a sub-label.
     */
    public function subContext(?User $user): ?array
    {
        if (!$user || !DistributorService::ready() || (string) $user->role !== 'label') {
            return null;
        }
        $uid = (int) $user->id;
        if (array_key_exists($uid, self::$ctx)) {
            return self::$ctx[$uid];
        }

        $found = null;
        try {
            $svc = app(DistributorService::class);
            $d = $svc->forUser($user);
            if ($d && !$svc->isOwner($user, $d)) {
                $label = DB::table('labels')->where('user_id', $uid)->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->orderBy('id')->first(['id', 'name']);
                if (!$label) {
                    $teamLabel = DB::table('label_team_members')->where('user_id', $uid)->where('status', 'active')->value('label_id');
                    if ($teamLabel) {
                        $label = DB::table('labels')->where('id', $teamLabel)->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->first(['id', 'name']);
                    }
                }
                if ($label) {
                    $found = ['distributor' => $d, 'label_id' => (int) $label->id, 'label_name' => (string) $label->name];
                }
            }
        } catch (\Throwable $e) {
            report($e);
        }

        return self::$ctx[$uid] = $found;
    }

    public function currentPercent(int $rootLabelId, int $labelId): ?float
    {
        $v = DB::table('label_revenue_shares')
            ->where('master_label_id', $rootLabelId)->where('beneficiary_type', 'label')->where('beneficiary_id', $labelId)
            ->where('is_active', true)->orderByDesc('id')->value('revenue_share_percent');

        return $v === null ? null : max(0.0, min(100.0, (float) $v));
    }

    /**
     * What every earning figure of a sub-label login is multiplied with on Analytics / Dashboard
     * (so it sees its own share, never the distributor's amount). null = not a sub-label, show as is.
     */
    public function factor(?User $user): ?float
    {
        $c = $this->subContext($user);
        if (!$c) {
            return null;
        }
        $p = $this->currentPercent((int) $c['distributor']->label_id, (int) $c['label_id']);

        return $p === null ? 0.0 : round($p / 100, 6);
    }

    /** published report totals per sub-label per report month (the distributor's earning on those songs) */
    private function monthTotals(array $labelIds): array
    {
        if (!$labelIds) {
            return [];
        }
        sort($labelIds);
        $compute = function () use ($labelIds) {
            $rows = DB::table('report_rows')
                ->whereIn('label_id', $labelIds)
                ->whereExists(function ($q) {
                    $q->selectRaw('1')->from('report_imports as w226_imp')
                        ->whereColumn('w226_imp.id', 'report_rows.report_import_id')
                        ->where('w226_imp.financial_status', 'published');
                })
                ->selectRaw("label_id, LEFT(COALESCE(NULLIF(reporting_month,''), NULLIF(sale_month,''), '0000-00'), 7) as m, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(earnings),0) as earnings, COUNT(*) as n")
                ->groupBy('label_id', 'm')
                ->get();

            $out = [];
            foreach ($rows as $r) {
                $out[] = ['label_id' => (int) $r->label_id, 'month' => (string) $r->m, 'streams' => (float) $r->streams, 'earnings' => (float) $r->earnings, 'rows' => (int) $r->n];
            }

            return $out;
        };

        try {
            return ReportDataStamp::remember('w226:'.sha1(json_encode([$labelIds, ReportDataStamp::value()])), 900, $compute);
        } catch (\Throwable $e) {
            report($e);

            return $compute();
        }
    }

    /** freeze the share % of every report month of one sub-label that has no frozen % yet */
    public function freezeLabel(int $distributorId, int $rootLabelId, int $labelId, ?float $percent = null): void
    {
        if (!self::ready()) {
            return;
        }
        $percent ??= $this->currentPercent($rootLabelId, $labelId);
        if ($percent === null) {
            return;
        }
        $have = DB::table('distributor_sub_label_rates')->where('label_id', $labelId)->pluck('report_month')->flip();
        $now = now();
        foreach ($this->monthTotals([$labelId]) as $t) {
            if (!isset($have[$t['month']])) {
                DB::table('distributor_sub_label_rates')->insertOrIgnore([
                    'distributor_id' => $distributorId, 'label_id' => $labelId, 'report_month' => $t['month'],
                    'percent' => round($percent, 3), 'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * The whole money picture of a distributor's tree (or of one sub-label when $onlyLabelId is given).
     * Amounts are rounded to paise per month, so the totals are exactly the sum of the lines shown.
     */
    public function ledger(Distributor $d, ?int $onlyLabelId = null): array
    {
        $root = (int) $d->label_id;
        $labels = DB::table('labels')->where('parent_label_id', $root)->whereNull('deleted_at')
            ->when($onlyLabelId, fn ($q) => $q->where('id', $onlyLabelId))
            ->orderBy('name')->get(['id', 'name', 'status']);
        $ids = $labels->pluck('id')->map(fn ($v) => (int) $v)->all();
        if (!$ids) {
            return ['labels' => [], 'totals' => ['earning' => 0.0, 'share' => 0.0, 'paid' => 0.0, 'balance' => 0.0, 'kept' => 0.0]];
        }

        $current = DB::table('label_revenue_shares')->where('master_label_id', $root)->where('beneficiary_type', 'label')
            ->whereIn('beneficiary_id', $ids)->where('is_active', true)->orderBy('id')->pluck('revenue_share_percent', 'beneficiary_id');

        foreach ($ids as $id) {
            try {
                $this->freezeLabel((int) $d->id, $root, $id, isset($current[$id]) ? (float) $current[$id] : null);
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $rates = [];
        foreach (DB::table('distributor_sub_label_rates')->whereIn('label_id', $ids)->get(['label_id', 'report_month', 'percent']) as $r) {
            $rates[(int) $r->label_id][(string) $r->report_month] = (float) $r->percent;
        }
        $months = [];
        foreach ($this->monthTotals($ids) as $t) {
            $months[$t['label_id']][] = $t;
        }
        $payments = [];
        $users = [];
        $payRows = DB::table('distributor_sub_label_payments')->where('distributor_id', $d->id)->whereIn('label_id', $ids)->orderByDesc('paid_on')->orderByDesc('id')->get();
        foreach ($payRows as $p) {
            $payments[(int) $p->label_id][] = [
                'id' => (int) $p->id, 'amount' => (float) $p->amount, 'paid_on' => (string) $p->paid_on,
                'method' => $p->method, 'reference' => $p->reference, 'note' => $p->note,
                'cancelled' => $p->cancelled_at !== null, 'cancel_reason' => $p->cancel_reason,
            ];
        }

        $out = [];
        $tot = ['earning' => 0.0, 'share' => 0.0, 'paid' => 0.0];
        foreach ($labels as $l) {
            $id = (int) $l->id;
            $cur = isset($current[$id]) ? (float) $current[$id] : null;
            $lines = [];
            $earning = 0.0;
            $share = 0.0;
            $streams = 0.0;
            foreach ($months[$id] ?? [] as $t) {
                $pct = $rates[$id][$t['month']] ?? $cur;
                $e = round($t['earnings'], 2);
                $s = $pct === null ? 0.0 : round($t['earnings'] * $pct / 100, 2);
                $lines[] = ['month' => $t['month'], 'streams' => $t['streams'], 'earning' => $e, 'percent' => $pct, 'share' => $s];
                $earning += $e;
                $share += $s;
                $streams += $t['streams'];
            }
            usort($lines, fn ($a, $b) => strcmp($b['month'], $a['month']));
            $paid = 0.0;
            foreach ($payments[$id] ?? [] as $p) {
                if (!$p['cancelled']) {
                    $paid += $p['amount'];
                }
            }
            $out[] = [
                'id' => $id, 'name' => (string) $l->name, 'status' => (string) $l->status, 'percent' => $cur,
                'streams' => $streams, 'earning' => round($earning, 2), 'share' => round($share, 2),
                'paid' => round($paid, 2), 'balance' => round($share - $paid, 2),
                'months' => $lines, 'payments' => $payments[$id] ?? [],
            ];
            $tot['earning'] += $earning;
            $tot['share'] += $share;
            $tot['paid'] += $paid;
        }

        return ['labels' => $out, 'totals' => [
            'earning' => round($tot['earning'], 2), 'share' => round($tot['share'], 2), 'paid' => round($tot['paid'], 2),
            'balance' => round($tot['share'] - $tot['paid'], 2), 'kept' => round($tot['earning'] - $tot['share'], 2),
        ]];
    }

    /** songs of one sub-label for one report month, with the sub-label's share already applied */
    public function monthSongs(int $labelId, string $month, ?float $percent): array
    {
        $rows = DB::table('report_rows')
            ->where('label_id', $labelId)
            ->whereExists(function ($q) {
                $q->selectRaw('1')->from('report_imports as w226_imp')
                    ->whereColumn('w226_imp.id', 'report_rows.report_import_id')
                    ->where('w226_imp.financial_status', 'published');
            })
            ->whereRaw("LEFT(COALESCE(NULLIF(reporting_month,''), NULLIF(sale_month,''), '0000-00'), 7) = ?", [$month])
            ->selectRaw("COALESCE(NULLIF(track_title,''),'(untitled)') as title, COALESCE(isrc,'') as isrc, COALESCE(NULLIF(platform,''),'Other') as platform, COALESCE(SUM(streams),0) as streams, COALESCE(SUM(earnings),0) as earnings")
            ->groupBy('title', 'isrc', 'platform')
            ->orderByDesc('earnings')
            ->limit(5000)
            ->get();

        $f = $percent === null ? 0.0 : $percent / 100;

        return $rows->map(fn ($r) => [
            'title' => (string) $r->title, 'isrc' => (string) $r->isrc, 'platform' => (string) $r->platform,
            'streams' => (float) $r->streams, 'share' => round((float) $r->earnings * $f, 4),
        ])->all();
    }

    public static function forget(): void
    {
        self::$ctx = [];
    }
}
