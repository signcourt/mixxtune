<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\DistributorControlService;
use App\Services\V2\DistributorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * W237 MIXXTUNE_DISTRIBUTOR_VIEW - the distributor opens one of his sub-labels' panels to LOOK at it.
 *
 * This is deliberately not the Super Admin "Login as":
 *  - its own session keys (distributor_view_*), so none of the Super Admin exceptions apply
 *    (a tree on hold or a suspended sub-label is still stopped);
 *  - view only: every change request is refused by DistributorViewOnly;
 *  - only the distributor owner, only a sub-label directly under his own label, only an active login;
 *  - never on top of another "Login as"; every start and stop is written to the audit log.
 */
class DistributorViewController extends Controller
{
    public const KEYS = ['distributor_view_user_id', 'distributor_view_name', 'distributor_view_target_id', 'distributor_view_started_at'];

    public function start(Request $request, int $label, DistributorService $service, AuditLogService $audit): Response|RedirectResponse
    {
        $me = $request->user();
        $d = $service->forUser($me);
        abort_unless($d && $service->isOwner($me, $d), 403, 'This is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off.');
        abort_if(app(DistributorControlService::class)->heldFor($me) !== null, 403, 'Your account is on hold.');

        if ($request->session()->has('impersonator_user_id') || $request->session()->has('distributor_view_user_id')) {
            return back()->withErrors(['view' => 'Another "Login as" is already active. Close it first.']);
        }

        $child = DB::table('labels')->where('id', $label)->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->first(['id', 'name', 'user_id']);
        abort_unless($child, 404);

        $target = $child->user_id ? User::query()->find($child->user_id) : null;
        if (! $target || (int) $target->id === (int) $me->id || (string) $target->role !== 'label') {
            return back()->withErrors(['view' => 'This sub-label has no login of its own to open.']);
        }
        if ((string) $target->account_status !== 'active') {
            return back()->withErrors(['view' => 'This sub-label\'s login is not active, so its panel cannot be opened.']);
        }

        $request->session()->put([
            'distributor_view_user_id' => (int) $me->id,
            'distributor_view_name' => (string) $me->name,
            'distributor_view_target_id' => (int) $target->id,
            'distributor_view_started_at' => now()->toIso8601String(),
        ]);

        $audit->record(
            'distributor.view_started',
            'users',
            "Distributor #{$d->id} ({$me->email}) opened the panel of sub-label \"{$child->name}\" ({$target->email}) - view only.",
            [],
            ['distributor_id' => (int) $d->id, 'distributor_user_id' => (int) $me->id, 'viewed_user_id' => (int) $target->id, 'label_id' => (int) $child->id],
            $target,
            $request
        );

        Auth::login($target, false);
        $request->session()->regenerate();

        /* full page load so the browser picks up the new CSRF token */
        return Inertia::location('/label/dashboard');
    }

    public function stop(Request $request, DistributorService $service, AuditLogService $audit): Response|RedirectResponse
    {
        $ownerId = (int) $request->session()->get('distributor_view_user_id');
        abort_unless($ownerId, 403, 'No sub-label view is open.');

        $viewed = $request->user();
        $owner = User::query()->find($ownerId);
        DistributorService::forget();
        $d = $owner ? $service->forUser($owner) : null;

        /* the account we go back to must still be an active distributor owner - otherwise everybody is signed out */
        if (! $owner || (string) $owner->account_status !== 'active' || ! $d || ! $service->isOwner($owner, $d)) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return Inertia::location('/login');
        }

        Auth::login($owner, false);

        $audit->record(
            'distributor.view_stopped',
            'users',
            "Distributor #{$d->id} ({$owner->email}) closed the panel of {$viewed?->email}.",
            ['viewed_user_id' => $viewed?->id],
            ['restored_user_id' => (int) $owner->id],
            $viewed,
            $request
        );

        $request->session()->forget(self::KEYS);
        $request->session()->regenerate();

        return Inertia::location('/label/distributor/sub-labels');
    }
}
