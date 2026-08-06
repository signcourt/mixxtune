#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/admin-review-engine/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    app/Services/V2 \
    v2/runtime/state

echo "=================================================="
echo "V2 ADMIN REVIEW ENGINE"
echo "=================================================="

echo "[1/8] Creating backups..."

for FILE in \
    routes/web.php \
    app/Services/V2/PermissionService.php \
    app/Services/V2/ReleaseWorkflowService.php \
    app/Http/Controllers/V2/Admin/ReleaseReviewController.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/8] Updating admin permissions..."

python3 - <<'PY'
from pathlib import Path

paths = [
    Path("app/Services/V2/PermissionService.php"),
    Path(
        "resources/js/V2/Shared/Permissions/permissions.js"
    ),
]

permissions = [
    "releases.request_changes",
    "releases.processing",
]

for path in paths:
    if not path.exists():
        print(f"Skipped missing file: {path}")
        continue

    text = path.read_text()

    marker = (
        "'releases.reject',"
        if path.suffix == ".php"
        else "'releases.reject',"
    )

    additions = []

    for permission in permissions:
        token = f"'{permission}',"

        if token not in text:
            additions.append(
                "            " + token
            )

    if additions and marker in text:
        replacement = (
            marker
            + "\n"
            + "\n".join(additions)
        )

        text = text.replace(
            marker,
            replacement,
            1
        )

        path.write_text(text)

        print(
            f"Permissions updated: {path}"
        )
    else:
        print(
            f"Permissions already present or marker missing: {path}"
        )
PY


echo "[3/8] Creating Admin Release Review Service..."

