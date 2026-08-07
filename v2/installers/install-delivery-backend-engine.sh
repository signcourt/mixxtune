#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/delivery-backend-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2/Admin \
    v2/runtime/state

echo "=================================================="
echo "V2 DELIVERY BACKEND ENGINE"
echo "=================================================="

echo "[1/10] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    resources/js/V2/Shared/Permissions/permissions.js \
    app/Services/V2/ReleaseWorkflowService.php \
    app/Models/ReleaseStoreDelivery.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/10] Creating delivery table migration..."

MIGRATION="database/migrations/2026_08_01_000002_create_v2_release_store_deliveries_table.php"

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
        if (
            Schema::hasTable(
                'release_store_deliveries'
            )
        ) {
            Schema::table(
                'release_store_deliveries',
                function (Blueprint $table) {
                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'public_id'
                        )
                    ) {
                        $table->string(
                            'public_id',
                            40
                        )->nullable()->unique();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'status'
                        )
                    ) {
                        $table->string(
                            'status',
                            30
                        )->default('pending');
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'delivery_note'
                        )
                    ) {
                        $table->text(
                            'delivery_note'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'error_message'
                        )
                    ) {
                        $table->text(
                            'error_message'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'external_reference'
                        )
                    ) {
                        $table->string(
                            'external_reference'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'delivered_at'
                        )
                    ) {
                        $table->timestamp(
                            'delivered_at'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'live_at'
                        )
                    ) {
                        $table->timestamp(
                            'live_at'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'failed_at'
                        )
                    ) {
                        $table->timestamp(
                            'failed_at'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'taken_down_at'
                        )
                    ) {
                        $table->timestamp(
                            'taken_down_at'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'created_by'
                        )
                    ) {
                        $table->unsignedBigInteger(
                            'created_by'
                        )->nullable();
                    }

                    if (
                        !Schema::hasColumn(
                            'release_store_deliveries',
                            'updated_by'
                        )
                    ) {
                        $table->unsignedBigInteger(
                            'updated_by'
                        )->nullable();
                    }
                }
            );

            return;
        }

        Schema::create(
            'release_store_deliveries',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'public_id',
                    40
                )->unique();

                $table->unsignedBigInteger(
                    'release_id'
                );

                $table->unsignedBigInteger(
                    'distribution_store_id'
                );

                $table->string(
                    'status',
                    30
                )->default('pending');

                $table->text(
                    'delivery_note'
                )->nullable();

                $table->text(
                    'error_message'
                )->nullable();

                $table->string(
                    'external_reference'
                )->nullable();

                $table->timestamp(
                    'delivered_at'
                )->nullable();

                $table->timestamp(
                    'live_at'
                )->nullable();

                $table->timestamp(
                    'failed_at'
                )->nullable();

                $table->timestamp(
                    'taken_down_at'
                )->nullable();

                $table->unsignedBigInteger(
                    'created_by'
                )->nullable();

                $table->unsignedBigInteger(
                    'updated_by'
                )->nullable();

                $table->timestamps();
                $table->softDeletes();

                $table->unique([
                    'release_id',
                    'distribution_store_id',
                ], 'release_store_unique');

                $table->index([
                    'release_id',
                    'status',
                ]);

                $table->foreign('release_id')
                    ->references('id')
                    ->on('releases')
                    ->cascadeOnDelete();

                $table->foreign(
                    'distribution_store_id'
                )
                    ->references('id')
                    ->on('distribution_stores')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        /*
         * Table may already be used by V1.
         * Do not automatically delete it.
         */
    }
};
PHP
fi


echo "[3/10] Creating ReleaseStoreDelivery model..."

cat > app/Models/ReleaseStoreDelivery.php <<'PHP'
<?php

namespace App\Models;

use App\Models\Distribution\Release;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class ReleaseStoreDelivery extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'public_id',
        'release_id',
        'distribution_store_id',
        'status',
        'delivery_note',
        'error_message',
        'external_reference',
        'delivered_at',
        'live_at',
        'failed_at',
        'taken_down_at',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'delivered_at' => 'datetime',
        'live_at' => 'datetime',
        'failed_at' => 'datetime',
        'taken_down_at' => 'datetime',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }

    public function store()
    {
        return $this->belongsTo(
            DistributionStore::class,
            'distribution_store_id'
        );
    }
}
PHP


echo "[4/10] Updating delivery permissions..."

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
    "delivery.view",
    "delivery.manage",
    "delivery.retry",
    "delivery.mark_delivered",
    "delivery.mark_live",
    "delivery.mark_failed",
    "delivery.takedown",
]

for path in files:
    if not path.exists():
        continue

    text = path.read_text()

    marker = "'delivery.manage',"

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


echo "[5/10] Creating Delivery Workflow Service..."

