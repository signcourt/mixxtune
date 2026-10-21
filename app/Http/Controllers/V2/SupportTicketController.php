<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Services\V2\PermissionService;
use App\Services\V2\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/*
 * MIXXTUNE_SUPPORT_DESK_V1 — owner side (label / artist / anyone logged in).
 */
class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportDesk $desk)
    {
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $tab = (string) $request->query('tab', 'active');
        $base = SupportTicket::query()->where('user_id', $request->user()->id);

        $query = (clone $base)->withCount(['messages' => fn ($q) => $q->where('is_internal', false)]);

        match ($tab) {
            'reply' => $query->whereIn('status', ['admin_reply', 'waiting']),
            'closed' => $query->where('status', 'closed'),
            'all' => null,
            default => $query->where('status', '!=', 'closed'),
        };

        return Inertia::render('V2/Support/Index', [
            'role' => $permissions->role($request->user()),
            'tab' => in_array($tab, ['active', 'reply', 'closed', 'all'], true) ? $tab : 'active',
            'counts' => [
                'active' => (clone $base)->where('status', '!=', 'closed')->count(),
                'reply' => (clone $base)->whereIn('status', ['admin_reply', 'waiting'])->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'all' => (clone $base)->count(),
            ],
            'categories' => SupportDesk::CATEGORIES,
            'tickets' => $query->orderByDesc('last_reply_at')->orderByDesc('id')->paginate(25)->withQueryString(),
        ]);
    }

    public function create(Request $request, PermissionService $permissions): Response
    {
        return Inertia::render('V2/Support/Create', [
            'role' => $permissions->role($request->user()),
            'legalSection' => $request->query('legal_section'),
            'categories' => SupportDesk::CATEGORIES,
            'presetCategory' => array_key_exists((string) $request->query('category'), SupportDesk::CATEGORIES)
                ? $request->query('category')
                : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
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
            'user_id' => $request->user()->id,
            'subject' => $validated['subject'],
            'category' => $validated['category'],
            'priority' => $validated['priority'],
            'status' => 'open',
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachments' => $this->desk->storeAttachments($request),
        ]);

        /* before: nobody on the team was told about a new ticket */
        $this->desk->notifyStaff($ticket, 'support.new_ticket', 'New support ticket', $request->user()->id);

        return redirect()->route('v2.support.show', $ticket)
            ->with('success', "Ticket {$ticket->ticket_number} created. We will reply here.");
    }

    public function show(Request $request, SupportTicket $ticket, PermissionService $permissions): Response
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        /* internal staff notes are never sent to the owner's browser */
        $messages = $ticket->messages()->where('is_internal', false)->with('user:id,name')->orderBy('id')->get();

        return Inertia::render('V2/Support/Show', [
            'role' => $permissions->role($request->user()),
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'category' => $ticket->category,
                'category_label' => SupportDesk::CATEGORIES[$ticket->category] ?? $ticket->category,
                'priority' => $ticket->priority,
                'status' => $ticket->status,
                'created_at' => $ticket->created_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'closed_at' => $ticket->closed_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'can_reopen' => $ticket->status === 'closed' && $ticket->closed_at && $ticket->closed_at->gt(now()->subDays(30)),
            ],
            'messages' => $messages->map(fn ($m) => $this->desk->present($m, $ticket))->values(),
        ]);
    }

    public function reply(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        abort_if($ticket->status === 'closed', 422, 'This ticket is closed. Press "Reopen" to continue, or open a new ticket.');

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:20000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => SupportDesk::ATTACHMENT_RULES,
        ]);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachments' => $this->desk->storeAttachments($request),
        ]);

        $ticket->update([
            'status' => 'customer_reply',
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
        ]);

        /* before: only an assigned manager was told; unassigned tickets told nobody */
        $this->desk->notifyStaff($ticket, 'support.customer_reply', 'New reply on a ticket', $request->user()->id);

        return back()->with('success', 'Reply sent.');
    }

    public function close(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        if ($ticket->status !== 'closed') {
            $ticket->messages()->create([
                'user_id' => $request->user()->id,
                'message' => '(Closed by '.$request->user()->name.': problem solved.)',
            ]);

            $ticket->update(['status' => 'closed', 'closed_at' => now(), 'closed_by' => $request->user()->id]);
        }

        return back()->with('success', 'Ticket closed. Thank you!');
    }

    public function reopen(Request $request, SupportTicket $ticket): RedirectResponse
    {
        abort_unless((int) $ticket->user_id === (int) $request->user()->id, 403);

        if ($ticket->status !== 'closed' || !$ticket->closed_at || $ticket->closed_at->lte(now()->subDays(30))) {
            return back()->withErrors(['message' => 'Only tickets closed in the last 30 days can be reopened. Please open a new ticket.']);
        }

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => '(Reopened by '.$request->user()->name.'.)',
        ]);

        $ticket->update([
            'status' => 'customer_reply',
            'closed_at' => null,
            'closed_by' => null,
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
        ]);

        $this->desk->notifyStaff($ticket, 'support.reopened', 'Ticket reopened', $request->user()->id);

        return back()->with('success', 'Ticket reopened. Write what is still wrong below.');
    }

    /** Download an attachment: only the ticket owner or staff; internal-note files only staff. */
    public function file(Request $request, SupportTicket $ticket, SupportTicketMessage $message, int $index): StreamedResponse
    {
        $user = $request->user();
        $staff = SupportDesk::isStaff($user);

        abort_unless((int) $message->support_ticket_id === (int) $ticket->id, 404);
        abort_unless($staff || ((int) $ticket->user_id === (int) $user->id && !$message->is_internal), 403);

        $file = ($message->attachments ?? [])[$index] ?? null;
        abort_unless(is_array($file) && !empty($file['path']), 404);

        $disk = $file['disk'] ?? 'public';
        abort_unless(in_array($disk, ['local', 'public'], true) && Storage::disk($disk)->exists($file['path']), 404);

        return Storage::disk($disk)->download($file['path'], $file['name'] ?? basename($file['path']));
    }
}
