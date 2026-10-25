<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\Mail\MailAttachment;
use App\Models\Mail\MailMessage;
use App\Services\V2\AuditLogService;
use App\Services\V2\Mail\MailAccess;
use App\Services\V2\Mail\MailConnector;
use App\Services\V2\Mail\MailSender;
use App\Services\V2\Mail\MailSync;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * The "Mail" page: Inbox / Sent of the mailboxes given to this person
 * (Super Admin: all), read, write, reply, forward, attachments.
 */
class MailController extends Controller
{
    private function account(Request $request, MailAccess $access, int $id): MailAccount
    {
        $account = MailAccount::query()->findOrFail($id);
        abort_unless($access->canUse($request->user(), $account), 403, 'This mailbox is not given to you.');

        return $account;
    }

    private function message(Request $request, MailAccess $access, MailMessage $message): MailMessage
    {
        $this->account($request, $access, (int) $message->mail_account_id);

        return $message;
    }

    private function people(array $list): string
    {
        return collect($list ?? [])->map(fn ($p) => trim(($p['name'] ?? '') !== '' ? $p['name'].' <'.$p['email'].'>' : ($p['email'] ?? '')))->filter()->implode(', ');
    }

    public function index(Request $request, MailAccess $access, PermissionService $permissions): Response
    {
        $accounts = $access->accountsFor($request->user());
        $mine = $access->assignedIds($request->user());
        $current = $accounts->firstWhere('id', (int) $request->query('account')) ?? $accounts->first(fn ($a) => $mine->contains((int) $a->id)) ?? $accounts->first();
        $folder = $request->query('folder') === 'sent' ? 'sent' : 'inbox';
        $search = trim((string) $request->query('q', ''));

        $list = null;
        $open = null;
        if ($current) {
            $q = MailMessage::query()->where('mail_account_id', $current->id)->where('folder', $folder);
            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('from_email', 'like', $like)->orWhere('from_name', 'like', $like)->orWhere('snippet', 'like', $like)->orWhere('to', 'like', $like));
            }
            $list = $q->orderByDesc('sent_at')->orderByDesc('id')
                ->paginate(30, ['id', 'subject', 'from_name', 'from_email', 'to', 'sent_at', 'snippet', 'seen', 'has_attachments', 'folder'])
                ->withQueryString()
                ->through(fn (MailMessage $m) => [
                    'id' => $m->id,
                    'subject' => $m->subject ?: '(no subject)',
                    'who' => $folder === 'sent' ? $this->people($m->to ?? []) : ($m->from_name ?: $m->from_email),
                    'snippet' => $m->snippet,
                    'at' => optional($m->sent_at)->toIso8601String(),
                    'seen' => (bool) $m->seen,
                    'has_attachments' => (bool) $m->has_attachments,
                ]);

