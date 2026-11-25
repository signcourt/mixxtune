<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ActionOtpGuard;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseTakedown;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** W210 - takedown: Super Admin does it, a Manager asks for it. Nobody else has a route. */
class ReleaseTakedownController extends Controller
{
    /** Super Admin: take the release down now (one e-mailed code) */
    public function takeDown(Request $request, Release $release, PermissionService $permissions, ReleaseTakedown $takedown): RedirectResponse
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can take a release down.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);

        $takedown->refuseUnless($release); /* say at once why not, before the code is asked */
        app(ActionOtpGuard::class)->require($request->user(), ReleaseTakedown::scope((int) $release->id), 'take down the release "'.$release->title.'" from all stores');
        $takedown->takeDown($release, $request->user(), $data['reason']);

        return back()->with('success', '"'.$release->title.'" is taken down from all stores. Its details, tracks and history stay on the release.');
    }

    /** Manager: ask a Super Admin to take it down (release must be in his assigned scope) */
    public function requestTakedown(Request $request, Release $release, PermissionService $permissions, ReleaseAccessService $access, ReleaseTakedown $takedown): RedirectResponse
    {
        $role = $permissions->role($request->user());
        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Labels and artists cannot request a takedown here - please write to your Manager or to the official e-mail.');
        abort_unless($access->canAccess($request->user(), $release), 403, 'This release is not in your assigned labels / artists.');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:1000']]);

        $takedown->request($release, $request->user(), $data['reason']);

        return back()->with('success', 'Takedown request sent. A Super Admin decides it under Administration → Approvals; you get a bell when it is done.');
    }
}
