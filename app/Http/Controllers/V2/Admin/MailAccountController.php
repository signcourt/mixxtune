<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\Mail\MailAccess;
use App\Services\V2\Mail\MailConnector;
use App\Services\V2\Mail\MailSync;
use App\Services\V2\Mail\SignatureHtml;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Super Admin > Email accounts: add a domain mailbox (created first in Hostinger
 * hPanel) with its password, test it, give it to managers, change or remove it.
 */
class MailAccountController extends Controller
{
    private function superAdmin(Request $request, PermissionService $permissions): void
    {
        abort_unless($permissions->role($request->user()) === 'super_admin', 403, 'Only a Super Admin can manage e-mail accounts.');
    }

    private function manage(Request $request, MailAccount $account): void
    {
        abort_unless(app(MailAccess::class)->canManage($request->user(), $account), 403, 'This is a private mailbox - only its own people can change it.');
    }

    /* W101: clean alias list - lower case, not the mailbox itself, no doubles */
    private function aliases(array $list, string $address): array
    {
        return collect($list)
            ->map(fn ($a) => ['address' => strtolower(trim((string) ($a['address'] ?? ''))), 'name' => trim((string) ($a['name'] ?? ''))])
            ->filter(fn ($a) => $a['address'] !== '' && $a['address'] !== strtolower($address))
            ->unique('address')->values()->all();
    }

    private function people()
    {
        return User::query()->whereIn('role', ['admin', 'super_admin'])->orderBy('role')->orderBy('name')
            ->get(['id', 'name', 'full_name', 'email', 'role', 'account_status']);
    }

    public function index(Request $request, PermissionService $permissions): Response
    {
        $this->superAdmin($request, $permissions);
        $assigned = DB::table('mail_account_user')->get()->groupBy('mail_account_id');
        $counts = DB::table('mail_messages')->selectRaw('mail_account_id, SUM(folder = ?) as inbox, SUM(folder = ? AND seen = 0) as unread', ['inbox', 'inbox'])->groupBy('mail_account_id')->get()->keyBy('mail_account_id');

        return Inertia::render('V2/Admin/MailAccounts/Index', [
            'role' => 'super_admin',
            'me' => $request->user()->id,
            'accounts' => MailAccount::query()->orderBy('address')->get()->map(fn (MailAccount $a) => [
                'is_private' => (bool) $a->is_private,
                'can_manage' => app(MailAccess::class)->canManage($request->user(), $a),
                'id' => $a->id,
                'address' => $a->address,
                'display_name' => $a->display_name,
                'username' => $a->username,
                'imap' => $a->imap_host.':'.$a->imap_port,
                'smtp' => $a->smtp_host.':'.$a->smtp_port,
                'sent_folder' => $a->sent_folder,
                'signature' => $a->signature,
                'signature_html' => $a->signature_html,
                'aliases' => array_values((array) ($a->aliases ?? [])),
                'status' => $a->status,
                'last_synced_at' => optional($a->last_synced_at)->toIso8601String(),
                'last_error' => $a->last_error,
                'user_ids' => collect($assigned->get($a->id, []))->pluck('user_id')->map(fn ($v) => (int) $v)->values(),
                'inbox' => (int) ($counts->get($a->id)->inbox ?? 0),
                'unread' => (int) ($counts->get($a->id)->unread ?? 0),
            ])->values(),
            'people' => $this->people(),
        ]);
    }

