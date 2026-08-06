<?php

namespace App\Services\V2;

use App\Models\Distribution\Contributor;
use App\Models\Distribution\Track;
use App\Models\Distribution\TrackContributor;
use App\Models\Distribution\TrackSplit;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TrackCreditsService
{
    private const ROLES = [
        'primary_artist',
        'featured_artist',
        'composer',
        'lyricist',
        'producer',
        'music_producer',
        'arranger',
        'performer',
        'remixer',
        'publisher',
        'music_director',
        'vocalist',
        'instrumentalist',
        'engineer',
        'mastering_engineer',
        'mixing_engineer',
        'other',
    ];

    private const SPLIT_TYPES = [
        'master',
        'publishing',
        'performance',
        'mechanical',
    ];

    public function createContributor(
        array $data,
        User $user
    ): Contributor {
        $name = trim(
            (string) (
                $data['name'] ?? ''
            )
        );

        if ($name === '') {
            throw ValidationException::withMessages([
                'name' =>
                    'Contributor name is required.',
            ]);
        }

        return Contributor::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'user_id' =>
                $data['user_id'] ?? null,

            'artist_id' =>
                $data['artist_id'] ?? null,

            'name' =>
                $name,

            'legal_name' =>
                $data['legal_name'] ?? null,

            'email' =>
                $data['email'] ?? null,

            'phone' =>
                $data['phone'] ?? null,

            'country' =>
                trim(
                    (string) (
                        $data['country']
                        ?? $data['country_code']
                        ?? 'India'
                    )
                ) ?: 'India',

            'ipi_number' =>
                $data['ipi_number'] ?? null,

            'isni' =>
                $data['isni'] ?? null,

            'primary_role' =>
                $data['primary_role'] ?? null,

            'can_receive_splits' =>
                (bool) (
                    $data['can_receive_splits']
                    ?? true
                ),

            'has_dashboard_access' =>
                (bool) (
                    $data['has_dashboard_access']
                    ?? false
                ),

            'status' =>
                $data['status'] ?? 'active',

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    public function attachContributor(
        Track $track,
        Contributor $contributor,
        string $role,
        User $user,
        array $data = []
    ): TrackContributor {
        $role = strtolower(
            trim($role)
        );

        if (
            !in_array(
                $role,
                self::ROLES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'role' => [
                    'Invalid contributor role.',
                ],
            ]);
        }

        $existing =
            TrackContributor::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'contributor_id',
                    $contributor->id
                )
                ->where(
                    'role',
                    $role
                )
                ->first();

        if ($existing) {
            throw ValidationException::withMessages([
                'contributor_id' => [
                    'This contributor already has this role on the track.',
                ],
            ]);
        }

        return TrackContributor::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'track_id' =>
                $track->id,

            'contributor_id' =>
                $contributor->id,

            'role' =>
                $role,

            'credited_name' =>
                $data['credited_name']
                ?? $contributor->name,

            'is_primary' =>
                (bool) (
                    $data['is_primary']
                    ?? false
                ),

            'is_featured' =>
                (bool) (
                    $data['is_featured']
                    ?? false
                ),

            'display_order' =>
                max(
                    1,
                    (int) (
                        $data['display_order']
                        ?? $data['sort_order']
                        ?? 1
                    )
                ),

            'metadata' =>
                $data['metadata']
                ?? (
                    isset($data['notes'])
                        ? [
                            'notes' =>
                                $data['notes'],
                        ]
                        : null
                ),

            'created_by' =>
                $user->id,

            'updated_by' =>
                $user->id,
        ]);
    }

    public function updateContributorCredit(
        TrackContributor $credit,
        array $data,
        User $user
    ): TrackContributor {
        if (isset($data['role'])) {
            $role = strtolower(
                trim($data['role'])
            );

            if (
                !in_array(
                    $role,
                    self::ROLES,
                    true
                )
            ) {
                throw ValidationException::withMessages([
                    'role' =>
                        'Invalid contributor role.',
                ]);
            }

            $duplicate =
                TrackContributor::query()
                    ->where(
                        'track_id',
                        $credit->track_id
                    )
                    ->where(
                        'contributor_id',
                        $credit->contributor_id
                    )
                    ->where(
                        'role',
                        $role
                    )
                    ->where(
                        'id',
                        '!=',
                        $credit->id
                    )
                    ->exists();

            if ($duplicate) {
                throw ValidationException::withMessages([
                    'role' =>
                        'This contributor already has this role.',
                ]);
            }
        }

        $credit->update([
            'role' =>
                $data['role']
                ?? $credit->role,

            'is_primary' =>
                array_key_exists(
                    'is_primary',
                    $data
                )
                    ? (bool) $data[
                        'is_primary'
                    ]
                    : $credit->is_primary,

            'credited_name' =>
                $data['credited_name']
                ?? $credit->credited_name,

            'is_featured' =>
                array_key_exists(
                    'is_featured',
                    $data
                )
                    ? (bool) $data[
                        'is_featured'
                    ]
                    : $credit->is_featured,

            'display_order' =>
                max(
                    1,
                    (int) (
                        $data['display_order']
                        ?? $data['sort_order']
                        ?? $credit->display_order
                        ?? 1
                    )
                ),

            'metadata' =>
                $data['metadata']
                ?? (
                    array_key_exists(
                        'notes',
                        $data
                    )
                        ? [
                            'notes' =>
                                $data['notes'],
                        ]
                        : $credit->metadata
                ),

            'updated_by' =>
                $user->id,
        ]);

        return $credit->fresh([
            'contributor',
        ]);
    }

    public function replaceSplits(
        Track $track,
        string $splitType,
        array $splits,
        User $user
    ): array {
        $splitType = strtolower(
            trim($splitType)
        );

        if (
            !in_array(
                $splitType,
                self::SPLIT_TYPES,
                true
            )
        ) {
            throw ValidationException::withMessages([
                'split_type' =>
                    'Invalid split type.',
            ]);
        }

        if (empty($splits)) {
            throw ValidationException::withMessages([
                'splits' =>
                    'At least one split recipient is required.',
            ]);
        }

        $total = 0;

        foreach ($splits as $index => $split) {
            $name = trim(
                (string) (
                    $split[
                        'recipient_name'
                    ] ?? ''
                )
            );

            $percentage = round(
                (float) (
                    $split['percentage']
                    ?? 0
                ),
                4
            );

            if ($name === '') {
                throw ValidationException::withMessages([
                    "splits.{$index}.recipient_name" =>
                        'Recipient name is required.',
                ]);
            }

            if (
                $percentage <= 0
                || $percentage > 100
            ) {
                throw ValidationException::withMessages([
                    "splits.{$index}.percentage" =>
                        'Percentage must be greater than 0 and not exceed 100.',
                ]);
            }

            $total += $percentage;
        }

        if (abs($total - 100) > 0.0001) {
            throw ValidationException::withMessages([
                'splits' =>
                    "Total {$splitType} split must equal 100%. Current total: {$total}%.",
            ]);
        }

        return DB::transaction(function () use (
            $track,
            $splitType,
            $splits,
            $user
        ) {
            TrackSplit::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'split_type',
                    $splitType
                )
                ->delete();

            $created = [];

            foreach ($splits as $split) {
                $created[] =
                    TrackSplit::query()->create([
                        'public_id' =>
                            (string) Str::ulid(),

                        'track_id' =>
                            $track->id,

                        'contributor_id' =>
                            $split[
                                'contributor_id'
                            ],

                        'split_type' =>
                            $splitType,

                        'percentage' =>
                            round(
                                (float) $split[
                                    'percentage'
                                ],
                                2
                            ),

                        'is_recoupable' =>
                            (bool) (
                                $split[
                                    'is_recoupable'
                                ] ?? false
                            ),

                        'effective_from' =>
                            $split[
                                'effective_from'
                            ] ?? null,

                        'effective_to' =>
                            $split[
                                'effective_to'
                            ] ?? null,

                        'status' =>
                            $split[
                                'status'
                            ] ?? 'active',

                        'created_by' =>
                            $user->id,

                        'updated_by' =>
                            $user->id,
                    ]);
            }

            return $created;
        });
    }

    public function validateTrackSplits(
        Track $track
    ): array {
        $errors = [];

        foreach (
            self::SPLIT_TYPES as $splitType
        ) {
            $splits = TrackSplit::query()
                ->where(
                    'track_id',
                    $track->id
                )
                ->where(
                    'split_type',
                    $splitType
                )
                ->where(
                    'status',
                    'active'
                )
                ->get();

            if ($splits->isEmpty()) {
                continue;
            }

            $total = round(
                (float) $splits->sum(
                    'percentage'
                ),
                4
            );

            if (abs($total - 100) > 0.0001) {
                $errors[$splitType] =
                    ucfirst($splitType)
                    . " split total must equal 100%. Current total: {$total}%.";
            }
        }

        return $errors;
    }

    public function trackCredits(
        Track $track
    ): array {
        return [
            'contributors' =>
                TrackContributor::query()
                    ->with('contributor')
                    ->where(
                        'track_id',
                        $track->id
                    )
                    ->orderBy('sort_order')
                    ->orderBy('id')
                    ->get(),

            'splits' =>
                TrackSplit::query()
                    ->with('contributor')
                    ->where(
                        'track_id',
                        $track->id
                    )
                    ->where(
                        'status',
                        'active'
                    )
                    ->orderBy('split_type')
                    ->orderBy('id')
                    ->get(),

            'validation' =>
                $this->validateTrackSplits(
                    $track
                ),
        ];
    }
}
