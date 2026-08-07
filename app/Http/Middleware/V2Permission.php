<?php

namespace App\Http\Middleware;

use App\Services\V2\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class V2Permission
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {
    }

    public function handle(
        Request $request,
        Closure $next,
        string $permission
    ): Response {
        $this->permissions->authorize(
            $request->user(),
            $permission
        );

        return $next($request);
    }
}
