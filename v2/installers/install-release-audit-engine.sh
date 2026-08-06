#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/release-audit-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Models \
    v2/runtime/state

echo "=================================================="
echo "V2 RELEASE AUDIT & ACTIVITY ENGINE"
echo "=================================================="

echo "[1/9] Creating backups..."

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/ReleaseController.php \
    app/Http/Controllers/V2/ReleaseTrackController.php \
    app/Services/V2/ReleaseWorkflowService.php \
    app/Services/V2/IsrcService.php \
    app/Services/V2/UpcService.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/9] Creating release activity migration..."

MIGRATION="database/migrations/2026_08_01_000006_create_release_activity_logs_table.php"

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
                'release_activity_logs'
            )
        ) {
            return;
        }

        Schema::create(
            'release_activity_logs',
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
                    'track_id'
                )->nullable();

                $table->unsignedBigInteger(
                    'user_id'
                )->nullable();

                $table->string(
                    'action',
                    100
                );

                $table->string(
                    'category',
                    50
                )->default('general');

                $table->string(
                    'title',
                    255
                );

                $table->text(
                    'description'
                )->nullable();

                $table->json(
                    'old_values'
                )->nullable();

                $table->json(
                    'new_values'
                )->nullable();

                $table->json(
                    'meta'
                )->nullable();

                $table->string(
                    'ip_address',
                    64
                )->nullable();

                $table->text(
                    'user_agent'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'release_id',
                    'created_at',
                ]);

                $table->index([
                    'category',
                    'action',
                ]);

                $table->foreign(
                    'release_id'
                )
                    ->references('id')
                    ->on('releases')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'release_activity_logs'
        );
    }
};
PHP
fi


echo "[3/9] Creating ReleaseActivityLog model..."

cat > app/Models/ReleaseActivityLog.php <<'PHP'
<?php

namespace App\Models;

use App\Models\Distribution\Release;
use App\Models\Distribution\Track;
use Illuminate\Database\Eloquent\Model;

class ReleaseActivityLog extends Model
{
    protected $fillable = [
        'public_id',
        'release_id',
        'track_id',
        'user_id',
        'action',
        'category',
        'title',
        'description',
        'old_values',
        'new_values',
        'meta',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'meta' => 'array',
    ];

    public function release()
    {
        return $this->belongsTo(
            Release::class
        );
    }

    public function track()
    {
        return $this->belongsTo(
            Track::class
        );
    }

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
PHP


echo "[4/9] Creating Release Audit Service..."

cat > app/Services/V2/ReleaseAuditService.php <<'PHP'
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
PHP


echo "[5/9] Connecting workflow audit..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Services/V2/ReleaseWorkflowService.php"
)

text = path.read_text()

old = """    public function __construct(
        private readonly ReleaseValidationService $validator,
        private readonly NotificationService $notifications,
        private readonly CatalogueService $catalogue
    ) {
    }
"""

new = """    public function __construct(
        private readonly ReleaseValidationService $validator,
        private readonly NotificationService $notifications,
        private readonly CatalogueService $catalogue,
        private readonly ReleaseAuditService $audit
    ) {
    }
"""

if old in text:
    text = text.replace(
        old,
        new,
        1
    )
elif "private readonly ReleaseAuditService" not in text:
    print(
        "ReleaseWorkflow constructor exact format me nahi mila."
    )

marker = """            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );
"""

replacement = """            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );

            $this->audit->statusChanged(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context['remarks']
                    ?? $context['notes']
                    ?? $context['reason']
                    ?? null
            );
"""

if marker in text:
    text = text.replace(
        marker,
        replacement,
        1
    )

path.write_text(text)

print("ReleaseWorkflow audit connected.")
PY


echo "[6/9] Connecting identifier audit..."

python3 - <<'PY'
from pathlib import Path

for file_name, service_type in [
    (
        "app/Services/V2/IsrcService.php",
        "isrc"
    ),
    (
        "app/Services/V2/UpcService.php",
        "upc"
    ),
]:
    path = Path(file_name)

    if not path.exists():
        continue

    text = path.read_text()

    old = """    public function __construct(
        private readonly NotificationService $notifications
    ) {
    }
"""

    new = """    public function __construct(
        private readonly NotificationService $notifications,
        private readonly ReleaseAuditService $audit
    ) {
    }
"""

    if old in text:
        text = text.replace(
            old,
            new,
            1
        )

    if service_type == "isrc":
        marker = """            if ($freshTrack->release) {
                $this->notifications
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc
                    );
            }
"""

        replacement = """            if ($freshTrack->release) {
                $this->notifications
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc
                    );

                $this->audit
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc,
                        $freshTrack,
                        $user
                    );
            }
"""
    else:
        marker = """            $this->notifications
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc
                );
"""

        replacement = """            $this->notifications
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc
                );

            $this->audit
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc,
                    null,
                    $user
                );
"""

    if marker in text:
        text = text.replace(
            marker,
            replacement
        )

    path.write_text(text)

print("Identifier audit connected.")
PY


echo "[7/9] Creating Activity Controller..."

cat > app/Http/Controllers/V2/ReleaseActivityController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseAuditService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReleaseActivityController extends Controller
{
    public function index(
        Request $request,
        Release $release,
        ReleaseAccessService $access,
        ReleaseAuditService $audit
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $release
        );

        return response()->json([
            'release' => [
                'id' =>
                    $release->id,

                'title' =>
                    $release->title,

                'status' =>
                    $release->status,
            ],

            'timeline' =>
                $audit->timeline(
                    $release,
                    (int) $request->input(
                        'limit',
                        200
                    )
                ),
        ]);
    }
}
PHP


echo "[8/9] Adding activity route..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

if "v2.releases.activity.index" not in text:
    text += r"""

Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/releases/{release}/activity',
        [\App\Http\Controllers\V2\ReleaseActivityController::class, 'index']
    )
    ->name('v2.releases.activity.index');
"""

    path.write_text(text)

    print("Release activity route added.")
else:
    print("Release activity route already exists.")
PY


echo "[9/9] Running migrations and checks..."

php artisan migrate --force

php -l app/Models/ReleaseActivityLog.php
php -l app/Services/V2/ReleaseAuditService.php
php -l app/Services/V2/ReleaseWorkflowService.php
php -l app/Services/V2/IsrcService.php
php -l app/Services/V2/UpcService.php
php -l app/Http/Controllers/V2/ReleaseActivityController.php
php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
echo get_class(
    app(
        \App\Services\V2\ReleaseAuditService::class
    )
).PHP_EOL;
"

printf '{\n  "module": "ReleaseAuditEngine",\n  "installed": true,\n  "version": "2.9.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/release-audit-engine-installed.json

echo ""
echo "===== ACTIVITY ROUTE ====="

php artisan route:list | grep \
"v2/releases/{release}/activity"

echo ""
echo "=================================================="
echo "RELEASE AUDIT ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/release-audit-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
