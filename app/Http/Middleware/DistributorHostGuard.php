<?php

namespace App\Http\Middleware;

use App\Services\V2\DistributorService;
use App\Support\DistributorHost;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/*
 * W230 MIXXTUNE_DISTRIBUTOR_HOST (second step, after login is known)
 * On a distributor's own address only people of that distributor's tree can be logged in. Anybody else
 * (another label, an artist, Mixx Tune staff) is signed out again with the ordinary "wrong details" message,
 * so the address tells nobody whether an account exists.
 */
class DistributorHostGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $d = DistributorHost::current();
        $user = $d ? $request->user() : null;

        if ($d && $user) {
            $mine = null;
            try {
                $mine = app(DistributorService::class)->forUser($user);
            } catch (\Throwable $e) {
                report($e);
            }
            if (!$mine || (int) $mine->id !== (int) $d->id) {
                Auth::guard('web')->logout();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() && !$request->header('X-Inertia')) {
                    return response()->json(['message' => 'These credentials do not match our records.'], 403);
                }

                return redirect('/login')->withErrors(['email' => 'These credentials do not match our records.']);
            }
        }

        return $next($request);
    }
}
