<?php

namespace App\Services\V2\LabelAccess;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Track;
use App\Models\LabelAccess\LabelTeamMember;
use App\Models\LabelAccess\LabelTeamScope;
use App\Models\User;
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
     * No recursive descendants.
     */
    public function accessibleLabelIds(
        User $user
    ): Collection {
        $label = $this->effectiveLabel($user);

        if (!$label) {
            return collect();
        }

        $base = collect([
            (int) $label->id,
        ]);

        /*
         * A direct child-label owner sees only
         * its own label.
         */
        if ($label->parent_label_id !== null) {
            return $base;
        }

        $childIds = Label::query()
            ->where(
                'parent_label_id',
                $label->id
            )
            ->whereNull('deleted_at')
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->values();

        /*
         * Master owner receives complete
         * strict two-tier catalogue.
         */
        if ($this->isOwner($user, $label)) {
            return $base
                ->merge($childIds)
                ->unique()
                ->values();
        }

        $member = $this->membership($user);

        if (!$member) {
            return collect();
        }

        if (
            $member->scope_level
            === 'entire_label'
        ) {
            return $base
                ->merge($childIds)
                ->unique()
                ->values();
        }

        /*
         * The master/root label itself remains
         * the Team User's base catalogue context.
         *
         * Child labels require explicit scope.
         */
        $selectedChildIds = $member->scopes
            ->filter(
                fn (LabelTeamScope $scope) =>
                    $scope->scope_type === 'label'
            )
            ->pluck('scope_id')
            ->map(
                fn ($id) => (int) $id
            )
            ->filter(
                fn ($id) =>
                    $childIds->contains($id)
            )
            ->values();

        return $base
            ->merge($selectedChildIds)
            ->unique()
            ->values();
    }

    /**
     * Artist IDs visible to this user.
     *
     * Selected scope is explicit:
     * selecting a child label exposes artists
     * directly belonging to that child label;
     * individually selected artists are also
     * included.
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

        $artistsFromSelectedLabels =
            Artist::query()
                ->whereIn(
                    'label_id',
                    $selectedLabelIds
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
        $validSelectedArtistIds =
            Artist::query()
                ->whereIn(
                    'id',
                    $selectedArtistIds
                )
                ->whereIn(
                    'label_id',
                    collect([
                        (int) $label->id,
                    ])->merge(
                        Label::query()
                            ->where(
                                'parent_label_id',
                                $label->id
                            )
                            ->whereNull(
                                'deleted_at'
                            )
                            ->pluck('id')
                    )
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
        $rootLabelIds = collect([
            (int) $label->id,
        ]);

        if ($label->parent_label_id === null) {
            $rootLabelIds = $rootLabelIds
                ->merge(
                    Label::query()
                        ->where(
                            'parent_label_id',
                            $label->id
                        )
                        ->whereNull('deleted_at')
                        ->pluck('id')
                )
                ->map(fn ($id) => (int) $id)
                ->unique()
                ->values();
        }

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
        $label = $this->effectiveLabel($user);

        if (!$label) {
            return false;
        }

        /*
         * Owner / Team User can only operate inside:
         *
         * Master Label
         *   -> direct artists
         *   -> direct child labels
         *
         * No recursive grandchildren.
         */
        $allowedLabelIds = collect([
            (int) $label->id,
        ]);

        if ($label->parent_label_id === null) {
            $children =
                Label::query()
                    ->where(
                        'parent_label_id',
                        $label->id
                    )
                    ->whereNull('deleted_at')
                    ->pluck('id')
                    ->map(fn ($id) => (int) $id);

            $allowedLabelIds =
                $allowedLabelIds
                    ->merge($children)
                    ->unique()
                    ->values();
        }

        if (
            !$allowedLabelIds->contains(
                (int) $artist->label_id
            )
        ) {
            return false;
        }

        if ($this->isOwner($user, $label)) {
            return true;
        }

        $member = $this->membership($user);

        if (!$member) {
            return false;
        }

        if (
            $member->scope_level
            === 'entire_label'
        ) {
            return true;
        }

        return $member->scopes
            ->contains(
                fn (LabelTeamScope $scope) =>
                    $scope->scope_type === 'artist'
                    && (int) $scope->scope_id
                        === (int) $artist->id
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

        $allowedChildLabelIds =
            Label::query()
                ->where(
                    'parent_label_id',
                    $ownerLabel->id
                )
                ->whereNull('deleted_at')
                ->pluck('id')
                ->map(fn ($id) => (int) $id);

        $allowedLabelIds =
            collect([
                (int) $ownerLabel->id,
            ])
                ->merge(
                    $allowedChildLabelIds
                )
                ->unique()
                ->values();

        $validLabelIds =
            $labelIds->filter(
                fn ($id) =>
                    $allowedChildLabelIds
                        ->contains($id)
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
