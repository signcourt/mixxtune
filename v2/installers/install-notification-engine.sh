#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/notification-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    app/Http/Controllers/V2 \
    app/Http/Controllers/V2/Admin \
    app/Models \
    v2/runtime/state

echo "=================================================="
echo "V2 NOTIFICATION ENGINE"
echo "=================================================="

echo "[1/9] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/ReleaseWorkflowService.php \
    app/Services/V2/IsrcService.php \
    app/Services/V2/UpcService.php \
    app/Models/PanelNotification.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/9] Creating notification table migration..."

MIGRATION="database/migrations/2026_08_01_000004_create_panel_notifications_table.php"

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
                'panel_notifications'
            )
        ) {
            return;
        }

        Schema::create(
            'panel_notifications',
            function (Blueprint $table) {
                $table->id();

                $table->string(
                    'public_id',
                    40
                )->unique();

                $table->unsignedBigInteger(
                    'user_id'
                );

                $table->string(
                    'type',
                    100
                );

                $table->string(
                    'title',
                    255
                );

                $table->text(
                    'message'
                )->nullable();

                $table->string(
                    'action_url',
                    500
                )->nullable();

                $table->string(
                    'severity',
                    30
                )->default('info');

                $table->string(
                    'related_type',
                    100
                )->nullable();

                $table->unsignedBigInteger(
                    'related_id'
                )->nullable();

                $table->json(
                    'data'
                )->nullable();

                $table->timestamp(
                    'read_at'
                )->nullable();

                $table->timestamp(
                    'dismissed_at'
                )->nullable();

                $table->timestamps();

                $table->index([
                    'user_id',
                    'read_at',
                ]);

                $table->index([
                    'related_type',
                    'related_id',
                ]);

                $table->foreign(
                    'user_id'
                )
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            }
        );
    }

    public function down(): void
    {
        Schema::dropIfExists(
            'panel_notifications'
        );
    }
};
PHP
fi


echo "[3/9] Creating PanelNotification model..."

cat > app/Models/PanelNotification.php <<'PHP'
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PanelNotification extends Model
{
    protected $fillable = [
        'public_id',
        'user_id',
        'type',
        'title',
        'message',
        'action_url',
        'severity',
        'related_type',
        'related_id',
        'data',
        'read_at',
        'dismissed_at',
    ];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
        'dismissed_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }

    public function scopeUnread($query)
    {
        return $query
            ->whereNull('read_at')
            ->whereNull('dismissed_at');
    }
}
PHP


echo "[4/9] Creating Notification Service..."

