<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/*
 * W59 MIXXTUNE_ACCOUNT_ACTIVE_V1
 *
 * A suspended / inactive account is logged out on its next click, also
 * when it still had an open session from before the suspension. A super
 * admin using "Login as" on such an account is not affected.
 */
class EnsureAccountActive
{
    public const BLOCKED = ['suspended', 'inactive'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user
            && in_array((string) $user->account_status, self::BLOCKED, true)
            && !$request->session()->has('impersonator_user_id')
        ) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson() && !$request->header('X-Inertia')) {
                return response()->json(['message' => 'This account is suspended.'], 403);
            }

            return redirect('/login')->with('error', 'This account is suspended. Please contact Mixx Tune support.');
        }

        return $next($request);
    }
}
