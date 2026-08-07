#!/usr/bin/env bash

set -Eeuo pipefail

cd /var/www/backstage-distribution

echo "=================================================="
echo "FINAL PERMISSION SYSTEM QA"
echo "=================================================="

FAILED=0

pass() {
    echo "✓ $1"
}

fail() {
    echo "✗ $1"
    FAILED=1
}

echo ""
echo "[1/8] Required files"

FILES=(
    "app/Services/V2/PermissionService.php"
    "app/Models/System/UserPanelPermission.php"
    "app/Http/Middleware/HandleInertiaRequests.php"
    "app/Http/Middleware/EnforcePanelPermission.php"
    "resources/js/V2/Shared/Layouts/PanelLayout.jsx"
    "resources/js/V2/Shared/Navigation/Sidebar.jsx"
    "resources/js/V2/Shared/Config/panelNavigation.js"
)

for FILE in "${FILES[@]}"; do
    if [ -f "$FILE" ]; then
        pass "$FILE"
    else
        fail "$FILE missing"
    fi
done

echo ""
echo "[2/8] PHP syntax"

PHP_FILES=(
    "app/Services/V2/PermissionService.php"
    "app/Models/System/UserPanelPermission.php"
    "app/Http/Middleware/HandleInertiaRequests.php"
    "app/Http/Middleware/EnforcePanelPermission.php"
)

for FILE in "${PHP_FILES[@]}"; do
    if php -l "$FILE" >/dev/null; then
        pass "$FILE syntax"
    else
        fail "$FILE syntax error"
    fi
done

echo ""
echo "[3/8] Middleware registration"

if grep -Rqs \
    "EnforcePanelPermission" \
    bootstrap/app.php \
    app/Http/Kernel.php \
    2>/dev/null
then
    pass "Direct URL permission middleware registered"
else
    fail "Direct URL middleware is not registered"
fi

echo ""
echo "[4/8] Inertia permission sharing"

for TOKEN in \
    "PermissionService" \
    "permissionData" \
    "panelPermissions" \
    "'permissions'"
do
    if grep -q "$TOKEN" \
        app/Http/Middleware/HandleInertiaRequests.php
    then
        pass "Shared prop found: $TOKEN"
    else
        fail "Shared prop missing: $TOKEN"
    fi
done

echo ""
echo "[5/8] Sidebar enforcement"

if grep -q \
    "filterNavigationItem" \
    resources/js/V2/Shared/Navigation/Sidebar.jsx
then
    pass "Top-level and child menu filtering enabled"
else
    fail "Child permission filtering missing"
fi

if grep -q \
    "permissions.includes('\\*')" \
    resources/js/V2/Shared/Navigation/Sidebar.jsx
then
    pass "Super Admin wildcard supported"
else
    fail "Super Admin wildcard check missing"
fi

echo ""
echo "[6/8] Permission database"

php artisan tinker --execute="
use App\Models\User;
use App\Models\System\UserPanelPermission;

echo 'USERS'.PHP_EOL;

User::query()
    ->orderBy('id')
    ->get([
        'id',
        'name',
        'email',
        'role',
        'account_status',
    ])
    ->each(function (\$user) {
        echo \$user->id
            .' | '
            .\$user->email
            .' | '
            .\$user->role
            .' | '
            .\$user->account_status
            .PHP_EOL;
    });

echo PHP_EOL.'PERMISSION ROWS: '
    .UserPanelPermission::query()->count()
    .PHP_EOL;
"

PERMISSION_COUNT="$(
php artisan tinker --execute="
echo \App\Models\System\UserPanelPermission::query()->count();
" 2>/dev/null | tr -dc '0-9'
)"

if [ "${PERMISSION_COUNT:-0}" -gt 0 ]; then
    pass "Permission records available: $PERMISSION_COUNT"
else
    fail "Permission table is empty"
fi

echo ""
echo "[7/8] Effective permission output"

php artisan tinker --execute="
\$service = app(
    \App\Services\V2\PermissionService::class
);

\App\Models\User::query()
    ->orderBy('id')
    ->get()
    ->each(function (\$user) use (\$service) {
        echo PHP_EOL;
        echo 'USER: '.\$user->email.PHP_EOL;
        echo 'ROLE: '.\$service->role(\$user).PHP_EOL;

        echo 'EFFECTIVE: '
            .json_encode(
                \$service->permissions(\$user),
                JSON_UNESCAPED_SLASHES
            )
            .PHP_EOL;

        echo 'PANEL: '
            .json_encode(
                \$service->panelPermissions(\$user),
                JSON_UNESCAPED_SLASHES
            )
            .PHP_EOL;

        foreach ([
            'catalogue.view',
            'releases.create',
            'reports.view',
            'royalties.view',
            'wallet.view',
            'withdrawals.view',
            'users.view',
            'support.manage',
            'settings.view',
            'delivery.view',
            'identifiers.view',
        ] as \$permission) {
            echo \$permission
                .' = '
                .(
                    \$service->allows(
                        \$user,
                        \$permission
                    )
                        ? 'ALLOW'
                        : 'DENY'
                )
                .PHP_EOL;
        }
    });
"

echo ""
echo "[8/8] Application boot and frontend build"

php artisan optimize:clear

if php artisan about >/dev/null; then
    pass "Laravel application boots successfully"
else
    fail "Laravel application boot failed"
fi

if npm run build; then
    pass "Frontend build successful"
else
    fail "Frontend build failed"
fi

echo ""
echo "===== PROTECTED ROUTES ====="

php artisan route:list |
grep -E \
"v2/(catalogue|reports|royalties|wallet|withdrawals|support|admin/settings|admin/users|admin/identifiers|admin/distribution)" |
head -100 \
|| true

echo ""
echo "=================================================="

if [ "$FAILED" -eq 0 ]; then
    echo "FINAL PERMISSION QA PASSED"
    echo "Permission system is ready for browser testing."
else
    echo "FINAL PERMISSION QA FAILED"
    echo "ऊपर ✗ वाले checks का output भेजें।"
    exit 1
fi

echo "=================================================="
