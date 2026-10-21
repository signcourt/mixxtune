<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Models\User;
use App\Services\V2\PermissionService;
use App\Services\V2\SupportDesk;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/*
 * MIXXTUNE_SUPPORT_DESK_V1 — Support Management (managers + super admins).
 */
class SupportManagementController extends Controller
{
    /* A ticket waiting on us longer than this is shown as late. */
    private const LATE_HOURS = 24;

    public function __construct(private readonly SupportDesk $desk)
    {
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $role = $this->authorizeAdmin($request, $permissions);

        $f = [
            'status' => (string) $request->query('status', 'needs_reply'),
            'search' => trim((string) $request->query('search', '')),
            'category' => (string) $request->query('category', ''),
            'priority' => (string) $request->query('priority', ''),
            'assigned' => (string) $request->query('assigned', ''),
        ];

        $query = SupportTicket::query()
            ->with(['user:id,name,email,role', 'assignedAdmin:id,name'])
            ->withCount(['messages' => fn ($q) => $q->where('is_internal', false)]);

        match ($f['status']) {
            'needs_reply' => $query->whereIn('status', ['open', 'customer_reply']),
            'all' => null,
            default => in_array($f['status'], SupportDesk::STATUSES, true) ? $query->where('status', $f['status']) : null,
        };

        if ($f['search'] !== '') {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $f['search']).'%';
            $query->where(fn ($w) => $w->where('ticket_number', 'like', $like)
                ->orWhere('subject', 'like', $like)
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', $like)->orWhere('email', 'like', $like)));
        }

        if (array_key_exists($f['category'], SupportDesk::CATEGORIES)) {
            $query->where('category', $f['category']);
        }

        if (in_array($f['priority'], SupportDesk::PRIORITIES, true)) {
            $query->where('priority', $f['priority']);
        }

        if ($f['assigned'] === 'me') {
            $query->where('assigned_admin_id', $request->user()->id);
        } elseif ($f['assigned'] === 'none') {
            $query->whereNull('assigned_admin_id');
        }

        $base = SupportTicket::query();
        $lateCut = now()->subHours(self::LATE_HOURS);

        $tickets = $query
            ->orderByRaw("CASE priority WHEN 'urgent' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")
            ->orderBy('last_reply_at')
            ->paginate(30)
            ->withQueryString();

        $tickets->getCollection()->transform(function (SupportTicket $t) use ($lateCut) {
            $waitingOnUs = in_array($t->status, ['open', 'customer_reply'], true);

            return [
                'id' => $t->id,
                'ticket_number' => $t->ticket_number,
                'subject' => $t->subject,
                'category' => $t->category,
                'category_label' => SupportDesk::CATEGORIES[$t->category] ?? $t->category,
                'priority' => $t->priority,
                'status' => $t->status,
                'user' => $t->user ? ['name' => $t->user->name, 'email' => $t->user->email, 'role' => $t->user->role] : null,
                'assigned' => $t->assignedAdmin?->name,
                'messages_count' => $t->messages_count,
                'last_reply_human' => $t->last_reply_at?->diffForHumans(),
                'late' => $waitingOnUs && $t->last_reply_at && $t->last_reply_at->lt($lateCut),
            ];
        });

        return Inertia::render('V2/Admin/Support/Index', [
            'role' => $role,
            'filters' => $f,
            'tickets' => $tickets,
            'categories' => SupportDesk::CATEGORIES,
            'counts' => [
                'needs_reply' => (clone $base)->whereIn('status', ['open', 'customer_reply'])->count(),
                'open' => (clone $base)->where('status', 'open')->count(),
                'customer_reply' => (clone $base)->where('status', 'customer_reply')->count(),
                'admin_reply' => (clone $base)->where('status', 'admin_reply')->count(),
                'waiting' => (clone $base)->where('status', 'waiting')->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'all' => (clone $base)->count(),
                'late' => (clone $base)->whereIn('status', ['open', 'customer_reply'])->where('last_reply_at', '<', $lateCut)->count(),
                'unassigned' => (clone $base)->where('status', '!=', 'closed')->whereNull('assigned_admin_id')->count(),
                'mine' => (clone $base)->where('status', '!=', 'closed')->where('assigned_admin_id', $request->user()->id)->count(),
            ],
            'lateHours' => self::LATE_HOURS,
        ]);
    }

