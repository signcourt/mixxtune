<?php

namespace App\Http\Middleware;

use App\Services\V2\SystemSettingService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/*
 * W135 MIXXTUNE_IDLE_LOGOUT
 * Signs a logged-in user out after a period of no real activity, so the dashboard does not
 * stay open. On the next request the session is gone and they must log in again — which goes
 * through the e-mail OTP flow. The idle minutes come from the Super Admin panel
 * (security.idle_minutes, default 30). Background polls (the office heartbeat and Inertia
 * partial reloads) do NOT count as activity, so an unattended open tab still times out.
 */
class EnforceIdleTimeout
{
    public function __construct(private SystemSettingService $settings)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (!$user || $this->isAuthRoute($request)) {
            return $next($request);
        }

        $minutes = (int) $this->settings->get('security.idle_minutes', 30);
        if ($minutes < 1) {
            $minutes = 30;
        }

        /* real wall-clock seconds (not Carbon's test clock) so idle is actual elapsed time */
        $now = time();
        $last = (int) $request->session()->get('mt_last_activity', 0);

        if ($last > 0 && ($now - $last) > $minutes * 60) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            $request->session()->flash(
                'status',
                'You were signed out after '.$minutes.' minutes of inactivity. Please sign in again.'
            );

            if ($request->header('X-Inertia')) {
                return \Inertia\Inertia::location(route('login'));
            }

            return redirect()->guest(route('login'));
        }

        /* only a real user action refreshes the clock, not a background poll */
        if (!$this->isBackground($request)) {
            $request->session()->put('mt_last_activity', $now);
        }

        return $next($request);
    }

    private function isAuthRoute(Request $request): bool
    {
        return $request->is(
            'login',
            'login/*',
            'logout',
            'register',
            'forgot-password',
            'reset-password',
            'reset-password/*',
            'verify-email',
            'verify-email/*',
            'confirm-password'
        );
    }

    private function isBackground(Request $request): bool
    {
        if ($request->routeIs('v2.office.ping')) {
            return true;
        }

        /* an Inertia partial reload (router.reload with `only:`) is a poll, not an action */
        if ($request->header('X-Inertia-Partial-Data')) {
            return true;
        }

        return false;
    }
}
