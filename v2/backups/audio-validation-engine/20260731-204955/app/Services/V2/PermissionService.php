<?php

namespace App\Services\V2;

use App\Models\User;

class PermissionService
{
    private array $permissions = [
        'super_admin' => ['*'],

        'admin' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'contributors.view',
            'contributors.manage',
            'splits.view',
            'splits.manage',
            'releases.review',
            'releases.approve',
            'releases.reject',
            'releases.request_changes',
            'releases.processing',
            'artists.view',
            'labels.view',
            'catalogue.view',
            'catalogue.manage',
            'catalogue.sync',
            'catalogue.export',
            'reports.view',
            'delivery.manage',
            'identifiers.view',
            'identifiers.assign',
            'identifiers.generate',
            'identifiers.bulk_assign',
            'identifiers.unassign',
            'delivery.view',
            'delivery.retry',
            'delivery.mark_delivered',
            'delivery.mark_live',
            'delivery.mark_failed',
            'delivery.takedown',
            'support.manage',
        ],

        'label' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'releases.submit',
            'artists.view',
            'artists.manage',
            'catalogue.view',
            'reports.view',
            'royalties.view',
            'wallet.view',
            'withdrawals.create',
            'support.create',
            'profile.update',
        ],

        'artist' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'releases.submit',
            'catalogue.view',
            'reports.view',
            'royalties.view',
            'wallet.view',
            'withdrawals.create',
            'support.create',
            'profile.update',
        ],
    ];

    public function role(?User $user): string
    {
        $role = strtolower(
            trim((string) ($user?->role ?? 'artist'))
        );

        return array_key_exists($role, $this->permissions)
            ? $role
            : 'artist';
    }

    public function permissions(?User $user): array
    {
        return $this->permissions[$this->role($user)] ?? [];
    }

    public function allows(
        ?User $user,
        string $permission
    ): bool {
        $permissions = $this->permissions($user);

        return in_array('*', $permissions, true)
            || in_array($permission, $permissions, true);
    }

    public function denies(
        ?User $user,
        string $permission
    ): bool {
        return !$this->allows($user, $permission);
    }

    public function authorize(
        ?User $user,
        string $permission
    ): void {
        abort_if(
            $this->denies($user, $permission),
            403,
            'You do not have permission to perform this action.'
        );
    }

    public function frontend(?User $user): array
    {
        return [
            'role' => $this->role($user),
            'permissions' => $this->permissions($user),
            'isSuperAdmin' =>
                $this->role($user) === 'super_admin',
        ];
    }
}
