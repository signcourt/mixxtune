#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/user-management-edit-actions/$STAMP"

mkdir -p \
    "$BACKUP" \
    resources/js/Pages/V2/Admin/Users \
    v2/runtime/state

CONTROLLER="app/Http/Controllers/V2/Admin/UserManagementController.php"

if [ ! -f "$CONTROLLER" ]; then
    echo "UserManagementController.php नहीं मिला।"
    exit 1
fi

cp -a "$CONTROLLER" "$BACKUP/UserManagementController.php"
cp -a routes/web.php "$BACKUP/web.php"

echo "=============================================="
echo "UPGRADING USER MANAGEMENT EDIT ACTIONS"
echo "=============================================="

echo "[1/4] Adding controller methods..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/Admin/UserManagementController.php"
)

text = path.read_text()

marker = """
    private function authorizeSuperAdmin(
"""

methods = r'''
    public function edit(
        Request $request,
        User $user,
        PermissionService $permissions
    ): Response {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        $user->load([
            'assignedArtists:id,stage_name,email',
            'assignedLabels:id,name,email',
        ]);

        $panelPermissions =
            UserPanelPermission::query()
                ->firstOrCreate([
                    'user_id' => $user->id,
                ]);

        return Inertia::render(
            'V2/Admin/Users/Edit',
            [
                'role' => 'super_admin',

                'managedUser' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'phone' => $user->phone,
                    'country' => $user->country,
                    'role' => $user->role,
                    'account_status' =>
                        $user->account_status,
                    'kyc_status' =>
                        $user->kyc_status,
                    'invitation_status' =>
                        $user->invitation_status,
                    'invitation_token' =>
                        $user->invitation_token,
                    'invitation_expires_at' =>
                        $user->invitation_expires_at,
                    'last_login_at' =>
                        $user->last_login_at,
                ],

                'permissions' =>
                    $panelPermissions->only(
                        $this->permissionFields()
                    ),

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

                'assignedArtistIds' =>
                    $user->assignedArtists
                        ->pluck('id'),

                'assignedLabelIds' =>
                    $user->assignedLabels
                        ->pluck('id'),
            ]
        );
    }

    public function update(
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
            $user->id === $request->user()->id
            && $request->input('role') !== 'super_admin',
            422,
            'You cannot remove your own Super Admin role.'
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
                Rule::unique(
                    'users',
                    'email'
                )->ignore($user->id),
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
                'in:super_admin,admin,label,artist',
            ],

            'account_status' => [
                'required',
                'string',
                'in:active,pending,suspended',
            ],

            'kyc_status' => [
                'nullable',
                'string',
                'in:pending,submitted,verified,rejected',
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

        $oldValues = [
            'user' => $user->only([
                'name',
                'email',
                'phone',
                'country',
                'role',
                'account_status',
                'kyc_status',
            ]),

            'artist_ids' =>
                $user->assignedArtists()
                    ->pluck('artists.id')
                    ->all(),

            'label_ids' =>
                $user->assignedLabels()
                    ->pluck('labels.id')
                    ->all(),
        ];

        DB::transaction(
            function () use (
                $validated,
                $request,
                $user
            ) {
                $user->update([
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

                    'kyc_status' =>
                        $validated['kyc_status']
                        ?? $user->kyc_status,
                ]);

                if (
                    $validated['role'] === 'admin'
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
                        $validated['artist_ids']
                        ?? []
                        as $artistId
                    ) {
                        $artistSync[
                            $artistId
                        ] = $pivot;
                    }

                    $labelSync = [];

                    foreach (
                        $validated['label_ids']
                        ?? []
                        as $labelId
                    ) {
                        $labelSync[
                            $labelId
                        ] = $pivot;
                    }

                    $user->assignedArtists()
                        ->sync($artistSync);

                    $user->assignedLabels()
                        ->sync($labelSync);
                } else {
                    $user->assignedArtists()
                        ->detach();

                    $user->assignedLabels()
                        ->detach();
                }

                $permissionValues = [
                    'user_id' => $user->id,
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
            }
        );

        $user->refresh();

        $newValues = [
            'user' => $user->only([
                'name',
                'email',
                'phone',
                'country',
                'role',
                'account_status',
                'kyc_status',
            ]),

            'artist_ids' =>
                $user->assignedArtists()
                    ->pluck('artists.id')
                    ->all(),

            'label_ids' =>
                $user->assignedLabels()
                    ->pluck('labels.id')
                    ->all(),
        ];

        $audit->record(
            'user.updated',
            'users',
            "User {$user->email} updated.",
            $oldValues,
            $newValues,
            $user,
            $request
        );

        return back()->with(
            'success',
            'User updated successfully.'
        );
    }

    public function resetPassword(
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
            $user->id === $request->user()->id,
            422,
            'Use your profile page to change your own password.'
        );

        $temporaryPassword =
            Str::password(
                length: 14,
                letters: true,
                numbers: true,
                symbols: true
            );

        $user->update([
            'password' =>
                Hash::make(
                    $temporaryPassword
                ),

            'password_set_at' =>
                null,

            'invitation_status' =>
                'password_reset',

            'invitation_error' =>
                null,
        ]);

        $audit->record(
            'user.password_reset',
            'users',
            "Temporary password generated for {$user->email}.",
            [],
            [
                'password_reset' => true,
            ],
            $user,
            $request
        );

        return back()->with(
            'temporary_password',
            $temporaryPassword
        );
    }

    public function invitationLink(
        Request $request,
        User $user,
        PermissionService $permissions
    ): RedirectResponse {
        $this->authorizeSuperAdmin(
            $request,
            $permissions
        );

        abort_unless(
            $user->invitation_token,
            422,
            'This user does not have an active invitation.'
        );

        abort_if(
            $user->invitation_expires_at
            && now()->greaterThan(
                $user->invitation_expires_at
            ),
            422,
            'Invitation has expired. Resend it first.'
        );

        $link = url(
            '/invitation/'
            . $user->invitation_token
        );

        return back()->with(
            'invitation_link',
            $link
        );
    }

'''

