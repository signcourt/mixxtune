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

    /** ids of the mailboxes given to this person */
    public function assignedIds(?User $user): Collection
    {
        if (!$user || !$this->ready()) {
            return collect();
        }

        return DB::table('mail_account_user')->where('user_id', $user->id)->pluck('mail_account_id')->map(fn ($v) => (int) $v);
    }

    /*
     * W100: Super Admin sees every shared mailbox plus his own; a private mailbox only
     * shows for the people it is given to. Own mailboxes come first and carry mine = true.
     *
     * @return Collection<int, MailAccount>
     */
    public function accountsFor(?User $user): Collection
    {
        if (!$user || !$this->ready()) {
            return collect();
        }
        $mine = $this->assignedIds($user);
        $q = MailAccount::query()->where('status', 'active')->orderBy('address');
        if ($this->isSuperAdmin($user)) {
            $q->where(fn ($w) => $w->where('is_private', false)->orWhereIn('id', $mine->all() ?: [0]));
        } else {
            $q->whereIn('id', $mine->all() ?: [0]);
        }

        return $q->get()
            ->sortBy(fn (MailAccount $a) => ($mine->contains((int) $a->id) ? '0' : '1').$a->address)
            ->values();
    }

    public function canUse(?User $user, MailAccount $account): bool
    {
        if (!$user || $account->status !== 'active') {
            return false;
        }
        $assigned = DB::table('mail_account_user')->where('user_id', $user->id)->where('mail_account_id', $account->id)->exists();

        return $assigned || ($this->isSuperAdmin($user) && !$account->is_private);
    }

    /* W100: a private mailbox is managed (password, people, remove) only by its own people */
    public function canManage(?User $user, MailAccount $account): bool
    {
        return $this->isSuperAdmin($user)
            && (!$account->is_private || DB::table('mail_account_user')->where('user_id', $user->id)->where('mail_account_id', $account->id)->exists());
    }

    /** sidebar: how many mailboxes and unread inbox mails this user has */
    public function summary(?User $user): ?array
    {
        if (!$user || !$this->ready() || !in_array($this->permissions->role($user), ['admin', 'super_admin'], true)) {
            return null;
        }
        $list = $this->accountsFor($user);
        $ids = $list->pluck('id');
        /* W100: the badge counts the person's own mailboxes when he has one (a Super Admin with his own mail) */
        $assigned = $this->assignedIds($user);
        $own = $ids->filter(fn ($id) => $assigned->contains((int) $id))->values();
        $badgeIds = $own->isNotEmpty() ? $own : $ids;

        return [
            'accounts' => $ids->count(),
            'mine' => $own->count(),
            'unread' => $badgeIds->isEmpty() ? 0 : DB::table('mail_messages')->whereIn('mail_account_id', $badgeIds)->where('folder', 'inbox')->where('seen', false)->where(fn ($w) => $w->whereNull('snooze_until')->orWhere('snooze_until', '<=', now()))->count(),
        ];
    }
}
