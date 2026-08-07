<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupportManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $status = trim(
            (string) $request->input(
                'status',
                'open'
            )
        );

        $query = SupportTicket::query()
            ->with([
                'user:id,name,email',
                'assignedAdmin:id,name,email',
            ])
            ->withCount('messages');

        if ($status !== '') {
            $query->where(
                'status',
                $status
            );
        }

        if (
            $role === 'admin'
            && $request->boolean('mine')
        ) {
            $query->where(
                'assigned_admin_id',
                $request->user()->id
            );
        }

        $base =
            SupportTicket::query();

        return Inertia::render(
            'V2/Admin/Support/Index',
            [
                'role' => $role,

                'filters' => [
                    'status' => $status,
                    'mine' =>
                        $request->boolean(
                            'mine'
                        ),
                ],

                'counts' => [
                    'open' =>
                        (clone $base)
                            ->where(
                                'status',
                                'open'
                            )
                            ->count(),

                    'customer_reply' =>
                        (clone $base)
                            ->where(
                                'status',
                                'customer_reply'
                            )
                            ->count(),

                    'admin_reply' =>
                        (clone $base)
                            ->where(
                                'status',
                                'admin_reply'
                            )
                            ->count(),

                    'waiting' =>
                        (clone $base)
                            ->where(
                                'status',
                                'waiting'
                            )
                            ->count(),

                    'closed' =>
                        (clone $base)
                            ->where(
                                'status',
                                'closed'
                            )
                            ->count(),
                ],

                'tickets' =>
                    $query
                        ->orderByRaw(
                            "FIELD(priority, 'urgent', 'high', 'normal', 'low')"
                        )
                        ->orderByDesc(
                            'last_reply_at'
                        )
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function show(
        Request $request,
        SupportTicket $ticket,
        PermissionService $permissions
    ): Response {
        $role = $this->authorizeAdmin(
            $request,
            $permissions
        );

        $ticket->load([
            'user:id,name,email',
            'assignedAdmin:id,name,email',
            'messages.user:id,name,email',
        ]);

        return Inertia::render(
            'V2/Admin/Support/Show',
            [
                'role' => $role,

                'ticket' =>
                    $ticket,

                'admins' =>
                    User::query()
                        ->whereIn(
                            'role',
                            [
                                'admin',
                                'super_admin',
                            ]
                        )
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                        ]),
            ]
        );
    }

    public function reply(
        Request $request,
        SupportTicket $ticket,
        PermissionService $permissions,
        PanelNotificationService $notifications
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'min:2',
                'max:20000',
            ],

            'is_internal' => [
                'nullable',
                'boolean',
            ],

            'status' => [
                'nullable',
                'string',
                'in:open,admin_reply,waiting,closed',
            ],
        ]);

        $internal = (bool) (
            $validated['is_internal']
            ?? false
        );

        $ticket->messages()->create([
            'user_id' =>
                $request->user()->id,

            'message' =>
                $validated['message'],

            'is_internal' =>
                $internal,
        ]);

        $newStatus =
            $validated['status']
            ?? (
                $internal
                    ? $ticket->status
                    : 'admin_reply'
            );

        $ticket->update([
            'status' =>
                $newStatus,

            'last_reply_at' =>
                now(),

            'last_reply_by' =>
                $request->user()->id,

            'closed_at' =>
                $newStatus === 'closed'
                    ? now()
                    : null,

            'closed_by' =>
                $newStatus === 'closed'
                    ? $request->user()->id
                    : null,
        ]);

        if (!$internal) {
            $notifications->send(
                $ticket->user,
                'support.admin_reply',
                'Support ticket updated',
                "{$ticket->ticket_number}: {$ticket->subject}",
                "/v2/support/{$ticket->id}"
            );
        }

        return back()->with(
            'success',
            'Support reply added.'
        );
    }

    public function update(
        Request $request,
        SupportTicket $ticket,
        PermissionService $permissions,
        PanelNotificationService $notifications
    ): RedirectResponse {
        $this->authorizeAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'assigned_admin_id' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'priority' => [
                'required',
                'string',
                'in:low,normal,high,urgent',
            ],

            'status' => [
                'required',
                'string',
                'in:open,customer_reply,admin_reply,waiting,closed',
            ],
        ]);

        $ticket->update([
            ...$validated,

            'closed_at' =>
                $validated['status']
                    === 'closed'
                    ? now()
                    : null,

            'closed_by' =>
                $validated['status']
                    === 'closed'
                    ? $request->user()->id
                    : null,
        ]);

        $notifications->send(
            $ticket->user,
            'support.status_updated',
            'Ticket status updated',
            "{$ticket->ticket_number} is now {$validated['status']}.",
            "/v2/support/{$ticket->id}"
        );

        return back()->with(
            'success',
            'Ticket updated.'
        );
    }

    private function authorizeAdmin(
        Request $request,
        PermissionService $permissions
    ): string {
        $role = $permissions->role(
            $request->user()
        );

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Admin access required.'
        );

        return $role;
    }
}
