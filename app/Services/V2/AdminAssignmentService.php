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

        if (
            $artistId
            && $this->canAccessArtist(
                $user,
                $artistId
            )
        ) {
            return true;
        }

        if (
            $labelId
            && $this->canAccessLabel(
                $user,
                $labelId
            )
        ) {
            return true;
        }

        return false;
    }
}
