<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\InvitationReminderService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/*
 * MIXXTUNE_INVITATION_REMINDERS_V1
 * "Send due reminders now" button on the Users page (super admin).
 * Sends only the reminders that are due by the settings; works even when the
 * daily automatic run is switched off.
 */
class InvitationReminderController extends Controller
{
    public function store(Request $request, PermissionService $permissions, InvitationReminderService $reminders): RedirectResponse
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Super Admin access required.');

        $result = $reminders->run(false, true);

        if ($result['sent'] === 0 && $result['failed'] === 0) {
            return back()->with('success', 'No reminder is due right now (each person is reminded every '
                .$reminders->config()['every_days'].' days, at most '.$reminders->config()['max'].' times).');
        }

        return back()->with(
            $result['failed'] > 0 ? 'error' : 'success',
            "Invitation reminders sent: {$result['sent']}".($result['failed'] > 0 ? ", failed: {$result['failed']}" : '').'.'
        );
    }
}
