<?php

namespace App\Services\Core;

use App\Models\Distribution\Release;
use App\Models\Distribution\ReleaseStatusLog;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReleaseService
{
    public function paginate(array $filters = []): LengthAwarePaginator
    {
        return Release::query()
            ->with([
                'artist:id,stage_name',
                'label:id,name',
            ])
            ->when(
                !empty($filters['search']),
                function (Builder $query) use ($filters): void {
                    $search = $filters['search'];

                    $query->where(function (Builder $q) use ($search): void {
                        $q->where('title', 'like', "%{$search}%")
                          ->orWhere('catalog_number', 'like', "%{$search}%")
                          ->orWhere('upc', 'like', "%{$search}%");
                    });
                }
            )
            ->when(
                !empty($filters['status']),
                fn (Builder $q) =>
                    $q->where('status', $filters['status'])
            )
            ->when(
                !empty($filters['artist_id']),
                fn (Builder $q) =>
                    $q->where('artist_id', $filters['artist_id'])
            )
            ->latest()
            ->paginate(
                $filters['per_page'] ?? 20
            );
    }

    public function create(array $data): Release
    {
        return DB::transaction(function () use ($data): Release {

            $release = Release::create([
                ...$data,
                'public_id' => (string) Str::ulid(),
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            ReleaseStatusLog::create([
                'release_id' => $release->id,
                'old_status' => null,
                'new_status' => $release->status,
                'action' => 'created',
                'remarks' => 'Release created.',
                'changed_by' => Auth::id(),
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);

            return $release;
        });
    }

    public function update(Release $release, array $data): Release
    {
        return DB::transaction(function () use ($release, $data): Release {

            $oldStatus = $release->status;

            $release->update([
                ...$data,
                'updated_by' => Auth::id(),
            ]);

            if (
                array_key_exists('status', $data)
                && $oldStatus !== $release->status
            ) {
                ReleaseStatusLog::create([
                    'release_id' => $release->id,
                    'old_status' => $oldStatus,
                    'new_status' => $release->status,
                    'action' => 'status_changed',
                    'remarks' => 'Status updated.',
                    'changed_by' => Auth::id(),
                    'ip_address' => request()->ip(),
                    'user_agent' => request()->userAgent(),
                ]);
            }

            return $release->fresh();
        });
    }

    public function delete(Release $release): void
    {
        $release->update([
            'updated_by' => Auth::id(),
        ]);

        $release->delete();
    }
}