cat > app/Services/V2/NotificationService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\Distribution\Release;
use App\Models\PanelNotification;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class NotificationService
{
    public function send(
        User $user,
        string $type,
        string $title,
        ?string $message = null,
        array $options = []
    ): PanelNotification {
        return PanelNotification::query()->create([
            'public_id' =>
                (string) Str::ulid(),

            'user_id' =>
                $user->id,

            'type' =>
                $type,

            'title' =>
                $title,

            'message' =>
                $message,

            'action_url' =>
                $options['action_url']
                ?? null,

            'severity' =>
                $options['severity']
                ?? 'info',

            'related_type' =>
                $options['related_type']
                ?? null,

            'related_id' =>
                $options['related_id']
                ?? null,

            'data' =>
                $options['data']
                ?? [],
        ]);
    }

    public function sendToUsers(
        iterable $users,
        string $type,
        string $title,
        ?string $message = null,
        array $options = []
    ): Collection {
        $notifications = collect();

        foreach ($users as $user) {
            if (!$user instanceof User) {
                continue;
            }

            $notifications->push(
                $this->send(
                    $user,
                    $type,
                    $title,
                    $message,
                    $options
                )
            );
        }

        return $notifications;
    }

    public function releaseSubmitted(
        Release $release
    ): void {
        $this->sendToAdmins(
            $release,
            'release.submitted',
            'New release submitted',
            "'{$release->title}' is waiting for review.",
            'warning'
        );

        $this->sendToOwner(
            $release,
            'release.submitted',
            'Release submitted',
            "'{$release->title}' was submitted for review.",
            'success'
        );
    }

    public function releaseApproved(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.approved',
            'Release approved',
            "'{$release->title}' has been approved.",
            'success'
        );
    }

    public function releaseRejected(
        Release $release,
        ?string $reason = null
    ): void {
        $message =
            "'{$release->title}' was rejected.";

        if ($reason) {
            $message .= " Reason: {$reason}";
        }

        $this->sendToOwner(
            $release,
            'release.rejected',
            'Release rejected',
            $message,
            'danger'
        );
    }

    public function changesRequested(
        Release $release,
        ?string $notes = null
    ): void {
        $message =
            "Changes were requested for '{$release->title}'.";

        if ($notes) {
            $message .= " Notes: {$notes}";
        }

        $this->sendToOwner(
            $release,
            'release.changes_requested',
            'Changes requested',
            $message,
            'warning'
        );
    }

    public function processingStarted(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.processing',
            'Distribution started',
            "'{$release->title}' is now processing.",
            'info'
        );
    }

    public function delivered(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.delivered',
            'Release delivered',
            "'{$release->title}' has been delivered to stores.",
            'success'
        );
    }

    public function live(
        Release $release
    ): void {
        $this->sendToOwner(
            $release,
            'release.live',
            'Release is live',
            "'{$release->title}' is now live.",
            'success'
        );
    }

    public function identifierAssigned(
        Release $release,
        string $identifierType,
        string $identifierCode
    ): void {
        $type = strtolower(
            $identifierType
        );

        $this->sendToOwner(
            $release,
            "identifier.{$type}.assigned",
            strtoupper($type) . ' assigned',
            strtoupper($type)
                . " {$identifierCode} was assigned to '{$release->title}'.",
            'success'
        );
    }

    public function unreadFor(
        User $user,
        int $limit = 50
    ): Collection {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull(
                'dismissed_at'
            )
            ->orderByDesc('id')
            ->limit($limit)
            ->get();
    }

    public function unreadCount(
        User $user
    ): int {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->unread()
            ->count();
    }

    public function markRead(
        PanelNotification $notification,
        User $user
    ): PanelNotification {
        abort_unless(
            (int) $notification->user_id
                === (int) $user->id,
            403,
            'You cannot update this notification.'
        );

        if (!$notification->read_at) {
            $notification->update([
                'read_at' => now(),
            ]);
        }

        return $notification->fresh();
    }

    public function markAllRead(
        User $user
    ): int {
        return PanelNotification::query()
            ->where(
                'user_id',
                $user->id
            )
            ->whereNull('read_at')
            ->update([
                'read_at' => now(),
            ]);
    }

    public function dismiss(
        PanelNotification $notification,
        User $user
    ): void {
        abort_unless(
            (int) $notification->user_id
                === (int) $user->id,
            403,
            'You cannot dismiss this notification.'
        );

        $notification->update([
            'dismissed_at' => now(),
        ]);
    }

    private function sendToOwner(
        Release $release,
        string $type,
        string $title,
        string $message,
        string $severity
    ): void {
        $users = $this->ownerUsers(
            $release
        );

        $this->sendToUsers(
            $users,
            $type,
            $title,
            $message,
            [
                'severity' =>
                    $severity,

                'action_url' =>
                    "/v2/releases/{$release->id}/edit",

                'related_type' =>
                    Release::class,

                'related_id' =>
                    $release->id,

                'data' => [
                    'release_id' =>
                        $release->id,

                    'status' =>
                        $release->status,
                ],
            ]
        );
    }

    private function sendToAdmins(
        Release $release,
        string $type,
        string $title,
        string $message,
        string $severity
    ): void {
        $query = User::query()
            ->whereIn(
                'role',
                [
                    'admin',
                    'super_admin',
                ]
            );

        if (
            Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artist = Artist::query()
                ->find(
                    $release->artist_id
                );

            if (
                $artist
                && $artist->assigned_admin_id
            ) {
                $query->where(function ($builder) use ($artist) {
                    $builder
                        ->where(
                            'role',
                            'super_admin'
                        )
                        ->orWhere(
                            'id',
                            $artist->assigned_admin_id
                        );
                });
            }
        }

        $this->sendToUsers(
            $query->get(),
            $type,
            $title,
            $message,
            [
                'severity' =>
                    $severity,

                'action_url' =>
                    "/v2/admin/release-reviews/{$release->id}",

                'related_type' =>
                    Release::class,

                'related_id' =>
                    $release->id,
            ]
        );
    }

    private function ownerUsers(
        Release $release
    ): Collection {
        $users = collect();

        $artist = Artist::query()
            ->find(
                $release->artist_id
            );

        if ($artist?->user_id) {
            $user = User::query()->find(
                $artist->user_id
            );

            if ($user) {
                $users->push($user);
            }
        }

        $label = Label::query()
            ->find(
                $release->label_id
            );

        if ($label?->user_id) {
            $user = User::query()->find(
                $label->user_id
            );

            if ($user) {
                $users->push($user);
            }
        }

        return $users
            ->unique('id')
            ->values();
    }
}
PHP