cat > app/Services/V2/DeliveryWorkflowService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\DistributionStore;
use App\Models\ReleaseStoreDelivery;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DeliveryWorkflowService
{
    private const STATUSES = [
        'pending',
        'processing',
        'delivered',
        'live',
        'failed',
        'takedown_requested',
        'taken_down',
    ];

    private const TRANSITIONS = [
        'pending' => [
            'processing',
            'failed',
        ],

        'processing' => [
            'delivered',
            'failed',
        ],

        'delivered' => [
            'live',
            'failed',
            'takedown_requested',
        ],

        'live' => [
            'takedown_requested',
            'failed',
        ],

        'failed' => [
            'processing',
        ],

        'takedown_requested' => [
            'taken_down',
            'live',
        ],

        'taken_down' => [],
    ];

    public function __construct(
        private readonly ReleaseWorkflowService $releaseWorkflow
    ) {
    }

    public function initialise(
        Release $release,
        User $user
    ): Collection {
        abort_unless(
            in_array(
                $release->status,
                [
                    'approved',
                    'processing',
                    'delivered',
                    'live',
                ],
                true
            ),
            422,
            'Release must be approved before delivery starts.'
        );

        $storeIds = $this->resolveStoreIds(
            $release
        );

        if (empty($storeIds)) {
            throw ValidationException::withMessages([
                'stores' =>
                    'No active stores are available for delivery.',
            ]);
        }

        DB::transaction(function () use (
            $release,
            $storeIds,
            $user
        ) {
            foreach ($storeIds as $storeId) {
                ReleaseStoreDelivery::query()
                    ->firstOrCreate(
                        [
                            'release_id' =>
                                $release->id,

                            'distribution_store_id' =>
                                $storeId,
                        ],
                        [
                            'public_id' =>
                                (string) Str::ulid(),

                            'status' =>
                                'pending',

                            'created_by' =>
                                $user->id,

                            'updated_by' =>
                                $user->id,
                        ]
                    );
            }

            if ($release->status === 'approved') {
                $this->releaseWorkflow
                    ->transition(
                        $release,
                        'processing',
                        $user,
                        [
                            'action' =>
                                'delivery_initialised',

                            'remarks' =>
                                'Store delivery records created.',
                        ]
                    );
            }
        });

        return $this->deliveries(
            $release
        );
    }

    public function transition(
        ReleaseStoreDelivery $delivery,
        string $newStatus,
        User $user,
        array $data = []
    ): ReleaseStoreDelivery {
        $newStatus = trim(
            strtolower($newStatus)
        );

        abort_unless(
            in_array(
                $newStatus,
                self::STATUSES,
                true
            ),
            422,
            'Invalid delivery status.'
        );

        $oldStatus = (string) $delivery->status;

        abort_unless(
            in_array(
                $newStatus,
                self::TRANSITIONS[
                    $oldStatus
                ] ?? [],
                true
            ),
            422,
            "Store delivery cannot move from {$oldStatus} to {$newStatus}."
        );

        if (
            $newStatus === 'failed'
            && trim(
                (string) (
                    $data['error_message']
                    ?? ''
                )
            ) === ''
        ) {
            throw ValidationException::withMessages([
                'error_message' =>
                    'Failure reason is required.',
            ]);
        }

        DB::transaction(function () use (
            $delivery,
            $newStatus,
            $user,
            $data
        ) {
            $updates = [
                'status' =>
                    $newStatus,

                'delivery_note' =>
                    $data['delivery_note']
                    ?? $delivery
                        ->delivery_note,

                'external_reference' =>
                    $data[
                        'external_reference'
                    ]
                    ?? $delivery
                        ->external_reference,

                'updated_by' =>
                    $user->id,
            ];

            if ($newStatus === 'processing') {
                $updates['error_message'] = null;
                $updates['failed_at'] = null;
            }

            if ($newStatus === 'delivered') {
                $updates['delivered_at'] = now();
                $updates['error_message'] = null;
            }

            if ($newStatus === 'live') {
                $updates['live_at'] = now();
                $updates['error_message'] = null;
            }

            if ($newStatus === 'failed') {
                $updates['failed_at'] = now();

                $updates['error_message'] =
                    $data['error_message'];
            }

            if (
                $newStatus ===
                'takedown_requested'
            ) {
                $updates['delivery_note'] =
                    $data['delivery_note']
                    ?? 'Takedown requested.';
            }

            if ($newStatus === 'taken_down') {
                $updates['taken_down_at'] = now();
            }

            $delivery->update($updates);
        });

        $this->syncReleaseStatus(
            $delivery->release,
            $user
        );

        return $delivery->fresh([
            'store',
            'release',
        ]);
    }

    public function bulkTransition(
        Release $release,
        array $deliveryIds,
        string $newStatus,
        User $user,
        array $data = []
    ): Collection {
        $deliveries =
            ReleaseStoreDelivery::query()
                ->where(
                    'release_id',
                    $release->id
                )
                ->whereIn(
                    'id',
                    array_map(
                        'intval',
                        $deliveryIds
                    )
                )
                ->get();

        abort_if(
            $deliveries->isEmpty(),
            422,
            'Select at least one store delivery.'
        );

        foreach ($deliveries as $delivery) {
            $this->transition(
                $delivery,
                $newStatus,
                $user,
                $data
            );
        }

        return $this->deliveries(
            $release
        );
    }

    public function deliveries(
        Release $release
    ): Collection {
        return ReleaseStoreDelivery::query()
            ->with('store')
            ->where(
                'release_id',
                $release->id
            )
            ->orderBy('id')
            ->get();
    }

    public function summary(
        Release $release
    ): array {
        $deliveries = $this->deliveries(
            $release
        );

        return [
            'total' => $deliveries->count(),

            'pending' =>
                $deliveries
                    ->where(
                        'status',
                        'pending'
                    )
                    ->count(),

            'processing' =>
                $deliveries
                    ->where(
                        'status',
                        'processing'
                    )
                    ->count(),

            'delivered' =>
                $deliveries
                    ->where(
                        'status',
                        'delivered'
                    )
                    ->count(),

            'live' =>
                $deliveries
                    ->where(
                        'status',
                        'live'
                    )
                    ->count(),

            'failed' =>
                $deliveries
                    ->where(
                        'status',
                        'failed'
                    )
                    ->count(),

            'takedown_requested' =>
                $deliveries
                    ->where(
                        'status',
                        'takedown_requested'
                    )
                    ->count(),

            'taken_down' =>
                $deliveries
                    ->where(
                        'status',
                        'taken_down'
                    )
                    ->count(),
        ];
    }

    private function syncReleaseStatus(
        Release $release,
        User $user
    ): void {
        $summary = $this->summary(
            $release
        );

        if ($summary['total'] === 0) {
            return;
        }

        if (
            $summary['live']
            === $summary['total']
            && $release->status !== 'live'
        ) {
            $this->safeReleaseTransition(
                $release,
                'live',
                $user,
                'All store deliveries are live.'
            );

            return;
        }

        if (
            $summary['taken_down']
            === $summary['total']
            && $release->status
                !== 'taken_down'
        ) {
            $this->safeReleaseTransition(
                $release,
                'taken_down',
                $user,
                'All store deliveries were taken down.'
            );

            return;
        }

        if (
            (
                $summary['delivered']
                + $summary['live']
            ) === $summary['total']
            && !in_array(
                $release->status,
                [
                    'delivered',
                    'live',
                ],
                true
            )
        ) {
            $this->safeReleaseTransition(
                $release,
                'delivered',
                $user,
                'All store deliveries completed.'
            );

            return;
        }

        if (
            $summary['processing'] > 0
            && $release->status === 'approved'
        ) {
            $this->safeReleaseTransition(
                $release,
                'processing',
                $user,
                'Store processing started.'
            );
        }
    }

    private function safeReleaseTransition(
        Release $release,
        string $status,
        User $user,
        string $remarks
    ): void {
        $release->refresh();

        if (
            $this->releaseWorkflow
                ->canTransition(
                    $release,
                    $status
                )
        ) {
            $this->releaseWorkflow
                ->transition(
                    $release,
                    $status,
                    $user,
                    [
                        'action' =>
                            "delivery_{$status}",

                        'remarks' =>
                            $remarks,
                    ]
                );
        }
    }

    private function resolveStoreIds(
        Release $release
    ): array {
        $storeIds = is_array(
            $release->stores
        )
            ? array_values(
                array_unique(
                    array_map(
                        'intval',
                        $release->stores
                    )
                )
            )
            : [];

        if (!empty($storeIds)) {
            return DistributionStore::query()
                ->where('is_active', true)
                ->whereIn('id', $storeIds)
                ->pluck('id')
                ->map(
                    fn ($id) => (int) $id
                )
                ->all();
        }

        return DistributionStore::query()
            ->where('is_active', true)
            ->pluck('id')
            ->map(
                fn ($id) => (int) $id
            )
            ->all();
    }
}
PHP


