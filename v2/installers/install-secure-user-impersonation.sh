#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/secure-user-impersonation/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/V2/Shared/Components \
    v2/runtime/state

CONTROLLER="app/Http/Controllers/V2/Admin/UserImpersonationController.php"
INDEX_FILE="resources/js/Pages/V2/Admin/Users/Index.jsx"

echo "=================================================="
echo "INSTALLING SECURE USER IMPERSONATION"
echo "=================================================="

for FILE in \
    routes/web.php \
    "$CONTROLLER" \
    "$INDEX_FILE"
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

echo "[1/5] Creating impersonation controller..."

cat > "$CONTROLLER" <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\V2\AuditLogService;
use App\Services\V2\PermissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserImpersonationController extends Controller
{
    public function start(
        Request $request,
        User $user,
        PermissionService $permissions,
        AuditLogService $audit
    ): RedirectResponse {
        abort_unless(
            $permissions->role(
                $request->user()
            ) === 'super_admin',
            403,
            'Super Admin access required.'
        );

        abort_if(
            $request->session()->has(
                'impersonator_user_id'
            ),
            422,
            'An impersonation session is already active.'
        );

        abort_if(
            $user->id === $request->user()->id,
            422,
            'You cannot impersonate your own account.'
        );

        abort_if(
            $user->role === 'super_admin',
            422,
            'Another Super Admin cannot be impersonated.'
        );

        abort_if(
            $user->account_status !== 'active',
            422,
            'Only active users can be impersonated.'
        );

        $superAdmin =
            $request->user();

        $request->session()->put([
            'impersonator_user_id' =>
                $superAdmin->id,

            'impersonator_name' =>
                $superAdmin->name,

            'impersonator_email' =>
                $superAdmin->email,

            'impersonated_user_id' =>
                $user->id,

            'impersonation_started_at' =>
                now()->toIso8601String(),
        ]);

        $audit->record(
            'user.impersonation_started',
            'users',
            "{$superAdmin->email} started impersonating {$user->email}.",
            [],
            [
                'impersonator_user_id' =>
                    $superAdmin->id,

                'impersonated_user_id' =>
                    $user->id,

                'impersonated_role' =>
                    $user->role,
            ],
            $user,
            $request
        );

        Auth::login(
            $user,
            false
        );

        $request->session()->regenerate();

        return redirect(
            $this->dashboardFor(
                $user
            )
        )->with(
            'success',
            "You are now viewing the panel as {$user->name}."
        );
    }

    public function stop(
        Request $request,
        AuditLogService $audit
    ): RedirectResponse {
        $originalUserId =
            $request->session()->get(
                'impersonator_user_id'
            );

        abort_unless(
            $originalUserId,
            403,
            'No impersonation session is active.'
        );

        $impersonatedUser =
            $request->user();

        $superAdmin =
            User::query()->findOrFail(
                $originalUserId
            );

        abort_unless(
            $superAdmin->role ===
                'super_admin',
            403,
            'Original Super Admin account is invalid.'
        );

        Auth::login(
            $superAdmin,
            false
        );

        $audit->record(
            'user.impersonation_stopped',
            'users',
            "{$superAdmin->email} stopped impersonating {$impersonatedUser?->email}.",
            [
                'impersonated_user_id' =>
                    $impersonatedUser?->id,
            ],
            [
                'restored_user_id' =>
                    $superAdmin->id,
            ],
            $impersonatedUser,
            $request
        );

        $request->session()->forget([
            'impersonator_user_id',
            'impersonator_name',
            'impersonator_email',
            'impersonated_user_id',
            'impersonation_started_at',
        ]);

        $request->session()->regenerate();

        return redirect(
            '/v2/admin/users'
        )->with(
            'success',
            'Returned to Super Admin account.'
        );
    }

    private function dashboardFor(
        User $user
    ): string {
        return match ($user->role) {
            'admin' =>
                '/v2/admin/dashboard',

            'label' =>
                '/v2/label/dashboard',

            'artist' =>
                '/v2/artist/dashboard',

            default =>
                '/v2/dashboard',
        };
    }
}
PHP

echo "[2/5] Adding routes..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