cat > app/Services/V2/AdminReleaseReviewService.php <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\Distribution\Release;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class AdminReleaseReviewService
{
    public function __construct(
        private readonly PermissionService $permissions,
        private readonly ReleaseAccessService $access,
        private readonly ReleaseValidationService $validator,
        private readonly ReleaseWorkflowService $workflow
    ) {
    }

    public function authorizeReviewer(
        ?User $user
    ): void {
        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $this->permissions->role($user);

        abort_unless(
            in_array(
                $role,
                [
                    'admin',
                    'super_admin',
                ],
                true
            ),
            403,
            'Only Admin or Super Admin can review releases.'
        );

        $this->permissions->authorize(
            $user,
            'releases.review'
        );
    }

    public function approve(
        Release $release,
        User $user,
        ?string $remarks = null
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Only submitted releases can be approved.'
        );

        /*
         * Revalidate before approval so incomplete
         * data cannot bypass the submission engine.
         */
        $errors = $this->validator
            ->validateForSubmission($release);

        if (!empty($errors)) {
            throw ValidationException::withMessages(
                $errors
            );
        }

        $this->permissions->authorize(
            $user,
            'releases.approve'
        );

        return $this->workflow->transition(
            $release,
            'approved',
            $user,
            [
                'action' =>
                    'approved_by_admin',

                'remarks' =>
                    $remarks
                    ?: 'Release approved.',

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function reject(
        Release $release,
        User $user,
        string $reason
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Only submitted releases can be rejected.'
        );

        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages([
                'reason' =>
                    'Rejection reason is required.',
            ]);
        }

        $this->permissions->authorize(
            $user,
            'releases.reject'
        );

        return $this->workflow->transition(
            $release,
            'rejected',
            $user,
            [
                'action' =>
                    'rejected_by_admin',

                'reason' => $reason,

                'remarks' => $reason,

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function requestChanges(
        Release $release,
        User $user,
        string $notes
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'submitted',
            422,
            'Changes can only be requested for submitted releases.'
        );

        $notes = trim($notes);

        if ($notes === '') {
            throw ValidationException::withMessages([
                'notes' =>
                    'Change request notes are required.',
            ]);
        }

        $this->permissions->authorize(
            $user,
            'releases.request_changes'
        );

        return $this->workflow->transition(
            $release,
            'changes_requested',
            $user,
            [
                'action' =>
                    'changes_requested_by_admin',

                'notes' => $notes,

                'remarks' => $notes,

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }

    public function startProcessing(
        Release $release,
        User $user,
        ?string $remarks = null
    ): Release {
        $this->authorizeReviewer($user);

        $this->access->authorizeView(
            $user,
            $release
        );

        abort_unless(
            $release->status === 'approved',
            422,
            'Only approved releases can enter processing.'
        );

        $this->permissions->authorize(
            $user,
            'releases.processing'
        );

        return $this->workflow->transition(
            $release,
            'processing',
            $user,
            [
                'action' =>
                    'distribution_processing_started',

                'remarks' =>
                    $remarks
                    ?: 'Distribution processing started.',

                'ip_address' =>
                    request()?->ip(),

                'user_agent' =>
                    request()?->userAgent(),
            ]
        );
    }
}
PHP


echo "[4/8] Creating Admin Review Controller..."

cat > app/Http/Controllers/V2/Admin/ReleaseReviewController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Distribution\Release;
use App\Services\V2\AdminReleaseReviewService;
use App\Services\V2\PermissionService;
use App\Services\V2\ReleaseAccessService;
use App\Services\V2\ReleaseValidationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ReleaseReviewController extends Controller
{
    public function index(
        Request $request,
        AdminReleaseReviewService $reviews,
        PermissionService $permissions
    ): JsonResponse {
        $reviews->authorizeReviewer(
            $request->user()
        );

        $role = $permissions->role(
            $request->user()
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
                    'submitted'
                )
            ),

            'sort' => trim(
                (string) $request->input(
                    'sort',
                    'oldest'
                )
            ),
        ];

        $query = Release::query()
            ->whereNull('deleted_at')
            ->withCount('tracks');

        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = Artist::query()
                ->where(
                    'assigned_admin_id',
                    $request->user()->id
                )
                ->pluck('id');

            $query->whereIn(
                'artist_id',
                $artistIds
            );
        }

        if ($filters['status'] !== '') {
            $query->where(
                'status',
                $filters['status']
            );
        }

        if ($filters['search'] !== '') {
            $search = $filters['search'];

            $query->where(
                function ($builder) use ($search) {
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
                            'upc',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'catalog_number',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        match ($filters['sort']) {
            'latest' =>
                $query->orderByDesc('submitted_at'),

            'title_asc' =>
                $query->orderBy('title'),

            'title_desc' =>
                $query->orderByDesc('title'),

            default =>
                $query
                    ->orderBy('submitted_at')
                    ->orderBy('id'),
        };

        $countsQuery = Release::query()
            ->whereNull('deleted_at');

        if (
            $role === 'admin'
            && isset($artistIds)
        ) {
            $countsQuery->whereIn(
                'artist_id',
                $artistIds
            );
        }

        return response()->json([
            'filters' => $filters,

            'counts' => [
                'submitted' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'submitted'
                        )
                        ->count(),

                'approved' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'approved'
                        )
                        ->count(),

                'changes_requested' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'changes_requested'
                        )
                        ->count(),

                'rejected' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'rejected'
                        )
                        ->count(),

                'processing' =>
                    (clone $countsQuery)
                        ->where(
                            'status',
                            'processing'
                        )
                        ->count(),
            ],

            'releases' =>
                $query
                    ->paginate(25)
                    ->withQueryString(),
        ]);
    }

    public function show(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews,
        ReleaseAccessService $access,
        ReleaseValidationService $validator
    ): JsonResponse {
        $reviews->authorizeReviewer(
            $request->user()
        );

        $access->authorizeView(
            $request->user(),
            $release
        );

        $release->load([
            'tracks' => function ($query) {
                $query
                    ->orderBy('disc_number')
                    ->orderBy('track_number')
                    ->orderBy('id');
            },
        ]);

        $statusLogs = [];

        if (
            Schema::hasTable(
                'release_status_logs'
            )
        ) {
            $statusLogs = DB::table(
                'release_status_logs'
            )
                ->where(
                    'release_id',
                    $release->id
                )
                ->orderByDesc('id')
                ->limit(100)
                ->get();
        }

        return response()->json([
            'release' => $release,

            'submission_checklist' =>
                $validator->checklist(
                    $release
                ),

            'available_actions' => [
                'approve' =>
                    $release->status
                    === 'submitted',

                'reject' =>
                    $release->status
                    === 'submitted',

                'request_changes' =>
                    $release->status
                    === 'submitted',

                'start_processing' =>
                    $release->status
                    === 'approved',
            ],

            'status_logs' =>
                $statusLogs,
        ]);
    }

    public function approve(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
        $validated = $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $reviews->approve(
            $release,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return response()->json([
            'message' =>
                'Release approved successfully.',

            'release' => $release,
        ]);
    }

    public function reject(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
        $validated = $request->validate([
            'reason' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $release = $reviews->reject(
            $release,
            $request->user(),
            $validated['reason']
        );

        return response()->json([
            'message' =>
                'Release rejected successfully.',

            'release' => $release,
        ]);
    }

    public function requestChanges(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
        $validated = $request->validate([
            'notes' => [
                'required',
                'string',
                'min:3',
                'max:5000',
            ],
        ]);

        $release = $reviews->requestChanges(
            $release,
            $request->user(),
            $validated['notes']
        );

        return response()->json([
            'message' =>
                'Changes requested successfully.',

            'release' => $release,
        ]);
    }

    public function startProcessing(
        Request $request,
        Release $release,
        AdminReleaseReviewService $reviews
    ): JsonResponse {
        $validated = $request->validate([
            'remarks' => [
                'nullable',
                'string',
                'max:2000',
            ],
        ]);

        $release = $reviews->startProcessing(
            $release,
            $request->user(),
            $validated['remarks'] ?? null
        );

        return response()->json([
            'message' =>
                'Release processing started.',

            'release' => $release,
        ]);
    }
}
PHP


echo "[5/8] Adding Admin Review routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.release-reviews.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/release-reviews',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'index']
    )
    ->name('v2.admin.release-reviews.index');
""",

    "v2.admin.release-reviews.show": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/release-reviews/{release}',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'show']
    )
    ->name('v2.admin.release-reviews.show');
""",

    "v2.admin.release-reviews.approve": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/approve',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'approve']
    )
    ->name('v2.admin.release-reviews.approve');
""",

    "v2.admin.release-reviews.reject": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/reject',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'reject']
    )
    ->name('v2.admin.release-reviews.reject');
""",

    "v2.admin.release-reviews.request-changes": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/request-changes',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'requestChanges']
    )
    ->name('v2.admin.release-reviews.request-changes');
