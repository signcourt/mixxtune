<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Support\SupportTicket;
use App\Services\V2\DistributorService;
use App\Services\V2\DistributorTickets;
use App\Services\V2\SupportDesk;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * W234 - the distributor's own support desk: tickets opened by his sub-labels.
 * He reads, answers and closes them. He never sees internal staff notes and never anybody else's tickets.
 */
class DistributorTicketController extends Controller
{
    public function __construct(private readonly SupportDesk $desk)
    {
    }

    private function owner(Request $request, DistributorService $service)
    {
        $d = $service->forUser($request->user());
        abort_unless($d && $service->isOwner($request->user(), $d), 403, 'This page is for the distributor account owner.');
        abort_if((string) $d->status !== 'active', 403, 'Your distributor account is switched off. Write to support.');

        return $d;
    }

    private function mine(Request $request, DistributorService $service, SupportTicket $ticket): void
    {
        $d = $this->owner($request, $service);
        abort_unless(in_array((int) $ticket->user_id, DistributorTickets::subUserIds($d), true), 404);
    }

    public function index(Request $request, DistributorService $service): Response
    {
        $d = $this->owner($request, $service);
        $ids = DistributorTickets::subUserIds($d) ?: [0];
        $tab = (string) $request->query('tab', 'active');
        $tab = in_array($tab, ['active', 'waiting', 'closed', 'all'], true) ? $tab : 'active';

        $base = SupportTicket::query()->whereIn('user_id', $ids);
        $query = (clone $base)->with('user:id,name')->withCount(['messages' => fn ($q) => $q->where('is_internal', false)]);
        match ($tab) {
            'waiting' => $query->whereIn('status', ['open', 'customer_reply']),
            'closed' => $query->where('status', 'closed'),
            'all' => null,
            default => $query->where('status', '!=', 'closed'),
        };

        $labels = DB::table('labels')->where('parent_label_id', $d->label_id)->whereNull('deleted_at')->pluck('name', 'user_id');
        $tickets = $query->orderByDesc('last_reply_at')->orderByDesc('id')->paginate(25)->withQueryString();
        $tickets->getCollection()->transform(fn ($t) => [
            'id' => $t->id,
            'ticket_number' => (string) $t->ticket_number,
            'subject' => (string) $t->subject,
            'category' => SupportDesk::CATEGORIES[$t->category] ?? (string) $t->category,
            'priority' => (string) $t->priority,
            'status' => (string) $t->status,
            'from' => (string) ($t->user?->name ?? '—'),
            'label' => (string) ($labels[$t->user_id] ?? ''),
            'messages' => (int) $t->messages_count,
            'last_reply_at' => $t->last_reply_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
        ]);

        return Inertia::render('V2/Distributor/Tickets', [
            'role' => 'label',
            'tab' => $tab,
            'counts' => [
                'active' => (clone $base)->where('status', '!=', 'closed')->count(),
                'waiting' => (clone $base)->whereIn('status', ['open', 'customer_reply'])->count(),
                'closed' => (clone $base)->where('status', 'closed')->count(),
                'all' => (clone $base)->count(),
            ],
            'tickets' => $tickets,
        ]);
    }

    public function show(Request $request, DistributorService $service, SupportTicket $ticket): Response
    {
        $this->mine($request, $service, $ticket);

        $messages = $ticket->messages()->where('is_internal', false)->with('user:id,name')->orderBy('id')->get();
        $me = (int) $request->user()->id;

        return Inertia::render('V2/Distributor/TicketShow', [
            'role' => 'label',
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => (string) $ticket->ticket_number,
                'subject' => (string) $ticket->subject,
                'category_label' => SupportDesk::CATEGORIES[$ticket->category] ?? (string) $ticket->category,
                'priority' => (string) $ticket->priority,
                'status' => (string) $ticket->status,
                'from' => (string) ($ticket->user?->name ?? '—'),
                'created_at' => $ticket->created_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
                'closed_at' => $ticket->closed_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
            ],
            'messages' => $messages->map(function ($m) use ($ticket, $me) {
                $row = $this->desk->present($m, $ticket);
                $row['mine'] = (int) $m->user_id === $me;
                $row['from_customer'] = (int) $m->user_id === (int) $ticket->user_id;

                return $row;
            })->values(),
        ]);
    }

    public function reply(Request $request, DistributorService $service, SupportTicket $ticket): RedirectResponse
    {
        $this->mine($request, $service, $ticket);
        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2', 'max:20000'],
            'close' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => SupportDesk::ATTACHMENT_RULES,
        ], [
            'attachments.max' => 'You can attach up to 5 files.',
            'attachments.*.max' => 'Each file can be at most 20 MB.',
            'attachments.*.mimes' => 'Allowed files: JPG, PNG, PDF, CSV, TXT, XLSX, ZIP, WAV, MP3.',
        ]);
        $close = (bool) ($validated['close'] ?? false);

        $ticket->messages()->create([
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'attachments' => $this->desk->storeAttachments($request),
        ]);

        $ticket->update([
            'status' => $close ? 'closed' : 'admin_reply',
            'last_reply_at' => now(),
            'last_reply_by' => $request->user()->id,
            'closed_at' => $close ? now() : null,
            'closed_by' => $close ? $request->user()->id : null,
        ]);

        $this->desk->notifyOwner($ticket, 'support.admin_reply', $close ? 'Your ticket was answered and closed' : 'New reply on your ticket');

        return back()->with('success', $close ? 'Reply sent and ticket closed.' : 'Reply sent.');
    }
}
