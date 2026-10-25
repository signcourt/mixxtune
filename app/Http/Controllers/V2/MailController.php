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
use App\Services\V2\Mail\SignatureHtml;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
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
        /* W110: also Starred (inbox + sent) and Drafts (own drafts only) */
        $folder = in_array($request->query('folder'), ['sent', 'drafts', 'starred'], true) ? $request->query('folder') : 'inbox';
        $me = (int) $request->user()->id;
        $search = trim((string) $request->query('q', ''));

        $list = null;
        $open = null;
        if ($current) {
            $q = MailMessage::query()->where('mail_account_id', $current->id);
            if ($folder === 'starred') {
                $q->whereIn('folder', ['inbox', 'sent'])->where('starred', true);
            } elseif ($folder === 'drafts') {
                $q->where('folder', 'drafts')->where('sent_by', $me);
            } else {
                $q->where('folder', $folder);
            }
            if ($search !== '') {
                $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $search).'%';
                $q->where(fn ($w) => $w->where('subject', 'like', $like)->orWhere('from_email', 'like', $like)->orWhere('from_name', 'like', $like)->orWhere('snippet', 'like', $like)->orWhere('to', 'like', $like));
            }
            $list = $q->orderByDesc('sent_at')->orderByDesc('id')
                ->paginate(30, ['id', 'subject', 'from_name', 'from_email', 'to', 'sent_at', 'snippet', 'seen', 'starred', 'has_attachments', 'folder'])
                ->withQueryString()
                ->through(fn (MailMessage $m) => [
                    'id' => $m->id,
                    'subject' => $m->subject ?: '(no subject)',
                    'who' => $m->folder === 'drafts' ? ($this->people($m->to ?? []) ?: '(no address yet)') : ($m->folder === 'sent' ? $this->people($m->to ?? []) : ($m->from_name ?: $m->from_email)),
                    'folder' => $m->folder,
                    'starred' => (bool) $m->starred,
                    'snippet' => $m->snippet,
                    'at' => optional($m->sent_at)->toIso8601String(),
                    'seen' => (bool) $m->seen,
                    'has_attachments' => (bool) $m->has_attachments,
                ]);

            if ($request->query('m')) {
                $msg = MailMessage::query()->where('mail_account_id', $current->id)->where(fn ($w) => $w->where('folder', '!=', 'drafts')->orWhere('sent_by', $me))->find((int) $request->query('m'));
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
                        'bcc' => $this->people($msg->bcc ?? []),
                        'at' => optional($msg->sent_at)->toIso8601String(),
                        'text' => $msg->body_text,
                        'has_html' => !empty($msg->body_html),
                        /* W104: the mail's own formatting, for Reply / Forward (cleaned; very big mails stay text) */
                        'html' => !empty($msg->body_html) && strlen((string) $msg->body_html) <= 600000 ? SignatureHtml::clean(preg_replace('/\bsrc=(["\'])cid:[^"\']*\1/i', '', (string) $msg->body_html)) : null,
                        'folder' => $msg->folder,
                        'starred' => (bool) $msg->starred,
                        'attachments' => $msg->attachments()->where('inline', false)->get(['id', 'filename', 'mime', 'size']),
                    ];
                    /* W110: a draft opens again in the Write window */
                    if ($msg->folder === 'drafts') {
                        $d = $msg->draft ?? [];
                        $keep = array_map('intval', $d['keep_attachments'] ?? []);
                        $carried = [];
                        if (!empty($d['forward_of']) && $keep !== []) {
                            $orig = MailMessage::query()->where('mail_account_id', $current->id)->find((int) $d['forward_of']);
                            $carried = $orig ? $orig->attachments()->whereIn('id', $keep)->where('inline', false)->get(['id', 'filename', 'mime', 'size'])->all() : [];
                        }
                        $open['draft'] = [
                            'id' => $msg->id,
                            'to' => (string) ($d['to'] ?? ''),
                            'cc' => (string) ($d['cc'] ?? ''),
                            'bcc' => (string) ($d['bcc'] ?? ''),
                            'from' => $d['from'] ?? null,
                            'subject' => (string) ($msg->subject ?? ''),
                            'html' => (string) ($msg->body_html ?? ''),
                            'signature' => ($d['signature'] ?? true) !== false,
                            'reply_to_id' => $d['reply_to_id'] ?? null,
                            'forward_of' => $d['forward_of'] ?? null,
                            'carried' => $carried,
                        ];
                    }
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
                'from' => $a->fromAddresses(),
                'signature' => $a->signature,
                'signature_html' => SignatureHtml::forAccount($a),
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
            'counts' => $current ? [
                'drafts' => MailMessage::query()->where('mail_account_id', $current->id)->where('folder', 'drafts')->where('sent_by', $me)->count(),
                'starred' => MailMessage::query()->where('mail_account_id', $current->id)->whereIn('folder', ['inbox', 'sent'])->where('starred', true)->count(),
            ] : ['drafts' => 0, 'starred' => 0],
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
        $request->merge(['to_list' => $split($request->input('to')), 'cc_list' => $split($request->input('cc')), 'bcc_list' => $split($request->input('bcc'))]);
        $data = $request->validate([
            'to' => ['required', 'string', 'max:4000'],
            'to_list' => ['required', 'array', 'min:1', 'max:50'],
            'to_list.*' => ['email:rfc'],
            'cc_list' => ['nullable', 'array', 'max:50'],
            'cc_list.*' => ['email:rfc'],
            'bcc_list' => ['nullable', 'array', 'max:50'],
            'bcc_list.*' => ['email:rfc'],
            'from' => ['nullable', 'string', Rule::in(collect($acc->fromAddresses())->pluck('address')->all())],
            'subject' => ['required', 'string', 'max:500'],
            'body' => ['required_without:html', 'nullable', 'string', 'max:500000'],
            /* W103: the text from the rich editor (cleaned again before sending) */
            'html' => ['nullable', 'string', 'max:26000000'],
            'signature' => ['nullable', 'boolean'],
            'files' => ['nullable', 'array', 'max:10'],
            'files.*' => ['file', 'max:20480'],
            'reply_to_id' => ['nullable', 'integer'],
            'forward_of' => ['nullable', 'integer'],
            'keep_attachments' => ['nullable', 'array', 'max:20'],
            'keep_attachments.*' => ['integer'],
            'draft_id' => ['nullable', 'integer'],
        ], [
            'to_list.*.email' => 'One of the "To" addresses is not a valid e-mail address.',
            'cc_list.*.email' => 'One of the "Cc" addresses is not a valid e-mail address.',
            'bcc_list.*.email' => 'One of the "Bcc" addresses is not a valid e-mail address.',
            'from.in' => 'Pick one of the addresses of this mailbox.',
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
        $total = collect($files)->sum(fn ($f) => $f->getSize()) + collect($carried)->sum(fn ($a) => (int) $a->size) + strlen((string) ($data['html'] ?? ''));
        if ($total > 24 * 1024 * 1024) {
            return back()->withErrors(['files' => 'Attachments and pictures together can be at most 24 MB (the mail server limit is 25 MB).']);
        }
        if (trim((string) ($data['body'] ?? '')) === '' && !preg_match('/<img\b/i', (string) ($data['html'] ?? ''))) {
            return back()->withErrors(['body' => 'Write something in the mail.']);
        }
        $reply = null;
        if (!empty($data['reply_to_id'])) {
            $reply = MailMessage::query()->where('mail_account_id', $acc->id)->find((int) $data['reply_to_id']);
        }
        try {
            $msg = $sender->send($acc, $request->user(), ['to' => $data['to_list'], 'cc' => $data['cc_list'] ?? [], 'bcc' => $data['bcc_list'] ?? [], 'from' => $data['from'] ?? null, 'subject' => $data['subject'], 'body' => (string) ($data['body'] ?? ''), 'html' => $data['html'] ?? null, 'signature' => !array_key_exists('signature', $data) || $data['signature'] === null || (bool) $data['signature']], $files, $reply, $carried);
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors(['send' => 'The mail was NOT sent: '.$e->getMessage()]);
        }
        /* W110: the draft of this mail is not needed any more */
        if (!empty($data['draft_id'])) {
            MailMessage::query()->where('mail_account_id', $acc->id)->where('folder', 'drafts')->where('sent_by', $request->user()->id)->whereKey((int) $data['draft_id'])->delete();
        }
        $audit->record('mail.sent', 'mail', 'Mail sent from '.($data['from'] ?? $acc->address).' to '.implode(', ', $data['to_list']).'.', [], ['subject' => $data['subject'], 'to' => $data['to_list'], 'cc' => $data['cc_list'] ?? [], 'bcc' => $data['bcc_list'] ?? [], 'from' => $data['from'] ?? $acc->address, 'attachments' => count($files) + count($carried)], $msg, $request);

        return redirect('/mail?account='.$acc->id.'&folder=sent&m='.$msg->id)->with('success', 'Mail sent.');
    }
    /* W110: star / unstar a mail (also in the mailbox itself as \\Flagged, so webmail and phones show it) */
    public function star(Request $request, MailMessage $message, MailAccess $access): RedirectResponse
    {
        $this->message($request, $access, $message);
        abort_if($message->folder === 'drafts', 422, 'A draft cannot be starred.');
        $on = $request->boolean('starred');
        $message->forceFill(['starred' => $on])->save();
        if ($message->uid && in_array($message->folder, ['inbox', 'sent'], true)) {
            $account = MailAccount::query()->find($message->mail_account_id);
            $box = $message->folder === 'inbox' ? 'INBOX' : (string) $account?->sent_folder;
            $uid = (int) $message->uid;
            if ($account && $box !== '') {
                dispatch(function () use ($account, $box, $uid, $on) {
                    try {
                        $imap = app(MailConnector::class)->imap($account);
                        $imap->select($box);
                        $on ? $imap->addFlags($uid, '\\Flagged') : $imap->removeFlags($uid, '\\Flagged');
                        $imap->logout();
                    } catch (Throwable) {
                    }
                })->afterResponse();
            }
        }

        return back();
    }

    /*
     * W110: keep what is being written as a draft (saved every few seconds and when the window is
     * closed). Drafts are kept in the panel only, and only the person who wrote one sees it.
     */
    public function saveDraft(Request $request, int $account, MailAccess $access): JsonResponse
    {
        $acc = $this->account($request, $access, $account);
        $data = $request->validate([
            'draft_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'string', Rule::in(collect($acc->fromAddresses())->pluck('address')->all())],
            'to' => ['nullable', 'string', 'max:4000'],
            'cc' => ['nullable', 'string', 'max:4000'],
            'bcc' => ['nullable', 'string', 'max:4000'],
            'subject' => ['nullable', 'string', 'max:500'],
            'body' => ['nullable', 'string', 'max:500000'],
            'html' => ['nullable', 'string', 'max:12000000'],
            'signature' => ['nullable', 'boolean'],
            'reply_to_id' => ['nullable', 'integer'],
            'forward_of' => ['nullable', 'integer'],
            'keep_attachments' => ['nullable', 'array', 'max:20'],
            'keep_attachments.*' => ['integer'],
        ]);
        $split = fn ($v) => collect(preg_split('/[,;\s]+/', (string) $v, -1, PREG_SPLIT_NO_EMPTY))->map(fn ($e) => strtolower(trim($e, " <>\"'")))->filter(fn ($e) => str_contains($e, '@'))->unique()->values()->map(fn ($e) => ['name' => '', 'email' => mb_substr($e, 0, 190)])->all();
        $draft = !empty($data['draft_id'])
            ? MailMessage::query()->where('mail_account_id', $acc->id)->where('folder', 'drafts')->where('sent_by', $request->user()->id)->find((int) $data['draft_id'])
            : null;
        $draft ??= new MailMessage(['mail_account_id' => $acc->id, 'folder' => 'drafts', 'seen' => true, 'sent_by' => $request->user()->id]);
        $html = SignatureHtml::clean($data['html'] ?? null);
        $text = trim((string) ($data['body'] ?? ''));
        $from = collect($acc->fromAddresses())->firstWhere('address', strtolower((string) ($data['from'] ?? $acc->address))) ?? $acc->fromAddresses()[0];
        $draft->forceFill([
            'subject' => mb_substr((string) ($data['subject'] ?? ''), 0, 998),
            'from_name' => $from['name'] ?: null,
            'from_email' => $from['address'],
            'to' => $split($data['to'] ?? ''),
            'cc' => $split($data['cc'] ?? ''),
            'bcc' => $split($data['bcc'] ?? ''),
            'sent_at' => now(),
            'snippet' => MailSync::snippet($text, $html),
            'body_text' => $text,
            'body_html' => $html,
            'has_attachments' => !empty($data['keep_attachments']),
            'size' => strlen((string) $html) + strlen($text),
            'draft' => [
                'to' => (string) ($data['to'] ?? ''),
                'cc' => (string) ($data['cc'] ?? ''),
                'bcc' => (string) ($data['bcc'] ?? ''),
                'from' => $from['address'],
                'signature' => !array_key_exists('signature', $data) || $data['signature'] === null || (bool) $data['signature'],
                'reply_to_id' => $data['reply_to_id'] ?? null,
                'forward_of' => $data['forward_of'] ?? null,
                'keep_attachments' => array_values(array_map('intval', $data['keep_attachments'] ?? [])),
            ],
        ])->save();

        return response()->json(['id' => $draft->id, 'saved_at' => now()->toIso8601String()]);
    }

    public function deleteDraft(Request $request, MailMessage $message, MailAccess $access): JsonResponse
    {
        $this->message($request, $access, $message);
        abort_unless($message->folder === 'drafts' && (int) $message->sent_by === (int) $request->user()->id, 404);
        $message->delete();

        return response()->json(['deleted' => true]);
    }

    /*
     * W110: people this mailbox writes to or gets mail from - for the To / Cc / Bcc suggestions.
     * People written to count more, and the newest name is kept.
     */
    public function contacts(Request $request, int $account, MailAccess $access): JsonResponse
    {
        $acc = $this->account($request, $access, $account);
        $own = collect($acc->fromAddresses())->pluck('address')->map(fn ($a) => strtolower($a))->all();
        $book = [];
        $add = function (?string $email, ?string $name, int $weight, $at) use (&$book, $own) {
            $e = strtolower(trim((string) $email));
            if ($e === '' || !str_contains($e, '@') || in_array($e, $own, true) || str_contains($e, 'noreply') || str_contains($e, 'no-reply') || str_contains($e, 'mailer-daemon')) {
                return;
            }
            $t = $at ? strtotime((string) $at) : 0;
            $row = $book[$e] ?? ['email' => $e, 'name' => '', 'score' => 0, 'sent' => 0, 'last' => 0];
            $row['score'] += $weight;
            $row['sent'] += $weight >= 3 ? 1 : 0;
            if ($t >= $row['last']) {
                $row['last'] = $t;
                if (trim((string) $name) !== '') {
                    $row['name'] = trim((string) $name);
                }
            } elseif ($row['name'] === '' && trim((string) $name) !== '') {
                $row['name'] = trim((string) $name);
            }
            $book[$e] = $row;
        };
        MailMessage::query()->where('mail_account_id', $acc->id)->where('folder', 'sent')->orderByDesc('sent_at')->limit(3000)
            ->get(['to', 'cc', 'bcc', 'sent_at'])
            ->each(function (MailMessage $m) use ($add) {
                foreach ([...($m->to ?? []), ...($m->cc ?? []), ...($m->bcc ?? [])] as $p) {
                    $add($p['email'] ?? null, $p['name'] ?? null, 3, $m->sent_at);
                }
            });
        MailMessage::query()->where('mail_account_id', $acc->id)->where('folder', 'inbox')->orderByDesc('sent_at')->limit(3000)
            ->get(['from_email', 'from_name', 'cc', 'sent_at'])
            ->each(function (MailMessage $m) use ($add) {
                $add($m->from_email, $m->from_name, 1, $m->sent_at);
            });
        $list = collect($book)->sortByDesc(fn ($r) => [$r['score'], $r['last']])->take(2000)->values()
            ->map(fn ($r) => ['email' => $r['email'], 'name' => $r['name'], 'sent' => $r['sent'], 'last' => $r['last'] ? date('c', $r['last']) : null]);

        return response()->json(['contacts' => $list]);
    }
}
