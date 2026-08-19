<?php

namespace App\Services\V2;

use App\Services\V2\LabelAccess\LabelTeamAccessService;

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

        /*
         * LABEL_TEAM_VIEW_PERMISSION
         *
         * Team members require explicit releases.view.
         * Normal Artist / Label Owner / Admin behaviour
         * continues through the existing code below.
         */
        $teamAccess = app(
            LabelTeamAccessService::class
        );

        if ($teamAccess->membership($user)) {
            abort_unless(
                $teamAccess->allows(
                    $user,
                    'releases.view'
                ),
                403,
                'You do not have permission to view releases.'
            );
        }

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

        /*
         * LABEL_TEAM_UPDATE_PERMISSION
         */
        $teamAccess = app(
            LabelTeamAccessService::class
        );

        if ($teamAccess->membership($user)) {
            abort_unless(
                $teamAccess->allows(
                    $user,
                    'releases.edit'
                ),
                403,
                'You do not have permission to edit releases.'
            );
        }

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

        /*
         * LABEL_TEAM_SUBMIT_PERMISSION
         *
         * Standard Team Users cannot receive this permission.
         * Advanced users receive it only when explicitly enabled.
         */
        $teamAccess = app(
            LabelTeamAccessService::class
        );

        if ($teamAccess->membership($user)) {
            abort_unless(
                $teamAccess->allows(
                    $user,
                    'releases.submit'
                ),
                403,
                'You do not have permission to submit releases.'
            );
        }

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

        /*
         * LABEL_TEAM_RELEASE_SCOPE
         *
         * Team User release access is resolved from the
         * central strict two-tier catalogue scope.
         *
         * Draft privacy remains enforced:
         * another user's draft is never exposed.
         */
        $teamAccess = app(
            LabelTeamAccessService::class
        );

        $membership =
            $teamAccess->membership($user);

        if ($membership) {
            if (
                (string) $release->status === 'draft'
                && (int) $release->created_by
                    !== (int) $user->id
            ) {
                return false;
            }

            $labelIds =
                $teamAccess
                    ->accessibleLabelIds($user);

            if (
                !$release->label_id
                || !$labelIds->contains(
                    (int) $release->label_id
                )
            ) {
                return false;
            }

            /*
             * Entire-label Team Users can access every
             * release inside their allowed label boundary.
             */
            if (
                $membership->scope_level
                === 'entire_label'
            ) {
                return true;
            }

            /*
             * Explicit Track scope grants visibility to the
             * parent Release shell so the assigned Track can
             * be reached.
             *
             * It does NOT grant access to sibling Tracks.
             */
            $trackIds =
                $teamAccess->accessibleTrackIds($user);

            if (
                $trackIds->isNotEmpty()
                && $release->tracks()
                    ->whereIn('id', $trackIds)
                    ->exists()
            ) {
                return true;
            }

            /*
             * Selected-scope releases attached to an Artist
             * require that Artist to be explicitly accessible.
             */
            if ($release->artist_id) {
                return $teamAccess
                    ->accessibleArtistIds($user)
                    ->contains(
                        (int) $release->artist_id
                    );
            }

            /*
             * Operator catalogue releases can have no Artist.
             * In that case the explicitly accessible Label
             * boundary controls visibility.
             */
            return $labelIds->contains(
                (int) $release->label_id
            );
        }

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
             * MIXX TUNE STRICT TWO-TIER LABEL ACCESS
             *
             * Each owned master label exposes:
             *   - itself
             *   - direct child labels
             *
             * No recursive grandchildren.
             */
            $ownedLabelIds = Label::query()
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            if ($ownedLabelIds->isEmpty()) {
                return false;
            }

            $directChildIds = Label::query()
                ->whereIn(
                    'parent_label_id',
                    $ownedLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

            $accessibleLabelIds =
                $ownedLabelIds
                    ->merge($directChildIds)
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
