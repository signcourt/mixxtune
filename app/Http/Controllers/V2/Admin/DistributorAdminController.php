<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distributor;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W221 - Super Admin: Distributors.
 * A distributor is made from a label account that already exists (invite the person as a Label
 * under Users first - mail, access and % are set there). Making him a distributor adds one row in
 * `distributors`; nothing in users / labels is changed. Suspending switches his brand off only.
 */
class DistributorAdminController extends Controller
{
    private const OUR_SLUGS = ['share', /* W238 */ 'www', 'mixxtune', 'admin', 'artist', 'label', 'home', 'staging', 'mail', 'api', 'app', 'panel', 'support'];

    private function superAdmin(Request $request): void
    {
        abort_unless((string) $request->user()?->role === 'super_admin', 403, 'Only a Super Admin manages distributors.');
    }

    public function index(Request $request, DistributorService $service): Response
    {
        $this->superAdmin($request);
        abort_unless(DistributorService::ready(), 503, 'The distributors table is not there yet - run the W221 migration.');

        $rows = Distributor::query()->orderByDesc('id')->get();
        $labelIds = $rows->pluck('label_id')->all();
        $labels = DB::table('labels')->whereIn('id', $labelIds)->get()->keyBy('id');
        $owners = DB::table('users')->whereIn('id', $rows->pluck('user_id')->all())->get(['id', 'name', 'email', 'account_status', 'invitation_status', 'last_login_at'])->keyBy('id');
        $subs = DB::table('labels')->whereIn('parent_label_id', $labelIds)->whereNull('deleted_at')->selectRaw('parent_label_id, COUNT(*) c')->groupBy('parent_label_id')->pluck('c', 'parent_label_id');
        $managers = DB::table('admin_label_assignments')
            ->join('users', 'users.id', '=', 'admin_label_assignments.user_id')
            ->whereIn('admin_label_assignments.label_id', $labelIds)
            ->where('users.role', 'admin') /* W223b */
            ->get(['admin_label_assignments.label_id', 'users.name'])
            ->groupBy('label_id');

        $taken = $labelIds;
        $candidates = DB::table('labels')
            ->join('users', 'users.id', '=', 'labels.user_id')
            ->whereNull('labels.deleted_at')
            ->whereNull('labels.parent_label_id')
            ->when($taken, fn ($q) => $q->whereNotIn('labels.id', $taken))
            ->whereNotIn('labels.user_id', $rows->pluck('user_id')->all() ?: [0])
            ->where('users.role', 'label')
            ->orderBy('labels.name')
            ->get(['labels.id', 'labels.name', 'users.name as owner_name', 'users.email as owner_email'])
            ->map(fn ($l) => ['id' => (int) $l->id, 'name' => $l->name, 'owner' => $l->owner_name, 'email' => $l->owner_email])
            ->values();

        /* W228: controls + figures of each tree */
        $w228On = \App\Services\V2\DistributorControlService::ready();
        $w228Figures = $w228On ? app(\App\Services\V2\DistributorControlService::class)->figures(array_map('intval', $labelIds)) : [];
        $w228Month = [];
        if ($labelIds) {
            $w228Tree = [];
            foreach ($labelIds as $w228Id) {
                $w228Tree[(int) $w228Id] = (int) $w228Id;
            }
            foreach (DB::table('labels')->whereIn('parent_label_id', $labelIds)->whereNull('deleted_at')->get(['id', 'parent_label_id']) as $w228Child) {
                $w228Tree[(int) $w228Child->id] = (int) $w228Child->parent_label_id;
            }
            foreach (DB::table('releases')->whereIn('label_id', array_keys($w228Tree))->whereNull('deleted_at')->where('submitted_at', '>=', now()->startOfMonth())->selectRaw('label_id, COUNT(*) c')->groupBy('label_id')->get() as $w228Row) {
                $w228Root = $w228Tree[(int) $w228Row->label_id];
                $w228Month[$w228Root] = ($w228Month[$w228Root] ?? 0) + (int) $w228Row->c;
            }
        }

        return Inertia::render('V2/Admin/Distributors/Index', [
            'role' => 'super_admin',
            'managerOptions' => DB::table('users')->where('role', 'admin')->where('account_status', 'active')->orderBy('name')->get(['id', 'name'])->map(fn ($m) => ['id' => (int) $m->id, 'name' => $m->name])->values(),
            'distributors' => $rows->map(function (Distributor $d) use ($labels, $owners, $subs, $managers, $service, $w228On, $w228Figures, $w228Month) {
                $w228c = $w228On ? app(\App\Services\V2\DistributorControlService::class)->row((int) $d->id) : null;
                $label = $labels->get($d->label_id);
                $owner = $owners->get($d->user_id);

                return [
                    'id' => (int) $d->id,
                    'label_id' => (int) $d->label_id,
                    'label_name' => $label->name ?? '(label removed)',
                    'percent' => $label->revenue_share_percentage ?? ($label->royalty_share_percentage ?? null),
                    'slug' => $d->slug,
                    'url' => 'https://'.\App\Support\DistributorHost::hostOf((string) $d->slug), /* W230 */
                    'url_ready' => (bool) $d->host_verified_at,
                    'host' => $d->host,
                    'brand_name' => $d->brand_name,
                    'dashboard_name' => $d->dashboard_name,
                    'logo_url' => $service->logoUrl($d),
                    'status' => $d->status,
                    'owner_id' => (int) $d->user_id,
                    'owner_name' => $owner->name ?? '-',
                    'owner_email' => $owner->email ?? '-',
                    'owner_invitation' => $owner->invitation_status ?? null,
                    'owner_last_login' => $owner->last_login_at ?? null,
                    'sub_labels' => (int) ($subs[$d->label_id] ?? 0),
                    'managers' => collect($managers->get($d->label_id, []))->pluck('name')->unique()->values()->all(),
                    'created_at' => optional($d->created_at)->toIso8601String(),
                    'control' => $w228On ? [
                        'tree_hold' => (bool) ($w228c->tree_hold ?? false), 'hold_reason' => $w228c->hold_reason ?? null, 'hold_at' => $w228c->hold_at ?? null,
                        'max_sub_labels' => $w228c->max_sub_labels ?? null, 'max_monthly_releases' => $w228c->max_monthly_releases ?? null, 'notes' => $w228c->notes ?? null,
                        'agreement_name' => $w228c->agreement_name ?? null, 'agreement_at' => $w228c->agreement_at ?? null,
                    ] : null,
                    'figures' => $w228Figures[(int) $d->label_id] ?? null,
                    'month_releases' => (int) ($w228Month[(int) $d->label_id] ?? 0),
                ];
            })->values(),
            'candidates' => $candidates,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->superAdmin($request);

        $data = $request->validate([
            'label_id' => ['required', 'integer', Rule::exists('labels', 'id')->whereNull('deleted_at')->whereNull('parent_label_id')],
            'slug' => ['required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_regex:/-stg$/', Rule::unique('distributors', 'slug'), Rule::notIn(self::OUR_SLUGS)],
            'brand_name' => ['nullable', 'string', 'max:120'],
            'dashboard_name' => ['nullable', 'string', 'max:120'],
        ], [
            'slug.regex' => 'Use small letters, numbers and single dashes only (e.g. abc-music).',
            'slug.not_in' => 'That name is reserved by Mixx Tune.',
            'label_id.exists' => 'Choose a master label (not a sub-label).',
        ]);

        $label = DB::table('labels')->where('id', (int) $data['label_id'])->first();
        $owner = $label && $label->user_id ? DB::table('users')->where('id', $label->user_id)->first() : null;
        if (!$owner || (string) $owner->role !== 'label') {
            return back()->withErrors(['label_id' => 'This label has no label login yet. Invite the person as a Label under Accounts → Users first, then make him a distributor.']);
        }
        if (Distributor::query()->where('label_id', $label->id)->orWhere('user_id', $owner->id)->exists()) {
            return back()->withErrors(['label_id' => 'This label / login is already a distributor.']);
        }

        app(ActionOtpGuard::class)->require($request->user(), 'distributor.make:'.$label->id, 'make "'.$label->name.'" ('.$owner->email.') a distributor');

        $d = Distributor::query()->create([
            'label_id' => (int) $label->id,
            'user_id' => (int) $owner->id,
            'slug' => $data['slug'],
            'brand_name' => trim((string) ($data['brand_name'] ?? '')) ?: null,
            'dashboard_name' => trim((string) ($data['dashboard_name'] ?? '')) ?: null,
            'status' => 'active',
            'created_by' => $request->user()->id,
            'updated_by' => $request->user()->id,
        ]);
        DistributorService::forget();
        $this->audit('distributor.created', 'Label "'.$label->name.'" (#'.$label->id.', '.$owner->email.') made a distributor (slug '.$d->slug.').', [], ['distributor_id' => $d->id, 'slug' => $d->slug], $d, $request);

        return back()->with('success', '"'.$label->name.'" is now a distributor. He sees his own name / logo in the panel and gets "My Brand" in his menu.');
    }

    public function update(Request $request, Distributor $distributor): RedirectResponse
    {
        $this->superAdmin($request);

        $data = $request->validate([
            'slug' => ['sometimes', 'required', 'string', 'min:3', 'max:40', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'not_regex:/-stg$/', Rule::unique('distributors', 'slug')->ignore($distributor->id), Rule::notIn(self::OUR_SLUGS)],
            'brand_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'dashboard_name' => ['sometimes', 'nullable', 'string', 'max:120'],
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
        ]);

        $old = $distributor->only(['slug', 'brand_name', 'dashboard_name', 'status']);
        if (array_key_exists('status', $data) && $data['status'] !== $distributor->status) {
            app(ActionOtpGuard::class)->require($request->user(), 'distributor.status:'.$distributor->id.':'.$data['status'], ($data['status'] === 'suspended' ? 'suspend' : 're-activate').' the distributor "'.($distributor->brand_name ?: $distributor->slug).'"');
        }
        foreach (['brand_name', 'dashboard_name'] as $k) {
            if (array_key_exists($k, $data)) {
                $data[$k] = trim((string) $data[$k]) ?: null;
            }
        }
        $distributor->fill($data + ['updated_by' => $request->user()->id])->save();
        DistributorService::forget();
        $this->audit('distributor.updated', 'Distributor #'.$distributor->id.' ('.$distributor->slug.') updated.', $old, $distributor->only(['slug', 'brand_name', 'dashboard_name', 'status']), $distributor, $request);

        return back()->with('success', 'Distributor saved.'.(($data['status'] ?? null) === 'suspended' ? ' His brand is switched off - his accounts keep working under the Mixx Tune name.' : ''));
    }

    private function audit(string $action, string $description, array $old, array $new, Distributor $d, Request $request): void
    {
        try {
            app(AuditLogService::class)->record($action, 'labels', $description, $old, $new, $d, $request);
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
