<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseGoLive;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** W218 - one-click Live: Super Admin, or a Manager with the "mark live" permission, inside his scope. One e-mailed code. */
class ReleaseGoLiveController extends Controller
{
    public function goLive(Request $request, Release $release, PermissionService $permissions, ReleaseAccessService $access, ReleaseGoLive $live): RedirectResponse
    {
        $role = $permissions->role($request->user());
        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Only the Mixx Tune team marks a release live.');
        if ($role !== 'super_admin') {
            $permissions->authorize($request->user(), 'delivery.mark_live');
            abort_unless($access->canAccess($request->user(), $release), 403, 'This release is not in your assigned labels / artists.');
        }

        $data = $request->validate(['note' => ['nullable', 'string', 'max:1000']]);

        $live->refuseUnless($release); /* say why not before a code is asked */
        app(ActionOtpGuard::class)->require($request->user(), ReleaseGoLive::scope((int) $release->id), 'mark the release "'.$release->title.'" live in all stores');
        $out = $live->goLive($release, $request->user(), $data['note'] ?? null);

        return back()->with('success', '"'.$release->title.'" is live - '.$out['stores'].' store(s) marked live'.($out['isrc_made'] ? ', '.$out['isrc_made'].' ISRC made' : '').'. The owner got a bell.');
    }
}
