<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\PanelNotification;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $validated = $request->validate([
            'category' => [
                'nullable',
                Rule::in([
                    'primary',
                    'announcement',
                    'promotion',
                ]),
            ],
        ]);

        $category = $validated['category'] ?? 'primary';

        $query = PanelNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereNull('dismissed_at');

        $query->where(
            'category',
            $category
        );

        return Inertia::render(
            'V2/Notifications/Index',
            [
                'role' => $permissions->role(
                    $request->user()
                ),

                'notifications' => $query
                    ->orderByDesc('id')
                    ->paginate(30)
                    ->withQueryString(),

                'unreadCount' =>
                    PanelNotification::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->whereNull('dismissed_at')
                        ->whereNull('read_at')
                        ->count(),

                'categoryCounts' => [
                    'primary' =>
                        PanelNotification::query()
                            ->where(
                                'user_id',
                                $request->user()->id
                            )
                            ->whereNull('dismissed_at')
                            ->where(
                                'category',
                                'primary'
                            )
                            ->count(),

                    'announcement' =>
                        PanelNotification::query()
                            ->where(
                                'user_id',
                                $request->user()->id
                            )
                            ->whereNull('dismissed_at')
                            ->where(
                                'category',
                                'announcement'
                            )
                            ->count(),

                    'promotion' =>
                        PanelNotification::query()
                            ->where(
                                'user_id',
                                $request->user()->id
                            )
                            ->whereNull('dismissed_at')
                            ->where(
                                'category',
                                'promotion'
                            )
                            ->count(),
                ],

                'activeCategory' => $category,
            ]
        );
    }

    public function markRead(
        Request $request,
        PanelNotification $notification
    ): RedirectResponse {
        $this->authorizeNotification(
            $request,
            $notification
        );

        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        if ($notification->action_url) {
            return redirect(
                $notification->action_url
            );
        }

        return back();
    }

    public function markAllRead(
        Request $request
    ): RedirectResponse {
        PanelNotification::query()
            ->where(
                'user_id',
                $request->user()->id
            )
            ->whereNull('dismissed_at')
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);

        return back()->with(
            'success',
            'All notifications marked as read.'
        );
    }

    public function toggleStar(
        Request $request,
        PanelNotification $notification
    ): RedirectResponse {
        $this->authorizeNotification(
            $request,
            $notification
        );

        $notification->update([
            'starred_at' =>
                $notification->starred_at
                    ? null
                    : now(),
        ]);

        return back();
    }

    public function dismiss(
        Request $request,
        PanelNotification $notification
    ): RedirectResponse {
        $this->authorizeNotification(
            $request,
            $notification
        );

        $notification->update([
            'dismissed_at' => now(),
        ]);

        return back()->with(
            'success',
            'Notification removed.'
        );
    }

    private function authorizeNotification(
        Request $request,
        PanelNotification $notification
    ): void {
        abort_unless(
            (int) $notification->user_id ===
                (int) $request->user()->id,
            403
        );
    }
}