            if ($request->query('m')) {
                $msg = MailMessage::query()->where('mail_account_id', $current->id)->find((int) $request->query('m'));
                if ($msg) {
                    if (!$msg->seen) {
                        $msg->forceFill(['seen' => true])->save();
                        if ($msg->uid && $msg->folder === 'inbox') {
                            $uid = $msg->uid;
                            dispatch(function () use ($current, $uid) {
                                try {
                                    $imap = app(MailConnector::class)->imap($current);
                                    $imap->select('INBOX');
                                    $imap->addFlags($uid, '\\Seen');
                                    $imap->logout();
                                } catch (Throwable) {
                                }
                            })->afterResponse();
                        }
                    }
                    $open = [
                        'id' => $msg->id,
                        'subject' => $msg->subject ?: '(no subject)',
                        'from_name' => $msg->from_name,
                        'from_email' => $msg->from_email,
                        'to' => $this->people($msg->to ?? []),
                        'cc' => $this->people($msg->cc ?? []),
                        'reply_to' => collect($msg->reply_to ?? [])->pluck('email')->first(),
                        'to_list' => collect($msg->to ?? [])->pluck('email')->values(),
                        'cc_list' => collect($msg->cc ?? [])->pluck('email')->values(),
                        'at' => optional($msg->sent_at)->toIso8601String(),
                        'text' => $msg->body_text,
                        'has_html' => !empty($msg->body_html),
                        'folder' => $msg->folder,
                        'attachments' => $msg->attachments()->where('inline', false)->get(['id', 'filename', 'mime', 'size']),
                    ];
                }
            }
        }

        return Inertia::render('V2/Mail/Index', [
            'role' => $permissions->role($request->user()),
            'accounts' => $accounts->map(fn (MailAccount $a) => [
                'id' => $a->id,
                'address' => $a->address,
                'display_name' => $a->display_name,
                'mine' => $mine->contains((int) $a->id),
                'is_private' => (bool) $a->is_private,
                'unread' => MailMessage::query()->where('mail_account_id', $a->id)->where('folder', 'inbox')->where('seen', false)->count(),
                'last_synced_at' => optional($a->last_synced_at)->toIso8601String(),
                'last_error' => $a->last_error,
            ])->values(),
            'current' => $current?->id,
            'folder' => $folder,
            'search' => $search,
            'messages' => $list,
            'open' => $open,
            'isSuperAdmin' => $access->isSuperAdmin($request->user()),
        ]);
    }

    /* the html part, shown in a locked frame: no scripts, no forms, links open in a new tab */
    public function html(Request $request, MailMessage $message, MailAccess $access): HttpResponse
    {
        $this->message($request, $access, $message);
        $html = (string) $message->body_html;
        $cids = $message->attachments()->whereNotNull('content_id')->get(['id', 'content_id'])->mapWithKeys(fn ($a) => [strtolower($a->content_id) => url('/mail/attachments/'.$a->id.'?inline=1')]);
        $html = preg_replace_callback('/\bcid:([^"\'\s>)]+)/i', fn ($m) => $cids[strtolower($m[1])] ?? 'about:blank', $html);
        $html = preg_replace('#<(script|iframe|object|embed|form|meta|link|base)\b[^>]*>(.*?</\1>)?#si', '', $html);
        $html = preg_replace('/\son[a-z]+\s*=\s*("[^"]*"|\'[^\']*\'|[^\s>]+)/i', '', $html);
        $html = preg_replace('/(href|src)\s*=\s*(["\'])\s*javascript:[^"\']*\2/i', '$1="#"', $html);
        $doc = '<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>body{font-family:Arial,sans-serif;font-size:14px;color:#111;margin:12px;word-wrap:break-word}img{max-width:100%;height:auto}</style></head><body>'.$html.'</body></html>';

        return response($doc, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "default-src 'none'; img-src * data:; style-src 'unsafe-inline' *; font-src * data:; sandbox allow-popups allow-popups-to-escape-sandbox",
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'no-referrer',
        ]);
    }

    public function attachment(Request $request, MailAttachment $attachment, MailAccess $access): StreamedResponse
    {
        $this->message($request, $access, $attachment->message);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404, 'The file is missing.');
        $safeInline = $request->boolean('inline') && str_starts_with((string) $attachment->mime, 'image/') && $attachment->mime !== 'image/svg+xml';

        return $safeInline
            ? Storage::disk('local')->response($attachment->path, $attachment->filename, ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff'])
            : Storage::disk('local')->download($attachment->path, $attachment->filename, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function refresh(Request $request, int $account, MailAccess $access, MailSync $sync): RedirectResponse
    {
        $acc = $this->account($request, $access, $account);
        try {
            $n = $sync->sync($acc);

            return back()->with('success', $n['inbox'] > 0 ? "{$n['inbox']} new mail." : 'No new mail.');
        } catch (Throwable $e) {
            $acc->forceFill(['last_error' => mb_substr($e->getMessage(), 0, 1000)])->save();

            return back()->withErrors(['mail' => 'Could not read the mailbox: '.$e->getMessage()]);
        }
    }

    public function send(Request $request, int $account, MailAccess $access, MailSender $sender, AuditLogService $audit): RedirectResponse
    {
        $acc = $this->account($request, $access, $account);
        $split = fn ($v) => collect(preg_split('/[,;\s]+/', (string) $v, -1, PREG_SPLIT_NO_EMPTY))->map(fn ($e) => strtolower(trim($e, " <>\"'")))->unique()->values()->all();
        $request->merge(['to_list' => $split($request->input('to')), 'cc_list' => $split($request->input('cc'))]);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:4000'],
            'to_list' => ['required', 'array', 'min:1', 'max:50'],
            'to_list.*' => ['email:rfc'],
            'cc_list' => ['nullable', 'array', 'max:50'],
            'cc_list.*' => ['email:rfc'],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required', 'string', 'max:100000'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
            'reply_to_id' => ['nullable', 'integer'],
            'forward_of' => ['nullable', 'integer'],
            'keep_attachments' => ['nullable', 'array', 'max:20'],
            'keep_attachments.*' => ['integer'],
        ], [
            'to_list.*.email' => 'One of the "To" addresses is not a valid e-mail address.',
            'cc_list.*.email' => 'One of the "Cc" addresses is not a valid e-mail address.',
            'files.*.max' => 'Each attachment can be at most 20 MB.',
        ]);
        $files = $request->file('files', []);
        /* W96: attachments of the forwarded mail (only from this mailbox, only files that still exist) */
        $carried = [];
        if (!empty($data['forward_of']) && !empty($data['keep_attachments'])) {
            $orig = MailMessage::query()->where('mail_account_id', $acc->id)->find((int) $data['forward_of']);
            if ($orig) {
                $carried = $orig->attachments()->whereIn('id', array_map('intval', $data['keep_attachments']))->where('inline', false)->get()
                    ->filter(fn (MailAttachment $a) => Storage::disk('local')->exists($a->path))->values()->all();
            }
        }
        $total = collect($files)->sum(fn ($f) => $f->getSize()) + collect($carried)->sum(fn ($a) => (int) $a->size);
        if ($total > 24 * 1024 * 1024) {
            return back()->withErrors(['files' => 'All attachments together can be at most 24 MB (the mail server limit is 25 MB).']);
        }
        $reply = null;
        if (!empty($data['reply_to_id'])) {
            $reply = MailMessage::query()->where('mail_account_id', $acc->id)->find((int) $data['reply_to_id']);
        }
        try {
            $msg = $sender->send($acc, $request->user(), ['to' => $data['to_list'], 'cc' => $data['cc_list'] ?? [], 'subject' => $data['subject'], 'body' => $data['body']], $files, $reply, $carried);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['send' => 'The mail was NOT sent: '.$e->getMessage()]);
        }
        $audit->record('mail.sent', 'mail', 'Mail sent from '.$acc->address.' to '.implode(', ', $data['to_list']).'.', [], ['subject' => $data['subject'], 'to' => $data['to_list'], 'cc' => $data['cc_list'] ?? [], 'attachments' => count($files) + count($carried)], $msg, $request);

        return redirect('/mail?account='.$acc->id.'&folder=sent&m='.$msg->id)->with('success', 'Mail sent.');
    }
}
