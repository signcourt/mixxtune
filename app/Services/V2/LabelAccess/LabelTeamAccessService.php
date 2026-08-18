<?php

namespace App\Services\V2\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Track;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\LabelAccess\LabelTeamScope;
use App\Models\User;
use App\Services\V2\LabelHierarchyService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LabelTeamAccessService
{
    public function catalogue(): array
    {
        return config(
            'label_team_permissions.permissions',
            []
        );
    }

    public function ownedLabel(User $user): ?Label
    {
        /*
         * Resolve the user's own Label identity deterministically.
         *
         * This must support both:
         * - Master Label owners
         * - Direct Child/Sub-Label owners
         *
         * Master-only restrictions belong in assertOwner(),
         * not here.
         *
         * Legacy production data may contain more than one
         * label row for one user, so preserve deterministic
         * oldest-label resolution until explicit switching exists.
         */
        return Label::query()
            ->where('user_id', $user->id)
            ->whereNull('deleted_at')
            ->orderBy('id')
            ->first();
    }

    /**
     * Return a Team membership regardless of status.
     *
     * Used by the authorization layer to distinguish:
     * - no Team relationship
     * - active Team relationship
     * - suspended/disabled Team relationship
     */
    public function membershipRecord(
        User $user
    ): ?LabelTeamMember {
        return LabelTeamMember::query()
            ->with(['label', 'scopes'])
            ->where('user_id', $user->id)
            ->first();
    }

    public function membership(User $user): ?LabelTeamMember
    {
        return LabelTeamMember::query()
            ->with(['label', 'scopes'])
            ->where('user_id', $user->id)
            ->where('status', 'active')
            ->first();
    }

    public function effectiveLabel(User $user): ?Label
    {
        $owned = $this->ownedLabel($user);

        if ($owned) {
            return $owned;
        }

        return $this->membership($user)?->label;
    }

    public function isOwner(
        User $user,
        ?Label $label = null
    ): bool {
        $label ??= $this->ownedLabel($user);

        return $label
            && (int) $label->user_id === (int) $user->id;
    }

    public function assertOwner(User $user): Label
    {
        $label = $this->ownedLabel($user);

        /*
         * User Access belongs ONLY to a Master Label Owner.
         *
         * Direct child/sub-label accounts cannot create or
         * manage Team Users.
         */
        if (
            !$label
            || $label->parent_label_id !== null
        ) {
            throw ValidationException::withMessages([
                'label' =>
                    'Only a Master Label Owner can manage User Access.',
            ]);
        }

        return $label;
    }

    public function allowedPermissionKeys(
        string $level
    ): array {
        if (!in_array(
            $level,
            ['standard', 'advanced'],
            true
        )) {
            throw ValidationException::withMessages([
                'permission_level' =>
                    'Invalid permission level.',
            ]);
        }

        return collect($this->catalogue())
            ->filter(
                fn (array $definition) =>
                    ($definition['owner_only'] ?? false) !== true
                    && ($definition[$level] ?? false) === true
            )
            ->keys()
            ->values()
            ->all();
    }

    public function normalizePermissions(
        string $level,
        array $requested
    ): array {
        $allowed = $this->allowedPermissionKeys(
            $level
        );

        return collect($requested)
            ->map(fn ($permission) =>
                trim((string) $permission)
            )
            ->filter()
            ->unique()
            ->filter(
                fn ($permission) =>
                    in_array(
                        $permission,
                        $allowed,
                        true
                    )
            )
            ->values()
            ->all();
    }

    public function createMember(
        User $owner,
        User $teamUser,
        string $permissionLevel,
        string $scopeLevel,
        array $permissions = [],
        array $artistIds = [],
        array $labelIds = [],
        array $trackIds = []
    ): LabelTeamMember {
        $label = $this->assertOwner($owner);

        if ((int) $teamUser->id === (int) $owner->id) {
            throw ValidationException::withMessages([
                'user' =>
                    'The Label Owner cannot be added as a Team User.',
            ]);
        }

        if (!in_array(
            $scopeLevel,
            ['entire_label', 'selected'],
            true
        )) {
            throw ValidationException::withMessages([
                'scope_level' =>
                    'Invalid catalogue scope.',
            ]);
        }

        $permissions = $this->normalizePermissions(
            $permissionLevel,
            $permissions
        );

        return DB::transaction(
            function () use (
                $label,
                $owner,
                $teamUser,
                $permissionLevel,
                $scopeLevel,
                $permissions,
                $artistIds,
                $labelIds,
                $trackIds
            ) {
                $member =
                    LabelTeamMember::query()
                        ->updateOrCreate(
                            [
                                'label_id' =>
                                    $label->id,

                                'user_id' =>
                                    $teamUser->id,
                            ],
                            [
                                'created_by' =>
                                    $owner->id,

                                'permission_level' =>
                                    $permissionLevel,

                                'scope_level' =>
                                    $scopeLevel,

                                'status' =>
                                    'active',

                                'permissions' =>
                                    $permissions,
                            ]
                        );

                $member->scopes()->delete();

                if ($scopeLevel === 'selected') {
                    $this->syncSelectedScopes(
                        $member,
                        $label,
                        $artistIds,
                        $labelIds,
                        $trackIds
                    );
                }

                return $member->fresh([
                    'user',
                    'label',
                    'scopes',
                ]);
            }
        );
    }

    public function updateMember(
        User $owner,
        LabelTeamMember $member,
        string $permissionLevel,
        string $scopeLevel,
        array $permissions = [],
        array $artistIds = [],
        array $labelIds = [],
        array $trackIds = []
    ): LabelTeamMember {
        $label = $this->assertOwner($owner);

        $this->assertMemberBelongsToLabel(
            $member,
            $label
        );

        if (!in_array(
            $scopeLevel,
            ['entire_label', 'selected'],
            true
        )) {
            throw ValidationException::withMessages([
                'scope_level' =>
                    'Invalid catalogue scope.',
            ]);
        }

        $permissions = $this->normalizePermissions(
            $permissionLevel,
            $permissions
        );

        return DB::transaction(
            function () use (
                $member,
                $label,
                $permissionLevel,
                $scopeLevel,
                $permissions,
                $artistIds,
                $labelIds,
                $trackIds
            ) {
                $member->update([
                    'permission_level' =>
                        $permissionLevel,

                    'scope_level' =>
                        $scopeLevel,

                    'permissions' =>
                        $permissions,
                ]);

                $member->scopes()->delete();

                if ($scopeLevel === 'selected') {
                    $this->syncSelectedScopes(
                        $member,
                        $label,
                        $artistIds,
                        $labelIds,
                        $trackIds
                    );
                }

                return $member->fresh([
                    'user',
                    'label',
                    'scopes',
                ]);
            }
        );
    }

    public function setStatus(
        User $owner,
        LabelTeamMember $member,
        string $status
    ): LabelTeamMember {
        $label = $this->assertOwner($owner);

        $this->assertMemberBelongsToLabel(
            $member,
            $label
        );

        if (!in_array(
            $status,
            ['active', 'suspended', 'disabled'],
            true
        )) {
            throw ValidationException::withMessages([
                'status' =>
                    'Invalid Team User status.',
            ]);
        }

        $member->update([
            'status' => $status,
        ]);

        return $member->fresh();
    }

    public function allows(
        User $user,
        string $permission
    ): bool {
        /*
         * Label Owner retains full Label Panel access.
         */
        if ($this->ownedLabel($user)) {
            return true;
        }

        $member = $this->membership($user);

        if (!$member) {
            return false;
        }

        /*
         * Team Users can never manage User Access
         * or revenue-sharing configuration.
         */
        $definition =
            $this->catalogue()[$permission]
            ?? null;

        if (!$definition) {
            return false;
        }

        if (
            ($definition['owner_only'] ?? false)
            === true
        ) {
            return false;
        }

        return in_array(
            $permission,
            $member->permissions ?? [],
            true
        );
    }

    /**
     * Label IDs visible to this user.
     *
     * Owner:
     *   master + direct child labels.
     *
     * Child-label owner:
     *   own label only.
     *
     * Team User / entire_label:
     *   master + direct child labels.
     *
     * Team User / selected:
     *   master catalogue plus only explicitly
     *   selected direct child labels.
     *
     * Recursive descendants are included within the granted subtree.
     */
    public function accessibleLabelIds(
        User $user
    ): Collection {
        $label = $this->effectiveLabel($user);

        if (!$label) {
            return collect();
        }

        $hierarchy = app(
            LabelHierarchyService::class
        );

        /*
         * The effective label is the user's hierarchy
         * boundary.
         *
         * Owner:
         *   own level + every descendant recursively.
         *
         * Team User / entire_label:
         *   complete subtree recursively.
         *
         * Team User / selected:
         *   base label + each selected label's complete
         *   descendant subtree.
         *
         * No ancestor or sibling leakage is allowed.
         */
        $fullTreeIds = $hierarchy
            ->descendantIds(
                (int) $label->id,
                true
            );

        if ($this->isOwner($user, $label)) {
            return $fullTreeIds;
        }

        $member = $this->membership($user);

        if (!$member) {
            return collect();
        }

        if (
            $member->scope_level
            === 'entire_label'
        ) {
            return $fullTreeIds;
        }

        $selectedLabelIds = $member->scopes
            ->filter(
                fn (LabelTeamScope $scope) =>
                    $scope->scope_type === 'label'
            )
            ->pluck('scope_id')
            ->map(fn ($id) => (int) $id)
            ->filter(
                fn ($id) =>
                    $fullTreeIds->contains($id)
            )
            ->unique()
            ->values();

        $visible = collect([
            (int) $label->id,
        ]);

        foreach ($selectedLabelIds as $selectedId) {
            $visible = $visible->merge(
                $hierarchy->descendantIds(
                    (int) $selectedId,
                    true
                )
            );
        }

        /*
         * MIXX_TUNE_RECURSIVE_LEVEL_ACCESS
         *
         * Expand every directly accessible/root label into
         * itself + all descendants. This is the canonical
         * report/catalogue visibility boundary.
         */
        $directLabelIds = collect($visible
            ->filter(
                fn ($id) =>
                    $fullTreeIds->contains(
                        (int) $id
                    )
            )
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values())
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $hierarchy = app(
            \App\Services\V2\LabelHierarchyService::class
        );

        $recursiveLabelIds = collect();

        foreach ($directLabelIds as $labelId) {
            $recursiveLabelIds =
                $recursiveLabelIds->merge(
                    $hierarchy->descendantIds(
                        (int) $labelId,
                        true
                    )
                );
        }

        return $recursiveLabelIds
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    /**
     * Artist IDs visible inside the recursive
     * catalogue hierarchy.
     */
    public function accessibleArtistIds(
        User $user
    ): Collection {
        $label = $this->effectiveLabel($user);

        if (!$label) {
            return collect();
        }

        $labelIds =
            $this->accessibleLabelIds($user);

        if ($labelIds->isEmpty()) {
            return collect();
        }

        if (
            $this->isOwner($user, $label)
            || (
                ($member = $this->membership($user))
                && $member->scope_level
                    === 'entire_label'
            )
        ) {
            return Artist::query()
                ->whereIn(
                    'label_id',
                    $labelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->values();
        }

        $member ??= $this->membership($user);

        if (!$member) {
            return collect();
        }

        $selectedArtistIds =
            $member->scopes
                ->filter(
                    fn (LabelTeamScope $scope) =>
                        $scope->scope_type
                            === 'artist'
                )
                ->pluck('scope_id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->values();

        $selectedLabelIds =
            $member->scopes
                ->filter(
                    fn (LabelTeamScope $scope) =>
                        $scope->scope_type
                            === 'label'
                )
                ->pluck('scope_id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->values();

        /*
         * Every selected level grants artist access
         * to that level + its complete descendant
         * subtree.
         */
        $selectedTreeLabelIds = collect();

        $hierarchy = app(
            LabelHierarchyService::class
        );

        foreach ($selectedLabelIds as $selectedLabelId) {
            $selectedTreeLabelIds =
                $selectedTreeLabelIds->merge(
                    $hierarchy->descendantIds(
                        (int) $selectedLabelId,
                        true
                    )
                );
        }

        $selectedTreeLabelIds =
            $selectedTreeLabelIds
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();

        $artistsFromSelectedLabels =
            Artist::query()
                ->whereIn(
                    'label_id',
                    $selectedTreeLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

        /*
         * Individually selected artists are
         * validated again against the strict
         * two-tier catalogue.
         */
        $fullTreeLabelIds = app(
            LabelHierarchyService::class
        )->descendantIds(
            (int) $label->id,
            true
        );

        $validSelectedArtistIds =
            Artist::query()
                ->whereIn(
                    'id',
                    $selectedArtistIds
                )
                ->whereIn(
                    'label_id',
                    $fullTreeLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                );

        return $validSelectedArtistIds
            ->merge(
                $artistsFromSelectedLabels
            )
            ->unique()
            ->values();
    }

    /**
     * Track IDs visible to this Label owner / Team User.
     *
     * Owner and entire-label Team Users receive all tracks
     * inside their strict two-tier catalogue.
     *
     * Selected-scope Team Users receive:
     * - tracks belonging to explicitly selected artists
     * - tracks belonging to explicitly selected child labels
     * - individually selected tracks
     *
     * Selecting one track does not expose sibling tracks.
     */
    public function accessibleTrackIds(
        User $user
    ): Collection {
        $label = $this->effectiveLabel($user);

        if (!$label) {
            return collect();
        }

        $member = $this->membership($user);

        /*
         * Owner / entire-label access:
         * all tracks inside accessible artists/labels.
         */
        if (
            $this->isOwner($user, $label)
            || (
                $member
                && $member->scope_level === 'entire_label'
            )
        ) {
            $labelIds =
                $this->accessibleLabelIds($user);

            $artistIds =
                $this->accessibleArtistIds($user);

            return Track::query()
                ->whereHas(
                    'release',
                    function ($query) use (
                        $labelIds,
                        $artistIds
                    ) {
                        $query->where(
                            function ($releaseQuery) use (
                                $labelIds,
                                $artistIds
                            ) {
                                if ($labelIds->isNotEmpty()) {
                                    $releaseQuery->whereIn(
                                        'label_id',
                                        $labelIds
                                    );
                                }

                                if ($artistIds->isNotEmpty()) {
                                    if ($labelIds->isNotEmpty()) {
                                        $releaseQuery->orWhereIn(
                                            'artist_id',
                                            $artistIds
                                        );
                                    } else {
                                        $releaseQuery->whereIn(
                                            'artist_id',
                                            $artistIds
                                        );
                                    }
                                }
                            }
                        );
                    }
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id)
                ->values();
        }

        if (!$member) {
            return collect();
        }

        /*
         * Selected artist / selected label scopes already
         * resolve through accessibleArtistIds().
         */
        $artistIds =
            $this->accessibleArtistIds($user);

        $tracksFromBroaderScopes = collect();

        if ($artistIds->isNotEmpty()) {
            $tracksFromBroaderScopes =
                Track::query()
                    ->whereHas(
                        'release',
                        fn ($query) =>
                            $query->whereIn(
                                'artist_id',
                                $artistIds
                            )
                    )
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);
        }

        /*
         * Explicit individual Track scopes.
         */
        $selectedTrackIds =
            $member->scopes
                ->filter(
                    fn (LabelTeamScope $scope) =>
                        $scope->scope_type === 'track'
                )
                ->pluck('scope_id')
                ->map(fn ($id) => (int) $id)
                ->values();

        if ($selectedTrackIds->isEmpty()) {
            return $tracksFromBroaderScopes
                ->unique()
                ->values();
        }

        /*
         * Revalidate selected tracks against the strict
         * two-tier label boundary.
         */
        $rootLabelIds = app(
            LabelHierarchyService::class
        )->descendantIds(
            (int) $label->id,
            true
        );

        $validSelectedTrackIds =
            Track::query()
                ->whereIn(
                    'id',
                    $selectedTrackIds
                )
                ->whereHas(
                    'release',
                    fn ($query) =>
                        $query->whereIn(
                            'label_id',
                            $rootLabelIds
                        )
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

        return $tracksFromBroaderScopes
            ->merge($validSelectedTrackIds)
            ->unique()
            ->values();
    }

    public function canAccessTrack(
        User $user,
        Track $track
    ): bool {
        return $this
            ->accessibleTrackIds($user)
            ->contains((int) $track->id);
    }

    public function canAccessLabel(
        User $user,
        Label $label
    ): bool {
        return $this
            ->accessibleLabelIds($user)
            ->contains(
                (int) $label->id
            );
    }

    public function canAccessArtist(
        User $user,
        Artist $artist
    ): bool {
        return $this
            ->accessibleArtistIds($user)
            ->contains(
                (int) $artist->id
            );
    }

    private function syncSelectedScopes(
        LabelTeamMember $member,
        Label $ownerLabel,
        array $artistIds,
        array $labelIds,
        array $trackIds
    ): void {
        $artistIds = collect($artistIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $labelIds = collect($labelIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $trackIds = collect($trackIds)
            ->map(fn ($id) => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $allowedLabelIds = app(
            LabelHierarchyService::class
        )->descendantIds(
            (int) $ownerLabel->id,
            true
        );

        /*
         * The owner/root itself is the base context,
         * not a selectable child scope.
         */
        $allowedChildLabelIds =
            $allowedLabelIds
                ->reject(
                    fn ($id) =>
                        (int) $id
                        === (int) $ownerLabel->id
                )
                ->values();

        $validLabelIds =
            $labelIds->filter(
                fn ($id) =>
                    $allowedChildLabelIds
                        ->contains((int) $id)
            );

        $validArtistIds =
            Artist::query()
                ->whereIn(
                    'id',
                    $artistIds
                )
                ->whereIn(
                    'label_id',
                    $allowedLabelIds
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

        $validTrackIds =
            Track::query()
                ->whereIn(
                    'id',
                    $trackIds
                )
                ->whereHas(
                    'release',
                    function ($query) use (
                        $allowedLabelIds
                    ) {
                        $query->whereIn(
                            'label_id',
                            $allowedLabelIds
                        );
                    }
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

        foreach ($validArtistIds as $artistId) {
            $member->scopes()->create([
                'scope_type' => 'artist',
                'scope_id' => $artistId,
            ]);
        }

        foreach ($validLabelIds as $labelId) {
            $member->scopes()->create([
                'scope_type' => 'label',
                'scope_id' => $labelId,
            ]);
        }

        foreach ($validTrackIds as $trackId) {
            $member->scopes()->create([
                'scope_type' => 'track',
                'scope_id' => $trackId,
            ]);
        }
    }

    private function assertMemberBelongsToLabel(
        LabelTeamMember $member,
        Label $label
    ): void {
        if (
            (int) $member->label_id
            !== (int) $label->id
        ) {
            throw ValidationException::withMessages([
                'member' =>
                    'This Team User does not belong to your Label.',
            ]);
        }
    }
}
