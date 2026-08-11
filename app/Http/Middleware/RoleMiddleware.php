<?php

namespace App\Http\Middleware;

use App\Services\V2\LabelAccess\LabelTeamAccessService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    public function handle(
        Request $request,
        Closure $next,
        string $role
    ): Response {
        $user = $request->user();

        if (!$user) {
            abort(403);
        }

        /*
         * Normal account-role access remains unchanged.
         */
        if ($user->role === $role) {
            return $next($request);
        }

        /*
         * Label Team Users are allowed through ONLY when
         * the requested panel role is "label".
         *
         * Their real users.role value is NOT changed.
         * Membership supplies the Label Panel context.
         */
        if ($role === 'label') {
            $teamAccess = app(
                LabelTeamAccessService::class
            );

            if ($teamAccess->membership($user)) {
                return $next($request);
            }
        }

        abort(403);
    }
}
