<?php

namespace App\Services\V2;

use App\Models\System\UserPanelPermission;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * W81 MIXXTUNE_PANEL_ACCESS
 *
 * Panel access is decided per sidebar tab:
 *   1. the role default (Label / Artist / Manager) - set by the Super Admin
 *      on the Panel Access page; with no saved row the standard default
 *      below is used (the same access every role had before W81);
 *   2. an "allow" or "block" for one account, set on Edit User.
 * Super Admin always sees everything. Label team members keep their own
 * team permissions (not handled here).
 *
 * The old per-user checkboxes (user_panel_permissions) are no longer
 * edited. Only a box that gave a Manager MORE than its role (for example
 * Wallet) still counts, so nobody loses an extra they were given. An old
 * unticked box no longer blocks anything: opening Edit User used to save
 * all boxes unticked, which hid most tabs by mistake.
 */
class PanelAccess
{
    /* role => group of tabs */
    public const ROLES = ['label' => 'owner', 'artist' => 'owner', 'admin' => 'manager'];

    public const ROLE_NAMES = ['label' => 'Label', 'artist' => 'Artist', 'admin' => 'Manager'];

    /* permissions every account of the group keeps (not a sidebar choice) */
    private const ALWAYS = [
        'owner' => ['dashboard.view', 'contributors.view', 'contributors.manage', 'splits.view', 'splits.manage', 'profile.update', 'profile.view'],
        'manager' => ['dashboard.view', 'releases.view', 'contributors.view', 'contributors.manage', 'splits.view', 'splits.manage'],
    ];

    /* key => [name, help, permissions, standard default per role, only for roles] */
    private const TABS = [
        'owner' => [
            'releases' => ['Releases', 'All releases, drafts, changes requested, rejected', ['releases.view'], ['label' => true, 'artist' => true]],
            'create_release' => ['Create release', 'Create, edit and send releases', ['releases.create', 'releases.update', 'releases.submit'], ['label' => true, 'artist' => true]],
            'catalogue' => ['Catalogue', 'Live catalogue', ['catalogue.view'], ['label' => true, 'artist' => true]],
            'wallet' => ['Wallet', 'Balance, transactions and invoices', ['wallet.view'], ['label' => true, 'artist' => true]],
            'withdrawals' => ['Withdrawals', 'Ask for a payout', ['withdrawals.view', 'withdrawals.create'], ['label' => true, 'artist' => true]],
            'royalties' => ['Royalties', 'Royalty statements', ['royalties.view'], ['label' => true, 'artist' => true]],
            'reports' => ['Reports & Analytics', 'Revenue reports and analytics', ['reports.view'], ['label' => true, 'artist' => true]],
            'legal' => ['Legal Operations', 'Copyright, platform and legal cases', ['legal.operations.view'], ['label' => true, 'artist' => true]],
            'support' => ['Support Tickets', 'Open and follow support tickets', ['support.create'], ['label' => true, 'artist' => true]],
            'artists' => ['Artists', 'Artists of the label', ['artists.view', 'artists.manage'], ['label' => true], ['label']],
            'revenue_sharing' => ['Revenue Sharing', 'Split revenue with artists', ['revenue_sharing.manage'], ['label' => true], ['label']],
            'team' => ['User Access', 'Team members of the label', ['team.manage'], ['label' => true], ['label']],
        ],
        'manager' => [
            'labels' => ['Labels', 'Label list', ['labels.view'], ['admin' => true]],
            'artists' => ['Artists', 'Artist list', ['artists.view'], ['admin' => true]],
            'release_review' => ['Release review', 'Review, approve, reject, request changes, processing, audio checks', ['releases.review', 'releases.approve', 'releases.reject', 'releases.request_changes', 'releases.processing', 'audio_validation.view', 'audio_validation.run', 'audio_validation.override'], ['admin' => true]],
            'create_release' => ['Create release', 'Create and edit releases', ['releases.create', 'releases.update'], ['admin' => true]],
            'distribution' => ['Distribution', 'Delivery to stores, retry, live, takedown', ['delivery.view', 'delivery.manage', 'delivery.retry', 'delivery.mark_delivered', 'delivery.mark_live', 'delivery.mark_failed', 'delivery.takedown'], ['admin' => true]],
            'identifiers' => ['ISRC & UPC', 'Assign and generate codes', ['identifiers.view', 'identifiers.assign', 'identifiers.generate', 'identifiers.bulk_assign', 'identifiers.unassign'], ['admin' => true]],
            'catalogue' => ['Catalogue', 'Catalogue, sync and export', ['catalogue.view', 'catalogue.manage', 'catalogue.sync', 'catalogue.export'], ['admin' => true]],
            'reports' => ['Reports & Analytics', 'Reports, analytics, revenue imports', ['reports.view'], ['admin' => true]],
            'royalties' => ['Royalties', 'Royalty statements', ['royalties.view'], ['admin' => false]],
            'wallet' => ['Wallet', 'Wallets of accounts', ['wallet.view'], ['admin' => false]],
            'withdrawals' => ['Withdrawals & KYC reviews', 'Withdraw requests and KYC reviews', ['withdrawals.view', 'withdrawals.manage'], ['admin' => false]],
            'support' => ['Support Tickets', 'Answer support tickets', ['support.manage'], ['admin' => true]],
            'legal' => ['Legal Operations', 'Legal cases', ['legal.operations.view'], ['admin' => true]],
            'settings' => ['Settings', 'Settings and audit logs', ['settings.view', 'settings.manage', 'audit_logs.view'], ['admin' => false]],
        ],
    ];

