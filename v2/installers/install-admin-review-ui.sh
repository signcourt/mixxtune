#!/usr/bin/env bash

set -Eeuo pipefail

PROJECT="/var/www/backstage-distribution"
cd "$PROJECT"

STAMP="$(date +%Y%m%d-%H%M%S)"
BACKUP="$PROJECT/v2/backups/admin-review-ui/$STAMP"

mkdir -p \
    "$BACKUP" \
    app/Http/Controllers/V2/Admin \
    resources/js/Pages/V2/Admin/ReleaseReviews \
    v2/runtime/state

echo "=============================================="
echo "INSTALLING ADMIN REVIEW QUEUE UI"
echo "=============================================="

for FILE in \
    routes/web.php \
    app/Http/Controllers/V2/Admin/ReleaseReviewController.php \
    resources/js/Pages/V2/Admin/ReleaseReviews/Index.jsx \
    resources/js/Pages/V2/Admin/ReleaseReviews/Show.jsx
do
    if [ -f "$FILE" ]; then
        mkdir -p "$BACKUP/$(dirname "$FILE")"
        cp -a "$FILE" "$BACKUP/$FILE"
    fi
done

cat > resources/js/Pages/V2/Admin/ReleaseReviews/Index.jsx <<'JSX'
import { Head, Link, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    submitted: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    rejected: 'bg-red-100 text-red-700',
    changes_requested: 'bg-orange-100 text-orange-700',
    processing: 'bg-blue-100 text-blue-700',
};

