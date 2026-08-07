<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use App\Models\ReleaseActivityLog;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class ReleaseAuditService
{
    public function log(
        Release $release,
        string $action,
        string $title,
        ?string $description = null,
        array $options = []
    ): ReleaseActivityLog {
        return ReleaseActivityLog::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'release_id' =>
                $release->id,

            'track_id' =>
                $options['track_id']
                ?? null,

            'user_id' =>
                $options['user_id']
                ?? auth()->id(),

            'action' =>
                $action,

            'category' =>
                $options['category']
                ?? 'general',

            'title' =>
                $title,

            'description' =>
                $description,

            'old_values' =>
                $options['old_values']
                ?? null,

            'new_values' =>
                $options['new_values']
                ?? null,

            'meta' =>
                $options['meta']
                ?? [],

            'ip_address' =>
                $options['ip_address']
                ?? request()?->ip(),

            'user_agent' =>
                $options['user_agent']
                ?? request()?->userAgent(),
        ]);
    }

    public function releaseCreated(
        Release $release,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'release.created',
            'Release created',
            "Release '{$release->title}' was created.",
            [
                'category' =>
                    'release',

                'user_id' =>
                    $user?->id,
            ]
        );
    }

    public function metadataUpdated(
        Release $release,
        array $oldValues,
        array $newValues,
        ?User $user = null
    ): void {
        $changes = [];

        foreach ($newValues as $key => $value) {
            $oldValue =
                $oldValues[$key] ?? null;

            if ($oldValue !== $value) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }
        }

        if (empty($changes)) {
            return;
        }

        $this->log(
            $release,
            'release.metadata_updated',
            'Release metadata updated',
            'One or more release fields were updated.',
            [
                'category' =>
                    'metadata',

                'user_id' =>
                    $user?->id,

                'old_values' =>
                    $oldValues,

                'new_values' =>
                    $newValues,

                'meta' => [
                    'changed_fields' =>
                        array_keys(
                            $changes
                        ),
                ],
            ]
        );
    }

    public function artworkUpdated(
        Release $release,
        ?string $oldPath,
        ?string $newPath,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'release.artwork_updated',
            'Artwork updated',
            'Release cover artwork was uploaded or replaced.',
            [
                'category' =>
                    'artwork',

                'user_id' =>
                    $user?->id,

                'old_values' => [
                    'artwork_path' =>
                        $oldPath,
                ],

                'new_values' => [
                    'artwork_path' =>
                        $newPath,
                ],
            ]
        );
    }

    public function trackAdded(
        Release $release,
        Track $track,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'track.added',
            'Track added',
            "Track '{$track->title}' was added.",
            [
                'category' =>
                    'track',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $user?->id,

                'new_values' =>
                    $track->only([
                        'title',
                        'version',
                        'isrc',
                        'language',
                        'genre',
                    ]),
            ]
        );
    }

    public function trackUpdated(
        Release $release,
        Track $track,
        array $oldValues,
        array $newValues,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'track.updated',
            'Track updated',
            "Track '{$track->title}' was updated.",
            [
                'category' =>
                    'track',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $user?->id,

                'old_values' =>
                    $oldValues,

                'new_values' =>
                    $newValues,
            ]
        );
    }

    public function trackDeleted(
        Release $release,
        Track $track,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'track.deleted',
            'Track deleted',
            "Track '{$track->title}' was deleted.",
            [
                'category' =>
                    'track',

                'track_id' =>
                    $track->id,

                'user_id' =>
                    $user?->id,

                'old_values' =>
                    $track->only([
                        'title',
                        'version',
                        'isrc',
                        'language',
                        'genre',
                    ]),
            ]
        );
    }

    public function distributionUpdated(
        Release $release,
        array $oldValues,
        array $newValues,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            'release.distribution_updated',
            'Stores and territory updated',
            'Distribution stores or territory settings were changed.',
            [
                'category' =>
                    'distribution',

                'user_id' =>
                    $user?->id,

                'old_values' =>
                    $oldValues,

                'new_values' =>
                    $newValues,
            ]
        );
    }

    public function statusChanged(
        Release $release,
        string $oldStatus,
        string $newStatus,
        ?User $user = null,
        ?string $remarks = null
    ): void {
        $this->log(
            $release,
            'release.status_changed',
            'Release status changed',
            "Status changed from {$oldStatus} to {$newStatus}.",
            [
                'category' =>
                    'status',

                'user_id' =>
                    $user?->id,

                'old_values' => [
                    'status' =>
                        $oldStatus,
                ],

                'new_values' => [
                    'status' =>
                        $newStatus,
                ],

                'meta' => [
                    'remarks' =>
                        $remarks,
                ],
            ]
        );
    }

    public function identifierAssigned(
        Release $release,
        string $type,
        string $code,
        ?Track $track = null,
        ?User $user = null
    ): void {
        $this->log(
            $release,
            "identifier.{$type}.assigned",
            strtoupper($type) . ' assigned',
            strtoupper($type)
                . " {$code} was assigned.",
            [
                'category' =>
                    'identifier',

                'track_id' =>
                    $track?->id,

                'user_id' =>
                    $user?->id,

                'new_values' => [
                    $type => $code,
                ],
            ]
        );
    }

    public function timeline(
        Release $release,
        int $limit = 200
    ): Collection {
        return ReleaseActivityLog::query()
            ->with([
                'user:id,name,email',
                'track:id,title',
            ])
            ->where(
                'release_id',
                $release->id
            )
            ->orderByDesc('id')
            ->limit(
                min(
                    max($limit, 1),
                    500
                )
            )
            ->get();
    }
}