    public function show(Request $request, SupportTicket $ticket, PermissionService $permissions): Response
    {
        $role = $this->authorizeAdmin($request, $permissions);

        $ticket->load(['user:id,name,email,role,client_id', 'assignedAdmin:id,name', 'messages.user:id,name']);

        $others = SupportTicket::query()
            ->where('user_id', $ticket->user_id)->whereKeyNot($ticket->id)
            ->orderByDesc('id')->limit(8)
            ->get(['id', 'ticket_number', 'subject', 'status']);

        return Inertia::render('V2/Admin/Support/Show', [
            'role' => $role,
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'assigned_admin_id' => $ticket->assigned_admin_id,
                'created_at' => $ticket->created_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'user' => $ticket->user ? [
                    'id' => $ticket->user->id, 'name' => $ticket->user->name, 'email' => $ticket->user->email,
                    'role' => $ticket->user->role, 'client_id' => $ticket->user->client_id,
                ] : null,
            ],
            'messages' => $ticket->messages->sortBy('id')->map(fn ($m) => $this->desk->present($m, $ticket))->values(),
            'otherTickets' => $others,
            'categories' => SupportDesk::CATEGORIES,
            'admins' => User::query()->whereIn('role', ['admin', 'super_admin'])->where('account_status', 'active')
                ->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket, PermissionService $permissions): RedirectResponse
    {
        $this->authorizeAdmin($request, $permissions);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:20000'],
            'is_internal' => ['nullable', 'boolean'],
            'status' => ['nullable', 'string', 'in:admin_reply,waiting,closed'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => SupportDesk::ATTACHMENT_RULES,
        ]);

        $internal = (bool) ($validated['is_internal'] ?? false);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_internal' => $internal,
            /* before: files chosen with a manager reply were silently dropped */
            'attachments' => $this->desk->storeAttachments($request),
        ]);

        if ($internal) {
            return back()->with('success', 'Internal note saved (the customer does not see it).');
        }

        $newStatus = $validated['status'] ?? 'admin_reply';

        $ticket->update([
            'status' => $newStatus,
            'assigned_admin_id' => $ticket->assigned_admin_id ?? $request->user()->id,
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
            'closed_at' => $newStatus === 'closed' ? now() : null,
            'closed_by' => $newStatus === 'closed' ? $request->user()->id : null,
        ]);

        $this->desk->notifyOwner(
            $ticket,
            'support.admin_reply',
            $newStatus === 'closed' ? 'Your ticket was answered and closed' : 'New reply on your ticket'
        );

        return back()->with('success', $newStatus === 'closed' ? 'Reply sent and ticket closed.' : 'Reply sent.');
    }

    public function update(Request $request, SupportTicket $ticket, PermissionService $permissions): RedirectResponse
    {
        $this->authorizeAdmin($request, $permissions);

        $validated = $request->validate([
            'assigned_admin_id' => ['nullable', 'integer', 'exists:users,id'],
            'priority' => ['required', 'string', 'in:'.implode(',', SupportDesk::PRIORITIES)],
            'status' => ['required', 'string', 'in:'.implode(',', SupportDesk::STATUSES)],
            'category' => ['nullable', 'string', 'in:'.implode(',', array_keys(SupportDesk::CATEGORIES))],
        ]);

        if (!empty($validated['assigned_admin_id'])) {
            abort_unless(
                User::query()->whereKey($validated['assigned_admin_id'])->whereIn('role', ['admin', 'super_admin'])->exists(),
                422,
                'Tickets can only be given to a manager or super admin.'
            );
        }

        $before = $ticket->only(['assigned_admin_id', 'priority', 'status', 'category']);
        $wasClosed = $ticket->status === 'closed';

        $ticket->update([
            'assigned_admin_id' => $validated['assigned_admin_id'] ?? null,
            'priority' => $validated['priority'],
            'status' => $validated['status'],
            'category' => $validated['category'] ?? $ticket->category,
            'closed_at' => $validated['status'] === 'closed' ? ($wasClosed ? $ticket->closed_at : now()) : null,
            'closed_by' => $validated['status'] === 'closed' ? ($wasClosed ? $ticket->closed_by : $request->user()->id) : null,
        ]);

        $changes = [];
        foreach ($before as $key => $old) {
            if ((string) $old !== (string) $ticket->{$key}) {
                $changes[] = $key;
            }
        }

        if ($changes) {
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'is_internal' => true,
                'message' => '(Changed by '.$request->user()->name.': '.implode(', ', array_map(
                    fn ($k) => str_replace('_', ' ', $k === 'assigned_admin_id' ? 'assigned to' : $k).' → '.($k === 'assigned_admin_id'
                        ? (User::query()->whereKey($ticket->assigned_admin_id)->value('name') ?? 'nobody')
                        : $ticket->{$k}),
                    $changes
                )).')',
            ]);
        }

        /* tell the new manager */
        if (in_array('assigned_admin_id', $changes, true) && $ticket->assigned_admin_id && (int) $ticket->assigned_admin_id !== (int) $request->user()->id) {
            $this->desk->notifyStaff($ticket->fresh(), 'support.assigned', 'A ticket was given to you');
        }

        /* the customer only hears about a status change */
        if (in_array('status', $changes, true)) {
            $this->desk->notifyOwner($ticket, 'support.status_updated', 'Ticket status updated',
                "{$ticket->ticket_number} is now ".str_replace('_', ' ', $ticket->status === 'admin_reply' ? 'answered' : $ticket->status).'.');
        }

        return back()->with('success', $changes ? 'Ticket updated.' : 'Nothing changed.');
    }

    /** Staff opens a ticket for a user (e.g. a problem found on our side). */
    public function store(Request $request, PermissionService $permissions): RedirectResponse
    {
        $this->authorizeAdmin($request, $permissions);

        $validated = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'subject' => ['required', 'string', 'min:3', 'max:255'],
            'category' => ['required', 'string', 'in:'.implode(',', array_keys(SupportDesk::CATEGORIES))],
            'priority' => ['required', 'string', 'in:'.implode(',', SupportDesk::PRIORITIES)],
            'message' => ['required', 'string', 'min:3', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => SupportDesk::ATTACHMENT_RULES,
        ]);

        $ticket = SupportTicket::query()->create([
            'public_id' => (string) Str::ulid(),
            'ticket_number' => SupportDesk::newNumber(),
            'user_id' => $validated['user_id'],
            'assigned_admin_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'waiting',
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachments' => $this->desk->storeAttachments($request),
        ]);

        $this->desk->notifyOwner($ticket->load('user'), 'support.admin_opened', 'Mixx Tune opened a ticket for you');

        return redirect('/v2/admin/support/'.$ticket->id)->with('success', "Ticket {$ticket->ticket_number} opened for the user.");
    }

    public function searchUsers(Request $request, PermissionService $permissions): JsonResponse
    {
        $this->authorizeAdmin($request, $permissions);

        $q = trim((string) $request->query('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['users' => []]);
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';

        return response()->json([
            'users' => User::query()
                ->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('client_id', 'like', $like))
                ->orderBy('name')->limit(15)->get(['id', 'name', 'email', 'role']),
        ]);
    }

    private function authorizeAdmin(Request $request, PermissionService $permissions): string
    {
        $role = $permissions->role($request->user());

        abort_unless(in_array($role, ['admin', 'super_admin'], true), 403, 'Admin access required.');

        return $role;
    }
}
