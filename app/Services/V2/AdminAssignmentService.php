<?php

namespace App\Services\V2;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AdminAssignmentService
{
    public function artistIds(User $user): Collection
    {
        if ($user->role === 'super_admin') {
            return DB::table('artists')
                ->whereNull('deleted_at')
                ->pluck('id');
        }

        if ($user->role !== 'admin') {
            return collect();
        }

        return DB::table('admin_artist_assignments')
            ->join(
                'artists',
                'artists.id',
                '=',
                'admin_artist_assignments.artist_id'
            )
            ->where(
                'admin_artist_assignments.user_id',
                $user->id
            )
            ->whereNull('artists.deleted_at')
            ->pluck('artists.id')
            ->unique()
            ->values();
    }

    public function labelIds(User $user): Collection
    {
        if ($user->role === 'super_admin') {
            return DB::table('labels')
                ->whereNull('deleted_at')
                ->pluck('id');
        }

        if ($user->role !== 'admin') {
            return collect();
        }

        return DB::table('admin_label_assignments')
            ->join(
                'labels',
                'labels.id',
                '=',
                'admin_label_assignments.label_id'
            )
            ->where(
                'admin_label_assignments.user_id',
                $user->id
            )
            ->whereNull('labels.deleted_at')
            ->pluck('labels.id')
            ->unique()
            ->values();
    }

    public function canAccessArtist(
        User $user,
        int $artistId
    ): bool {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        return DB::table('admin_artist_assignments')
            ->where('user_id', $user->id)
            ->where('artist_id', $artistId)
            ->exists();
    }

    public function canAccessLabel(
        User $user,
        int $labelId
    ): bool {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        return DB::table('admin_label_assignments')
            ->where('user_id', $user->id)
            ->where('label_id', $labelId)
            ->exists();
    }

    public function canAccessRelease(
        User $user,
        ?int $artistId,
        ?int $labelId
    ): bool {
        if ($user->role === 'super_admin') {
            return true;
        }

        if ($user->role !== 'admin') {
            return false;
        }

        /*
         * Admin creation/access rules:
         *
         * 1. Artist directly assigned to Admin => allowed.
         *
         * 2. Artist belongs to a Label assigned to Admin
         *    => allowed.
         *
         * 3. When label_id is supplied, Artist must actually
         *    belong to that Label. This prevents mixing an
         *    assigned Artist with an unrelated Label.
         */

        /*
         * Operator-owned catalog release:
         * a Label assignment is sufficient when no Artist
         * account is intentionally attached.
         */
        if (! $artistId) {
            return $labelId
                ? $this->canAccessLabel($user, $labelId)
                : false;
        }

        $artist = DB::table('artists')
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->first([
                'id',
                'label_id',
            ]);

        if (! $artist) {
            return false;
        }

        if (
            $labelId
            && (int) ($artist->label_id ?? 0)
                !== (int) $labelId
        ) {
            return false;
        }

        if (
            $this->canAccessArtist(
                $user,
                (int) $artist->id
            )
        ) {
            return true;
        }

        if (
            $artist->label_id
            && $this->canAccessLabel(
                $user,
                (int) $artist->label_id
            )
        ) {
            return true;
        }

        return false;
    }
}