echo "[6/10] Creating Admin Delivery Controller..."

cat > app/Http/Controllers/V2/Admin/DeliveryController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Models\ReleaseStoreDelivery;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function index(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.view'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json([
            'release' => [
                'id' => $release->id,
                'title' => $release->title,
                'status' => $release->status,
                'upc' => $release->upc,
                'catalog_number' =>
                    $release->catalog_number,
            ],

            'summary' =>
                $delivery->summary($release),

            'deliveries' =>
                $delivery->deliveries(
                    $release
                ),
        ]);
    }

    public function initialise(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $deliveries =
            $delivery->initialise(
                $release,
                $request->user()
            );

        return response()->json([
            'message' =>
                'Delivery records initialised.',

            'summary' =>
                $delivery->summary(
                    $release
                ),

            'deliveries' =>
                $deliveries,
        ]);
    }

    public function update(
        Request $request,
        ReleaseStoreDelivery $deliveryRecord,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $deliveryRecord->loadMissing(
            'release'
        );

        abort_unless(
            $deliveryRecord->release,
            404,
            'Release not found.'
        );

        $access->authorizeView(
            $request->user(),
            $deliveryRecord->release
        );

        $validated = $request->validate([
            'status' => [
                'required',
                'string',
                'in:pending,processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $record = $delivery->transition(
            $deliveryRecord,
            $validated['status'],
            $request->user(),
            $validated
        );

        return response()->json([
            'message' =>
                'Store delivery status updated.',

            'delivery' =>
                $record,

            'summary' =>
                $delivery->summary(
                    $record->release
                ),
        ]);
    }

    public function bulkUpdate(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'delivery.manage'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $validated = $request->validate([
            'delivery_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'delivery_ids.*' => [
                'required',
                'integer',
                'distinct',
                'exists:release_store_deliveries,id',
            ],

            'status' => [
                'required',
                'string',
                'in:pending,processing,delivered,live,failed,takedown_requested,taken_down',
            ],

            'delivery_note' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'error_message' => [
                'nullable',
                'string',
                'max:5000',
            ],

            'external_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
        ]);

        $deliveries =
            $delivery->bulkTransition(
                $release,
                $validated['delivery_ids'],
                $validated['status'],
                $request->user(),
                $validated
            );

        return response()->json([
            'message' =>
                'Selected store deliveries updated.',

            'summary' =>
                $delivery->summary(
                    $release
                ),

            'deliveries' =>
                $deliveries,
        ]);
    }
}
PHP


