<?php

namespace App\Http\Middleware;

use App\Services\V2\ReleaseShareService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W238 MIXXTUNE_PROMO_KIT
 * share.mixxtune.com shows public listen pages and nothing else: no login page, no panel.
 * Any other path on that address goes to the normal site (the bare address) or is a 404.
 */
class ShareHostOnly
{
    public function handle(Request $request, Closure $next): Response
    {
        if (strtolower($request->getHost()) !== ReleaseShareService::shareHost()) {
            return $next($request);
        }

        $path = trim($request->path(), '/');
        if ($request->isMethod('GET') && preg_match('#^r/[a-z0-9]{8,16}(/poster)?$#', $path)) {
            /* a visitor of a public page never needs a session of ours */
            config(['session.driver' => 'array']);

            return $next($request);
        }
        if ($path === '') {
            return redirect()->away(rtrim((string) config('app.url'), '/'));
        }

        abort(404);
    }
}
