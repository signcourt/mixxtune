#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/dynamic-permission-engine/$STAMP"

PERMISSION_SERVICE="app/Services/V2/PermissionService.php"
INERTIA_MIDDLEWARE="app/Http/Middleware/HandleInertiaRequests.php"

mkdir -p \
    "$BACKUP" \
    app/Services/V2 \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING DYNAMIC PERMISSION ENGINE"
echo "=================================================="

for FILE in \
    "$PERMISSION_SERVICE" \
    "$INERTIA_MIDDLEWARE"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/6] Creating database-driven PermissionService..."

cat > "$PERMISSION_SERVICE" <<'PHP'
<?php

namespace App\Services\V2;

use App\Models\System\UserPanelPermission;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class PermissionService
{
    /**
     * Base role permissions keep existing accounts working.
     * User-specific database permissions can add or remove
     * controlled panel modules.
     */
    private array $rolePermissions = [
        'super_admin' => ['*'],

        'admin' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'contributors.view',
            'contributors.manage',
            'splits.view',
            'splits.manage',
            'releases.review',
            'releases.approve',
            'releases.reject',
            'releases.request_changes',
            'releases.processing',
            'artists.view',
            'labels.view',
            'catalogue.view',
            'catalogue.manage',
            'catalogue.sync',
            'catalogue.export',
            'reports.view',
            'delivery.manage',
            'audio_validation.view',
            'audio_validation.run',
            'audio_validation.override',
            'identifiers.view',
            'identifiers.assign',
            'identifiers.generate',
            'identifiers.bulk_assign',
            'identifiers.unassign',
            'delivery.view',
            'delivery.retry',
            'delivery.mark_delivered',
            'delivery.mark_live',
            'delivery.mark_failed',
            'delivery.takedown',
            'support.manage',
        ],

        'label' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'releases.submit',
            'artists.view',
            'artists.manage',
            'catalogue.view',
            'reports.view',
            'royalties.view',
            'wallet.view',
            'withdrawals.create',
            'support.create',
            'profile.update',
        ],

        'artist' => [
            'dashboard.view',
            'releases.view',
            'releases.create',
            'releases.update',
            'releases.submit',
            'catalogue.view',
            'reports.view',
            'royalties.view',
            'wallet.view',
            'withdrawals.create',
            'support.create',
            'profile.update',
        ],
    ];

    /**
     * Database checkbox field to application permission mapping.
     */
    private array $panelPermissionMap = [
        'can_view_catalogue' => [
            'catalogue.view',
        ],

        'can_create_releases' => [
            'releases.create',
            'releases.submit',
        ],

        'can_manage_releases' => [
            'releases.view',
            'releases.update',
            'releases.review',
            'releases.approve',
            'releases.reject',
            'releases.request_changes',
            'releases.processing',
        ],

        'can_view_reports' => [
            'reports.view',
        ],

        'can_view_royalties' => [
            'royalties.view',
        ],

        'can_manage_wallet' => [
            'wallet.view',
            'wallet.manage',
        ],

        'can_manage_withdrawals' => [
            'withdrawals.view',
            'withdrawals.create',
            'withdrawals.manage',
        ],

        'can_manage_users' => [
            'users.view',
            'users.manage',
            'artists.view',
            'labels.view',
        ],

        'can_manage_support' => [
            'support.create',
            'support.manage',
        ],

        'can_manage_settings' => [
            'settings.view',
            'settings.manage',
            'audit_logs.view',
        ],

        'can_manage_delivery' => [
            'delivery.view',
            'delivery.manage',
            'delivery.retry',
            'delivery.mark_delivered',
            'delivery.mark_live',
            'delivery.mark_failed',
            'delivery.takedown',
        ],

        'can_manage_identifiers' => [
            'identifiers.view',
            'identifiers.assign',
            'identifiers.generate',
            'identifiers.bulk_assign',
            'identifiers.unassign',
        ],
    ];

    public function role(?User $user): string
    {
        $role = strtolower(
            trim(
                (string) (
                    $user?->role
                    ?? 'artist'
                )
            )
        );

        return array_key_exists(
            $role,
            $this->rolePermissions
        )
            ? $role
            : 'artist';
    }

    /**
     * Effective permission list used by controllers and frontend.
     */
    public function permissions(?User $user): array
    {
        if (!$user) {
            return [];
        }

        $role = $this->role($user);

        if ($role === 'super_admin') {
            return ['*'];
        }

        $basePermissions =
            $this->rolePermissions[$role]
            ?? [];

        $panelRecord =
            $this->panelRecord($user);

        /*
         * No database record means legacy role defaults remain active.
         * This prevents existing accounts from losing access suddenly.
         */
        if (!$panelRecord) {
            return array_values(
                array_unique(
                    $basePermissions
                )
            );
        }

        $controlledPermissions =
            $this->controlledPermissions();

        /*
         * Remove permissions controlled by database checkboxes.
         * Enabled fields are added back below.
         */
        $effective = array_values(
            array_diff(
                $basePermissions,
                $controlledPermissions
            )
        );

        foreach (
            $this->panelPermissionMap
            as $field => $mappedPermissions
        ) {
            if (!(bool) $panelRecord->{$field}) {
                continue;
            }

            $effective = array_merge(
                $effective,
                $mappedPermissions
            );
        }

        return array_values(
            array_unique(
                $effective
            )
        );
    }

    public function allows(
        ?User $user,
        string $permission
    ): bool {
        $permissions =
            $this->permissions($user);

        return in_array(
            '*',
            $permissions,
            true
        ) || in_array(
            $permission,
            $permissions,
            true
        );
    }

    public function denies(
        ?User $user,
        string $permission
    ): bool {
        return !$this->allows(
            $user,
            $permission
        );
    }

    public function authorize(
        ?User $user,
        string $permission
    ): void {
        abort_if(
            $this->denies(
                $user,
                $permission
            ),
            403,
            'You do not have permission to perform this action.'
        );
    }

    public function panelPermissions(
        ?User $user
    ): array {
        $defaults = array_fill_keys(
            array_keys(
                $this->panelPermissionMap
            ),
            false
        );

        if (!$user) {
            return $defaults;
        }

        if (
            $this->role($user)
            === 'super_admin'
        ) {
            return array_fill_keys(
                array_keys(
                    $this->panelPermissionMap
                ),
                true
            );
        }

        $record =
            $this->panelRecord($user);

        if (!$record) {
            return $this->rolePanelDefaults(
                $this->role($user)
            );
        }

        $values = [];

        foreach (
            array_keys(
                $this->panelPermissionMap
            )
            as $field
        ) {
            $values[$field] =
                (bool) $record->{$field};
        }

        return $values;
    }

    public function frontend(
        ?User $user
    ): array {
        return [
            'role' =>
                $this->role($user),

            'permissions' =>
                $this->permissions($user),

            'panelPermissions' =>
                $this->panelPermissions(
                    $user
                ),

            'isSuperAdmin' =>
                $this->role($user)
                === 'super_admin',
        ];
    }

    public function defaultPanelValues(
        string $role
    ): array {
        return $this->rolePanelDefaults(
            strtolower(
                trim($role)
            )
        );
    }

    private function panelRecord(
        User $user
    ): ?UserPanelPermission {
        if (
            !Schema::hasTable(
                'user_panel_permissions'
            )
        ) {
            return null;
        }

        return UserPanelPermission::query()
            ->where(
                'user_id',
                $user->id
            )
            ->first();
    }

    private function controlledPermissions(): array
    {
        $permissions = [];

        foreach (
            $this->panelPermissionMap
            as $mappedPermissions
        ) {
            $permissions = array_merge(
                $permissions,
                $mappedPermissions
            );
        }

        return array_values(
            array_unique(
                $permissions
            )
        );
    }

    private function rolePanelDefaults(
        string $role
    ): array {
        $defaults = [
            'can_view_catalogue' => false,
            'can_create_releases' => false,
            'can_manage_releases' => false,
            'can_view_reports' => false,
            'can_view_royalties' => false,
            'can_manage_wallet' => false,
            'can_manage_withdrawals' => false,
            'can_manage_users' => false,
            'can_manage_support' => false,
            'can_manage_settings' => false,
            'can_manage_delivery' => false,
            'can_manage_identifiers' => false,
        ];

        if ($role === 'admin') {
            return array_replace(
                $defaults,
                [
                    'can_view_catalogue' =>
                        true,

                    'can_manage_releases' =>
                        true,

                    'can_view_reports' =>
                        true,

                    'can_manage_users' =>
                        true,

                    'can_manage_support' =>
                        true,

                    'can_manage_delivery' =>
                        true,

                    'can_manage_identifiers' =>
                        true,
                ]
            );
        }

        if ($role === 'label') {
            return array_replace(
                $defaults,
                [
                    'can_view_catalogue' =>
                        true,

                    'can_create_releases' =>
                        true,

                    'can_view_reports' =>
                        true,

                    'can_view_royalties' =>
                        true,

                    'can_manage_wallet' =>
                        true,

                    'can_manage_withdrawals' =>
                        true,

                    'can_manage_support' =>
                        true,
                ]
            );
        }

        if ($role === 'artist') {
            return array_replace(
                $defaults,
                [
                    'can_view_catalogue' =>
                        true,

                    'can_create_releases' =>
                        true,

                    'can_view_reports' =>
                        true,

                    'can_view_royalties' =>
                        true,

                    'can_manage_wallet' =>
                        true,

                    'can_manage_withdrawals' =>
                        true,

                    'can_manage_support' =>
                        true,
                ]
            );
        }

        if ($role === 'super_admin') {
            return array_fill_keys(
                array_keys($defaults),
                true
            );
        }

        return $defaults;
    }
}
PHP

