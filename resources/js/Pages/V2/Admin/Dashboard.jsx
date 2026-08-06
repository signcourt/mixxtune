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
