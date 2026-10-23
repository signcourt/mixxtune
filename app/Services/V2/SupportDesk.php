<?php

namespace App\Services\V2;

use App\Models\Support\SupportTicket;
use App\Models\Support\SupportTicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/*
 * MIXXTUNE_SUPPORT_DESK_V1
 *
 * Shared rules for the support desk (owner side + Support Management):
 * categories, attachments (private disk, downloaded only by the ticket
 * owner or staff), who is told about new tickets / replies, and how a
 * message is shown (internal notes never leave the staff side).
 */
class SupportDesk
{
    public const CATEGORIES = [
        'release' => 'Release / delivery',
        'report' => 'Revenue report',
        'royalty' => 'Royalty / statement',
        'wallet' => 'Wallet / balance',
        'withdrawal' => 'Withdrawal / payout',
        'kyc' => 'KYC / bank details',
        'technical' => 'Panel / login problem',
        'copyright' => 'Copyright / claim',
        'general' => 'Other question',
    ];

    public const PRIORITIES = ['low', 'normal', 'high', 'urgent'];

    /*
     * W73 MIXXTUNE_LEGAL_OPS_V2: Legal Operations sections. A legal case is a
     * normal support ticket whose subject starts with "Legal · <title>:" (set
     * by the server), so Case History and the staff desk can find them
     * without a new table. Older cases used "<title> — ..." as subject.
     */
    public const LEGAL_SECTIONS = [
        'copyright' => ['title' => 'Copyright & Claims', 'category' => 'copyright'],
        'youtube' => ['title' => 'YouTube Operations', 'category' => 'copyright'],
        'spotify' => ['title' => 'Spotify Operations', 'category' => 'general'],
        'dsp' => ['title' => 'Other DSP Operations', 'category' => 'general'],
        'rights' => ['title' => 'Rights & Ownership', 'category' => 'general'],
        'request' => ['title' => 'Legal Request', 'category' => 'general'],
    ];

    public const LEGAL_PREFIX = 'Legal · ';

    /** where-clause: only Legal Operations cases (new and old subject style) */
    public static function scopeLegal($query, ?string $section = null)
    {
        $sections = $section !== null && isset(self::LEGAL_SECTIONS[$section])
            ? [$section => self::LEGAL_SECTIONS[$section]]
            : self::LEGAL_SECTIONS;

        return $query->where(function ($w) use ($sections, $section) {
            if ($section === null) {
                $w->orWhere('subject', 'like', self::LEGAL_PREFIX.'%');
            }
            foreach ($sections as $meta) {
                $title = $meta['title']; /* fixed titles, no % or _ in them */
                $w->orWhere('subject', 'like', self::LEGAL_PREFIX.$title.':%')
                    ->orWhere('subject', 'like', $title.' — %');
            }
        });
    }

    /** "Legal · YouTube Operations: Video blocked" -> ["youtube", "Video blocked"] */
    public static function legalSectionOf(?string $subject): ?string
    {
        $subject = (string) $subject;
        foreach (self::LEGAL_SECTIONS as $key => $meta) {
            if (str_starts_with($subject, self::LEGAL_PREFIX.$meta['title'].':') || str_starts_with($subject, $meta['title'].' — ')) {
                return $key;
            }
        }

        return null;
    }

    public const STATUSES = ['open', 'customer_reply', 'admin_reply', 'waiting', 'closed'];

    public const ATTACHMENT_RULES = ['file', 'max:20480', 'mimes:jpg,jpeg,png,pdf,csv,txt,xlsx,zip,wav,mp3'];

    public function __construct(private readonly PanelNotificationService $notifications)
    {
    }

    public static function isStaff(?User $user): bool
    {
        return in_array($user?->role, ['admin', 'super_admin'], true);
    }

    public static function newNumber(): string
    {
        return 'TKT-'.now()->format('Ymd').'-'.strtoupper(Str::random(7));
    }

    /** Stores uploads on the private disk; returns the attachments array for the message. */
    public function storeAttachments(Request $request): array
    {
        $stored = [];

        foreach ($request->file('attachments', []) as $file) {
            $stored[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $file->store('support-attachments', 'local'),
                'disk' => 'local',
                'size' => $file->getSize(),
                'mime' => $file->getMimeType(),
            ];
        }

        return $stored;
    }

    /** Message for the page: attachment links go through the protected download route. */
    public function present(SupportTicketMessage $message, SupportTicket $ticket): array
    {
        $files = collect($message->attachments ?? [])->values()->map(fn ($file, $i) => [
            'name' => $file['name'] ?? 'file',
            'size' => $file['size'] ?? null,
            'url' => '/support/'.$ticket->id.'/files/'.$message->id.'/'.$i,
        ])->all();

        return [
            'id' => $message->id,
            'user_id' => $message->user_id,
            'author' => $message->user?->name ?? 'Mixx Tune',
            'from_staff' => (int) $message->user_id !== (int) $ticket->user_id,
            'is_internal' => (bool) $message->is_internal,
            'message' => $message->message,
            'attachments' => $files,
            'created_at' => $message->created_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A'),
        ];
    }

    /** Panel notice to every active manager / super admin (new ticket, reply on an unassigned ticket). */
    public function notifyStaff(SupportTicket $ticket, string $type, string $title, ?int $exceptUserId = null): void
    {
        try {
            $staff = $ticket->assigned_admin_id
                ? User::query()->whereKey($ticket->assigned_admin_id)->get()
                : User::query()->whereIn('role', ['admin', 'super_admin'])->where('account_status', 'active')->get();

            foreach ($staff as $user) {
                if ($exceptUserId && (int) $user->id === $exceptUserId) {
                    continue;
                }

                $this->notifications->send(
                    $user,
                    $type,
                    $title,
                    "{$ticket->ticket_number}: {$ticket->subject}",
                    "/admin/support/{$ticket->id}"
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }

    public function notifyOwner(SupportTicket $ticket, string $type, string $title, ?string $message = null): void
    {
        try {
            if ($ticket->user) {
                $this->notifications->send(
                    $ticket->user,
                    $type,
                    $title,
                    $message ?? "{$ticket->ticket_number}: {$ticket->subject}",
                    "/support/{$ticket->id}"
                );
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
