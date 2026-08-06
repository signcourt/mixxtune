import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    processing:
        'bg-blue-100 text-blue-700',

    delivered:
        'bg-indigo-100 text-indigo-700',

    live:
        'bg-emerald-100 text-emerald-700',

    failed:
        'bg-red-100 text-red-700',

    takedown_requested:
        'bg-amber-100 text-amber-700',
};

export default function Index({
    role = 'admin',
    releases = {},
    counts = {},
    filters = {},
}) {
    const rows =
        releases.data ?? [];

    const openStatus = (
        status
    ) => {
        router.get(
            '/v2/admin/distribution',
            {
                status,
                search:
                    filters.search ?? '',
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
            title="DSP Distribution"
            subtitle="Manage store delivery and live status"
        >
            <Head title="DSP Distribution" />

            <div className="space-y-5">
                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                    {[
                        [
                            'processing',
                            'Processing',
                        ],
                        [
                            'delivered',
                            'Delivered',
                        ],
                        [
                            'live',
                            'Live',
                        ],
                        [
                            'failed',
                            'Failed',
                        ],
                        [
                            'takedown_requested',
                            'Takedown',
                        ],
                    ].map(
                        ([status, label]) => (
                            <button
                                key={status}
                                type="button"
                                onClick={() =>
                                    openStatus(
                                        status
                                    )
                                }
                                className={[
                                    'rounded-2xl border bg-white p-5 text-left shadow-sm transition',
                                    filters.status ===
                                    status
                                        ? 'border-violet-500 ring-2 ring-violet-100'
                                        : 'border-slate-200 hover:border-violet-300',
                                ].join(' ')}
                            >
                                <div className="text-sm text-slate-500">
                                    {label}
                                </div>

                                <div className="mt-2 text-3xl font-bold text-slate-900">
                                    {counts[
                                        status
                                    ] ?? 0}
                                </div>
                            </button>
                        )
                    )}
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-3 border-b border-slate-200 p-5 lg:flex-row lg:items-center lg:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Distribution Queue
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Open a release to update
                                individual DSP statuses.
                            </p>
                        </div>

                        <input
                            type="search"
                            defaultValue={
                                filters.search ??
                                ''
                            }
                            placeholder="Search release, artist, UPC..."
                            onKeyDown={(
                                event
                            ) => {
                                if (
                                    event.key ===
                                    'Enter'
                                ) {
                                    router.get(
                                        '/v2/admin/distribution',
                                        {
                                            ...filters,
                                            search:
                                                event
                                                    .currentTarget
                                                    .value,
                                        },
                                        {
                                            preserveState:
                                                true,
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
                                        'Action',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                            >
                                                {
                                                    heading
                                                }
                                            </th>
                                        )
                                    )}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {rows.length >
                                0 ? (
                                    rows.map(
                                        (
                                            release
                                        ) => (
                                            <tr
                                                key={
                                                    release.id
                                                }
                                            >
                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            release.title
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
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
                                                                release
                                                                    .status
                                                            ] ??
                                                                'bg-slate-100 text-slate-700',
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {release.status.replaceAll(
                                                            '_',
                                                            ' '
                                                        )}
                                                    </span>
                                                </td>

                                                <td className="px-5 py-4">
                                                    <Link
                                                        href={`/v2/admin/distribution/${release.id}`}
                                                        className="rounded-lg border border-violet-300 px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                                                    >
                                                        Manage DSPs
                                                    </Link>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No distribution
                                            releases found.
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
