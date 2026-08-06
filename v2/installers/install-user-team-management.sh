#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/user-team-management/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Models/System \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/Users \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING USER & TEAM MANAGEMENT"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Models/User.php \
    app/Http/Controllers/V2/Admin/UserManagementController.php
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/5] Creating user permissions migration..."

MIGRATION="database/migrations/2026_08_01_000015_create_v2_user_panel_permissions_table.php"

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
        if (!Schema::hasTable('user_panel_permissions')) {
            Schema::create(
                'user_panel_permissions',
                function (Blueprint $table) {
                    $table->id();

                    $table->unsignedBigInteger(
                        'user_id'
                    )->unique();

                    $table->boolean(
                        'can_view_catalogue'
                    )->default(false);

                    $table->boolean(
                        'can_create_releases'
                    )->default(false);

                    $table->boolean(
                        'can_manage_releases'
                    )->default(false);

                    $table->boolean(
                        'can_view_reports'
                    )->default(false);

                    $table->boolean(
                        'can_view_royalties'
                    )->default(false);

                    $table->boolean(
                        'can_manage_wallet'
                    )->default(false);

                    $table->boolean(
                        'can_manage_withdrawals'
                    )->default(false);

                    $table->boolean(
                        'can_manage_users'
                    )->default(false);

                    $table->boolean(
                        'can_manage_support'
                    )->default(false);

                    $table->boolean(
                        'can_manage_settings'
                    )->default(false);

                    $table->boolean(
                        'can_manage_delivery'
                    )->default(false);

                    $table->boolean(
                        'can_manage_identifiers'
                    )->default(false);

                    $table->unsignedBigInteger(
                        'updated_by'
                    )->nullable();

                    $table->timestamps();

                    $table->foreign('user_id')
                        ->references('id')
                        ->on('users')
                        ->cascadeOnDelete();
                }
            );
        }
    }

    public function down(): void
    {
        /*
         * Permissions are preserved in production.
         */
    }
};
PHP
fi

echo "[2/5] Creating permission model..."

cat > app/Models/System/UserPanelPermission.php <<'PHP'
<?php

namespace App\Models\System;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPanelPermission extends Model
{
    protected $fillable = [
        'user_id',
        'can_view_catalogue',
        'can_create_releases',
        'can_manage_releases',
        'can_view_reports',
        'can_view_royalties',
        'can_manage_wallet',
        'can_manage_withdrawals',
        'can_manage_users',
        'can_manage_support',
        'can_manage_settings',
        'can_manage_delivery',
        'can_manage_identifiers',
        'updated_by',
    ];

    protected $casts = [
        'can_view_catalogue' => 'boolean',
        'can_create_releases' => 'boolean',
        'can_manage_releases' => 'boolean',
        'can_view_reports' => 'boolean',
        'can_view_royalties' => 'boolean',
        'can_manage_wallet' => 'boolean',
        'can_manage_withdrawals' => 'boolean',
        'can_manage_users' => 'boolean',
        'can_manage_support' => 'boolean',
        'can_manage_settings' => 'boolean',
        'can_manage_delivery' => 'boolean',
        'can_manage_identifiers' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(
            User::class
        );
    }
}
PHP

echo "[3/5] Creating User Management Controller..."

