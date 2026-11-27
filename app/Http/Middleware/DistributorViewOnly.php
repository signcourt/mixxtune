<?php

namespace App\Http\Middleware;

use App\Http\Controllers\V2\DistributorViewController;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W237 MIXXTUNE_DISTRIBUTOR_VIEW
 * While a distributor has one of his sub-labels' panels open, he can look at every page but cannot change anything:
 * every request that is not a plain page read is refused, except "back to my account" and logout.
 */
class DistributorViewOnly
{
    private const ALLOWED = ['label/distributor/view/stop', 'logout'];

    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->hasSession() || ! $request->session()->has('distributor_view_user_id')) {
            return $next($request);
        }

        $user = $request->user();

        /* the marker belongs to exactly one viewed login; anything else (signed out, another account) drops it */
        if (! $user || (int) $user->id !== (int) $request->session()->get('distributor_view_target_id')) {
            $request->session()->forget(DistributorViewController::KEYS);

            return $next($request);
        }

        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true) || in_array(trim($request->path(), '/'), self::ALLOWED, true)) {
            return $next($request);
        }

        $message = 'View only: you are looking at your sub-label\'s panel. Changes are made from the sub-label\'s own login.';

        if ($request->expectsJson() && ! $request->header('X-Inertia')) {
            return response()->json(['message' => $message], 403);
        }

        return back(303)->with('panel_notice', $message);
    }
}
