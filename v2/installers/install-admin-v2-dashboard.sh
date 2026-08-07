#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/admin-v2-dashboard/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING V2 ADMIN DASHBOARD"
echo "=============================================="

echo "[1/6] Creating backups..."

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/DashboardController.php \
    resources/js/Pages/V2/Admin/Dashboard.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done


echo "[2/6] Creating Admin Dashboard Controller..."

cat > app/Http/Controllers/V2/Admin/DashboardController.php <<'PHP'
<?php

namespace App\Http\Controllers\V2\Admin;

use App\Http\Controllers\Controller;
use App\Models\Distribution\Release;
use App\Services\V2\PermissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(
        Request $request,
        PermissionService $permissions
    ): Response {
        $user = $request->user();

        abort_unless(
            $user,
            401,
            'Authentication required.'
        );

        $role = $permissions->role($user);

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
            'Admin access required.'
        );

        $releaseQuery = Release::query()
            ->whereNull('deleted_at');

        /*
         * Normal Admin केवल assigned artists की
         * releases देखेगा। Super Admin सब देखेगा।
         */
        if (
            $role === 'admin'
            && Schema::hasColumn(
                'artists',
                'assigned_admin_id'
            )
        ) {
            $artistIds = DB::table('artists')
                ->where(
                    'assigned_admin_id',
                    $user->id
                )
                ->whereNull('deleted_at')
                ->pluck('id');

            $releaseQuery->whereIn(
                'artist_id',
                $artistIds
            );
        }

        $recentReleases = (clone $releaseQuery)
            ->select([
                'id',
                'title',
                'primary_artist_name',
                'status',
                'upc',
                'digital_release_date',
                'created_at',
                'submitted_at',
            ])
            ->orderByDesc('id')
            ->limit(10)
            ->get();

        return Inertia::render(
            'V2/Admin/Dashboard',
            [
                'role' => $role,

                'stats' => [
                    'total_releases' =>
                        (clone $releaseQuery)->count(),

                    'draft' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'draft'
                            )
                            ->count(),

                    'submitted' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'submitted'
                            )
                            ->count(),

                    'approved' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'approved'
                            )
                            ->count(),

                    'processing' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'processing'
                            )
                            ->count(),

                    'delivered' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'delivered'
                            )
                            ->count(),

                    'live' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'live'
                            )
                            ->count(),

                    'rejected' =>
                        (clone $releaseQuery)
                            ->where(
                                'status',
                                'rejected'
                            )
                            ->count(),
                ],

                'recentReleases' =>
                    $recentReleases,
            ]
        );
    }
}
PHP


echo "[3/6] Creating Admin Dashboard Page..."

cat > resources/js/Pages/V2/Admin/Dashboard.jsx <<'JSX'
import {
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    draft:
        'bg-slate-100 text-slate-700',

    submitted:
        'bg-amber-100 text-amber-700',

    approved:
        'bg-emerald-100 text-emerald-700',

    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-green-100 text-green-700',

    rejected:
        'bg-red-100 text-red-700',
};

export default function Dashboard({
    role = 'admin',
    stats = {},
    recentReleases = [],
}) {
    const cards = [
        {
            label: 'Total Releases',
            value: stats.total_releases ?? 0,
        },
        {
            label: 'In Review',
            value: stats.submitted ?? 0,
        },
        {
            label: 'Approved',
            value: stats.approved ?? 0,
        },
        {
            label: 'Processing',
            value: stats.processing ?? 0,
        },
        {
            label: 'Delivered',
            value: stats.delivered ?? 0,
        },
        {
            label: 'Live',
            value: stats.live ?? 0,
        },
        {
            label: 'Drafts',
            value: stats.draft ?? 0,
        },
        {
            label: 'Rejected',
            value: stats.rejected ?? 0,
        },
    ];

    return (
        <PanelLayout
            role={role}
            title={
                role === 'super_admin'
                    ? 'Super Admin Dashboard'
                    : 'Admin Dashboard'
            }
            subtitle="Release, review and distribution management"
        >
            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Dashboard Overview
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Current release workflow status
                        </p>
                    </div>

                    <Link
                        href="/v2/admin/release-reviews"
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        Open Review Queue
                    </Link>
                </div>

                <div className="grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                    {cards.map((card) => (
                        <div
                            key={card.label}
                            className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                        >
                            <div className="text-sm font-medium text-slate-500">
                                {card.label}
                            </div>

                            <div className="mt-3 text-3xl font-bold text-slate-900">
                                {card.value}
                            </div>
                        </div>
                    ))}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                Recent Releases
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Latest release activity
                            </p>
                        </div>

                        <Link
                            href="/v2/admin/release-reviews"
                            className="text-sm font-semibold text-violet-600"
                        >
                            View all
                        </Link>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <Header>
                                        Release
                                    </Header>

                                    <Header>
                                        Artist
                                    </Header>

                                    <Header>
                                        UPC
                                    </Header>

                                    <Header>
                                        Status
                                    </Header>

                                    <Header>
                                        Action
                                    </Header>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {recentReleases.length >
                                0 ? (
                                    recentReleases.map(
                                        (release) => (
                                            <tr
                                                key={
                                                    release.id
                                                }
                                            >
                                                <Cell>
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            release.title
                                                        }
                                                    </div>
                                                </Cell>

                                                <Cell>
                                                    {release.primary_artist_name ||
                                                        '—'}
                                                </Cell>

                                                <Cell>
                                                    {release.upc ||
                                                        'Pending'}
                                                </Cell>

                                                <Cell>
                                                    <span
                                                        className={`rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                                                            statusClasses[
                                                                release
                                                                    .status
                                                            ] ??
                                                            'bg-slate-100 text-slate-700'
                                                        }`}
                                                    >
                                                        {
                                                            release.status
                                                        }
                                                    </span>
                                                </Cell>

                                                <Cell>
                                                    <Link
                                                        href={`/v2/admin/release-reviews/${release.id}`}
                                                        className="font-semibold text-violet-600"
                                                    >
                                                        View
                                                    </Link>
                                                </Cell>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="5"
                                            className="px-6 py-14 text-center text-sm text-slate-500"
                                        >
                                            No releases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}

function Header({ children }) {
    return (
        <th className="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function Cell({ children }) {
    return (
        <td className="whitespace-nowrap px-6 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
JSX


echo "[4/6] Adding Admin subdomain route..."

python3 - <<'PY'
from pathlib import Path

path = Path("routes/web.php")
text = path.read_text()

route_name = "v2.admin.dashboard"

if route_name not in text:
    route = r'''

Route::domain('admin.mixxtune.com')
    ->middleware(['auth', 'verified'])
    ->get(
        '/v2/admin/dashboard',
        [\App\Http\Controllers\V2\Admin\DashboardController::class, 'index']
    )
    ->name('v2.admin.dashboard');
'''

    text += route
    path.write_text(text)

    print("Admin V2 dashboard route added.")
else:
    print("Admin V2 dashboard route already exists.")
PY


echo "[5/6] Running checks and build..."

php -l \
app/Http/Controllers/V2/Admin/DashboardController.php

php -l routes/web.php

npm run build

php artisan optimize:clear


echo "[6/6] Saving state..."

printf '{\n  "module": "AdminV2Dashboard",\n  "installed": true,\n  "version": "3.3.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/admin-v2-dashboard-installed.json

echo ""
echo "===== ADMIN DASHBOARD ROUTE ====="

php artisan route:list | grep -E \
"v2/admin/dashboard|v2.admin.dashboard"

echo ""
echo "=============================================="
echo "ADMIN V2 DASHBOARD INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/admin-v2-dashboard-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