    private function rules(?MailAccount $account = null): array
    {
        return [
            'address' => [$account ? 'prohibited' : 'required', 'email:rfc', 'max:190', Rule::unique('mail_accounts', 'address')],
            'display_name' => ['nullable', 'string', 'max:190'],
            'password' => [$account ? 'nullable' : 'required', 'string', 'max:500'],
            'imap_host' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/i'],
            'imap_port' => ['nullable', 'integer', 'between:1,65535'],
            'smtp_host' => ['nullable', 'string', 'max:190', 'regex:/^[a-z0-9.-]+$/i'],
            'smtp_port' => ['nullable', 'integer', 'between:1,65535'],
            'signature' => ['nullable', 'string', 'max:2000'],
            'signature_html' => ['nullable', 'string', 'max:200000'],
            'status' => ['nullable', Rule::in(['active', 'disabled'])],
            'is_private' => ['nullable', 'boolean'],
            'aliases' => ['nullable', 'array', 'max:20'],
            'aliases.*.address' => ['required', 'email:rfc', 'max:190', 'distinct:ignore_case'],
            'aliases.*.name' => ['nullable', 'string', 'max:190'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', Rule::exists('users', 'id')->whereIn('role', ['admin', 'super_admin'])],
        ];
    }

    private function check(MailAccount $account, MailConnector $connector, MailSync $sync): ?string
    {
        try {
            $imap = $connector->imap($account);
            try {
                $account->sent_folder = $sync->findSentFolder($imap);
            } finally {
                $imap->logout();
            }

            return null;
        } catch (Throwable $e) {
            return $e->getMessage();
        }
    }

    public function store(Request $request, PermissionService $permissions, MailConnector $connector, MailSync $sync, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $data = $request->validate($this->rules(), ['address.unique' => 'This mailbox is already added.']);
        $address = strtolower(trim($data['address']));
        $account = new MailAccount([
            'address' => $address,
            'display_name' => $data['display_name'] ?? null,
            'username' => $address,
            'password' => $data['password'],
            'imap_host' => $data['imap_host'] ?? 'imap.hostinger.com',
            'imap_port' => $data['imap_port'] ?? 993,
            'imap_encryption' => 'ssl',
            'smtp_host' => $data['smtp_host'] ?? 'smtp.hostinger.com',
            'smtp_port' => $data['smtp_port'] ?? 465,
            'smtp_encryption' => (int) ($data['smtp_port'] ?? 465) === 587 ? 'tls' : 'ssl',
            'signature' => $data['signature'] ?? null,
            'signature_html' => SignatureHtml::clean($data['signature_html'] ?? null),
            'status' => 'active',
            'is_private' => (bool) ($data['is_private'] ?? false),
            'aliases' => $this->aliases($data['aliases'] ?? [], $address),
            'created_by' => $request->user()->id,
        ]);
        if ($account->is_private && empty($data['user_ids'])) {
            return back()->withErrors(['user_ids' => 'A private mailbox needs at least one person who can open it (tick yourself for your own mailbox).'])->withInput($request->except('password'));
        }
        if ($error = $this->check($account, $connector, $sync)) {
            return back()->withErrors(['password' => 'Could not log in to '.$address.': '.$error])->withInput($request->except('password'));
        }
        $account->save();
        $account->users()->sync($data['user_ids'] ?? []);
        $audit->record('mail.account_added', 'mail', 'Mailbox '.$address.' added to the panel.', [], ['address' => $address, 'users' => $data['user_ids'] ?? []], $account, $request);

        return back()->with('success', "Mailbox {$address} added - login works. Mail is read every 2 minutes.");
    }

    public function update(Request $request, MailAccount $account, PermissionService $permissions, MailConnector $connector, MailSync $sync, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $this->manage($request, $account);
        $data = $request->validate($this->rules($account));
        $privateAfter = array_key_exists('is_private', $data) && $data['is_private'] !== null ? (bool) $data['is_private'] : (bool) $account->is_private;
        $usersAfter = array_key_exists('user_ids', $data) ? ($data['user_ids'] ?? []) : $account->users()->pluck('users.id')->all();
        if ($privateAfter && empty($usersAfter)) {
            return back()->withErrors(['user_ids' => 'A private mailbox needs at least one person who can open it.']);
        }
        if ($privateAfter && !in_array((int) $request->user()->id, array_map('intval', $usersAfter), true)) {
            return back()->withErrors(['user_ids' => 'Tick yourself too - otherwise you could no longer open or change this private mailbox.']);
        }
        $before = ['users' => $account->users()->pluck('users.id')->all(), 'status' => $account->status, 'private' => (bool) $account->is_private];
        $account->fill(collect($data)->only(['display_name', 'signature', 'status', 'imap_host', 'imap_port', 'smtp_host', 'smtp_port'])->filter(fn ($v) => $v !== null)->all());
        if (array_key_exists('signature', $data)) {
            $account->signature = $data['signature'];
        }
        if (array_key_exists('signature_html', $data)) {
            $account->signature_html = SignatureHtml::clean($data['signature_html']);
        }
        $account->is_private = $privateAfter;
        if (array_key_exists('aliases', $data)) {
            $account->aliases = $this->aliases($data['aliases'] ?? [], $account->address);
        }
        if (!empty($data['password'])) {
            $account->password = $data['password'];
            if ($error = $this->check($account, $connector, $sync)) {
                return back()->withErrors(['password' => 'The new password does not work: '.$error]);
            }
            $account->last_error = null;
        }
        $account->save();
        if (array_key_exists('user_ids', $data)) {
            $account->users()->sync($data['user_ids'] ?? []);
        }
        $audit->record('mail.account_changed', 'mail', 'Mailbox '.$account->address.' changed'.(!empty($data['password']) ? ' (new password)' : '').'.', $before, ['users' => $account->users()->pluck('users.id')->all(), 'status' => $account->status, 'private' => (bool) $account->is_private], $account, $request);

        return back()->with('success', 'Mailbox '.$account->address.' saved.');
    }

    public function test(Request $request, MailAccount $account, PermissionService $permissions, MailConnector $connector, MailSync $sync): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $this->manage($request, $account);
        if ($error = $this->check($account, $connector, $sync)) {
            $account->forceFill(['last_error' => mb_substr($error, 0, 1000)])->save();

            return back()->withErrors(['mail' => $account->address.': '.$error]);
        }
        $account->save();
        try {
            $n = $sync->sync($account);
        } catch (Throwable $e) {
            return back()->withErrors(['mail' => $account->address.': '.$e->getMessage()]);
        }

        return back()->with('success', $account->address.': login works. '.$n['inbox'].' new mail read.');
    }

    public function destroy(Request $request, MailAccount $account, PermissionService $permissions, AuditLogService $audit): RedirectResponse
    {
        $this->superAdmin($request, $permissions);
        $this->manage($request, $account);
        $request->validate(['confirm' => ['required', 'in:'.$account->address]], ['confirm.in' => 'Type the mailbox address to remove it.']);
        $address = $account->address;
        DB::transaction(function () use ($account) {
            $ids = DB::table('mail_messages')->where('mail_account_id', $account->id)->pluck('id');
            DB::table('mail_attachments')->whereIn('mail_message_id', $ids)->delete();
            DB::table('mail_messages')->where('mail_account_id', $account->id)->delete();
            DB::table('mail_folders')->where('mail_account_id', $account->id)->delete();
            DB::table('mail_account_user')->where('mail_account_id', $account->id)->delete();
            $account->delete();
        });
        Storage::disk('local')->deleteDirectory('mail/'.$account->id);
        $audit->record('mail.account_removed', 'mail', 'Mailbox '.$address.' removed from the panel (the mailbox itself stays at Hostinger).', ['address' => $address], [], null, $request);

        return back()->with('success', $address.' removed from the panel. The mailbox and its mail stay at Hostinger.');
    }
}