echo "[5/9] Connecting Release Workflow notifications..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Services/V2/ReleaseWorkflowService.php"
)

text = path.read_text()

old_constructor = """    public function __construct(
        private readonly ReleaseValidationService $validator
    ) {
    }
"""

new_constructor = """    public function __construct(
        private readonly ReleaseValidationService $validator,
        private readonly NotificationService $notifications
    ) {
    }
"""

if old_constructor in text:
    text = text.replace(
        old_constructor,
        new_constructor,
        1
    )

marker = """            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );

            return $release->fresh();
"""

replacement = """            $this->writeStatusLog(
                $release,
                $oldStatus,
                $newStatus,
                $user,
                $context
            );

            $freshRelease =
                $release->fresh();

            match ($newStatus) {
                'submitted' =>
                    $this->notifications
                        ->releaseSubmitted(
                            $freshRelease
                        ),

                'approved' =>
                    $this->notifications
                        ->releaseApproved(
                            $freshRelease
                        ),

                'rejected' =>
                    $this->notifications
                        ->releaseRejected(
                            $freshRelease,
                            $context['reason']
                                ?? $context['remarks']
                                ?? null
                        ),

                'changes_requested' =>
                    $this->notifications
                        ->changesRequested(
                            $freshRelease,
                            $context['notes']
                                ?? $context['remarks']
                                ?? null
                        ),

                'processing' =>
                    $this->notifications
                        ->processingStarted(
                            $freshRelease
                        ),

                'delivered' =>
                    $this->notifications
                        ->delivered(
                            $freshRelease
                        ),

                'live' =>
                    $this->notifications
                        ->live(
                            $freshRelease
                        ),

                default => null,
            };

            return $freshRelease;
"""

if marker in text:
    text = text.replace(
        marker,
        replacement,
        1
    )
else:
    print(
        "Workflow notification marker nahi mila."
    )

path.write_text(text)

print("ReleaseWorkflow notifications connected.")
PY


echo "[6/9] Connecting ISRC and UPC notifications..."

python3 - <<'PY'
from pathlib import Path

# ISRC service
path = Path(
    "app/Services/V2/IsrcService.php"
)

text = path.read_text()

if (
    "private readonly NotificationService"
    not in text
):
    class_marker = """class IsrcService
{
"""

    replacement = """class IsrcService
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {
    }

"""

    text = text.replace(
        class_marker,
        replacement,
        1
    )

return_marker = """            return $track->fresh();
"""

replacement = """            $freshTrack =
                $track->fresh([
                    'release',
                ]);

            if ($freshTrack->release) {
                $this->notifications
                    ->identifierAssigned(
                        $freshTrack->release,
                        'isrc',
                        $freshTrack->isrc
                    );
            }

            return $freshTrack;
"""

text = text.replace(
    return_marker,
    replacement
)

path.write_text(text)


# UPC service
path = Path(
    "app/Services/V2/UpcService.php"
)

text = path.read_text()

