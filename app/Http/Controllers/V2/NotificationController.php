<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\PanelNotification;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        return Inertia::render(
            'V2/Notifications/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'notifications' =>
                    PanelNotification::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->orderByDesc('id')
                        ->paginate(30),

                'unreadCount' =>
                    PanelNotification::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->whereNull('read_at')
                        ->count(),
            ]
        );
    }

    public function read(
        Request $request,
        PanelNotification $notification
    ): RedirectResponse {
        abort_unless(
            $notification->user_id ===
                $request->user()->id,
            403
        );

        $notification->update([
            'read_at' =>
                now(),
        ]);

        if ($notification->action_url) {
            return redirect(
                $notification->action_url
            );
        }

        return back();
    }

    public function readAll(
        Request $request
    ): RedirectResponse {
        PanelNotification::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->whereNull('read_at')
            ->update([
                'read_at' =>
                    now(),
            ]);

        return back()->with(
            'success',
            'All notifications marked as read.'
        );
    }
}
