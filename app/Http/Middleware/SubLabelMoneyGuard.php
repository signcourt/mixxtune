<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\V2\DistributorMoneyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * W226 MIXXTUNE_SUB_LABEL_MONEY
 * A sub-label under a distributor is paid by the distributor, not by Mixx Tune. Its money pages
 * (wallet, withdrawals, reports, statements...) would show the DISTRIBUTOR's amounts, so they are closed
 * for it and it is sent to "My Earnings", which shows its own share. Everybody else passes untouched.
 */
class SubLabelMoneyGuard
{
    /* first part of the address (after an optional "label/") */
    private const CLOSED = ['wallet', 'withdrawals', 'reports', 'generated-reports', 'statements', 'royalties', 'royalty-ledgers', 'invoices', 'revenue-reports'];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User && (string) $user->role === 'label') {
            $path = trim($request->path(), '/');
            if (str_starts_with($path, 'label/')) {
                $path = substr($path, 6);
            }
            $first = explode('/', $path)[0] ?? '';
            if (in_array($first, self::CLOSED, true) || $path === 'analytics/export') {
                try {
                    $sub = DistributorMoneyService::ready() ? app(DistributorMoneyService::class)->subContext($user) : null;
                } catch (\Throwable $e) {
                    report($e);
                    $sub = null;
                }
                if ($sub) {
                    if ($request->expectsJson() && !$request->header('X-Inertia')) {
                        return response()->json(['message' => 'Your earnings are on the My Earnings page.'], 403);
                    }
                    if (!$request->isMethod('GET')) {
                        abort(403, 'Your earnings are on the My Earnings page.');
                    }

                    return redirect('/label/my-earnings');
                }
            }
        }

        return $next($request);
    }
}
