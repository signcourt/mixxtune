<?php

namespace App\Services\V2;

use App\Models\CatalogueItem;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Throwable;

class CatalogueService
{
    public function __construct(
        private readonly AdminAssignmentService $assignments
    ) {
    }

    private const VISIBLE_STATUSES = [
        'approved',
        'processing',
        'delivered',
        'live',
        'takedown_requested',
    ];

    public function syncRelease(
        Release $release,
        ?User $user = null
    ): CatalogueItem {
        try {
            $release->loadMissing([
                'tracks',
            ]);

            $trackCount =
                $release->tracks->count();

            $isrcAssignedCount =
                $release->tracks
                    ->filter(
                        fn ($track) =>
                            filled($track->isrc)
                    )
                    ->count();

            $labelName = null;

            if ($release->label_id) {
                $labelName =
                    Label::query()
                        ->where(
                            'id',
                            $release->label_id
                        )
                        ->value('name');
            }

            $deliverySummary =
                $this->deliverySummary(
                    $release
                );

            $visible = in_array(
                $release->status,
                self::VISIBLE_STATUSES,
                true
            );

            $item = DB::transaction(
                function () use (
                    $release,
                    $trackCount,
                    $isrcAssignedCount,
                    $labelName,
                    $deliverySummary,
                    $visible
                ) {
                    return CatalogueItem::query()
                        ->updateOrCreate(
                            [
                                'release_id' =>
                                    $release->id,
                            ],
                            [
                                'public_id' =>
                                    CatalogueItem::query()
                                        ->where(
                                            'release_id',
                                            $release->id
                                        )
                                        ->value(
                                            'public_id'
                                        )
                                    ?: (string) Str::ulid(),

                                'artist_id' =>
                                    $release->artist_id,

                                'label_id' =>
                                    $release->label_id,

                                'title' =>
                                    $release->title,

                                'release_type' =>
                                    $release->release_type,

                                'primary_artist_name' =>
                                    $release
                                        ->primary_artist_name,

                                'primary_artists' =>
                                    is_array(
                                        $release
                                            ->primary_artists
                                    )
                                        ? $release
                                            ->primary_artists
                                        : [],

                                'featuring_artists' =>
                                    is_array(
                                        $release
                                            ->featuring_artists
                                    )
                                        ? $release
                                            ->featuring_artists
                                        : [],

                                'label_name' =>
                                    $labelName,

                                'upc' =>
                                    $release->upc,

                                'catalog_number' =>
                                    $release
                                        ->catalog_number,

                                'language' =>
                                    $release->language,

                                'primary_genre' =>
                                    $release
                                        ->primary_genre,

                                'sub_genre' =>
                                    $release->sub_genre,

                                'artwork_path' =>
                                    $release->artwork_path,

                                'digital_release_date' =>
                                    $release
                                        ->digital_release_date,

                                'release_status' =>
                                    $release->status,

                                'track_count' =>
                                    $trackCount,

                                'isrc_assigned_count' =>
                                    $isrcAssignedCount,

                                'store_ids' =>
                                    is_array(
                                        $release->stores
                                    )
                                        ? array_values(
                                            array_unique(
                                                array_map(
                                                    'intval',
                                                    $release
                                                        ->stores
                                                )
                                            )
                                        )
                                        : [],

                                'delivery_summary' =>
                                    $deliverySummary,

                                'is_visible' =>
                                    $visible,

                                'last_synced_at' =>
                                    now(),
                            ]
                        );
                }
            );

            $this->log(
                $release,
                'synced',
                true,
                'Catalogue record synchronized.',
                $user
            );

            return $item->fresh();
        } catch (Throwable $exception) {
            $this->log(
                $release,
                'sync_failed',
                false,
                $exception->getMessage(),
                $user
            );

            throw $exception;
        }
    }

    public function removeVisibility(
        Release $release,
        ?User $user = null
    ): ?CatalogueItem {
        $item = CatalogueItem::query()
            ->where(
                'release_id',
                $release->id
            )
            ->first();

        if (!$item) {
            return null;
        }

        $item->update([
            'release_status' =>
                $release->status,

            'is_visible' =>
                false,

            'last_synced_at' =>
                now(),
        ]);

        $this->log(
            $release,
            'hidden',
            true,
            'Catalogue item hidden.',
            $user
        );

        return $item->fresh();
    }

