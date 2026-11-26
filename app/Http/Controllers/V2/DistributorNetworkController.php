<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorMoneyService;
use App\Services\V2\DistributorService;
use App\Services\V2\StaffAlerts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W229 - the distributor's "My Network": his whole tree on one page (sub-labels, their releases by state,
 * streams, money) and a notice he can send to all his sub-labels' bells. Read-only except the notice.
 */
class DistributorNetworkController extends Controller
{
    private const STATES = ['draft', 'submitted', 'changes_requested', 'approved', 'processing', 'delivered', 'live', 'rejected', 'taken_down'];

    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off. Write to support.');

        return $d;
    }

    public function index(Request $request, DistributorService $service): Response
    {
        $d = $this->owner($request, $service);
        $root = (int) $d->label_id;

        $children = DB::table('labels')->where('parent_label_id', $root)->whereNull('deleted_at')->orderBy('name')->get(['id', 'name', 'status', 'user_id']);
        $ids = $children->pluck('id')->map(fn ($v) => (int) $v)->all();
        $tree = array_merge([$root], $ids);

        $counts = [];
        foreach (DB::table('releases')->whereIn('label_id', $tree)->whereNull('deleted_at')->selectRaw('label_id, status, COUNT(*) c')->groupBy('label_id', 'status')->get() as $r) {
            $counts[(int) $r->label_id][(string) $r->status] = (int) $r->c;
        }
        $waiting = 0;
        try {
            $waiting = DB::table('distributor_release_reviews')->where('distributor_id', $d->id)->where('status', 'pending')->count();
        } catch (\Throwable $e) {
            report($e);
        }

        $money = ['labels' => [], 'totals' => ['earning' => 0, 'share' => 0, 'paid' => 0, 'balance' => 0, 'kept' => 0]];
        try {
            if (DistributorMoneyService::ready()) {
                $money = app(DistributorMoneyService::class)->ledger($d);
            }
        } catch (\Throwable $e) {
            report($e);
        }
        $byLabel = [];
        $trend = [];
        foreach ($money['labels'] as $l) {
            $byLabel[(int) $l['id']] = $l;
            foreach ($l['months'] as $m) {
                $trend[$m['month']] ??= ['month' => $m['month'], 'streams' => 0.0, 'earning' => 0.0, 'share' => 0.0];
                $trend[$m['month']]['streams'] += $m['streams'];
                $trend[$m['month']]['earning'] += $m['earning'];
                $trend[$m['month']]['share'] += $m['share'];
            }
        }
        krsort($trend);

        $users = DB::table('users')->whereIn('id', $children->pluck('user_id')->filter()->all() ?: [0])->get(['id', 'name', 'email', 'last_login_at', 'invitation_status', 'account_status'])->keyBy('id');
        $sum = fn (array $c, array $keys) => array_sum(array_map(fn ($k) => $c[$k] ?? 0, $keys));

        $rows = $children->map(function ($l) use ($counts, $byLabel, $users, $sum) {
            $c = $counts[(int) $l->id] ?? [];
            $m = $byLabel[(int) $l->id] ?? null;
            $u = $l->user_id ? $users->get($l->user_id) : null;

            return [
                'id' => (int) $l->id,
                'name' => $l->name,
                'owner' => $u->name ?? null,
                'email' => $u->email ?? null,
                'joined' => $u ? in_array((string) $u->invitation_status, ['accepted', 'not_required'], true) : false,
                'last_login' => $u->last_login_at ?? null,
                'releases' => array_sum($c),
                'live' => $sum($c, ['live', 'delivered']),
                'in_review' => $sum($c, ['submitted', 'approved', 'processing']),
                'drafts' => $sum($c, ['draft', 'changes_requested']),
                'streams' => (float) ($m['streams'] ?? 0),
                'earning' => (float) ($m['earning'] ?? 0),
                'share' => (float) ($m['share'] ?? 0),
                'due' => (float) ($m['balance'] ?? 0),
                'percent' => $m['percent'] ?? null,
            ];
        })->values();

        $recent = DB::table('releases')->leftJoin('labels', 'labels.id', '=', 'releases.label_id')
            ->whereIn('releases.label_id', $tree)->whereNull('releases.deleted_at')
            ->orderByDesc('releases.updated_at')->limit(12)
            ->get(['releases.id', 'releases.title', 'releases.primary_artist_name', 'releases.status', 'releases.updated_at', 'labels.name as label_name']);

        $own = $counts[$root] ?? [];

        return Inertia::render('V2/Distributor/Network', [
            'role' => 'label',
            'tiles' => [
                'sub_labels' => count($ids),
                'releases' => array_sum(array_map('array_sum', $counts)),
                'live' => array_sum(array_map(fn ($c) => $sum($c, ['live', 'delivered']), $counts)),
                'waiting_for_you' => (int) $waiting,
                'own_releases' => array_sum($own),
                'earning' => (float) $money['totals']['earning'],
                'share' => (float) $money['totals']['share'],
                'due' => (float) $money['totals']['balance'],
                'kept' => (float) $money['totals']['kept'],
            ],
            'subLabels' => $rows,
            'trend' => array_values(array_map(fn ($t) => ['month' => $t['month'], 'streams' => $t['streams'], 'earning' => round($t['earning'], 2), 'share' => round($t['share'], 2)], array_slice($trend, 0, 12, true))),
            'recent' => $recent->map(fn ($r) => ['id' => (int) $r->id, 'title' => $r->title, 'artist' => $r->primary_artist_name, 'status' => $r->status, 'label' => $r->label_name, 'at' => $r->updated_at])->values(),
        ]);
    }

    /** a notice to the bell of every sub-label login (owners and their active staff). Plain text only. */
    public function notice(Request $request, DistributorService $service, StaffAlerts $alerts, AuditLogService $audit): RedirectResponse
    {
        $d = $this->owner($request, $service);
        $data = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:120', 'regex:/^[^<>{}]+$/u'],
            'message' => ['required', 'string', 'min:3', 'max:1000', 'regex:/^[^<>{}]+$/u'],
            'label_id' => ['nullable', 'integer'],
        ], ['title.regex' => 'Plain text only.', 'message.regex' => 'Plain text only.']);

        $children = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')
            ->when(!empty($data['label_id']), fn ($q) => $q->where('id', (int) $data['label_id']))->get(['id', 'user_id']);
        if ($children->isEmpty()) {
            return back()->with('error', !empty($data['label_id']) ? 'That sub-label is not in your account.' : 'You have no sub-label yet.');
        }
        $userIds = $children->pluck('user_id')->filter()->map(fn ($v) => (int) $v);
        try {
            $userIds = $userIds->merge(DB::table('label_team_members')->whereIn('label_id', $children->pluck('id')->all())->where('status', 'active')->pluck('user_id')->map(fn ($v) => (int) $v));
        } catch (\Throwable $e) {
            report($e);
        }
        $users = User::query()->whereIn('id', $userIds->unique()->values()->all() ?: [0])->where('role', 'label')->whereNotIn('account_status', ['suspended', 'inactive'])->get();
        if ($users->isEmpty()) {
            return back()->with('error', 'No sub-label login to send it to yet.');
        }

        $brand = trim((string) $d->brand_name) ?: 'Your distributor';
        $n = $alerts->bell($users, 'distributor.notice', mb_substr($brand.': '.trim($data['title']), 0, 250), trim($data['message']), '/notifications', 'info', ['distributor_id' => (int) $d->id]);

        try {
            $audit->record('distributor.notice_sent', 'distributors', 'Distributor #'.$d->id.' sent a notice to '.$n.' sub-label login(s): '.trim($data['title']), [], ['title' => trim($data['title']), 'message' => trim($data['message']), 'recipients' => $n, 'label_id' => $data['label_id'] ?? null], null, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Notice sent to '.$n.' sub-label login'.($n === 1 ? '' : 's').'. It shows in their bell.');
    }
}
