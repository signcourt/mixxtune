<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Services\V2\PanelNotificationService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SupportTicketController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        return Inertia::render(
            'V2/Support/Index',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'tickets' =>
                    SupportTicket::query()
                        ->where(
                            'user_id',
                            $request->user()->id
                        )
                        ->withCount('messages')
                        ->orderByDesc('id')
                        ->paginate(25),
            ]
        );
    }

    public function create(
        Request $request,
        PermissionService $permissions
    ): Response {
        return Inertia::render(
            'V2/Support/Create',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),
            ]
        );
    }

    public function store(
        Request $request
    ): RedirectResponse {
        $validated = $request->validate([
            'subject' => [
                'required',
                'string',
                'min:3',
                'max:255',
            ],

            'category' => [
                'required',
                'string',
                'in:release,report,royalty,wallet,withdrawal,kyc,technical,copyright,general',
            ],

            'priority' => [
                'required',
                'string',
                'in:low,normal,high,urgent',
            ],

            'message' => [
                'required',
                'string',
                'min:3',
                'max:20000',
            ],

            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,pdf,csv,txt,xlsx,zip',
            ],
        ]);

        $ticket = SupportTicket::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'ticket_number' =>
                'TKT-'
                . now()->format('Ymd')
                . '-'
                . strtoupper(
                    Str::random(7)
                ),

            'user_id' =>
                $request->user()->id,

            'subject' =>
                $validated['subject'],

            'category' =>
                $validated['category'],

            'priority' =>
                $validated['priority'],

            'status' =>
                'open',

            'last_reply_at' =>
                now(),

            'last_reply_by' =>
                $request->user()->id,
        ]);

        $attachments =
            $this->storeAttachments(
                $request
            );

        $ticket->messages()->create([
            'user_id' =>
                $request->user()->id,

            'message' =>
                $validated['message'],

            'attachments' =>
                $attachments,
        ]);

        return redirect()
            ->route(
                'v2.support.show',
                $ticket
            )
            ->with(
                'success',
                "Ticket {$ticket->ticket_number} created."
            );
    }

    public function show(
        Request $request,
        SupportTicket $ticket,
        PermissionService $permissions
    ): Response {
        abort_unless(
            $ticket->user_id ===
                $request->user()->id,
            403
        );

        $ticket->load([
            'messages.user:id,name,email',
        ]);

        return Inertia::render(
            'V2/Support/Show',
            [
                'role' =>
                    $permissions->role(
                        $request->user()
                    ),

                'ticket' =>
                    $ticket,
            ]
        );
    }

    public function reply(
        Request $request,
        SupportTicket $ticket,
        PanelNotificationService $notifications
    ): RedirectResponse {
        abort_unless(
            $ticket->user_id ===
                $request->user()->id,
            403
        );

        abort_unless(
            $ticket->status !== 'closed',
            422,
            'Closed ticket cannot receive replies.'
        );

        $validated = $request->validate([
            'message' => [
                'required',
                'string',
                'min:2',
                'max:20000',
            ],

            'attachments' => [
                'nullable',
                'array',
                'max:5',
            ],

            'attachments.*' => [
                'file',
                'max:20480',
                'mimes:jpg,jpeg,png,pdf,csv,txt,xlsx,zip',
            ],
        ]);

        $ticket->messages()->create([
            'user_id' =>
                $request->user()->id,

            'message' =>
                $validated['message'],

            'attachments' =>
                $this->storeAttachments(
                    $request
                ),
        ]);

        $ticket->update([
            'status' =>
                'customer_reply',

            'last_reply_at' =>
                now(),

            'last_reply_by' =>
                $request->user()->id,
        ]);

        if ($ticket->assignedAdmin) {
            $notifications->send(
                $ticket->assignedAdmin,
                'support.customer_reply',
                'New ticket reply',
                "{$ticket->ticket_number}: {$ticket->subject}",
                "/v2/admin/support/{$ticket->id}"
            );
        }

        return back()->with(
            'success',
            'Reply added.'
        );
    }

    private function storeAttachments(
        Request $request
    ): array {
        $stored = [];

        foreach (
            $request->file(
                'attachments',
                []
            ) as $file
        ) {
            $stored[] = [
                'name' =>
                    $file->getClientOriginalName(),

                'path' =>
                    $file->store(
                        'support-attachments',
                        'public'
                    ),

                'size' =>
                    $file->getSize(),

                'mime' =>
                    $file->getMimeType(),
            ];
        }

        return $stored;
    }
}
