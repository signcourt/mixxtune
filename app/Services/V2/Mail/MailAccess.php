<?php

namespace App\Services\V2\Mail;

use App\Models\Mail\MailAccount;
use App\Models\User;
use App\Services\V2\PermissionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/* W95 MIXXTUNE_PANEL_MAIL: Super Admin uses every mailbox; anyone else only the ones given to them */
class MailAccess
{
    public function __construct(private readonly PermissionService $permissions)
    {
    }

    public function ready(): bool
    {
        static $ready = null;

        return $ready ??= Schema::hasTable('mail_accounts');
    }

    public function isSuperAdmin(?User $user): bool
    {
        return $user && $this->permissions->role($user) === 'super_admin';
    }

    /** @return Collection<int, MailAccount> */
    public function accountsFor(?User $user): Collection
    {
        if (!$user || !$this->ready()) {
            return collect();
        }
        $q = MailAccount::query()->where('status', 'active')->orderBy('address');
        if (!$this->isSuperAdmin($user)) {
            $q->whereIn('id', DB::table('mail_account_user')->where('user_id', $user->id)->pluck('mail_account_id'));
        }

        return $q->get();
    }

    public function canUse(?User $user, MailAccount $account): bool
    {
        if (!$user || $account->status !== 'active') {
            return false;
        }

        return $this->isSuperAdmin($user)
            || DB::table('mail_account_user')->where('user_id', $user->id)->where('mail_account_id', $account->id)->exists();
    }

    /** sidebar: how many mailboxes and unread inbox mails this user has */
    public function summary(?User $user): ?array
    {
        if (!$user || !$this->ready() || !in_array($this->permissions->role($user), ['admin', 'super_admin'], true)) {
            return null;
        }
        $ids = $this->accountsFor($user)->pluck('id');

        return [
            'accounts' => $ids->count(),
            'unread' => $ids->isEmpty() ? 0 : DB::table('mail_messages')->whereIn('mail_account_id', $ids)->where('folder', 'inbox')->where('seen', false)->count(),
        ];
    }
}