echo "[2/6] Updating Inertia shared authentication props..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Middleware/HandleInertiaRequests.php"
)

text = path.read_text()

service_import = (
    "use App\\Services\\V2\\PermissionService;\n"
)

if service_import not in text:
    marker = "namespace App\\Http\\Middleware;\n\n"

    if marker not in text:
        raise SystemExit(
            "Middleware namespace marker not found."
        )

    text = text.replace(
        marker,
        marker + service_import,
        1
    )

old_signature = (
    "    public function share(Request $request): array\n"
    "    {\n"
)

new_signature = (
    "    public function share(Request $request): array\n"
    "    {\n"
    "        $permissionService = app(\n"
    "            PermissionService::class\n"
    "        );\n\n"
    "        $permissionData =\n"
    "            $permissionService->frontend(\n"
    "                $request->user()\n"
    "            );\n\n"
)

if "$permissionData =" not in text:
    if old_signature not in text:
        raise SystemExit(
            "Middleware share signature not found."
        )

    text = text.replace(
        old_signature,
        new_signature,
        1
    )

old_auth = """            'auth' => [
                'user' => $request->user(),
            ],
"""

new_auth = """            'role' =>
                $permissionData['role'],

            'permissions' =>
                $permissionData[
                    'permissions'
                ],

            'panelPermissions' =>
                $permissionData[
                    'panelPermissions'
                ],

            'auth' => [
                'user' =>
                    $request->user(),

                'role' =>
                    $permissionData['role'],

                'permissions' =>
                    $permissionData[
                        'permissions'
                    ],

                'panel_permissions' =>
                    $permissionData[
                        'panelPermissions'
                    ],

                'is_super_admin' =>
                    $permissionData[
                        'isSuperAdmin'
                    ],
            ],
"""