export default function Index({
    role = 'admin',
    releases = {},
    counts = {},
    filters = {},
}) {
    const rows = releases.data ?? [];

    const updateStatus = (status) => {
        router.get(
            '/v2/admin/release-reviews',
            {
                ...filters,
                status,
            },
            {
                preserveState: true,
                preserveScroll: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Release Review Queue"
            subtitle="Review submitted releases"
        >
            <Head title="Release Review Queue" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        ['submitted', 'In Review'],
                        ['approved', 'Approved'],
                        ['changes_requested', 'Need Changes'],
                        ['rejected', 'Rejected'],
                        ['processing', 'Processing'],
                    ].map(([status, label]) => (
                        <button
                            key={status}
                            type="button"
                            onClick={() =>
                                updateStatus(status)
                            }
                            className={[
                                'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                filters.status === status
                                    ? 'border-violet-500 ring-2 ring-violet-100'
                                    : 'border-slate-200 hover:border-violet-300',
                            ].join(' ')}
                        >
                            <div className="text-sm text-slate-500">
                                {label}
                            </div>

                            <div className="mt-2 text-3xl font-bold text-slate-900">
                                {counts[status] ?? 0}
                            </div>
                        </button>
                    ))}
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Releases
                            </h2>

                            <p className="text-sm text-slate-500">
                                Open a release to review metadata, audio and stores.
                            </p>
                        </div>

                        <input
                            type="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Search title, artist, UPC..."
                            onKeyDown={(event) => {
                                if (
                                    event.key === 'Enter'
                                ) {
                                    router.get(
                                        '/v2/admin/release-reviews',
                                        {
                                            ...filters,
                                            search:
                                                event.currentTarget.value,
                                        },
                                        {
                                            preserveState: true,
                                        }
                                    );
                                }
                            }}
                            className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 lg:w-80"
                        />
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Release',
                                        'Artist',
                                        'UPC',
                                        'Tracks',
                                        'Status',
                                        'Submitted',
                                        'Action',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.length > 0 ? (
                                    rows.map((release) => (
                                        <tr key={release.id}>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">
                                                    {release.title}
                                                </div>

                                                <div className="text-xs text-slate-500">
                                                    {release.catalog_number ||
                                                        'No catalogue number'}
                                                </div>
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {release.primary_artist_name ||
                                                    '—'}
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {release.upc ||
                                                    'Pending'}
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {release.tracks_count ??
                                                    0}
                                            </td>

                                            <td className="px-5 py-4">
                                                <span
                                                    className={[
                                                        'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                        statusClasses[
                                                            release.status
                                                        ] ??
                                                            'bg-slate-100 text-slate-700',
                                                    ].join(' ')}
                                                >
                                                    {release.status.replaceAll(
                                                        '_',
                                                        ' '
                                                    )}
                                                </span>
                                            </td>

                                            <td className="px-5 py-4 text-sm text-slate-600">
                                                {release.submitted_at ||
                                                    release.updated_at}
                                            </td>

                                            <td className="px-5 py-4">
                                                <Link
                                                    href={`/v2/admin/release-reviews/${release.id}`}
                                                    className="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                                                >
                                                    Review
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No releases found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}
JSX

cat > resources/js/Pages/V2/Admin/ReleaseReviews/Show.jsx <<'JSX'
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'admin',
    release,
    submissionChecklist = {},
    availableActions = {},
    statusLogs = [],
}) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    const action = (endpoint, payload = {}) => {
        setProcessing(true);

        router.post(endpoint, payload, {
            preserveScroll: true,
            onFinish: () =>
                setProcessing(false),
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Review Release"
            subtitle={release.title}
        >
            <Head title={`Review ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/release-reviews"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Back to Queue
                    </Link>

                    <span className="rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold capitalize text-amber-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Section title="Release Information">
                            <InfoGrid
                                rows={[
                                    ['Title', release.title],
                                    [
                                        'Primary Artist',
                                        release.primary_artist_name,
                                    ],
                                    ['Label', release.label_name],
                                    ['UPC', release.upc || 'Pending'],
                                    [
                                        'Catalogue Number',
                                        release.catalog_number,
                                    ],
                                    [
                                        'Release Date',
                                        release.digital_release_date,
                                    ],
                                    ['Language', release.language],
                                    [
                                        'Genre',
                                        release.primary_genre,
                                    ],
                                ]}
                            />
                        </Section>

                        <Section title="Tracks">
                            <div className="space-y-3">
                                {(release.tracks ?? []).map(
                                    (track, index) => (
                                        <div
                                            key={track.id}
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <div className="font-semibold text-slate-900">
                                                        {index + 1}.{' '}
                                                        {track.title}
                                                    </div>

                                                    <div className="mt-1 text-sm text-slate-500">
                                                        ISRC:{' '}
                                                        {track.isrc ||
                                                            'Pending'}
                                                    </div>
                                                </div>

                                                <div className="text-sm text-slate-500">
                                                    {track.audio_path
                                                        ? 'WAV uploaded'
                                                        : 'Audio missing'}
                                                </div>
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        <Section title="Validation Checklist">
                            <pre className="overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">
                                {JSON.stringify(
                                    submissionChecklist,
                                    null,
                                    2
                                )}
                            </pre>
                        </Section>

                        <Section title="Status History">
                            <div className="space-y-3">
                                {statusLogs.length > 0 ? (
                                    statusLogs.map((log) => (
                                        <div
                                            key={log.id}
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="font-semibold capitalize text-slate-900">
                                                {(
                                                    log.new_status ||
                                                    log.action ||
                                                    'Updated'
                                                ).replaceAll(
                                                    '_',
                                                    ' '
                                                )}
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                {log.remarks ||
                                                    log.notes ||
                                                    log.reason ||
                                                    'No remarks'}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-sm text-slate-500">
                                        No status history available.
                                    </div>
                                )}
                            </div>
                        </Section>
                    </div>

                    <aside className="space-y-5">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-slate-900">
                                Review Actions
                            </h3>

                            <textarea
                                value={notes}
                                onChange={(event) =>
                                    setNotes(
                                        event.target.value
                                    )
                                }
                                placeholder="Add remarks, rejection reason or change request..."
                                className="mt-4 min-h-32 w-full rounded-xl border border-slate-300 p-3 text-sm outline-none focus:border-violet-500"
                            />

                            <div className="mt-4 space-y-3">
                                {availableActions.approve && (
                                    <ActionButton
                                        label="Approve Release"
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/approve`,
                                                {
                                                    remarks:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.request_changes && (
                                    <ActionButton
                                        label="Request Changes"
                                        className="bg-amber-500 hover:bg-amber-600"
                                        disabled={
                                            processing ||
                                            notes.trim()
                                                .length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/request-changes`,
                                                {
                                                    notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.reject && (
                                    <ActionButton
                                        label="Reject Release"
                                        className="bg-red-600 hover:bg-red-700"
                                        disabled={
                                            processing ||
                                            notes.trim()
                                                .length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/reject`,
                                                {
                                                    reason:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.start_processing && (
                                    <ActionButton
                                        label="Start Processing"
                                        className="bg-blue-600 hover:bg-blue-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/start-processing`,
                                                {
                                                    remarks:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </PanelLayout>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="mb-4 text-lg font-semibold text-slate-900">
                {title}
            </h2>

            {children}
        </section>
    );
}

function InfoGrid({ rows }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="rounded-xl bg-slate-50 p-4"
                >
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {label}
                    </div>

                    <div className="mt-2 text-sm font-medium text-slate-900">
                        {value || '—'}
                    </div>
                </div>
            ))}
        </div>
    );
}

function ActionButton({
    label,
    className,
    disabled,
    onClick,
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={[
                'w-full rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {label}
        </button>
    );
}
JSX

python3 - <<'PY'
from pathlib import Path

path = Path(
    "app/Http/Controllers/V2/Admin/ReleaseReviewController.php"
)

text = path.read_text()

text = text.replace(
    "use Illuminate\\Http\\JsonResponse;\n",
    "use Illuminate\\Http\\JsonResponse;\nuse Inertia\\Inertia;\nuse Inertia\\Response;\n",
)

text = text.replace(
    "    ): JsonResponse {\n        $reviews->authorizeReviewer(",
    "    ): Response {\n        $reviews->authorizeReviewer(",
    1
)

index_return = """        return response()->json([
            'filters' => $filters,

            'counts' => [
"""

if index_return in text:
    text = text.replace(
        index_return,
        """        $counts = [
""",
        1
    )

    end = """            'releases' =>
                $query
                    ->paginate(25)
                    ->withQueryString(),
        ]);
    }
"""

    replacement = """        ];

        return Inertia::render(
            'V2/Admin/ReleaseReviews/Index',
            [
                'role' => $role,
                'filters' => $filters,
                'counts' => $counts,
                'releases' =>
                    $query
                        ->paginate(25)
                        ->withQueryString(),
            ]
        );
    }
"""

    text = text.replace(
        end,
        replacement,
        1
    )

# Show method return type and response.
show_signature = """    ): JsonResponse {
        $reviews->authorizeReviewer(
"""

if show_signature in text:
    text = text.replace(
        show_signature,
        """    ): Response {
        $reviews->authorizeReviewer(
""",
        1
    )

show_return = """        return response()->json([
            'release' => $release,

            'submission_checklist' =>
                $validator->checklist(
                    $release
                ),

            'available_actions' => [
"""

if show_return in text:
    text = text.replace(
        show_return,
        """        return Inertia::render(
            'V2/Admin/ReleaseReviews/Show',
            [
                'role' =>
                    app(
                        \\App\\Services\\V2\\PermissionService::class
                    )->role(
                        $request->user()
                    ),

                'release' => $release,

                'submissionChecklist' =>
                    $validator->checklist(
                        $release
                    ),

                'availableActions' => [
""",
        1
    )

    text = text.replace(
        """            'status_logs' =>
                $statusLogs,
        ]);
    }
""",
        """                'statusLogs' =>
                    $statusLogs,
            ]
        );
    }
""",
        1
    )

path.write_text(text)
print("ReleaseReviewController UI response patch processed.")
PY

php -l \
app/Http/Controllers/V2/Admin/ReleaseReviewController.php

npm run build
php artisan optimize:clear

php artisan route:list | grep \
"v2/admin/release-reviews"

printf '{\n  "module": "AdminReviewUI",\n  "installed": true,\n  "version": "3.6.0",\n  "installed_at": "%s"\n}\n' \
"$(date --iso-8601=seconds)" \
> v2/runtime/state/admin-review-ui-installed.json

echo ""
echo "=============================================="
echo "ADMIN REVIEW UI INSTALLED"
echo "=============================================="

cat \
v2/runtime/state/admin-review-ui-installed.json

echo ""
echo "Backup:"
echo "$BACKUP"