    /* old checkbox => tabs it stands for */
    private const LEGACY = [
        'can_view_catalogue' => ['catalogue'],
        'can_create_releases' => ['releases', 'create_release'],
        'can_manage_releases' => ['release_review'],
        'can_view_reports' => ['reports'],
        'can_view_royalties' => ['royalties'],
        'can_manage_wallet' => ['wallet'],
        'can_manage_withdrawals' => ['withdrawals'],
        'can_manage_users' => ['labels', 'artists'],
        'can_manage_support' => ['support'],
        'can_manage_settings' => ['settings'],
        'can_manage_delivery' => ['distribution'],
        'can_manage_identifiers' => ['identifiers'],
    ];

    private ?array $savedDefaults = null;

    private ?bool $ready = null;

    private array $overrideCache = [];

    private array $legacyCache = [];

    public function ready(): bool
    {
        return $this->ready ??= Schema::hasTable('panel_role_defaults') && Schema::hasTable('panel_access_overrides');
    }

    public function handles(string $role): bool
    {
        return isset(self::ROLES[$role]) && $this->ready();
    }

    /** tab key => [name, help] for one role */
    public function tabs(string $role): array
    {
        $group = self::ROLES[$role] ?? null;
        if (!$group) {
            return [];
        }

        $out = [];
        foreach (self::TABS[$group] as $key => $tab) {
            $only = $tab[4] ?? null;
            if ($only && !in_array($role, $only, true)) {
                continue;
            }
            $out[$key] = $tab;
        }

        return $out;
    }

    public function standardDefault(string $role, string $tab): bool
    {
        return (bool) ($this->tabs($role)[$tab][3][$role] ?? false);
    }

    /** role => tab => bool (saved value, else standard) */
    public function roleDefaults(string $role): array
    {
        $saved = $this->saved()[$role] ?? [];
        $out = [];
        foreach (array_keys($this->tabs($role)) as $key) {
            $out[$key] = array_key_exists($key, $saved) ? $saved[$key] : $this->standardDefault($role, $key);
        }

        return $out;
    }

    /** rows for the Edit User card */
    public function tabsFor(User $user): array
    {
        $role = (string) $user->role;
        $defaults = $this->roleDefaults($role);
        $overrides = $this->overrides((int) $user->id);
        $legacy = $this->legacyExtras($user, $role);

        $rows = [];
        foreach ($this->tabs($role) as $key => $tab) {
            $override = $overrides[$key] ?? null;
            $rows[] = [
                'key' => $key,
                'name' => $tab[0],
                'help' => $tab[1],
                'role_default' => $defaults[$key],
                'old_extra' => in_array($key, $legacy, true),
                'override' => $override === null ? 'default' : ($override ? 'allow' : 'block'),
                'allowed' => $this->decide($defaults[$key], $override, in_array($key, $legacy, true)),
            ];
        }

        return $rows;
    }

