<?php

namespace App\Http\Middleware;

use App\Support\DistributorHost;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W230 MIXXTUNE_DISTRIBUTOR_HOST (first step, before the session starts)
 * A request that came in on a distributor's own address (<slug>.mixxtune.com) is marked with that distributor
 * and gets a session cookie of its own for exactly that address. Every other address passes untouched.
 */
class DistributorHostContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = $request->getHost();
        if (DistributorHost::slugFromHost($host) !== null) {
            $d = DistributorHost::resolve($host);
            abort_unless($d, 404);

            /* the cookie belongs to this address only - never shared with www or with another distributor */
            config(['session.domain' => null]);
            app()->instance('mixxtune.host_distributor', $d);
        }

        return $next($request);
    }
}