if (
    "private readonly NotificationService"
    not in text
):
    class_marker = """class UpcService
{
"""

    replacement = """class UpcService
{
    public function __construct(
        private readonly NotificationService $notifications
    ) {
    }

"""

    text = text.replace(
        class_marker,
        replacement,
        1
    )

return_marker = """            return $release->fresh();
"""

replacement = """            $freshRelease =
                $release->fresh();

            $this->notifications
                ->identifierAssigned(
                    $freshRelease,
                    'upc',
                    $freshRelease->upc
                );

            return $freshRelease;
"""

text = text.replace(
    return_marker,
    replacement
)

path.write_text(text)

print("Identifier notifications connected.")
PY


echo "[7/9] Creating Notification Controller..."

cat > app/Http/Controllers/V2/NotificationController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2;

use App\Http\Controllers\Controller;
use App\Models\PanelNotification;
use App\Services\V2\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(
        Request $request,
        NotificationService $notifications
    ): JsonResponse {
        return response()->json([
            'unread_count' =>
                $notifications->unreadCount(
                    $request->user()
                ),

            'notifications' =>
                $notifications->unreadFor(
                    $request->user(),
                    min(
                        max(
                            (int) $request->input(
                                'limit',
                                50
                            ),
                            1
                        ),
                        100
                    )
                ),
        ]);
    }

    public function markRead(
        Request $request,
        PanelNotification $notification,
        NotificationService $notifications
    ): JsonResponse {
        $notification =
            $notifications->markRead(
                $notification,
                $request->user()
            );

        return response()->json([
            'message' =>
                'Notification marked as read.',

            'notification' =>
                $notification,
        ]);
    }

    public function markAllRead(
        Request $request,
        NotificationService $notifications
    ): JsonResponse {
        $count =
            $notifications->markAllRead(
                $request->user()
            );

        return response()->json([
            'message' =>
                'All notifications marked as read.',

            'updated' =>
                $count,
        ]);
    }

    public function dismiss(
        Request $request,
        PanelNotification $notification,
        NotificationService $notifications
    ): JsonResponse {
        $notifications->dismiss(
            $notification,
            $request->user()
        );

        return response()->json([
            'message' =>
                'Notification dismissed.',
        ]);
    }
}
PHP


echo "[8/9] Adding notification routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.notifications.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/notifications',
        [\App\Http\Controllers\V2\NotificationController::class, 'index']
    )
    ->name('v2.notifications.index');
""",

    "v2.notifications.read": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/notifications/{notification}/read',
        [\App\Http\Controllers\V2\NotificationController::class, 'markRead']
    )
    ->name('v2.notifications.read');
""",

    "v2.notifications.read-all": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/notifications/read-all',
        [\App\Http\Controllers\V2\NotificationController::class, 'markAllRead']
    )
    ->name('v2.notifications.read-all');
""",

    "v2.notifications.dismiss": r"""
Route::middleware(['auth', 'verified'])
    ->delete(
        '/v2/notifications/{notification}',
        [\App\Http\Controllers\V2\NotificationController::class, 'dismiss']
    )
    ->name('v2.notifications.dismiss');
""",
}

count = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        count += 1

path.write_text(text)

print(f"{count} notification routes added.")
PY


echo "[9/9] Running migrations and final checks..."

php artisan migrate --force

php -l app/Models/PanelNotification.php
php -l app/Services/V2/NotificationService.php
php -l app/Services/V2/ReleaseWorkflowService.php
php -l app/Services/V2/IsrcService.php
php -l app/Services/V2/UpcService.php
php -l app/Http/Controllers/V2/NotificationController.php
php -l routes/web.php

php artisan optimize:clear

php artisan tinker --execute="
echo get_class(
    app(
        \App\Services\V2\NotificationService::class
    )
).PHP_EOL;
"

printf '{\n  "module": "NotificationEngine",\n  "installed": true,\n  "version": "2.7.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/notification-engine-installed.json

echo ""
echo "===== NOTIFICATION ROUTES ====="

php artisan route:list | grep \
"v2/notifications"

echo ""
echo "=================================================="
echo "NOTIFICATION ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/notification-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
