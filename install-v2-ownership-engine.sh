#!/usr/bin/env bash

set -euo pipefail

APP="/var/www/backstage-distribution"
cd "$APP"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$APP/storage/app/backups/v2-ownership-$STAMP"

mkdir -p "$BACKUP"

echo "========================================"
echo " V2 Ownership Engine Installer"
echo " Backup: $BACKUP"
echo "========================================"

cp -a routes/web.php "$BACKUP/web.php"

[ -f app/Http/Controllers/V2/Admin/FinanceController.php ] && \
cp -a app/Http/Controllers/V2/Admin/FinanceController.php \
"$BACKUP/FinanceController.php"

[ -f app/Services/V2/RoyaltyService.php ] && \
cp -a app/Services/V2/RoyaltyService.php \
"$BACKUP/RoyaltyService.php"

# ---------------------------------------------------------
# 1. DATABASE MIGRATION
# ---------------------------------------------------------

MIGRATION="database/migrations/$(date +%Y_%m_%d_%H%M%S)_add_v2_catalogue_ownership_engine.php"

cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('report_rows', function (Blueprint $table) {
            if (!Schema::hasColumn('report_rows', 'revenue_owner_type')) {
                $table->string(
                    'revenue_owner_type',
                    20
                )->nullable()->index();
            }

            if (!Schema::hasColumn('report_rows', 'revenue_owner_id')) {
                $table->unsignedBigInteger(
                    'revenue_owner_id'
                )->nullable()->index();
            }

            if (!Schema::hasColumn('report_rows', 'mapping_status')) {
                $table->string(
                    'mapping_status',
                    30
                )->default('unmapped')->index();
            }

            if (!Schema::hasColumn('report_rows', 'mapped_at')) {
                $table->timestamp(
                    'mapped_at'
                )->nullable();
            }
        });

        if (!Schema::hasTable('catalogue_transfers')) {
            Schema::create(
                'catalogue_transfers',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        30
                    )->unique();

                    $table->string(
                        'transfer_type',
                        40
                    )->index();

                    $table->string(
                        'entity_type',
                        40
                    )->index();

                    $table->unsignedBigInteger(
                        'entity_id'
                    )->index();

                    $table->string(
                        'from_owner_type',
                        20
                    )->nullable();

                    $table->unsignedBigInteger(
                        'from_owner_id'
                    )->nullable();

                    $table->string(
                        'to_owner_type',
                        20
                    )->nullable();

                    $table->unsignedBigInteger(
                        'to_owner_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'from_user_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'to_user_id'
                    )->nullable();

                    $table->string(
                        'revenue_scope',
                        40
                    )->default('future_only');

                    $table->text('reason')->nullable();

                    $table->json(
                        'old_values'
                    )->nullable();

                    $table->json(
                        'new_values'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'transferred_by'
                    )->nullable();

                    $table->timestamp(
                        'transferred_at'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'entity_type',
                        'entity_id',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'catalogue_transfers'
        );

        Schema::table('report_rows', function (Blueprint $table) {
            foreach ([
                'revenue_owner_type',
                'revenue_owner_id',
                'mapping_status',
                'mapped_at',
            ] as $column) {
                if (
                    Schema::hasColumn(
                        'report_rows',
                        $column
                    )
                ) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
PHP

# ---------------------------------------------------------
# 2. OWNERSHIP SERVICE
# ---------------------------------------------------------

cat > app/Services/V2/CatalogueOwnershipService.php <<'PHP'
<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CatalogueOwnershipService
{
    public function resolveReleaseOwner(
        object $release
    ): array {
        if (!empty($release->label_id)) {
            return [
                'type' => 'label',
                'id' => (int) $release->label_id,
            ];
        }

        return [
            'type' => 'artist',
            'id' => (int) $release->artist_id,
        ];
    }

    public function remapReports(
        bool $onlyUnmapped = false
    ): array {
        $mapped = 0;
        $unmapped = 0;

        $query = DB::table('report_rows')
            ->select([
                'id',
                'isrc',
                'upc',
            ])
            ->orderBy('id');

        if ($onlyUnmapped) {
            $query->where(function ($builder) {
                $builder
                    ->whereNull('mapping_status')
                    ->orWhere(
                        'mapping_status',
                        'unmapped'
                    );
            });
        }

        $query->chunkById(
            500,
            function ($rows) use (
                &$mapped,
                &$unmapped
            ) {
                foreach ($rows as $row) {
                    $track = $this->findTrack(
                        $row->isrc
                    );

                    $release = null;

                    if ($track) {
                        $release = DB::table('releases')
                            ->where(
                                'id',
                                $track->release_id
                            )
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if (
                        !$release
                        && !empty($row->upc)
                    ) {
                        $release = DB::table('releases')
                            ->where(
                                'upc',
                                $this->cleanCode(
                                    $row->upc
                                )
                            )
                            ->whereNull('deleted_at')
                            ->first();
                    }

                    if (!$release) {
                        DB::table('report_rows')
                            ->where('id', $row->id)
                            ->update([
                                'mapping_status' =>
                                    'unmapped',

                                'release_id' =>
                                    null,

                                'track_id' =>
                                    null,

                                'artist_id' =>
                                    null,

                                'label_id' =>
                                    null,

                                'revenue_owner_type' =>
                                    null,

                                'revenue_owner_id' =>
                                    null,

                                'mapped_at' =>
                                    null,

                                'updated_at' =>
                                    now(),
                            ]);

                        $unmapped++;

                        continue;
                    }

                    $owner =
                        $this->resolveReleaseOwner(
                            $release
                        );

                    DB::table('report_rows')
                        ->where('id', $row->id)
                        ->update([
                            'release_id' =>
                                $release->id,

                            'track_id' =>
                                $track?->id,

                            // Performer metadata
                            'artist_id' =>
                                $release->artist_id,

                            // Catalogue label
                            'label_id' =>
                                $release->label_id,

                            // Financial ownership
                            'revenue_owner_type' =>
                                $owner['type'],

                            'revenue_owner_id' =>
                                $owner['id'],

                            'mapping_status' =>
                                'mapped',

                            'mapped_at' =>
                                now(),

                            'updated_at' =>
                                now(),
                        ]);

                    $mapped++;
                }
            }
        );

        return [
            'mapped' => $mapped,
            'unmapped' => $unmapped,
        ];
    }

    public function transferRelease(
        int $releaseId,
        string $ownerType,
        int $ownerId,
        string $scope,
        ?string $reason,
        int $adminId
    ): void {
        $release = DB::table('releases')
            ->where('id', $releaseId)
            ->whereNull('deleted_at')
            ->first();

        if (!$release) {
            throw ValidationException::withMessages([
                'release' =>
                    'Release not found.',
            ]);
        }

        $oldOwner =
            $this->resolveReleaseOwner(
                $release
            );

        $oldValues = [
            'artist_id' =>
                $release->artist_id,

            'label_id' =>
                $release->label_id,

            'owner_type' =>
                $oldOwner['type'],

            'owner_id' =>
                $oldOwner['id'],
        ];

        if ($ownerType === 'label') {
            $label = DB::table('labels')
                ->where('id', $ownerId)
                ->whereNull('deleted_at')
                ->first();

            if (!$label) {
                throw ValidationException::withMessages([
                    'owner_id' =>
                        'Target label not found.',
                ]);
            }

            DB::table('releases')
                ->where('id', $releaseId)
                ->update([
                    // Performer remains unchanged
                    'label_id' =>
                        $label->id,

                    'updated_by' =>
                        $adminId,

                    'updated_at' =>
                        now(),
                ]);

            $newArtistId =
                (int) $release->artist_id;

            $newLabelId =
                (int) $label->id;
        } elseif ($ownerType === 'artist') {
            $artist = DB::table('artists')
                ->where('id', $ownerId)
                ->whereNull('deleted_at')
                ->first();

            if (!$artist) {
                throw ValidationException::withMessages([
                    'owner_id' =>
                        'Target artist not found.',
                ]);
            }

            DB::table('releases')
                ->where('id', $releaseId)
                ->update([
                    'artist_id' =>
                        $artist->id,

                    // Direct artist ownership
                    'label_id' =>
                        null,

                    'primary_artist_name' =>
                        $artist->stage_name
                        ?: $artist->legal_name,

                    'updated_by' =>
                        $adminId,

                    'updated_at' =>
                        now(),
                ]);

            $newArtistId =
                (int) $artist->id;

            $newLabelId = null;
        } else {
            throw ValidationException::withMessages([
                'owner_type' =>
                    'Owner must be artist or label.',
            ]);
        }

        if ($scope === 'pending_and_future') {
            $protectedRowIds = DB::table(
                'royalty_allocations as ra'
            )
                ->join(
                    'royalty_statements as rs',
                    'rs.id',
                    '=',
                    'ra.royalty_statement_id'
                )
                ->whereIn(
                    'rs.status',
                    [
                        'approved',
                        'available',
                        'paid',
                    ]
                )
                ->pluck('ra.report_row_id');

            DB::table('report_rows')
                ->where(
                    'release_id',
                    $releaseId
                )
                ->when(
                    $protectedRowIds->isNotEmpty(),
                    fn ($query) =>
                        $query->whereNotIn(
                            'id',
                            $protectedRowIds
                        )
                )
                ->update([
                    'artist_id' =>
                        $newArtistId,

                    'label_id' =>
                        $newLabelId,

                    'revenue_owner_type' =>
                        $ownerType,

                    'revenue_owner_id' =>
                        $ownerId,

                    'mapping_status' =>
                        'mapped',

                    'mapped_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        $this->audit([
            'transfer_type' =>
                'release_owner_transfer',

            'entity_type' =>
                'release',

            'entity_id' =>
                $releaseId,

            'from_owner_type' =>
                $oldOwner['type'],

            'from_owner_id' =>
                $oldOwner['id'],

            'to_owner_type' =>
                $ownerType,

            'to_owner_id' =>
                $ownerId,

            'revenue_scope' =>
                $scope,

            'reason' =>
                $reason,

            'old_values' =>
                $oldValues,

            'new_values' => [
                'artist_id' =>
                    $newArtistId,

                'label_id' =>
                    $newLabelId,

                'owner_type' =>
                    $ownerType,

                'owner_id' =>
                    $ownerId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferTrack(
        int $trackId,
        int $newReleaseId,
        string $scope,
        ?string $reason,
        int $adminId
    ): void {
        $track = DB::table('tracks')
            ->where('id', $trackId)
            ->whereNull('deleted_at')
            ->first();

        $newRelease = DB::table('releases')
            ->where('id', $newReleaseId)
            ->whereNull('deleted_at')
            ->first();

        if (!$track || !$newRelease) {
            throw ValidationException::withMessages([
                'track' =>
                    'Track or target release not found.',
            ]);
        }

        $oldReleaseId =
            (int) $track->release_id;

        DB::table('tracks')
            ->where('id', $trackId)
            ->update([
                'release_id' =>
                    $newReleaseId,

                'updated_at' =>
                    now(),
            ]);

        $owner =
            $this->resolveReleaseOwner(
                $newRelease
            );

        if ($scope === 'pending_and_future') {
            DB::table('report_rows')
                ->where('track_id', $trackId)
                ->whereNotIn(
                    'id',
                    DB::table(
                        'royalty_allocations as ra'
                    )
                        ->join(
                            'royalty_statements as rs',
                            'rs.id',
                            '=',
                            'ra.royalty_statement_id'
                        )
                        ->whereIn(
                            'rs.status',
                            [
                                'approved',
                                'available',
                                'paid',
                            ]
                        )
                        ->pluck(
                            'ra.report_row_id'
                        )
                )
                ->update([
                    'release_id' =>
                        $newReleaseId,

                    'artist_id' =>
                        $newRelease->artist_id,

                    'label_id' =>
                        $newRelease->label_id,

                    'revenue_owner_type' =>
                        $owner['type'],

                    'revenue_owner_id' =>
                        $owner['id'],

                    'mapped_at' =>
                        now(),

                    'updated_at' =>
                        now(),
                ]);
        }

        $this->audit([
            'transfer_type' =>
                'track_release_transfer',

            'entity_type' =>
                'track',

            'entity_id' =>
                $trackId,

            'from_owner_type' =>
                'release',

            'from_owner_id' =>
                $oldReleaseId,

            'to_owner_type' =>
                'release',

            'to_owner_id' =>
                $newReleaseId,

            'revenue_scope' =>
                $scope,

            'reason' =>
                $reason,

            'old_values' => [
                'release_id' =>
                    $oldReleaseId,
            ],

            'new_values' => [
                'release_id' =>
                    $newReleaseId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferLabelUser(
        int $labelId,
        int $newUserId,
        ?string $reason,
        int $adminId
    ): void {
        $label = DB::table('labels')
            ->where('id', $labelId)
            ->whereNull('deleted_at')
            ->first();

        $user = DB::table('users')
            ->where('id', $newUserId)
            ->first();

        if (!$label || !$user) {
            throw ValidationException::withMessages([
                'user_id' =>
                    'Label or user not found.',
            ]);
        }

        $oldUserId =
            $label->user_id;

        DB::table('labels')
            ->where('id', $labelId)
            ->update([
                'user_id' =>
                    $newUserId,

                'updated_by' =>
                    $adminId,

                'updated_at' =>
                    now(),
            ]);

        $this->audit([
            'transfer_type' =>
                'label_user_transfer',

            'entity_type' =>
                'label',

            'entity_id' =>
                $labelId,

            'from_user_id' =>
                $oldUserId,

            'to_user_id' =>
                $newUserId,

            'revenue_scope' =>
                'access_only',

            'reason' =>
                $reason,

            'old_values' => [
                'user_id' =>
                    $oldUserId,
            ],

            'new_values' => [
                'user_id' =>
                    $newUserId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    public function transferArtistUser(
        int $artistId,
        int $newUserId,
        ?string $reason,
        int $adminId
    ): void {
        $artist = DB::table('artists')
            ->where('id', $artistId)
            ->whereNull('deleted_at')
            ->first();

        $user = DB::table('users')
            ->where('id', $newUserId)
            ->first();

        if (!$artist || !$user) {
            throw ValidationException::withMessages([
                'user_id' =>
                    'Artist or user not found.',
            ]);
        }

        $oldUserId =
            $artist->user_id;

        DB::table('artists')
            ->where('id', $artistId)
            ->update([
                'user_id' =>
                    $newUserId,

                'updated_by' =>
                    $adminId,

                'updated_at' =>
                    now(),
            ]);

        $this->audit([
            'transfer_type' =>
                'artist_user_transfer',

            'entity_type' =>
                'artist',

            'entity_id' =>
                $artistId,

            'from_user_id' =>
                $oldUserId,

            'to_user_id' =>
                $newUserId,

            'revenue_scope' =>
                'access_only',

            'reason' =>
                $reason,

            'old_values' => [
                'user_id' =>
                    $oldUserId,
            ],

            'new_values' => [
                'user_id' =>
                    $newUserId,
            ],

            'transferred_by' =>
                $adminId,
        ]);
    }

    private function findTrack(
        ?string $isrc
    ): ?object {
        $normalized =
            $this->normalizeIsrc($isrc);

        if ($normalized === '') {
            return null;
        }

        return DB::table('tracks')
            ->whereNull('deleted_at')
            ->whereRaw(
                "
                REPLACE(
                    REPLACE(
                        UPPER(TRIM(isrc)),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ) = ?
                ",
                [$normalized]
            )
            ->first();
    }

    private function normalizeIsrc(
        ?string $isrc
    ): string {
        return strtoupper(
            preg_replace(
                '/[^A-Z0-9]/i',
                '',
                trim((string) $isrc)
            )
        );
    }

    private function cleanCode(
        ?string $value
    ): string {
        return trim(
            preg_replace(
                '/\.0+$/',
                '',
                (string) $value
            )
        );
    }

    private function audit(
        array $data
    ): void {
        DB::table('catalogue_transfers')
            ->insert([
                'public_id' =>
                    (string) Str::ulid(),

                'transfer_type' =>
                    $data['transfer_type'],

                'entity_type' =>
                    $data['entity_type'],

                'entity_id' =>
                    $data['entity_id'],

                'from_owner_type' =>
                    $data[
                        'from_owner_type'
                    ] ?? null,

                'from_owner_id' =>
                    $data[
                        'from_owner_id'
                    ] ?? null,

                'to_owner_type' =>
                    $data[
                        'to_owner_type'
                    ] ?? null,

                'to_owner_id' =>
                    $data[
                        'to_owner_id'
                    ] ?? null,

                'from_user_id' =>
                    $data[
                        'from_user_id'
                    ] ?? null,

                'to_user_id' =>
                    $data[
                        'to_user_id'
                    ] ?? null,

                'revenue_scope' =>
                    $data[
                        'revenue_scope'
                    ] ?? 'future_only',

                'reason' =>
                    $data['reason'] ?? null,

                'old_values' =>
                    json_encode(
                        $data[
                            'old_values'
                        ] ?? []
                    ),

                'new_values' =>
                    json_encode(
                        $data[
                            'new_values'
                        ] ?? []
                    ),

                'transferred_by' =>
                    $data[
                        'transferred_by'
                    ] ?? null,

                'transferred_at' =>
                    now(),

                'created_at' =>
                    now(),

                'updated_at' =>
                    now(),
            ]);
    }
}
PHP

# ---------------------------------------------------------
# 3. OWNERSHIP ROYALTY SERVICE
# ---------------------------------------------------------

cat > app/Services/V2/OwnershipRoyaltyService.php <<'PHP'
<?php

namespace App\Services\V2;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OwnershipRoyaltyService
{
    public function generateMonthlyStatements(
        string $month,
        float $commissionPercent = 0,
        string $currency = 'INR'
    ): array {
        $owners = DB::table('report_rows')
            ->where('sale_month', $month)
            ->where('mapping_status', 'mapped')
            ->whereNotNull(
                'revenue_owner_type'
            )
            ->whereNotNull(
                'revenue_owner_id'
            )
            ->select([
                'revenue_owner_type',
                'revenue_owner_id',
            ])
            ->distinct()
            ->get();

        $created = 0;
        $updated = 0;
        $failed = [];

        foreach ($owners as $owner) {
            try {
                $existing = DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'statement_month',
                        $month
                    )
                    ->where(
                        'currency',
                        strtoupper($currency)
                    )
                    ->when(
                        $owner->revenue_owner_type
                            === 'label',
                        fn ($query) =>
                            $query
                                ->where(
                                    'label_id',
                                    $owner
                                        ->revenue_owner_id
                                )
                                ->whereNull(
                                    'artist_id'
                                ),
                        fn ($query) =>
                            $query
                                ->where(
                                    'artist_id',
                                    $owner
                                        ->revenue_owner_id
                                )
                                ->whereNull(
                                    'label_id'
                                )
                    )
                    ->first();

                $this->generateOwnerStatement(
                    $month,
                    $owner->revenue_owner_type,
                    (int) $owner->revenue_owner_id,
                    $commissionPercent,
                    $currency,
                    $existing
                );

                $existing
                    ? $updated++
                    : $created++;
            } catch (\Throwable $exception) {
                $failed[] = [
                    'owner_type' =>
                        $owner->revenue_owner_type,

                    'owner_id' =>
                        $owner->revenue_owner_id,

                    'message' =>
                        $exception->getMessage(),
                ];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'failed_count' =>
                count($failed),
            'failed' => $failed,
        ];
    }

    private function generateOwnerStatement(
        string $month,
        string $ownerType,
        int $ownerId,
        float $commissionPercent,
        string $currency,
        ?object $existing
    ): void {
        DB::transaction(function () use (
            $month,
            $ownerType,
            $ownerId,
            $commissionPercent,
            $currency,
            $existing
        ) {
            $query = DB::table('report_rows')
                ->where(
                    'sale_month',
                    $month
                )
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->where(
                    'revenue_owner_type',
                    $ownerType
                )
                ->where(
                    'revenue_owner_id',
                    $ownerId
                );

            $gross = round(
                (float) (clone $query)
                    ->sum('earnings'),
                8
            );

            $commission = round(
                $gross
                * ($commissionPercent / 100),
                8
            );

            $net = round(
                $gross - $commission,
                8
            );

            $statementData = [
                'public_id' =>
                    $existing?->public_id
                    ?: (string) Str::ulid(),

                'artist_id' =>
                    $ownerType === 'artist'
                        ? $ownerId
                        : null,

                'label_id' =>
                    $ownerType === 'label'
                        ? $ownerId
                        : null,

                'statement_month' =>
                    $month,

                'currency' =>
                    strtoupper($currency),

                'gross_earnings' =>
                    $gross,

                'commission_amount' =>
                    $commission,

                'tax_amount' =>
                    0,

                'other_deductions' =>
                    0,

                'net_payable' =>
                    $net,

                'status' =>
                    'pending',

                'updated_at' =>
                    now(),
            ];

            if ($existing) {
                DB::table(
                    'royalty_statements'
                )
                    ->where(
                        'id',
                        $existing->id
                    )
                    ->update(
                        $statementData
                    );

                $statementId =
                    $existing->id;
            } else {
                $statementData[
                    'created_at'
                ] = now();

                $statementId =
                    DB::table(
                        'royalty_statements'
                    )->insertGetId(
                        $statementData
                    );
            }

            DB::table('royalty_allocations')
                ->where(
                    'royalty_statement_id',
                    $statementId
                )
                ->delete();

            $query
                ->select([
                    'id',
                    'release_id',
                    'track_id',
                    'earnings',
                ])
                ->orderBy('id')
                ->chunkById(
                    1000,
                    function ($rows) use (
                        $statementId,
                        $commissionPercent
                    ) {
                        $payload = [];

                        foreach ($rows as $row) {
                            $grossAmount =
                                (float) $row->earnings;

                            $payload[] = [
                                'public_id' =>
                                    (string) Str::ulid(),

                                'royalty_statement_id' =>
                                    $statementId,

                                'report_row_id' =>
                                    $row->id,

                                'release_id' =>
                                    $row->release_id,

                                'track_id' =>
                                    $row->track_id,

                                'gross_amount' =>
                                    $grossAmount,

                                'net_amount' =>
                                    round(
                                        $grossAmount
                                        * (
                                            1
                                            - (
                                                $commissionPercent
                                                / 100
                                            )
                                        ),
                                        8
                                    ),

                                'share_percentage' =>
                                    100,

                                'created_at' =>
                                    now(),

                                'updated_at' =>
                                    now(),
                            ];
                        }

                        if ($payload !== []) {
                            DB::table(
                                'royalty_allocations'
                            )->insert($payload);
                        }
                    }
                );
        });
    }
}
PHP

# ---------------------------------------------------------
# 4. TRANSFER CONTROLLER
# ---------------------------------------------------------

cat > app/Http/Controllers/V2/Admin/OwnershipTransferController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Services\V2\CatalogueOwnershipService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class OwnershipTransferController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );

        return Inertia::render(
            'V2/Admin/Ownership/Index',
            [
                'releases' =>
                    DB::table('releases')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'title',
                            'upc',
                            'artist_id',
                            'label_id',
                            'primary_artist_name',
                        ])
                        ->orderByDesc('id')
                        ->limit(300)
                        ->get(),

                'tracks' =>
                    DB::table('tracks')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'release_id',
                            'title',
                            'isrc',
                        ])
                        ->orderByDesc('id')
                        ->limit(300)
                        ->get(),

                'artists' =>
                    DB::table('artists')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'stage_name',
                            'user_id',
                            'label_id',
                        ])
                        ->orderBy('stage_name')
                        ->get(),

                'labels' =>
                    DB::table('labels')
                        ->whereNull('deleted_at')
                        ->select([
                            'id',
                            'name',
                            'user_id',
                        ])
                        ->orderBy('name')
                        ->get(),

                'users' =>
                    DB::table('users')
                        ->select([
                            'id',
                            'name',
                            'email',
                        ])
                        ->orderBy('name')
                        ->get(),

                'history' =>
                    DB::table(
                        'catalogue_transfers'
                    )
                        ->orderByDesc('id')
                        ->limit(100)
                        ->get(),
            ]
        );
    }

    public function transferRelease(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'release_id' => [
                'required',
                'integer',
                'exists:releases,id',
            ],

            'owner_type' => [
                'required',
                'in:artist,label',
            ],

            'owner_id' => [
                'required',
                'integer',
            ],

            'scope' => [
                'required',
                'in:future_only,pending_and_future',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferRelease(
            (int) $validated['release_id'],
            $validated['owner_type'],
            (int) $validated['owner_id'],
            $validated['scope'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Release ownership transferred.'
        );
    }

    public function transferTrack(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'track_id' => [
                'required',
                'integer',
                'exists:tracks,id',
            ],

            'release_id' => [
                'required',
                'integer',
                'exists:releases,id',
            ],

            'scope' => [
                'required',
                'in:future_only,pending_and_future',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferTrack(
            (int) $validated['track_id'],
            (int) $validated['release_id'],
            $validated['scope'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Track transferred successfully.'
        );
    }

    public function transferLabelUser(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'label_id' => [
                'required',
                'integer',
                'exists:labels,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferLabelUser(
            (int) $validated['label_id'],
            (int) $validated['user_id'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Label user changed successfully.'
        );
    }

    public function transferArtistUser(
        Request $request,
        PermissionService $permissions,
        CatalogueOwnershipService $ownership
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'artist_id' => [
                'required',
                'integer',
                'exists:artists,id',
            ],

            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],

            'reason' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $ownership->transferArtistUser(
            (int) $validated['artist_id'],
            (int) $validated['user_id'],
            $validated['reason'] ?? null,
            $request->user()->id
        );

        return back()->with(
            'success',
            'Artist user changed successfully.'
        );
    }

    private function authorizeSuperAdmin(
        Request $request,
        PermissionService $permissions
    ): void {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );
    }
}
PHP

# ---------------------------------------------------------
# 5. ONE-CLICK COMMAND
# ---------------------------------------------------------

cat > app/Console/Commands/V2OwnershipRepair.php <<'PHP'
<?php

namespace App\Console\Commands;

use App\Services\V2\CatalogueOwnershipService;
use App\Services\V2\OwnershipRoyaltyService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class V2OwnershipRepair extends Command
{
    protected $signature =
        'v2:ownership-repair
        {--month= : Generate statements for one month}
        {--commission=0 : Commission percentage}
        {--rebuild-pending : Remove pending/generated statements before rebuild}';

    protected $description =
        'Remap reports by ISRC/UPC and generate owner-based royalty statements';

    public function handle(
        CatalogueOwnershipService $ownership,
        OwnershipRoyaltyService $royalties
    ): int {
        $this->info(
            'Starting V2 ownership repair...'
        );

        $result =
            $ownership->remapReports();

        $this->line(
            'Mapped rows: '
            .$result['mapped']
        );

        $this->line(
            'Unmapped rows: '
            .$result['unmapped']
        );

        if ($this->option('rebuild-pending')) {
            $statementIds = DB::table(
                'royalty_statements'
            )
                ->whereIn(
                    'status',
                    [
                        'pending',
                        'generated',
                    ]
                )
                ->pluck('id');

            if ($statementIds->isNotEmpty()) {
                DB::table(
                    'royalty_allocations'
                )
                    ->whereIn(
                        'royalty_statement_id',
                        $statementIds
                    )
                    ->delete();

                DB::table(
                    'royalty_statements'
                )
                    ->whereIn(
                        'id',
                        $statementIds
                    )
                    ->delete();
            }

            $this->line(
                'Pending/generated statements cleared.'
            );
        }

        $month = trim(
            (string) $this->option('month')
        );

        $months = $month !== ''
            ? collect([$month])
            : DB::table('report_rows')
                ->where(
                    'mapping_status',
                    'mapped'
                )
                ->whereNotNull(
                    'sale_month'
                )
                ->whereRaw(
                    "sale_month REGEXP '^[0-9]{4}-[0-9]{2}$'"
                )
                ->distinct()
                ->orderBy('sale_month')
                ->pluck('sale_month');

        foreach ($months as $saleMonth) {
            $royaltyResult =
                $royalties
                    ->generateMonthlyStatements(
                        $saleMonth,
                        (float) $this->option(
                            'commission'
                        )
                    );

            $this->line(
                $saleMonth
                .' | Created: '
                .$royaltyResult['created']
                .' | Updated: '
                .$royaltyResult['updated']
                .' | Failed: '
                .$royaltyResult['failed_count']
            );

            foreach (
                $royaltyResult['failed']
                as $failure
            ) {
                $this->warn(
                    json_encode($failure)
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Check', 'Value'],
            [
                [
                    'Total report rows',
                    DB::table(
                        'report_rows'
                    )->count(),
                ],
                [
                    'Mapped rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'mapping_status',
                            'mapped'
                        )
                        ->count(),
                ],
                [
                    'Unmapped rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'mapping_status',
                            'unmapped'
                        )
                        ->count(),
                ],
                [
                    'Label-owned rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'revenue_owner_type',
                            'label'
                        )
                        ->count(),
                ],
                [
                    'Artist-owned rows',
                    DB::table(
                        'report_rows'
                    )
                        ->where(
                            'revenue_owner_type',
                            'artist'
                        )
                        ->count(),
                ],
                [
                    'Statements',
                    DB::table(
                        'royalty_statements'
                    )->count(),
                ],
                [
                    'Transfer history',
                    DB::table(
                        'catalogue_transfers'
                    )->count(),
                ],
            ]
        );

        $this->info(
            'V2 ownership repair completed.'
        );

        return self::SUCCESS;
    }
}
PHP

# ---------------------------------------------------------
# 6. BASIC TRANSFER CENTRE UI
# ---------------------------------------------------------

mkdir -p \
resources/js/Pages/V2/Admin/Ownership

cat > resources/js/Pages/V2/Admin/Ownership/Index.jsx <<'JSX'
import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/PanelLayout';

const Field = ({ label, children }) => (
    <label className="block space-y-1">
        <span className="text-sm font-semibold text-slate-700">
            {label}
        </span>
        {children}
    </label>
);

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500';

export default function OwnershipIndex({
    releases = [],
    tracks = [],
    artists = [],
    labels = [],
    users = [],
    history = [],
}) {
    const [search, setSearch] = useState('');

    const filteredReleases = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (!q) return releases;

        return releases.filter((release) =>
            [
                release.title,
                release.upc,
                release.primary_artist_name,
            ]
                .filter(Boolean)
                .some((value) =>
                    String(value)
                        .toLowerCase()
                        .includes(q)
                )
        );
    }, [releases, search]);

    const post = (url, data) => {
        router.post(url, data, {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role="super_admin"
            title="Ownership Transfer"
            subtitle="V2 Catalogue Ownership & User Transfer Centre"
        >
            <Head title="Ownership Transfer" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h1 className="text-2xl font-bold text-slate-900">
                        Ownership Transfer Centre
                    </h1>

                    <p className="mt-2 text-sm text-slate-600">
                        Revenue follows catalogue ownership resolved from
                        ISRC/UPC. Artist names remain metadata credits.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <ReleaseTransfer
                        releases={filteredReleases}
                        artists={artists}
                        labels={labels}
                        search={search}
                        setSearch={setSearch}
                        post={post}
                    />

                    <TrackTransfer
                        tracks={tracks}
                        releases={releases}
                        post={post}
                    />

                    <UserTransfer
                        title="Transfer Label User"
                        entities={labels}
                        users={users}
                        entityKey="label_id"
                        entityLabel="Label"
                        endpoint="/v2/admin/ownership/label-user"
                        post={post}
                    />

                    <UserTransfer
                        title="Transfer Artist User"
                        entities={artists}
                        users={users}
                        entityKey="artist_id"
                        entityLabel="Artist"
                        endpoint="/v2/admin/ownership/artist-user"
                        post={post}
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-200 p-4">
                        <h2 className="font-bold text-slate-900">
                            Transfer History
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Entity</th>
                                    <th className="px-4 py-3">From</th>
                                    <th className="px-4 py-3">To</th>
                                    <th className="px-4 py-3">Scope</th>
                                    <th className="px-4 py-3">Date</th>
                                </tr>
                            </thead>

                            <tbody>
                                {history.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-t border-slate-100"
                                    >
                                        <td className="px-4 py-3">
                                            {item.transfer_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.entity_type} #{item.entity_id}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.from_owner_type || 'User'}{' '}
                                            #{item.from_owner_id || item.from_user_id || '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.to_owner_type || 'User'}{' '}
                                            #{item.to_owner_id || item.to_user_id || '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.revenue_scope}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.transferred_at}
                                        </td>
                                    </tr>
                                ))}

                                {history.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No transfer history yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}

function ReleaseTransfer({
    releases,
    artists,
    labels,
    search,
    setSearch,
    post,
}) {
    const [form, setForm] = useState({
        release_id: '',
        owner_type: 'label',
        owner_id: '',
        scope: 'future_only',
        reason: '',
    });

    const owners =
        form.owner_type === 'label'
            ? labels
            : artists;

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post('/v2/admin/ownership/release', form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                Song / Release Transfer
            </h2>

            <Field label="Search ISRC / UPC / Release">
                <input
                    className={inputClass}
                    value={search}
                    onChange={(event) =>
                        setSearch(event.target.value)
                    }
                    placeholder="Search catalogue..."
                />
            </Field>

            <Field label="Release">
                <select
                    className={inputClass}
                    value={form.release_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            release_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select release</option>
                    {releases.map((release) => (
                        <option key={release.id} value={release.id}>
                            {release.title} — {release.upc || 'No UPC'}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New Revenue Owner">
                <select
                    className={inputClass}
                    value={form.owner_type}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            owner_type: event.target.value,
                            owner_id: '',
                        })
                    }
                >
                    <option value="label">Label</option>
                    <option value="artist">Artist</option>
                </select>
            </Field>

            <Field label="Owner">
                <select
                    className={inputClass}
                    value={form.owner_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            owner_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select owner</option>
                    {owners.map((owner) => (
                        <option key={owner.id} value={owner.id}>
                            {owner.name || owner.stage_name}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Revenue Scope">
                <select
                    className={inputClass}
                    value={form.scope}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            scope: event.target.value,
                        })
                    }
                >
                    <option value="future_only">
                        Future reports only
                    </option>
                    <option value="pending_and_future">
                        Unpaid historical + future
                    </option>
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Transfer Release
            </button>
        </form>
    );
}

function TrackTransfer({ tracks, releases, post }) {
    const [form, setForm] = useState({
        track_id: '',
        release_id: '',
        scope: 'future_only',
        reason: '',
    });

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post('/v2/admin/ownership/track', form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                Track Transfer
            </h2>

            <Field label="Track / ISRC">
                <select
                    className={inputClass}
                    value={form.track_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            track_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select track</option>
                    {tracks.map((track) => (
                        <option key={track.id} value={track.id}>
                            {track.title} — {track.isrc || 'No ISRC'}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New Release">
                <select
                    className={inputClass}
                    value={form.release_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            release_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select release</option>
                    {releases.map((release) => (
                        <option key={release.id} value={release.id}>
                            {release.title}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Revenue Scope">
                <select
                    className={inputClass}
                    value={form.scope}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            scope: event.target.value,
                        })
                    }
                >
                    <option value="future_only">
                        Future reports only
                    </option>
                    <option value="pending_and_future">
                        Unpaid historical + future
                    </option>
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Transfer Track
            </button>
        </form>
    );
}

function UserTransfer({
    title,
    entities,
    users,
    entityKey,
    entityLabel,
    endpoint,
    post,
}) {
    const [form, setForm] = useState({
        [entityKey]: '',
        user_id: '',
        reason: '',
    });

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post(endpoint, form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                {title}
            </h2>

            <Field label={entityLabel}>
                <select
                    className={inputClass}
                    value={form[entityKey]}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            [entityKey]: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">
                        Select {entityLabel.toLowerCase()}
                    </option>
                    {entities.map((entity) => (
                        <option key={entity.id} value={entity.id}>
                            {entity.name || entity.stage_name}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New User">
                <select
                    className={inputClass}
                    value={form.user_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            user_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select user</option>
                    {users.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.name} — {user.email}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Change User
            </button>
        </form>
    );
}
JSX

# ---------------------------------------------------------
# 7. ROUTES
# ---------------------------------------------------------

if ! grep -q "V2 OWNERSHIP ENGINE ROUTES" routes/web.php; then
cat >> routes/web.php <<'PHP'

/*
|--------------------------------------------------------------------------
| V2 OWNERSHIP ENGINE ROUTES
|--------------------------------------------------------------------------
*/

Route::middleware(['auth'])
    ->prefix('v2/admin/ownership')
    ->name('v2.admin.ownership.')
    ->group(function () {
        Route::get(
            '/',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'index',
            ]
        )->name('index');

        Route::post(
            '/release',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferRelease',
            ]
        )->name('release');

        Route::post(
            '/track',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferTrack',
            ]
        )->name('track');

        Route::post(
            '/label-user',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferLabelUser',
            ]
        )->name('label-user');

        Route::post(
            '/artist-user',
            [
                \App\Http\Controllers\V2\Admin\OwnershipTransferController::class,
                'transferArtistUser',
            ]
        )->name('artist-user');
    });
PHP
fi

# ---------------------------------------------------------
# 8. SYNTAX CHECKS
# ---------------------------------------------------------

php -l "$MIGRATION"
php -l app/Services/V2/CatalogueOwnershipService.php
php -l app/Services/V2/OwnershipRoyaltyService.php
php -l app/Http/Controllers/V2/Admin/OwnershipTransferController.php
php -l app/Console/Commands/V2OwnershipRepair.php

# ---------------------------------------------------------
# 9. MIGRATE / BUILD / CACHE
# ---------------------------------------------------------

php artisan migrate --force
php artisan optimize:clear

if [ -f package.json ]; then
    npm run build
fi

echo ""
echo "========================================"
echo " INSTALLATION COMPLETE"
echo "========================================"

php artisan route:list \
| grep "v2/admin/ownership" || true

php artisan list \
| grep "v2:ownership-repair" || true

echo ""
echo "Backup directory:"
echo "$BACKUP"
echo ""
echo "Transfer Centre:"
echo "/v2/admin/ownership"
echo ""
echo "Initial ownership rebuild command:"
echo "php artisan v2:ownership-repair --rebuild-pending --commission=0"
