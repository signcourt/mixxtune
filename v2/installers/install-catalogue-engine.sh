#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/catalogue-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    app/Models \
    v2/runtime/state

echo "=================================================="
echo "V2 CATALOGUE BACKEND ENGINE"
echo "=================================================="

echo "[1/10] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Permissions/permissions.js \
    app/Services/V2/ReleaseWorkflowService.php \
    app/Models/CatalogueItem.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/10] Creating catalogue migrations..."

MIGRATION="database/migrations/2026_08_01_000005_create_v2_catalogue_tables.php"

if [ ! -f "$MIGRATION" ]; then
cat > "$MIGRATION" <<'PHP'
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('catalogue_items')) {
            Schema::create(
                'catalogue_items',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'release_id'
                    )->unique();

                    $table->unsignedBigInteger(
                        'artist_id'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'label_id'
                    )->nullable();

                    $table->string(
                        'title'
                    );

                    $table->string(
                        'release_type',
                        50
                    )->nullable();

                    $table->string(
                        'primary_artist_name'
                    )->nullable();

                    $table->json(
                        'primary_artists'
                    )->nullable();

                    $table->json(
                        'featuring_artists'
                    )->nullable();

                    $table->string(
                        'label_name'
                    )->nullable();

                    $table->string(
                        'upc',
                        30
                    )->nullable();

                    $table->string(
                        'catalog_number',
                        100
                    )->nullable();

                    $table->string(
                        'language',
                        100
                    )->nullable();

                    $table->string(
                        'primary_genre',
                        100
                    )->nullable();

                    $table->string(
                        'sub_genre',
                        100
                    )->nullable();

                    $table->string(
                        'artwork_path'
                    )->nullable();

                    $table->date(
                        'digital_release_date'
                    )->nullable();

                    $table->string(
                        'release_status',
                        50
                    );

                    $table->unsignedInteger(
                        'track_count'
                    )->default(0);

                    $table->unsignedInteger(
                        'isrc_assigned_count'
                    )->default(0);

                    $table->json(
                        'store_ids'
                    )->nullable();

                    $table->json(
                        'delivery_summary'
                    )->nullable();

                    $table->boolean(
                        'is_visible'
                    )->default(false);

                    $table->timestamp(
                        'last_synced_at'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'artist_id',
                        'release_status',
                    ]);

                    $table->index([
                        'label_id',
                        'release_status',
                    ]);

                    $table->index([
                        'is_visible',
                        'digital_release_date',
                    ]);

                    $table->index('upc');
                    $table->index('catalog_number');

                    $table->foreign('release_id')
                        ->references('id')
                        ->on('releases')
                        ->cascadeOnDelete();
                }
            );
        }

        if (
            !Schema::hasTable(
                'catalogue_sync_logs'
            )
        ) {
            Schema::create(
                'catalogue_sync_logs',
                function (Blueprint $table) {
                    $table->id();

                    $table->string(
                        'public_id',
                        40
                    )->unique();

                    $table->unsignedBigInteger(
                        'release_id'
                    );

                    $table->string(
                        'action',
                        50
                    );

                    $table->string(
                        'release_status',
                        50
                    )->nullable();

                    $table->boolean(
                        'successful'
                    )->default(true);

                    $table->text(
                        'message'
                    )->nullable();

                    $table->unsignedBigInteger(
                        'performed_by'
                    )->nullable();

                    $table->timestamps();

                    $table->index([
                        'release_id',
                        'created_at',
                    ]);
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Production catalogue data should not be
         * removed automatically.
         */
    }
};
PHP
fi


echo "[3/10] Creating CatalogueItem model..."

cat > app/Models/CatalogueItem.php <<'PHP'
<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;

class CatalogueItem extends Model
{
    protected $fillable = [
        'public_id',
        'release_id',
        'artist_id',
        'label_id',
        'title',
        'release_type',
        'primary_artist_name',
        'primary_artists',
        'featuring_artists',
        'label_name',
        'upc',
        'catalog_number',
        'language',
        'primary_genre',
        'sub_genre',
        'artwork_path',
        'digital_release_date',
        'release_status',
        'track_count',
        'isrc_assigned_count',
        'store_ids',
        'delivery_summary',
        'is_visible',
        'last_synced_at',
    ];

    protected $casts = [
        'primary_artists' => 'array',
        'featuring_artists' => 'array',
        'store_ids' => 'array',
        'delivery_summary' => 'array',
        'digital_release_date' => 'date',
        'is_visible' => 'boolean',
        'last_synced_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }
}
PHP


echo "[4/10] Updating catalogue permissions..."

python3 - <<'PY'
from pathlib import Path

files = [
    Path(
        "app/Services/V2/PermissionService.php"
    ),
    Path(
        "resources/js/V2/Shared/Permissions/permissions.js"
    ),
]

permissions = [
    "catalogue.manage",
    "catalogue.sync",
    "catalogue.export",
]

for path in files:
    if not path.exists():
        continue

    text = path.read_text()

    marker = "'catalogue.view',"

    if marker not in text:
        continue

    additions = []

    for permission in permissions:
        token = f"'{permission}',"

        if token not in text:
            additions.append(
                "            " + token
            )

    if additions:
        text = text.replace(
            marker,
            marker + "\n" + "\n".join(additions),
            1
        )

        path.write_text(text)

        print(f"Updated: {path}")
PY


echo "[5/10] Creating Catalogue Service..."

cat > app/Services/V2/CatalogueService.php <<'PHP'
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

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            return $query->whereIn(
                'artist_id',
                $artistIds
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
PHP


echo "[6/10] Connecting Catalogue to Release Workflow..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Services/V2/ReleaseWorkflowService.php"
)

text = path.read_text()

old = """    public function __construct(
        private readonly ReleaseValidationService $validator,
        private readonly NotificationService $notifications
    ) {
    }
"""

new = """    public function __construct(
        private readonly ReleaseValidationService $validator,
        private readonly NotificationService $notifications,
        private readonly CatalogueService $catalogue
    ) {
    }
"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )
elif "private readonly CatalogueService" not in text:
    print(
        "Workflow constructor exact format me nahi mila."
    )

marker = """            match ($newStatus) {
"""

sync_code = """            /*
             * Catalogue synchronization is independent
             * from the panel interface.
             */
            if (
                in_array(
                    $newStatus,
                    [
                        'approved',
                        'processing',
                        'delivered',
                        'live',
                        'takedown_requested',
                    ],
                    true
                )
            ) {
                $this->catalogue->syncRelease(
                    $freshRelease,
                    $user
                );
            } elseif (
                in_array(
                    $newStatus,
                    [
                        'draft',
                        'submitted',
                        'changes_requested',
                        'rejected',
                        'taken_down',
                        'archived',
                    ],
                    true
                )
            ) {
                $this->catalogue->removeVisibility(
                    $freshRelease,
                    $user
                );
            }

"""

if (
    sync_code.strip() not in text
    and marker in text
):
    text = text.replace(
        marker,
        sync_code + marker,
        1
    )

path.write_text(text)

print("Catalogue workflow connection processed.")
PY


echo "[7/10] Creating role-scoped Catalogue Controller..."

cat > app/Http/Controllers/V2/CatalogueController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\CatalogueItem;
use App\Services\V2\CatalogueService;
use App\Services\V2\PermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $filters = [
            'search' => trim(
                (string) $request->input(
                    'search',
                    ''
                )
            ),

            'status' => trim(
                (string) $request->input(
                    'status',
                    ''
                )
            ),

            'visibility' => trim(
                (string) $request->input(
                    'visibility',
                    ''
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'latest'
                )
            ),
        ];

        $query = $catalogue->scopedQuery(
            $request->user(),
            $permissions
        );

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(function ($builder) use ($search) {
                $builder
                    ->where(
                        'title',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'primary_artist_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'label_name',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'upc',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhere(
                        'catalog_number',
                        'like',
                        "%{$search}%"
                    )
                    ->orWhereHas(
                        'release.tracks',
                        function ($trackQuery) use ($search) {
                            $trackQuery
                                ->where(
                                    'title',
                                    'like',
                                    "%{$search}%"
                                )
                                ->orWhere(
                                    'isrc',
                                    'like',
                                    "%{$search}%"
                                );
                        }
                    );
            });
        }

        if ($filters['status'] !== '') {
            $query->where(
                'release_status',
                $filters['status']
            );
        }

        if ($filters['visibility'] === 'visible') {
            $query->where(
                'is_visible',
                true
            );
        }

        if ($filters['visibility'] === 'hidden') {
            $query->where(
                'is_visible',
                false
            );
        }

        match ($filters['sort']) {
            'oldest' =>
                $query->orderBy('id'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            'release_date' =>
                $query->orderByDesc(
                    'digital_release_date'
                ),

            default =>
                $query->orderByDesc('id'),
        };

        return response()->json([
            'filters' => $filters,

            'counts' => [
                'total' =>
                    (clone $catalogue->scopedQuery(
                        $request->user(),
                        $permissions
                    ))->count(),

                'visible' =>
                    (clone $catalogue->scopedQuery(
                        $request->user(),
                        $permissions
                    ))
                        ->where(
                            'is_visible',
                            true
                        )
                        ->count(),

                'live' =>
                    (clone $catalogue->scopedQuery(
                        $request->user(),
                        $permissions
                    ))
                        ->where(
                            'release_status',
                            'live'
                        )
                        ->count(),

                'processing' =>
                    (clone $catalogue->scopedQuery(
                        $request->user(),
                        $permissions
                    ))
                        ->where(
                            'release_status',
                            'processing'
                        )
                        ->count(),
            ],

            'catalogue' =>
                $query
                    ->paginate(25)
                    ->withQueryString(),
        ]);
    }

    public function show(
        Request $request,
        CatalogueItem $catalogueItem,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.view'
        );

        $allowed = $catalogue
            ->scopedQuery(
                $request->user(),
                $permissions
            )
            ->where(
                'id',
                $catalogueItem->id
            )
            ->exists();

        abort_unless(
            $allowed,
            403,
            'You cannot access this catalogue item.'
        );

        $catalogueItem->load([
            'release.tracks',
        ]);

        return response()->json([
            'catalogue_item' =>
                $catalogueItem,
        ]);
    }
}
PHP


echo "[8/10] Creating Admin Catalogue Controller..."

cat > app/Http/Controllers/V2/Admin/CatalogueController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\CatalogueService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CatalogueController extends Controller
{
    public function syncRelease(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.sync'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $item = $catalogue->syncRelease(
            $release,
            $request->user()
        );

        return response()->json([
            'message' =>
                'Release synchronized with catalogue.',

            'catalogue_item' =>
                $item,
        ]);
    }

    public function bulkSync(
        Request $request,
        PermissionService $permissions,
        CatalogueService $catalogue
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'catalogue.sync'
        );

        $validated = $request->validate([
            'statuses' => [
                'nullable',
                'array',
                'min:1',
            ],

            'statuses.*' => [
                'string',
                'in:approved,processing,delivered,live,takedown_requested,taken_down',
            ],

            'limit' => [
                'nullable',
                'integer',
                'min:1',
                'max:10000',
            ],
        ]);

        $result = $catalogue->syncByStatus(
            $validated['statuses']
                ?? [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                    'takedown_requested',
                ],
            $request->user(),
            $validated['limit'] ?? 1000
        );

        return response()->json([
            'message' =>
                'Catalogue synchronization completed.',

            'result' =>
                $result,
        ]);
    }
}
PHP


echo "[9/10] Adding catalogue routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.catalogue.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue',
        [\App\Http\Controllers\V2\CatalogueController::class, 'index']
    )
    ->name('v2.catalogue.index');
""",

    "v2.catalogue.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/catalogue/{catalogueItem}',
        [\App\Http\Controllers\V2\CatalogueController::class, 'show']
    )
    ->name('v2.catalogue.show');
""",

    "v2.admin.catalogue.sync-release": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/catalogue/releases/{release}/sync',
        [\App\Http\Controllers\V2\Admin\CatalogueController::class, 'syncRelease']
    )
    ->name('v2.admin.catalogue.sync-release');
""",

    "v2.admin.catalogue.bulk-sync": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/catalogue/bulk-sync',
        [\App\Http\Controllers\V2\Admin\CatalogueController::class, 'bulkSync']
    )
    ->name('v2.admin.catalogue.bulk-sync');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} catalogue routes added.")
PY


echo "[10/10] Running migrations and checks..."

php artisan migrate --force

php -l app/Models/CatalogueItem.php
php -l app/Services/V2/CatalogueService.php
php -l app/Services/V2/ReleaseWorkflowService.php
php -l app/Http/Controllers/V2/CatalogueController.php
php -l app/Http/Controllers/V2/Admin/CatalogueController.php
php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
echo get_class(
    app(
        \App\Services\V2\CatalogueService::class
    )
).PHP_EOL;
"

printf '{\n  "module": "CatalogueEngine",\n  "installed": true,\n  "version": "2.8.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/catalogue-engine-installed.json

echo ""
echo "===== CATALOGUE ROUTES ====="

php artisan route:list | grep \
"v2/.*catalogue"

echo ""
echo "=================================================="
echo "CATALOGUE ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/catalogue-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