if "'panelPermissions' =>" not in text:
    if old_auth not in text:
        raise SystemExit(
            "Existing auth shared block not found."
        )

    text = text.replace(
        old_auth,
        new_auth,
        1
    )

path.write_text(text)

print(
    "Inertia permission props connected."
)
PY

echo "[3/6] Creating default permission rows for existing users..."

php artisan tinker --execute="
\$service = app(
    \App\Services\V2\PermissionService::class
);

\$created = 0;
\$existing = 0;

\App\Models\User::query()
    ->whereIn(
        'role',
        [
            'admin',
            'label',
            'artist',
        ]
    )
    ->orderBy('id')
    ->get()
    ->each(
        function (\$user) use (
            \$service,
            &\$created,
            &\$existing
        ) {
            \$defaults =
                \$service->defaultPanelValues(
                    \$user->role
                );

            \$record =
                \App\Models\System\UserPanelPermission::query()
                    ->firstOrCreate(
                        [
                            'user_id' =>
                                \$user->id,
                        ],
                        array_merge(
                            \$defaults,
                            [
                                'updated_by' =>
                                    null,
                            ]
                        )
                    );

            if (\$record->wasRecentlyCreated) {
                \$created++;
            } else {
                \$existing++;
            }

            echo \$user->id
                .' | '
                .\$user->email
                .' | '
                .\$user->role
                .' | '
                .(
                    \$record->wasRecentlyCreated
                        ? 'CREATED'
                        : 'EXISTS'
                )
                .PHP_EOL;
        }
    );

echo 'Created: '.\$created.PHP_EOL;
echo 'Existing: '.\$existing.PHP_EOL;
"

echo "[4/6] Running PHP syntax checks..."

php -l "$PERMISSION_SERVICE"
php -l "$INERTIA_MIDDLEWARE"

echo "[5/6] Testing effective permissions..."

php artisan tinker --execute="
\$service = app(
    \App\Services\V2\PermissionService::class
);

\App\Models\User::query()
    ->orderBy('id')
    ->get()
    ->each(
        function (\$user) use (\$service) {
            echo PHP_EOL
                .'USER: '
                .\$user->email
                .' | ROLE: '
                .\$service->role(\$user)
                .PHP_EOL;

            echo 'PERMISSIONS: '
                .implode(
                    ', ',
                    \$service->permissions(
                        \$user
                    )
                )
                .PHP_EOL;

            echo 'PANEL: '
                .json_encode(
                    \$service->panelPermissions(
                        \$user
                    )
                )
                .PHP_EOL;
        }
    );
"

echo "[6/6] Building frontend and clearing caches..."

npm run build

php artisan optimize:clear

printf '{\n  "module": "DynamicPermissionEngine",\n  "installed": true,\n  "version": "5.0.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/dynamic-permission-engine-installed.json

echo ""
echo "===== PERMISSION TABLE COUNT ====="

php artisan tinker --execute="
echo \App\Models\System\UserPanelPermission::query()
    ->count()
    .PHP_EOL;
"

echo ""
echo "=================================================="
echo "DYNAMIC PERMISSION ENGINE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/dynamic-permission-engine-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
