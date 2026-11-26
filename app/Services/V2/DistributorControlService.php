<?php

namespace App\Services\V2;

use App\Models\Distributor;
use App\Models\Distribution\Release;
use App\Models\User;
use App\Support\ReportDataStamp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * W228 - what a Super Admin can set for one distributor: hold the whole tree (nobody of the tree can use the
 * panel), how many sub-labels and how many releases a month the tree may send, the agreement file, and the
 * figures of the tree (collected, the distributor's earning, Mixx Tune's margin).
 */
class DistributorControlService
{
    private static ?bool $ready = null;

    /** @var array<int, ?object> */
    private static array $memo = [];

    public static function ready(): bool
    {
        if (self::$ready === null) {
            try {
                self::$ready = DistributorService::ready() && Schema::hasTable('distributor_controls');
            } catch (\Throwable $e) {
                self::$ready = false;
            }
        }

        return self::$ready;
    }

    public function row(int $distributorId): ?object
    {
        if (!self::ready()) {
            return null;
        }
        if (!array_key_exists($distributorId, self::$memo)) {
            self::$memo[$distributorId] = DB::table('distributor_controls')->where('distributor_id', $distributorId)->first();
        }

        return self::$memo[$distributorId];
    }

    public static function forget(): void
    {
        self::$memo = [];
    }

    /** the hold reason when this login belongs to a tree that is on hold, otherwise null */
    public function heldFor(?User $user): ?string
    {
        try {
            if (!$user || (string) $user->role !== 'label' || !self::ready()) {
                return null;
            }
            $d = app(DistributorService::class)->forUser($user);
            $c = $d ? $this->row((int) $d->id) : null;

            return $c && $c->tree_hold ? (trim((string) $c->hold_reason) ?: 'on hold') : null;
        } catch (\Throwable $e) {
            report($e);

            return null;
        }
    }

    public function assertCanAddSubLabel(Distributor $d): void
    {
        $c = $this->row((int) $d->id);
        if (!$c || $c->max_sub_labels === null) {
            return;
        }
        $have = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->count();
        if ($have >= (int) $c->max_sub_labels) {
            throw ValidationException::withMessages(['label_name' => 'Your account can have '.(int) $c->max_sub_labels.' sub-label'.((int) $c->max_sub_labels === 1 ? '' : 's').' and you already have '.$have.'. Write to Mixx Tune support to raise the limit.']);
        }
    }

    /** a release of a distributor's tree is being sent for review: is the tree's monthly limit used up? */
    public function assertCanSubmit(Release $release, User $user): void
    {
        try {
            if (!self::ready() || !$release->label_id || in_array((string) $user->role, ['admin', 'super_admin'], true)) {
                return;
            }
            $label = DB::table('labels')->where('id', $release->label_id)->first(['id', 'parent_label_id']);
            if (!$label) {
                return;
            }
            $root = (int) ($label->parent_label_id ?: $label->id);
            $d = Distributor::query()->where('label_id', $root)->first();
            $c = $d ? $this->row((int) $d->id) : null;
            if (!$c || $c->max_monthly_releases === null) {
                return;
            }
            $tree = DB::table('labels')->where('parent_label_id', $root)->whereNull('deleted_at')->pluck('id')->push($root)->all();
            $sent = DB::table('releases')->whereIn('label_id', $tree)->whereNull('deleted_at')->where('id', '!=', $release->id)
                ->where('submitted_at', '>=', now()->startOfMonth())->count();
        } catch (\Throwable $e) {
            report($e);

            return; /* a problem in the limit check never blocks a submission */
        }

        if ($sent >= (int) $c->max_monthly_releases) {
            throw ValidationException::withMessages(['release' => 'The monthly limit of '.(int) $c->max_monthly_releases.' release'.((int) $c->max_monthly_releases === 1 ? '' : 's').' for '.(trim((string) $d->brand_name) ?: 'this distributor account').' is used up ('.$sent.' sent this month). It opens again on the 1st, or ask Mixx Tune support to raise it.']);
        }
    }

    /** a Manager of the distributor's master label is a Manager of every sub-label too (rows are only added) */
    public function syncManagers(Distributor $d, ?int $assignedBy = null): int
    {
        $managers = DB::table('admin_label_assignments')->where('label_id', $d->label_id)->where('assignment_role', 'manager')->get();
        if ($managers->isEmpty()) {
            return 0;
        }
        $children = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->pluck('id');
        $added = 0;
        foreach ($managers as $m) {
            $have = DB::table('admin_label_assignments')->where('user_id', $m->user_id)->whereIn('label_id', $children->all() ?: [0])->pluck('label_id')->flip();
            foreach ($children as $cid) {
                if (!isset($have[$cid])) {
                    $added += DB::table('admin_label_assignments')->insertOrIgnore([
                        'user_id' => $m->user_id, 'label_id' => $cid, 'assignment_role' => 'manager',
                        'can_view' => $m->can_view, 'can_edit' => $m->can_edit, 'can_manage_releases' => $m->can_manage_releases,
                        'can_manage_team' => $m->can_manage_team, 'can_manage_splits' => $m->can_manage_splits,
                        'assigned_by' => $assignedBy ?: $m->assigned_by, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }

        return $added;
    }

    /** published report totals of the whole tree, keyed by the distributor's master label id */
    public function figures(array $rootLabelIds): array
    {
        if (!$rootLabelIds) {
            return [];
        }
        sort($rootLabelIds);
        $compute = function () use ($rootLabelIds) {
            $map = [];
            foreach ($rootLabelIds as $r) {
                $map[(int) $r] = (int) $r;
            }
            foreach (DB::table('labels')->whereIn('parent_label_id', $rootLabelIds)->get(['id', 'parent_label_id']) as $c) {
                $map[(int) $c->id] = (int) $c->parent_label_id;
            }
            $out = [];
            foreach ($rootLabelIds as $r) {
                $out[(int) $r] = ['collected' => 0.0, 'earning' => 0.0, 'streams' => 0.0];
            }
            $rows = DB::table('report_rows')->whereIn('label_id', array_keys($map))
                ->whereExists(function ($q) {
                    $q->selectRaw('1')->from('report_imports as w228_imp')->whereColumn('w228_imp.id', 'report_rows.report_import_id')->where('w228_imp.financial_status', 'published');
                })
                ->selectRaw('label_id, COALESCE(SUM(collected_revenue),0) as collected, COALESCE(SUM(earnings),0) as earning, COALESCE(SUM(streams),0) as streams')
                ->groupBy('label_id')->get();
            foreach ($rows as $row) {
                $root = $map[(int) $row->label_id] ?? null;
                if ($root !== null) {
                    $out[$root]['collected'] += (float) $row->collected;
                    $out[$root]['earning'] += (float) $row->earning;
                    $out[$root]['streams'] += (float) $row->streams;
                }
            }
            foreach ($out as $k => $v) {
                $out[$k] = ['collected' => round($v['collected'], 2), 'earning' => round($v['earning'], 2), 'margin' => round($v['collected'] - $v['earning'], 2), 'streams' => $v['streams']];
            }

            return $out;
        };

        try {
            return ReportDataStamp::remember('w228:'.sha1(json_encode([$rootLabelIds, ReportDataStamp::value()])), 900, $compute);
        } catch (\Throwable $e) {
            report($e);

            return $compute();
        }
    }
}
