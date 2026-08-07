<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\User;

class ReleaseAccessService
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly AdminAssignmentService $assignments
    ) {
    }

    public function role(?User $user): string
    {
        return $this->permissions->role($user);
    }

    public function authorizeView(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.view'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot access this release.'
        );
    }

    public function authorizeUpdate(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.update'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot manage this release.'
        );

        abort_unless(
            $this->isEditable($release),
            403,
            'This release is locked for editing.'
        );
    }

    public function authorizeSubmit(
        ?User $user,
        Release $release
    ): void {
        $this->permissions->authorize(
            $user,
            'releases.submit'
        );

        abort_unless(
            $this->canAccess($user, $release),
            403,
            'You cannot submit this release.'
        );

        abort_unless(
            $this->isEditable($release),
            403,
            'This release has already been submitted.'
        );
    }

    public function canAccess(
        ?User $user,
        Release $release
    ): bool {
        if (!$user) {
            return false;
        }

        $role = $this->role($user);

        if ($role === 'super_admin') {
            return true;
        }

        if ($role === 'artist') {
            $artist = Artist::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            return $artist
                && (int) $release->artist_id
                    === (int) $artist->id;
        }

        if ($role === 'label') {
            $label = Label::query()
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->first();

            return $label
                && (int) $release->label_id
                    === (int) $label->id;
        }

        if ($role === 'admin') {
            return $this->adminCanAccess(
                $user,
                $release
            );
        }

        return false;
    }

    public function isEditable(
        Release $release
    ): bool {
        return in_array(
            $release->status,
            [
                'draft',
                'changes_requested',
                'rejected',
            ],
            true
        );
    }

    private function adminCanAccess(
        User $user,
        Release $release
    ): bool {
        return $this->assignments->canAccessRelease(
            $user,
            $release->artist_id
                ? (int) $release->artist_id
                : null,
            $release->label_id
                ? (int) $release->label_id
                : null
        );
    }
}
