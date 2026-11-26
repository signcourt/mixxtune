<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Http\Controllers\V2\Admin\UserManagementController;
use App\Models\Core\Label;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorService;
use App\Services\V2\LabelIdentityService;
use App\Services\V2\LabelRevenueShareService;
use App\Services\V2\PermissionService;
use App\Services\V2\UserInvitationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W223 - the distributor's own "Sub-labels" page.
 *
 * A sub-label = a child label under the distributor's master label, WITH its own login.
 * The login is created by the same code path as Accounts -> Users -> Create (client id, profile,
 * permissions, invitation), so nothing is re-invented. Afterwards the new label is hung under the
 * distributor: parent_label_id = his master label, no wallet / no withdrawal (the distributor is
 * paid for the whole tree and pays his sub-labels himself), its own Mixx Tune % cleared so the
 * master's % applies, and the distributor's share for it saved in label_revenue_shares.
 */
class DistributorSubLabelController extends Controller
{
    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off. Write to support.');

        return $d;
    }

    private function child(int $rootId, int $labelId): Label
    {
        return Label::query()->whereKey($labelId)->where('parent_label_id', $rootId)->whereNull('deleted_at')->firstOrFail();
    }

    public function index(Request $request, DistributorService $service): Response
    {
        $d = $this->owner($request, $service);
        $root = DB::table('labels')->where('id', $d->label_id)->first();

        $children = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->orderBy('name')->get();
        $ids = $children->pluck('id')->all();
        $users = DB::table('users')->whereIn('id', $children->pluck('user_id')->filter()->all())->get(['id', 'name', 'email', 'account_status', 'invitation_status', 'last_login_at'])->keyBy('id');
        $shares = DB::table('label_revenue_shares')->where('master_label_id', $d->label_id)->where('beneficiary_type', 'label')->whereIn('beneficiary_id', $ids ?: [0])->where('is_active', true)->pluck('revenue_share_percent', 'beneficiary_id');
        $releases = DB::table('releases')->whereIn('label_id', $ids ?: [0])->whereNull('deleted_at')->selectRaw('label_id, COUNT(*) c')->groupBy('label_id')->pluck('c', 'label_id');

        return Inertia::render('V2/Distributor/SubLabels', [
            'role' => 'label',
            'master' => ['name' => $root->name ?? '', 'brand' => $d->brand_name ?: ($root->name ?? '')],
            'subLabels' => $children->map(function ($l) use ($users, $shares, $releases) {
                $u = $l->user_id ? $users->get($l->user_id) : null;

                return [
                    'id' => (int) $l->id,
                    'name' => $l->name,
                    'status' => $l->status,
                    'percent' => isset($shares[$l->id]) ? (float) $shares[$l->id] : null,
                    'releases' => (int) ($releases[$l->id] ?? 0),
                    'owner_name' => $u->name ?? null,
                    'owner_email' => $u->email ?? null,
                    'owner_status' => $u->account_status ?? null,
                    'invitation' => $u->invitation_status ?? null,
                    'last_login' => $u->last_login_at ?? null,
                ];
            })->values(),
        ]);
    }

    public function store(Request $request, DistributorService $service, LabelRevenueShareService $shares): RedirectResponse
    {
        $d = $this->owner($request, $service);

        $data = $request->validate([
            'label_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[^<>{}\\\\]+$/u'],
            'full_name' => ['required', 'string', 'min:2', 'max:150', 'regex:/^[^<>{}\\\\]+$/u'],
            'email' => ['required', 'email', 'max:190', Rule::unique('users', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'state_code' => ['nullable', 'string', 'max:10'],
            'state' => ['nullable', 'string', 'max:100'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'address_line_1' => ['nullable', 'string', 'max:255'],
            'percent' => ['required', 'numeric', 'min:0', 'max:100'],
        ], [
            'email.unique' => 'This e-mail already has an account.',
            'label_name.regex' => 'Plain text only.',
            'full_name.regex' => 'Plain text only.',
        ]);

        $name = trim($data['label_name']);
        if (app(LabelIdentityService::class)->representative($name)) {
            throw ValidationException::withMessages(['label_name' => 'A label with this name already exists. Choose another name.']);
        }

        $root = Label::query()->whereKey($d->label_id)->whereNull('deleted_at')->firstOrFail();

        /* the account is created by the standard Create User code; it insists on a Super Admin actor */
        $actor = User::query()->whereKey($d->created_by)->where('role', 'super_admin')->first()
            ?: User::query()->where('role', 'super_admin')->where('account_status', 'active')->orderBy('id')->first();
        abort_unless($actor, 500, 'No Super Admin account to create the login with.');

        $rootRate = $root->revenue_share_percentage ?? 0;
        $sub = Request::create('/admin/users', 'POST', [
            'name' => $name,
            'full_name' => trim($data['full_name']),
            'email' => strtolower(trim($data['email'])),
            'phone' => $data['phone'] ?? null,
            'country' => $root->country ?: 'India',
            'country_code' => 'IN',
            'state_code' => $data['state_code'] ?? null,
            'state' => $data['state'] ?? null,
            'city' => $data['city'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'address_line_1' => $data['address_line_1'] ?? null,
            'role' => 'label',
            'revenue_share_percentage' => $rootRate,
            'payout_cycle' => in_array((string) $root->payout_cycle, ['monthly', 'quarterly'], true) ? $root->payout_cycle : 'monthly',
            'send_invitation' => false,   /* sent below, after everything is saved */
            'kyc_by_owner' => true,
            'new_labels' => [$name],
            'new_label_rates' => [$rootRate],
        ]);
        $sub->setUserResolver(fn () => $actor);
        if ($request->hasSession()) {
            $sub->setLaravelSession($request->session());
        }

        /*
         * ONE transaction for everything: the login, its label, hanging it under the distributor and the
         * share. If any step fails (validation, a compulsory field, anything) nothing at all is kept.
         * The invitation is e-mailed only after the commit.
         */
        $email = strtolower(trim($data['email']));
        [$user, $child] = DB::transaction(function () use ($sub, $root, $shares, $data, $request, $email) {
            app(UserManagementController::class)->store($sub, app(PermissionService::class), app(AuditLogService::class));

            $user = User::query()->where('email', $email)->first();
            $child = $user ? Label::query()->where('user_id', $user->id)->whereNull('deleted_at')->orderByDesc('id')->first() : null;
            if (!$user || !$child || (int) $child->id === (int) $root->id || $child->parent_label_id !== null) {
                throw ValidationException::withMessages(['label_name' => 'The account could not be created. Nothing was saved - try again or write to support.']);
            }

            /* first set-up of this label, not a rate change by hand: the rate-confirm code is not asked here */
            app()->instance('mixxtune.label_rate_confirmed', true);
            try {
                $child->forceFill([
                    'parent_label_id' => (int) $root->id,
                    'revenue_share_percentage' => null,   /* money rolls up to the master: its % applies */
                    'can_access_wallet' => false,
                    'can_withdraw' => false,
                    'updated_by' => $request->user()->id,
                ])->save();
            } finally {
                app()->forgetInstance('mixxtune.label_rate_confirmed');
            }
            $shares->saveForLabel($root, $child->fresh(), (float) $data['percent'], true, $request->user());

            return [$user, $child];
        });

        $mailed = true;
        try {
            app(UserInvitationService::class)->send($user->fresh());
        } catch (\Throwable $e) {
            report($e);
            $mailed = false;
        }
        $request->session()->forget(['success', 'warning']);

        try {
            app(AuditLogService::class)->record('distributor.sublabel_created', 'labels', 'Distributor #'.$d->id.' ('.$d->slug.') created sub-label "'.$child->name.'" (#'.$child->id.') with login '.$user->email.' at '.round((float) $data['percent'], 2).'%.', [], ['label_id' => $child->id, 'user_id' => $user->id, 'percent' => round((float) $data['percent'], 2)], $child, $request);
        } catch (\Throwable $e) {
            report($e);
        }

        return back()->with('success', 'Sub-label "'.$child->name.'" created. '.($mailed ? 'An invitation was e-mailed to '.$user->email.'.' : 'The invitation e-mail could not be sent - press "Send invitation again" in the list.').' Share: '.round((float) $data['percent'], 2).'% to the sub-label, '.round(100 - (float) $data['percent'], 2).'% stays with you.');
    }

    /** change the share of one sub-label (the e-mailed code is asked by the share service when it changes) */
    public function share(Request $request, int $label, DistributorService $service, LabelRevenueShareService $shares): RedirectResponse
    {
        $d = $this->owner($request, $service);
        $data = $request->validate(['percent' => ['required', 'numeric', 'min:0', 'max:100']]);
        $root = Label::query()->whereKey($d->label_id)->firstOrFail();
        $child = $this->child((int) $d->label_id, $label);
        try { /* W226: months already reported keep the share they had */
            app(\App\Services\V2\DistributorMoneyService::class)->freezeLabel((int) $d->id, (int) $d->label_id, (int) $child->id);
        } catch (\Throwable $e) {
            report($e);
        }
        $shares->saveForLabel($root, $child, (float) $data['percent'], true, $request->user());

        return back()->with('success', 'Share of "'.$child->name.'" is now '.round((float) $data['percent'], 2).'%. It applies to reports published from now on.');
    }

    public function resend(Request $request, int $label, DistributorService $service): RedirectResponse
    {
        $d = $this->owner($request, $service);
        $child = $this->child((int) $d->label_id, $label);
        $user = $child->user_id ? User::query()->find($child->user_id) : null;
        if (!$user || in_array((string) $user->invitation_status, ['accepted', 'not_required'], true)) {
            return back()->with('error', 'This sub-label has already accepted its invitation (or has no login).');
        }
        try {
            app(UserInvitationService::class)->send($user, true);
        } catch (\Throwable $e) {
            report($e);

            return back()->with('error', 'The invitation e-mail could not be sent. Try again in a minute.');
        }

        return back()->with('success', 'Invitation e-mailed again to '.$user->email.'.');
    }
}
