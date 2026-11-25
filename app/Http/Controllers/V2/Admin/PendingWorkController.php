<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\PendingWorkService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/* W204 MIXXTUNE_PENDING_WORK: JSON for the "Pending work" pop-up on the admin dashboard. */
class PendingWorkController extends Controller
{
    public function index(Request $request, PermissionService $permissions, PendingWorkService $pending): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && in_array($permissions->role($user), ['super_admin', 'admin'], true), 403);

        return response()->json($pending->forUser($user))
            ->header('Cache-Control', 'no-store');
    }
}