    /** effective permission list for a Label / Artist / Manager account */
    public function permissions(User $user): array
    {
        $role = (string) $user->role;
        $group = self::ROLES[$role];
        $defaults = $this->roleDefaults($role);
        $overrides = $this->overrides((int) $user->id);
        $legacy = $this->legacyExtras($user, $role);

        $permissions = self::ALWAYS[$group];
        foreach ($this->tabs($role) as $key => $tab) {
            if ($this->decide($defaults[$key], $overrides[$key] ?? null, in_array($key, $legacy, true))) {
                $permissions = array_merge($permissions, $tab[2]);
            }
        }

        return array_values(array_unique($permissions));
    }

    /** @param array<string,bool> $values */
    public function saveRoleDefaults(string $role, array $values, int $actorId): array
    {
        $before = $this->roleDefaults($role);
        $changed = [];
        foreach ($this->tabs($role) as $key => $tab) {
            if (!array_key_exists($key, $values)) {
                continue;
            }
            $value = (bool) $values[$key];
            DB::table('panel_role_defaults')->updateOrInsert(
                ['role' => $role, 'tab' => $key],
                ['allowed' => $value, 'updated_by' => $actorId, 'updated_at' => now(), 'created_at' => now()]
            );
            if ($before[$key] !== $value) {
                $changed[$key] = ['from' => $before[$key], 'to' => $value];
            }
        }
        $this->savedDefaults = null;

        return $changed;
    }

    /** @param array<string,string> $choices tab => default|allow|block */
    public function saveOverrides(User $user, array $choices, int $actorId): array
    {
        $before = $this->overrides((int) $user->id);
        $changed = [];
        foreach (array_keys($this->tabs((string) $user->role)) as $key) {
            if (!array_key_exists($key, $choices)) {
                continue;
            }
            $choice = $choices[$key];
            $old = $before[$key] ?? null;
            if ($choice === 'default') {
                DB::table('panel_access_overrides')->where('user_id', $user->id)->where('tab', $key)->delete();
                $new = null;
            } else {
                $new = $choice === 'allow';
                DB::table('panel_access_overrides')->updateOrInsert(
                    ['user_id' => $user->id, 'tab' => $key],
                    ['allowed' => $new, 'updated_by' => $actorId, 'updated_at' => now(), 'created_at' => now()]
                );
            }
            if ($old !== $new) {
                $changed[$key] = ['from' => $this->word($old), 'to' => $this->word($new)];
            }
        }
        unset($this->overrideCache[(int) $user->id]);

        return $changed;
    }

    private function word(?bool $v): string
    {
        return $v === null ? 'role default' : ($v ? 'allow' : 'block');
    }

    private function decide(bool $roleDefault, ?bool $override, bool $oldExtra): bool
    {
        if ($override !== null) {
            return $override;
        }

        return $roleDefault || $oldExtra;
    }

    private function saved(): array
    {
        if ($this->savedDefaults !== null) {
            return $this->savedDefaults;
        }
        $out = [];
        if ($this->ready()) {
            foreach (DB::table('panel_role_defaults')->get(['role', 'tab', 'allowed']) as $row) {
                $out[$row->role][$row->tab] = (bool) $row->allowed;
            }
        }

        return $this->savedDefaults = $out;
    }

    private function overrides(int $userId): array
    {
        if (!$this->ready()) {
            return [];
        }

        return $this->overrideCache[$userId] ??= DB::table('panel_access_overrides')->where('user_id', $userId)->pluck('allowed', 'tab')
            ->map(fn ($v) => (bool) $v)->all();
    }

    /** tabs an old ticked box gave beyond the standard role access */
    private function legacyExtras(User $user, string $role): array
    {
        $key = $user->id.'|'.$role;
        if (array_key_exists($key, $this->legacyCache)) {
            return $this->legacyCache[$key];
        }
        if (!Schema::hasTable('user_panel_permissions')) {
            return $this->legacyCache[$key] = [];
        }
        $record = UserPanelPermission::query()->where('user_id', $user->id)->first();
        if (!$record) {
            return $this->legacyCache[$key] = [];
        }
        $tabs = $this->tabs($role);
        $out = [];
        foreach (self::LEGACY as $field => $keys) {
            if (!(bool) $record->{$field}) {
                continue;
            }
            foreach ($keys as $key) {
                if (isset($tabs[$key]) && !$this->standardDefault($role, $key)) {
                    $out[] = $key;
                }
            }
        }

        return $this->legacyCache[$key] = array_values(array_unique($out));
    }
}