cat > app/Http/Controllers/V2/Admin/UserManagementController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Core\Artist;
use App\Models\Core\Label;
use App\Models\System\UserPanelPermission;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $search = trim(
            (string) $request->input(
                'search',
                ''
            )
        );

        $roleFilter = trim(
            (string) $request->input(
                'role',
                ''
            )
        );

        $status = trim(
            (string) $request->input(
                'status',
                ''
            )
        );

        $query = User::query()
            ->with([
                'assignedArtists:id,stage_name',
                'assignedLabels:id,name',
            ]);

        if ($search !== '') {
            $query->where(
                function ($builder) use ($search) {
                    $builder
                        ->where(
                            'name',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'email',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'phone',
                            'like',
                            "%{$search}%"
                        )
                        ->orWhere(
                            'label_name',
                            'like',
                            "%{$search}%"
                        );
                }
            );
        }

        if ($roleFilter !== '') {
            $query->where(
                'role',
                $roleFilter
            );
        }

        if ($status !== '') {
            $query->where(
                'account_status',
                $status
            );
        }

        $summary = [
            'total' =>
                User::query()->count(),

            'super_admins' =>
                User::query()
                    ->where(
                        'role',
                        'super_admin'
                    )
                    ->count(),

            'admins' =>
                User::query()
                    ->where(
                        'role',
                        'admin'
                    )
                    ->count(),

            'labels' =>
                User::query()
                    ->where(
                        'role',
                        'label'
                    )
                    ->count(),

            'artists' =>
                User::query()
                    ->where(
                        'role',
                        'artist'
                    )
                    ->count(),

            'pending_invitations' =>
                User::query()
                    ->where(
                        'invitation_status',
                        'pending'
                    )
                    ->count(),

            'suspended' =>
                User::query()
                    ->where(
                        'account_status',
                        'suspended'
                    )
                    ->count(),
        ];

        return Inertia::render(
            'V2/Admin/Users/Index',
            [
                'role' => 'super_admin',

                'summary' =>
                    $summary,

                'filters' => [
                    'search' => $search,
                    'role' => $roleFilter,
                    'status' => $status,
                ],

                'users' =>
                    $query
                        ->orderByDesc('id')
                        ->paginate(30)
                        ->withQueryString(),
            ]
        );
    }

    public function create(
        Request $request,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        return Inertia::render(
            'V2/Admin/Users/Create',
            [
                'role' => 'super_admin',

                'artists' =>
                    Artist::query()
                        ->orderBy('stage_name')
                        ->get([
                            'id',
                            'stage_name',
                            'email',
                        ]),

                'labels' =>
                    Label::query()
                        ->orderBy('name')
                        ->get([
                            'id',
                            'name',
                            'email',
                        ]),
            ]
        );
    }

    public function store(
        Request $request,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $validated = $request->validate([
            'name' => [
                'required',
                'string',
                'max:150',
            ],

            'email' => [
                'required',
                'email',
                'max:190',
                Rule::unique('users', 'email'),
            ],

            'phone' => [
                'nullable',
                'string',
                'max:30',
            ],

            'country' => [
                'nullable',
                'string',
                'max:100',
            ],

            'role' => [
                'required',
                'string',
                'in:admin,label,artist',
            ],

            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],

            'send_invitation' => [
                'required',
                'boolean',
            ],

            'artist_ids' => [
                'nullable',
                'array',
            ],

            'artist_ids.*' => [
                'integer',
                'distinct',
                'exists:artists,id',
            ],

            'label_ids' => [
                'nullable',
                'array',
            ],

            'label_ids.*' => [
                'integer',
                'distinct',
                'exists:labels,id',
            ],

            'permissions' => [
                'nullable',
                'array',
            ],
        ]);

        $user = DB::transaction(
            function () use (
                $validated,
                $request
            ) {
                $sendInvitation =
                    (bool) $validated[
                        'send_invitation'
                    ];

                $token = $sendInvitation
                    ? Str::random(64)
                    : null;

                $user = User::query()->create([
                    'name' =>
                        $validated['name'],

                    'email' =>
                        strtolower(
                            $validated['email']
                        ),

                    'phone' =>
                        $validated['phone']
                        ?? null,

                    'country' =>
                        $validated['country']
                        ?? null,

                    'role' =>
                        $validated['role'],

                    'account_status' =>
                        $validated[
                            'account_status'
                        ],

                    'password' =>
                        Hash::make(
                            Str::random(32)
                        ),

                    'email_verified_at' =>
                        $sendInvitation
                            ? null
                            : now(),

                    'invitation_status' =>
                        $sendInvitation
                            ? 'pending'
                            : 'not_required',

                    'invitation_token' =>
                        $token,

                    'invitation_sent_at' =>
                        $sendInvitation
                            ? now()
                            : null,

                    'invitation_expires_at' =>
                        $sendInvitation
                            ? now()->addDays(7)
                            : null,

                    'invitation_count' =>
                        $sendInvitation
                            ? 1
                            : 0,
                ]);

                if (
                    $validated['role'] ===
                    'admin'
                ) {
                    $pivot = [
                        'assignment_role' =>
                            'manager',

                        'can_view' => true,
                        'can_edit' => true,

                        'can_manage_releases' =>
                            true,

                        'can_manage_team' =>
                            false,

                        'can_manage_splits' =>
                            false,

                        'assigned_by' =>
                            $request->user()->id,
                    ];

                    $artistSync = [];

                    foreach (
                        $validated[
                            'artist_ids'
                        ] ?? []
                        as $artistId
                    ) {
                        $artistSync[
                            $artistId
                        ] = $pivot;
                    }

                    $labelSync = [];

                    foreach (
                        $validated[
                            'label_ids'
                        ] ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            $labelId
                        ] = $pivot;
                    }

                    $user
                        ->assignedArtists()
                        ->sync($artistSync);

                    $user
                        ->assignedLabels()
                        ->sync($labelSync);
                }

                $permissionValues = [
                    'user_id' =>
                        $user->id,

                    'updated_by' =>
                        $request->user()->id,
                ];

                foreach (
                    $this->permissionFields()
                    as $field
                ) {
                    $permissionValues[$field] =
                        (bool) data_get(
                            $validated,
                            "permissions.{$field}",
                            false
                        );
                }

                UserPanelPermission::query()
                    ->updateOrCreate(
                        [
                            'user_id' =>
                                $user->id,
                        ],
                        $permissionValues
                    );

                return $user;
            }
        );

        $audit->record(
            'user.created',
            'users',
            "User {$user->email} created.",
            [],
            $user->only([
                'id',
                'name',
                'email',
                'role',
                'account_status',
            ]),
            $user,
            $request
        );

        return redirect()
            ->route(
                'v2.admin.users.index'
            )
            ->with(
                'success',
                'User created successfully.'
            );
    }

    public function toggleStatus(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_if(
            $user->id ===
                $request->user()->id,
            422,
            'You cannot suspend your own account.'
        );

        abort_if(
            $user->role ===
                'super_admin',
            422,
            'Super Admin account cannot be suspended here.'
        );

        $oldStatus =
            $user->account_status;

        $newStatus =
            $oldStatus === 'active'
                ? 'suspended'
                : 'active';

        $user->update([
            'account_status' =>
                $newStatus,
        ]);

        $audit->record(
            'user.status_updated',
            'users',
            "User status changed to {$newStatus}.",
            [
                'account_status' =>
                    $oldStatus,
            ],
            [
                'account_status' =>
                    $newStatus,
            ],
            $user,
            $request
        );

        return back()->with(
            'success',
            'User status updated.'
        );
    }

    public function resendInvitation(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $user->update([
            'invitation_status' =>
                'pending',

            'invitation_token' =>
                Str::random(64),

            'invitation_sent_at' =>
                now(),

            'invitation_expires_at' =>
                now()->addDays(7),

            'invitation_count' =>
                ((int) $user
                    ->invitation_count) + 1,

            'invitation_error' =>
                null,
        ]);

        $audit->record(
            'user.invitation_resent',
            'users',
            "Invitation regenerated for {$user->email}.",
            [],
            [
                'invitation_status' =>
                    'pending',
            ],
            $user,
            $request
        );

        return back()->with(
            'success',
            'Invitation link regenerated.'
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

    private function permissionFields(): array
    {
        return [
            'can_view_catalogue',
            'can_create_releases',
            'can_manage_releases',
            'can_view_reports',
            'can_view_royalties',
            'can_manage_wallet',
            'can_manage_withdrawals',
            'can_manage_users',
            'can_manage_support',
            'can_manage_settings',
            'can_manage_delivery',
            'can_manage_identifiers',
        ];
    }
}
PHP

echo "[4/5] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.users.index": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'index']
    )
    ->name('v2.admin.users.index');
""",

    "v2.admin.users.create": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users/create',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'create']
    )
    ->name('v2.admin.users.create');
""",

    "v2.admin.users.store": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'store']
    )
    ->name('v2.admin.users.store');
""",

    "v2.admin.users.toggle-status": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/toggle-status',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'toggleStatus']
    )
    ->name('v2.admin.users.toggle-status');
""",

    "v2.admin.users.resend-invitation": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/resend-invitation',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'resendInvitation']
    )
    ->name('v2.admin.users.resend-invitation');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} user management routes added.")
PY

echo "[5/5] Backend checks..."

php artisan migrate --force

php -l app/Models/System/UserPanelPermission.php
php -l app/Http/Controllers/V2/Admin/UserManagementController.php
php -l routes/web.php

php artisan optimize:clear

echo ""
echo "===== USER MANAGEMENT ROUTES ====="

php artisan route:list | grep -E \
"v2/admin/users"

echo ""
echo "USER MANAGEMENT BACKEND INSTALLED"
echo "Backup: $BACKUP"
