<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminFinancialAccessService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments
    ) {
    }

    public function labelIds(
        User $user
    ): Collection {
        return $this->assignments
            ->labelIds($user)
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();
    }

    public function artistIds(
        User $user
    ): Collection {
        return $this->assignments
            ->artistIds($user)
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();
    }

    public function applyFinancialOwnerScope(
        Builder $query,
        User $user,
        string $labelColumn = 'label_id',
        string $artistColumn = 'artist_id'
    ): Builder {
        if (
            $user->role === 'super_admin'
        ) {
            return $query;
        }

        if (
            $user->role !== 'admin'
        ) {
            return $query
                ->whereRaw('1 = 0');
        }

        $labelIds =
            $this->labelIds($user);

        $artistIds =
            $this->artistIds($user);

        if (
            $labelIds->isEmpty()
            && $artistIds->isEmpty()
        ) {
            return $query
                ->whereRaw('1 = 0');
        }

        return $query
            ->where(
                function ($builder) use (
                    $labelIds,
                    $artistIds,
                    $labelColumn,
                    $artistColumn
                ) {
                    $hasClause = false;

                    if (
                        $labelIds->isNotEmpty()
                    ) {
                        $builder->whereIn(
                            $labelColumn,
                            $labelIds
                        );

                        $hasClause = true;
                    }

                    if (
                        $artistIds->isNotEmpty()
                    ) {
                        if ($hasClause) {
                            $builder->orWhereIn(
                                $artistColumn,
                                $artistIds
                            );
                        } else {
                            $builder->whereIn(
                                $artistColumn,
                                $artistIds
                            );
                        }
                    }
                }
            );
    }

    public function canAccessFinancialOwner(
        User $user,
        ?int $labelId,
        ?int $artistId
    ): bool {
        if (
            $user->role === 'super_admin'
        ) {
            return true;
        }

        if (
            $user->role !== 'admin'
        ) {
            return false;
        }

        if (
            $labelId !== null
            && $this->labelIds($user)
                ->contains($labelId)
        ) {
            return true;
        }

        if (
            $artistId !== null
            && $this->artistIds($user)
                ->contains($artistId)
        ) {
            return true;
        }

        return false;
    }

    public function accessibleUserIds(
        User $user
    ): Collection {
        if (
            $user->role === 'super_admin'
        ) {
            return DB::table('users')
                ->pluck('id')
                ->map(
                    fn ($id) =>
                        (int) $id
                )
                ->values();
        }

        if (
            $user->role !== 'admin'
        ) {
            return collect();
        }

        $labelUserIds =
            DB::table('labels')
                ->whereIn(
                    'id',
                    $this->labelIds($user)
                )
                ->whereNull('deleted_at')
                ->whereNotNull('user_id')
                ->pluck('user_id');

        $artistUserIds =
            DB::table('artists')
                ->whereIn(
                    'id',
                    $this->artistIds($user)
                )
                ->whereNull('deleted_at')
                ->whereNotNull('user_id')
                ->pluck('user_id');

        return $labelUserIds
            ->merge($artistUserIds)
            ->map(
                fn ($id) =>
                    (int) $id
            )
            ->unique()
            ->values();
    }

    public function canAccessUser(
        User $user,
        int $targetUserId
    ): bool {
        if (
            $user->role === 'super_admin'
        ) {
            return true;
        }

        return $this
            ->accessibleUserIds($user)
            ->contains($targetUserId);
    }
}
