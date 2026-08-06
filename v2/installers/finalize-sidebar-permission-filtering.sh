#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/sidebar-permission-filtering/$STAMP"

SIDEBAR="resources/js/V2/Shared/Navigation/Sidebar.jsx"

mkdir -p \
    "$BACKUP" \
    v2/runtime/state

echo "=================================================="
echo "FINALIZING SIDEBAR PERMISSION FILTERING"
echo "=================================================="

if [ ! -f "$SIDEBAR" ]; then
    echo "ERROR: $SIDEBAR नहीं मिला।"
    exit 1
fi

cp -a \
    "$SIDEBAR" \
    "$BACKUP/Sidebar.jsx"

echo "[1/4] Upgrading permission filtering..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/V2/Shared/Navigation/Sidebar.jsx"
)

text = path.read_text()

old_function = """const permissionAllowed = (
    item,
    permissions
) => {
    if (!item.permission) {
        return true;
    }

    if (
        !Array.isArray(permissions) ||
        permissions.length === 0
    ) {
        return true;
    }

    return permissions.includes(
        item.permission
    );
};
"""

new_function = """const permissionAllowed = (
    item,
    permissions
) => {
    if (!item?.permission) {
        return true;
    }

    if (
        !Array.isArray(permissions)
    ) {
        return false;
    }

    if (
        permissions.includes('*')
    ) {
        return true;
    }

    return permissions.includes(
        item.permission
    );
};

const filterNavigationItem = (
    item,
    permissions
) => {
    if (
        !permissionAllowed(
            item,
            permissions
        )
    ) {
        return null;
    }

    if (
        !Array.isArray(item.children)
    ) {
        return item;
    }

    const allowedChildren =
        item.children.filter(
            (child) =>
                permissionAllowed(
                    child,
                    permissions
                )
        );

    return {
        ...item,
        children: allowedChildren,
    };
};
"""

if "const filterNavigationItem" not in text:
    if old_function not in text:
        raise SystemExit(
            "permissionAllowed function का expected block नहीं मिला।"
        )

    text = text.replace(
        old_function,
        new_function,
        1
    )

old_navigation = """    const navigation = useMemo(
        () =>
            getPanelNavigation(
                role
            ).filter((item) =>
                permissionAllowed(
                    item,
                    permissions
                )
            ),
        [role, permissions]
    );
"""

new_navigation = """    const navigation = useMemo(
        () =>
            getPanelNavigation(role)
                .map((item) =>
                    filterNavigationItem(
                        item,
                        permissions
                    )
                )
                .filter(Boolean),
        [role, permissions]
    );
"""

if ".map((item) =>\n                    filterNavigationItem" not in text:
    if old_navigation not in text:
        raise SystemExit(
            "Sidebar navigation memo block नहीं मिला।"
        )

    text = text.replace(
        old_navigation,
        new_navigation,
        1
    )

path.write_text(text)

print(
    "Top-level and child permission filtering enabled."
)
PY

echo "[2/4] Checking Sidebar source..."

grep -nE \
"permissionAllowed|filterNavigationItem|permissions.includes" \
"$SIDEBAR"

echo "[3/4] Building frontend..."

npm run build

echo "[4/4] Clearing application cache..."

php artisan optimize:clear

printf '{\n  "module": "SidebarPermissionFiltering",\n  "installed": true,\n  "version": "5.0.1",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/sidebar-permission-filtering-installed.json

echo ""
echo "=================================================="
echo "SIDEBAR PERMISSION FILTERING INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/sidebar-permission-filtering-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
