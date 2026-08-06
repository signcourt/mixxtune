#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/direct-url-permission/$STAMP"

MIDDLEWARE_FILE="app/Http/Middleware/EnforcePanelPermission.php"
BOOTSTRAP_FILE="bootstrap/app.php"
KERNEL_FILE="app/Http/Kernel.php"

mkdir -p \
    "$BACKUP" \
    app/Http/Middleware \
    v2/runtime/state

echo "=================================================="
echo "INSTALLING DIRECT URL PERMISSION MIDDLEWARE"
echo "=================================================="

for FILE in \
    "$MIDDLEWARE_FILE" \
    "$BOOTSTRAP_FILE" \
    "$KERNEL_FILE"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/6] Creating permission middleware..."

cat > "$MIDDLEWARE_FILE" <<'PHP'
<?php

namespace App\Http\Middleware;

use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EnforcePanelPermission
{
    public function __construct(
        private readonly PermissionService $permissions
    ) {
    }

    public function handle(
        Request $request,
        Closure $next
    ): Response {
        $user = $request->user();

        /*
         * Authentication middleware will handle guests.
         * Only V2 panel URLs are checked here.
         */
        if (
            !$user
            || !$request->is('v2/*')
        ) {
            return $next($request);
        }

        $requiredPermission =
            $this->requiredPermission(
                $request
            );

        if (!$requiredPermission) {
            return $next($request);
        }

        if (
            $this->permissions->allows(
                $user,
                $requiredPermission
            )
        ) {
            return $next($request);
        }

        $this->recordDeniedAccess(
            $request,
            $requiredPermission
        );

        abort(
            403,
            'You do not have permission to access this section.'
        );
    }

    private function requiredPermission(
        Request $request
    ): ?string {
        $method = strtoupper(
            $request->method()
        );

        /*
         * Super Admin user management.
         */
        if (
            $request->is(
                'v2/admin/users*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'users.view'
                : 'users.manage';
        }

        /*
         * Master settings and audit logs.
         */
        if (
            $request->is(
                'v2/admin/settings*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'settings.view'
                : 'settings.manage';
        }

        if (
            $request->is(
                'v2/admin/audit-logs*'
            )
        ) {
            return 'audit_logs.view';
        }

        /*
         * Catalogue.
         */
        if (
            $request->is(
                'v2/catalogue*'
            )
            || $request->is(
                'v2/admin/catalogue*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'catalogue.view'
                : 'catalogue.manage';
        }

        /*
         * Reports.
         */
        if (
            $request->is(
                'v2/reports*'
            )
            || $request->is(
                'v2/admin/reports*'
            )
        ) {
            return 'reports.view';
        }

        /*
         * Royalties.
         */
        if (
            $request->is(
                'v2/royalties*'
            )
            || $request->is(
                'v2/admin/royalties*'
            )
        ) {
            return 'royalties.view';
        }

        /*
         * Wallet.
         */
        if (
            $request->is(
                'v2/wallet*'
            )
            || $request->is(
                'v2/admin/wallet*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'wallet.view'
                : 'wallet.manage';
        }

        /*
         * Withdrawals.
         */
        if (
            $request->is(
                'v2/admin/withdrawals*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'withdrawals.view'
                : 'withdrawals.manage';
        }

        if (
            $request->is(
                'v2/withdrawals*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'withdrawals.view'
                : 'withdrawals.create';
        }

        /*
         * Support tickets.
         */
        if (
            $request->is(
                'v2/admin/support*'
            )
        ) {
            return 'support.manage';
        }

        if (
            $request->is(
                'v2/support*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'support.create'
                : 'support.create';
        }

        /*
         * DSP delivery and distribution.
         */
        if (
            $request->is(
                'v2/admin/distribution*'
            )
            || $request->is(
                'v2/admin/delivery*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'delivery.view'
                : 'delivery.manage';
        }

        /*
         * ISRC and UPC management.
         */
        if (
            $request->is(
                'v2/admin/identifiers*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'identifiers.view'
                : 'identifiers.assign';
        }

        /*
         * Release creation must be checked before
         * the general releases pattern.
         */
        if (
            $request->is(
                'v2/releases/create'
            )
        ) {
            return 'releases.create';
        }

        if (
            $request->is(
                'v2/releases*'
            )
        ) {
            if (
                in_array(
                    $method,
                    ['GET', 'HEAD'],
                    true
                )
            ) {
                return 'releases.view';
            }

            return 'releases.update';
        }

        /*
         * Admin release review and processing.
         */
        if (
            $request->is(
                'v2/admin/release-reviews*'
            )
        ) {
            return in_array(
                $method,
                ['GET', 'HEAD'],
                true
            )
                ? 'releases.review'
                : 'releases.processing';
        }

        /*
         * Notifications and dashboards remain
         * available to authenticated users.
         */
        return null;
    }

    private function recordDeniedAccess(
        Request $request,
        string $permission
    ): void {
        try {
            app(
                AuditLogService::class
            )->record(
                'permission.denied',
                'permissions',
                sprintf(
                    'Access denied for %s on %s.',
                    $permission,
                    $request->path()
                ),
                [],
                [
                    'required_permission' =>
                        $permission,

                    'method' =>
                        $request->method(),

                    'path' =>
                        $request->path(),
                ],
                $request->user(),
                $request
            );
        } catch (Throwable) {
            /*
             * Permission enforcement must continue
             * even if audit logging is unavailable.
             */
        }
    }
}
PHP

echo "[2/6] Registering middleware..."

python3 - <<'PY'
from pathlib import Path
import re

bootstrap = Path("bootstrap/app.php")
kernel = Path("app/Http/Kernel.php")

middleware_class = (
    "\\App\\Http\\Middleware\\"
    "EnforcePanelPermission::class"
)

if bootstrap.exists():
    text = bootstrap.read_text()

    if "EnforcePanelPermission::class" in text:
        print(
            "Middleware already registered "
            "in bootstrap/app.php."
        )
    else:
        pattern = re.compile(
            r"(->withMiddleware\s*"
            r"\(\s*function\s*\([^)]*"
            r"\)\s*\{)"
        )

        match = pattern.search(text)

        if not match:
            raise SystemExit(
                "withMiddleware block नहीं मिला "
                "in bootstrap/app.php"
            )

        insertion = (
            match.group(1)
            + "\n        $middleware"
            + "->appendToGroup(\n"
            + "            'web',\n"
            + "            "
            + middleware_class
            + "\n"
            + "        );"
        )

        text = (
            text[:match.start()]
            + insertion
            + text[match.end():]
        )

        bootstrap.write_text(text)

        print(
            "Middleware registered in "
            "bootstrap/app.php."
        )

elif kernel.exists():
    text = kernel.read_text()

    if "EnforcePanelPermission::class" in text:
        print(
            "Middleware already registered "
            "in Kernel.php."
        )
    else:
        marker = """        'web' => [
"""

        if marker not in text:
            raise SystemExit(
                "Web middleware group नहीं मिला "
                "in Kernel.php"
            )

        replacement = marker + (
            "            "
            "\\App\\Http\\Middleware\\"
            "EnforcePanelPermission::class,\n"
        )

        text = text.replace(
            marker,
            replacement,
            1
        )

        kernel.write_text(text)

        print(
            "Middleware registered in "
            "Kernel.php."
        )
else:
    raise SystemExit(
        "bootstrap/app.php और "
        "app/Http/Kernel.php दोनों नहीं मिले।"
    )
PY

echo "[3/6] PHP syntax checks..."

php -l "$MIDDLEWARE_FILE"

if [ -f "$BOOTSTRAP_FILE" ]; then
    php -l "$BOOTSTRAP_FILE"
fi

if [ -f "$KERNEL_FILE" ]; then
    php -l "$KERNEL_FILE"
fi

echo "[4/6] Checking middleware registration..."

grep -RIn \
    "EnforcePanelPermission" \
    bootstrap/app.php \
    app/Http/Kernel.php \
    2>/dev/null \
    || true

echo "[5/6] Clearing Laravel caches..."

php artisan optimize:clear

echo "[6/6] Checking application boot..."

php artisan about >/dev/null

php artisan route:list \
    | grep -E \
    "v2/(catalogue|reports|royalties|wallet|withdrawals|support|admin/settings|admin/users|admin/identifiers|admin/distribution)" \
    | head -80 \
    || true

printf '{\n  "module": "DirectUrlPermissionMiddleware",\n  "installed": true,\n  "version": "5.0.2",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/direct-url-permission-middleware-installed.json

echo ""
echo "=================================================="
echo "DIRECT URL PERMISSION MIDDLEWARE INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/direct-url-permission-middleware-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