    public function syncByStatus(
        array $statuses,
        ?User $user = null,
        int $limit = 1000
    ): array {
        $statuses = array_values(
            array_unique(
                array_filter(
                    array_map(
                        'strval',
                        $statuses
                    )
                )
            )
        );

        $releases = Release::query()
            ->whereNull('deleted_at')
            ->whereIn(
                'status',
                $statuses
            )
            ->orderBy('id')
            ->limit(
                min(
                    max($limit, 1),
                    10000
                )
            )
            ->get();

        $synced = 0;
        $failed = [];

        foreach ($releases as $release) {
            try {
                $this->syncRelease(
                    $release,
                    $user
                );

                $synced++;
            } catch (Throwable $exception) {
                $failed[] = [
                    'release_id' =>
                        $release->id,

                    'message' =>
                        $exception->getMessage(),
                ];
            }
        }

        return [
            'total' => $releases->count(),
            'synced' => $synced,
            'failed_count' => count($failed),
            'failed' => $failed,
        ];
    }

    public function scopedQuery(
        User $user,
        PermissionService $permissions
    ): Builder {
        $role = $permissions->role(
            $user
        );

        $query = CatalogueItem::query()
            ->with([
                'release.tracks',
            ]);

        if ($role === 'super_admin') {
            return $query;
        }

        if ($role === 'artist') {
            $artistId = DB::table('artists')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $artistId
                ? $query->where(
                    'artist_id',
                    $artistId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'label') {
            $labelId = DB::table('labels')
                ->where(
                    'user_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->value('id');

            return $labelId
                ? $query->where(
                    'label_id',
                    $labelId
                )
                : $query->whereRaw('1 = 0');
        }

        if ($role === 'admin') {
            $artistIds =
                $this->assignments->artistIds($user);

            $labelIds =
                $this->assignments->labelIds($user);

            if (
                $artistIds->isEmpty()
                && $labelIds->isEmpty()
            ) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(
                function ($builder) use (
                    $artistIds,
                    $labelIds
                ) {
                    if ($artistIds->isNotEmpty()) {
                        $builder->whereIn(
                            'artist_id',
                            $artistIds
                        );
                    }

                    if ($labelIds->isNotEmpty()) {
                        if ($artistIds->isNotEmpty()) {
                            $builder->orWhereIn(
                                'label_id',
                                $labelIds
                            );
                        } else {
                            $builder->whereIn(
                                'label_id',
                                $labelIds
                            );
                        }
                    }
                }
            );
        }

        return $query->whereRaw('1 = 0');
    }

    private function deliverySummary(
        Release $release
    ): array {
        if (
            !Schema::hasTable(
                'release_store_deliveries'
            )
        ) {
            return [
                'total' => 0,
                'pending' => 0,
                'processing' => 0,
                'delivered' => 0,
                'live' => 0,
                'failed' => 0,
                'takedown_requested' => 0,
                'taken_down' => 0,
            ];
        }

        $counts =
            ReleaseStoreDelivery::query()
                ->where(
                    'release_id',
                    $release->id
                )
                ->selectRaw(
                    'status, COUNT(*) as total'
                )
                ->groupBy('status')
                ->pluck(
                    'total',
                    'status'
                );

        return [
            'total' =>
                (int) $counts->sum(),

            'pending' =>
                (int) (
                    $counts['pending']
                    ?? 0
                ),

            'processing' =>
                (int) (
                    $counts['processing']
                    ?? 0
                ),

            'delivered' =>
                (int) (
                    $counts['delivered']
                    ?? 0
                ),

            'live' =>
                (int) (
                    $counts['live']
                    ?? 0
                ),

            'failed' =>
                (int) (
                    $counts['failed']
                    ?? 0
                ),

            'takedown_requested' =>
                (int) (
                    $counts[
                        'takedown_requested'
                    ] ?? 0
                ),

            'taken_down' =>
                (int) (
                    $counts['taken_down']
                    ?? 0
                ),
        ];
    }

    private function log(
        Release $release,
        string $action,
        bool $successful,
        ?string $message,
        ?User $user
    ): void {
        if (
            !Schema::hasTable(
                'catalogue_sync_logs'
            )
        ) {
            return;
        }

        DB::table(
            'catalogue_sync_logs'
        )->insert([
            'public_id' =>
                (string) Str::ulid(),

            'release_id' =>
                $release->id,

            'action' =>
                $action,

            'release_status' =>
                $release->status,

            'successful' =>
                $successful,

            'message' =>
                $message,

            'performed_by' =>
                $user?->id,

            'created_at' =>
                now(),

            'updated_at' =>
                now(),
        ]);
    }
}