if "public function edit(" not in text:
    if marker not in text:
        raise SystemExit(
            "Controller insertion marker नहीं मिला."
        )

    text = text.replace(
        marker,
        methods + marker,
        1
    )

path.write_text(text)

print("User edit/update actions added.")
PY

echo "[2/4] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.users.edit": r"""
Route::middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/users/{user}/edit',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'edit']
    )
    ->name('v2.admin.users.edit');
""",

    "v2.admin.users.update": r"""
Route::middleware(['auth', 'verified'])
    ->patch(
        '/v2/admin/users/{user}',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'update']
    )
    ->name('v2.admin.users.update');
""",

    "v2.admin.users.reset-password": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/reset-password',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'resetPassword']
    )
    ->name('v2.admin.users.reset-password');
""",

    "v2.admin.users.invitation-link": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/invitation-link',
        [\App\Http\Controllers\V2\Admin\UserManagementController::class, 'invitationLink']
    )
    ->name('v2.admin.users.invitation-link');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} edit-user routes added.")
PY

echo "[3/4] PHP checks..."

php -l \
app/Http/Controllers/V2/Admin/UserManagementController.php

php -l routes/web.php

php artisan optimize:clear

echo "[4/4] Route check..."

php artisan route:list | grep -E \
"v2/admin/users.*(edit|reset-password|invitation-link)|v2.admin.users.update"

printf '{\n  "module": "UserManagementEditActions",\n  "installed": true,\n  "version": "4.7.2",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/user-management-edit-actions-installed.json

echo ""
echo "USER MANAGEMENT EDIT ACTIONS INSTALLED"
echo "Backup: $BACKUP"