routes = {
    "v2.admin.users.impersonate": r"""
Route::middleware(['auth', 'verified'])
    ->post(
        '/v2/admin/users/{user}/impersonate',
        [\App\Http\Controllers\V2\Admin\UserImpersonationController::class, 'start']
    )
    ->name('v2.admin.users.impersonate');
""",

    "v2.impersonation.stop": r"""
Route::middleware(['auth'])
    ->post(
        '/v2/impersonation/stop',
        [\App\Http\Controllers\V2\Admin\UserImpersonationController::class, 'stop']
    )
    ->name('v2.impersonation.stop');
""",
}

added = 0

for name, route in routes.items():
    if name not in text:
        text += "\n" + route
        added += 1

path.write_text(text)

print(f"{added} impersonation routes added.")
PY

echo "[3/5] Adding Login As button to Users page..."

python3 - <<'PY'
from pathlib import Path

path = Path(
    "resources/js/Pages/V2/Admin/Users/Index.jsx"
)

if not path.exists():
    raise SystemExit(
        "Users Index page नहीं मिला। पहले User Management UI install करें."
    )

text = path.read_text()

needle = """                                                    {user.role !==
                                                        'super_admin' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    user
                                                                )
                                                            }
"""

replacement = """                                                    {user.role !==
                                                        'super_admin' &&
                                                        user.account_status ===
                                                            'active' && (
                                                        <button
                                                            type="button"
                                                            onClick={() => {
                                                                if (
                                                                    window.confirm(
                                                                        `Open the panel as ${user.name}?`
                                                                    )
                                                                ) {
                                                                    router.post(
                                                                        `/v2/admin/users/${user.id}/impersonate`
                                                                    );
                                                                }
                                                            }}
                                                            className="rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-700"
                                                        >
                                                            Login As
                                                        </button>
                                                    )}

                                                    {user.role !==
                                                        'super_admin' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                toggleStatus(
                                                                    user
                                                                )
                                                            }
"""

if "/impersonate`" not in text:
    if needle not in text:
        raise SystemExit(
            "Users action section नहीं मिला।"
        )

    text = text.replace(
        needle,
        replacement,
        1
    )

path.write_text(text)

print("Login As button added.")
PY

echo "[4/5] Creating reusable impersonation banner..."

cat > resources/js/V2/Shared/Components/ImpersonationBanner.jsx <<'JSX'
import {
    router,
    usePage,
} from '@inertiajs/react';

export default function ImpersonationBanner() {
    const {
        auth = {},
        impersonation = null,
    } = usePage().props;

    if (!impersonation?.active) {
        return null;
    }

    const stop = () => {
        if (
            !window.confirm(
                'Return to the Super Admin account?'
            )
        ) {
            return;
        }

        router.post(
            '/v2/impersonation/stop'
        );
    };

    return (
        <div className="sticky top-0 z-[100] flex flex-wrap items-center justify-between gap-3 bg-amber-400 px-5 py-3 text-sm text-amber-950 shadow-md">
            <div>
                <strong>
                    Impersonation active:
                </strong>{' '}
                You are viewing the panel as{' '}
                <strong>
                    {auth?.user?.name ??
                        'another user'}
                </strong>
                .
            </div>

            <button
                type="button"
                onClick={stop}
                className="rounded-lg bg-slate-950 px-4 py-2 text-xs font-semibold text-white hover:bg-slate-800"
            >
                Return to Super Admin
            </button>
        </div>
    );
}
JSX

echo "[5/5] PHP checks, build and route verification..."

php -l "$CONTROLLER"
php -l routes/web.php

npm run build

php artisan optimize:clear

echo ""
echo "===== IMPERSONATION ROUTES ====="

php artisan route:list | grep -E \
"v2/(admin/users/.*/impersonate|impersonation/stop)"

printf '{\n  "module": "SecureUserImpersonation",\n  "installed": true,\n  "version": "4.7.4",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/secure-user-impersonation-installed.json

echo ""
echo "=================================================="
echo "SECURE USER IMPERSONATION INSTALLED"
echo "=================================================="

cat \
v2/runtime/state/secure-user-impersonation-installed.json

echo ""
echo "Users page:"
echo "https://admin.mixxtune.com/v2/admin/users"

echo ""
echo "Backup:"
echo "$BACKUP"
