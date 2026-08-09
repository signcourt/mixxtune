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

        /*
         * Drafts are creator-private.
         *
         * Run this before the super-admin shortcut so an unpublished
         * working draft cannot leak into another dashboard or through
         * a direct release URL.
         */
        if ($release->status === 'draft') {
            return (int) $release->created_by
                === (int) $user->id;
        }

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
            /*
             * Mixx Tune hierarchy is strictly two-tier.
             *
             * A Label login may access:
             * - releases of labels directly owned by it
             * - releases of direct child/sub-labels
             *
             * Grandchildren and unrelated labels remain
             * outside the access boundary.
             */
            $rootLabelIds = Label::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            if ($rootLabelIds->isEmpty()) {
                return false;
            }

            $childLabelIds = Label::query()
                ->whereIn(
                    'parent_label_id',
                    $rootLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            $accessibleLabelIds =
                $rootLabelIds
                    ->merge($childLabelIds)
                    ->unique()
                    ->values();

            return $release->label_id
                && $accessibleLabelIds->contains(
                    (int) $release->label_id
                );
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
