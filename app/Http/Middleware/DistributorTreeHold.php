<?php

namespace App\Http\Middleware;

use App\Services\V2\DistributorControlService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/*
 * W228 MIXXTUNE_DISTRIBUTOR_TREE_HOLD
 * When a Super Admin puts a distributor's whole tree on hold, the distributor, his sub-labels and their staff
 * are signed out on their next click and cannot use the panel until the hold is lifted. Their data stays
 * exactly as it is. A Super Admin using "Login as" is not affected. Everybody else passes untouched.
 */
class DistributorTreeHold
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user && (string) $user->role === 'label' && !$request->session()->has('impersonator_user_id')) {
            $reason = app(DistributorControlService::class)->heldFor($user);
            if ($reason !== null) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['message' => 'This account is on hold.'], 403);
                }

                return redirect('/login')->with('error', 'This account is on hold. Please contact support.');
            }
        }

        return $next($request);
    }
}