echo "[7/10] Creating read-only Delivery Controller..."

cat > app/Http/Controllers/V2/DeliveryController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\DeliveryWorkflowService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryController extends Controller
{
    public function show(
        Request $request,
        Release $release,
        PermissionService $permissions,
        ReleaseAccessService $access,
        DeliveryWorkflowService $delivery
    ): JsonResponse {
        $permissions->authorize(
            $request->user(),
            'releases.view'
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json([
            'release' => [
                'id' => $release->id,
                'title' => $release->title,
                'status' => $release->status,
                'upc' => $release->upc,
                'catalog_number' =>
                    $release->catalog_number,
            ],

            'summary' =>
                $delivery->summary($release),

            'deliveries' =>
                $delivery->deliveries(
                    $release
                ),
        ]);
    }
}
PHP


echo "[8/10] Adding delivery routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.releases.delivery.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/delivery',
        [\App\Http\Controllers\V2\DeliveryController::class, 'show']
    )
    ->name('v2.releases.delivery.show');
""",

    "v2.admin.delivery.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/releases/{release}/delivery',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'index']
    )
    ->name('v2.admin.delivery.index');
""",

    "v2.admin.delivery.initialise": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/releases/{release}/delivery/initialise',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'initialise']
    )
    ->name('v2.admin.delivery.initialise');
""",

    "v2.admin.delivery.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/deliveries/{deliveryRecord}',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'update']
    )
    ->name('v2.admin.delivery.update');
""",

    "v2.admin.delivery.bulk-update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/releases/{release}/delivery/bulk',
        [\App\Http\Controllers\V2\Admin\DeliveryController::class, 'bulkUpdate']
    )
    ->name('v2.admin.delivery.bulk-update');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} delivery routes added.")
PY


echo "[9/10] Running migrations and syntax checks..."

php artisan migrate --force

php -l app/Models/ReleaseStoreDelivery.php
php -l app/Services/V2/DeliveryWorkflowService.php
php -l app/Http/Controllers/V2/DeliveryController.php
php -l app/Http/Controllers/V2/Admin/DeliveryController.php
php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
\$service = app(
    \App\Services\V2\DeliveryWorkflowService::class
);

echo get_class(\$service).PHP_EOL;
"


echo "[10/10] Saving installation state..."

printf '{\n  "module": "DeliveryBackendEngine",\n  "installed": true,\n  "version": "2.5.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/delivery-backend-engine-installed.json

echo ""
echo "===== DELIVERY ROUTES ====="

php artisan route:list | grep -E \
"v2/(admin/.*delivery|releases/.*/delivery)"

echo ""
echo "=================================================="
echo "DELIVERY BACKEND ENGINE INSTALLED"
echo "=================================================="

echo ""
echo "State:"

cat \
v2/runtime/state/delivery-backend-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