""",

    "v2.admin.release-reviews.start-processing": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/release-reviews/{release}/start-processing',
        [\App\Http\Controllers\V2\Admin\ReleaseReviewController::class, 'startProcessing']
    )
    ->name('v2.admin.release-reviews.start-processing');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(
    f"{added} Admin Review routes added."
)
PY


echo "[6/8] Running syntax checks..."

php -l \
app/Services/V2/AdminReleaseReviewService.php

php -l \
app/Http/Controllers/V2/Admin/ReleaseReviewController.php

php -l \
app/Services/V2/PermissionService.php

php -l routes/web.php


echo "[7/8] Running Laravel checks..."

php artisan optimize:clear

php artisan tinker --execute="
\$service = app(
    \App\Services\V2\AdminReleaseReviewService::class
);

echo get_class(\$service).PHP_EOL;
"

echo ""
echo "===== ADMIN REVIEW ROUTES ====="

php artisan route:list | grep \
"v2/admin/release-reviews"


echo "[8/8] Saving installation state..."

printf '{\n  "module": "AdminReviewEngine",\n  "installed": true,\n  "version": "2.4.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/admin-review-engine-installed.json

echo ""
echo "=================================================="
echo "ADMIN REVIEW ENGINE INSTALLED"
echo "=================================================="

echo ""
echo "State:"

cat \
v2/runtime/state/admin-review-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
