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

        /* W233: the Super Admin's switch - off means the pop-up never opens by itself for anybody (the button stays) */
        $data = $pending->forUser($user);
        $data['popup_enabled'] = (bool) app(\App\Services\V2\SystemSettingService::class)->get('pending_work.popup_enabled', true);
        $data['can_switch'] = $permissions->role($user) === 'super_admin';

        return response()->json($data)
            ->header('Cache-Control', 'no-store');
    }

    /* W233: Super Admin turns the automatic pop-up on or off for every Super Admin and Manager */
    public function setting(Request $request, PermissionService $permissions): JsonResponse
    {
        $user = $request->user();
        abort_unless($user && $permissions->role($user) === 'super_admin', 403);
        abort_if($request->hasSession() && $request->session()->has('impersonator_user_id'), 403);

        $on = $request->boolean('enabled');
        app(\App\Services\V2\SystemSettingService::class)->set('pending_work.popup_enabled', $on, 'pending_work', 'boolean', $user->id);

        return response()->json(['popup_enabled' => $on])->header('Cache-Control', 'no-store');
    }
}
