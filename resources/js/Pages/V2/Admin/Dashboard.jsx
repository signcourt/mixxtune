import {
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const statusClasses = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-100 text-amber-700',
    approved: 'bg-emerald-100 text-emerald-700',
    processing: 'bg-blue-100 text-blue-700',
    delivered: 'bg-indigo-100 text-indigo-700',
    live: 'bg-green-100 text-green-700',
    rejected: 'bg-red-100 text-red-700',
    failed: 'bg-red-100 text-red-700',
    pending: 'bg-amber-100 text-amber-700',
    completed: 'bg-emerald-100 text-emerald-700',
};

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

const money = (value) =>
    `₹${Number(value ?? 0).toLocaleString(
        'en-IN',
        {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }
    )}`;

export default function Dashboard({
    role = 'admin',
    stats = {},
    analytics = {},
    recentReleases = [],
    recentImports = [],
    recentWithdrawals = [],
}) {
    const isSuperAdmin =
        role === 'super_admin';

    const primaryCards = [
        {
            label: 'Revenue',
            value: money(stats.earnings),
            helper: 'Mapped DSP earnings',
        },
        {
            label: 'Streams',
            value: number(stats.streams),
            helper: 'Reported streams',
        },
        {
            label: 'Artists',
            value: number(stats.artists),
            helper: 'Accessible artists',
        },
        {
            label: 'Labels',
            value: number(stats.labels),
            helper: 'Accessible labels',
        },
        {
            label: 'Releases',
            value: number(
                stats.total_releases
            ),
            helper: 'Catalogue releases',
        },
        {
            label: 'Tracks',
            value: number(stats.tracks),
            helper: 'Catalogue tracks',
        },
    ];

    const workflowCards = [
        {
            label: 'In Review',
            value: stats.submitted ?? 0,
        },
        {
            label: 'Processing',
            value: stats.processing ?? 0,
        },
        {
            label: 'Live',
            value: stats.live ?? 0,
        },
        {
            label: 'Failed Delivery',
            value:
                stats.failed_deliveries ?? 0,
        },
        {
            label: 'Withdrawals',
            value:
                stats.pending_withdrawals ?? 0,
        },
        {
            label: 'Support',
            value: stats.open_tickets ?? 0,
        },
    ];

    return (
        <PanelLayout
            role={role}
            title={
                isSuperAdmin
                    ? 'Super Admin Control Center'
                    : 'Admin Dashboard'
            }
            subtitle={
                isSuperAdmin
                    ? 'Platform-wide catalogue, revenue and operations'
                    : 'Assigned catalogue, revenue and operations'
            }
        >
            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h2 className="text-2xl font-black text-slate-950">
                            Executive Overview
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Revenue, catalogue and
                            operational health in one
                            place.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        <Link
                            href="/admin/release-reviews"
                            className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-bold text-slate-700 shadow-sm hover:bg-slate-50"
                        >
                            Review Queue
                        </Link>

                        <Link
                            href="/admin/reports/imports"
                            className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-bold text-white hover:bg-violet-700"
                        >
                            Import Revenue
                        </Link>
                    </div>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
                    {primaryCards.map(
                        (card) => (
                            <MetricCard
                                key={card.label}
                                {...card}
                            />
                        )
                    )}
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                    {workflowCards.map(
                        (card) => (
                            <SmallMetric
                                key={card.label}
                                {...card}
                            />
                        )
                    )}
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <AnalyticsPanel
                        title="Monthly Performance"
                        subtitle="Streams and earnings trend"
                    >
                        {(analytics.monthly ?? [])
                            .length > 0 ? (
                            analytics.monthly.map(
                                (item) => (
                                    <AnalyticsRow
                                        key={
                                            item.month
                                        }
                                        label={
                                            item.month
                                        }
                                        primary={`${number(
                                            item.streams
                                        )} streams`}
                                        secondary={money(
                                            item.earnings
                                        )}
                                    />
                                )
                            )
                        ) : (
                            <EmptyAnalytics />
                        )}
                    </AnalyticsPanel>

                    <AnalyticsPanel
                        title="Top Platforms"
                        subtitle="DSP performance"
                    >
                        {(
                            analytics.topPlatforms ??
                            []
                        ).length > 0 ? (
                            analytics.topPlatforms.map(
                                (item) => (
                                    <AnalyticsRow
                                        key={
                                            item.name
                                        }
                                        label={
                                            item.name
                                        }
                                        primary={`${number(
                                            item.streams
                                        )} streams`}
                                        secondary={money(
                                            item.earnings
                                        )}
                                    />
                                )
                            )
                        ) : (
                            <EmptyAnalytics />
                        )}
                    </AnalyticsPanel>

                    <AnalyticsPanel
                        title="Top Tracks"
                        subtitle="Highest performing catalogue"
                    >
                        {(
                            analytics.topTracks ??
                            []
                        ).length > 0 ? (
                            analytics.topTracks.map(
                                (item) => (
                                    <AnalyticsRow
                                        key={`${item.track_id}-${item.isrc}`}
                                        label={
                                            item.title
                                        }
                                        sublabel={
                                            item.artist ||
                                            item.isrc ||
                                            ''
                                        }
                                        primary={`${number(
                                            item.streams
                                        )} streams`}
                                        secondary={money(
                                            item.earnings
                                        )}
                                    />
                                )
                            )
                        ) : (
                            <EmptyAnalytics />
                        )}
                    </AnalyticsPanel>

                    <AnalyticsPanel
                        title="Top Countries"
                        subtitle="Territory performance"
                    >
                        {(
                            analytics.topCountries ??
                            []
                        ).length > 0 ? (
                            analytics.topCountries.map(
                                (item) => (
                                    <AnalyticsRow
                                        key={
                                            item.name
                                        }
                                        label={
                                            item.name
                                        }
                                        primary={`${number(
                                            item.streams
                                        )} streams`}
                                        secondary={money(
                                            item.earnings
                                        )}
                                    />
                                )
                            )
                        ) : (
                            <EmptyAnalytics />
                        )}
                    </AnalyticsPanel>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <TableCard
                        title="Recent Releases"
                        href="/admin/release-reviews"
                    >
                        {recentReleases.length >
                        0 ? (
                            recentReleases.map(
                                (release) => (
                                    <ActivityRow
                                        key={
                                            release.id
                                        }
                                        title={
                                            release.title
                                        }
                                        subtitle={
                                            release.primary_artist_name ||
                                            release.upc ||
                                            'Release'
                                        }
                                        status={
                                            release.status
                                        }
                                        href={`/v2/admin/release-reviews/${release.id}`}
                                    />
                                )
                            )
                        ) : (
                            <EmptyRow text="No releases yet." />
                        )}
                    </TableCard>

                    <TableCard
                        title="Revenue Imports"
                        href="/admin/reports/imports"
                    >
                        {recentImports.length >
                        0 ? (
                            recentImports.map(
                                (item) => (
                                    <ActivityRow
                                        key={item.id}
                                        title={
                                            item.original_filename
                                        }
                                        subtitle={`${number(
                                            item.imported_rows
                                        )} imported / ${number(
                                            item.total_rows
                                        )} rows`}
                                        status={
                                            item.status
                                        }
                                    />
                                )
                            )
                        ) : (
                            <EmptyRow text="No revenue imports yet." />
                        )}
                    </TableCard>

                    <TableCard
                        title="Recent Withdrawals"
                        href="/admin/withdrawals"
                    >
                        {recentWithdrawals.length >
                        0 ? (
                            recentWithdrawals.map(
                                (item) => (
                                    <ActivityRow
                                        key={item.id}
                                        title={
                                            item.withdrawal_number
                                        }
                                        subtitle={`${item.currency ?? 'INR'} ${number(
                                            item.amount
                                        )}`}
                                        status={
                                            item.status
                                        }
                                    />
                                )
                            )
                        ) : (
                            <EmptyRow text="No withdrawal requests." />
                        )}
                    </TableCard>

                    <TableCard
                        title="Operations Health"
                    >
                        <ActivityRow
                            title="Processing Deliveries"
                            subtitle="DSP delivery queue"
                            status={`${stats.processing_deliveries ?? 0}`}
                        />

                        <ActivityRow
                            title="Live Deliveries"
                            subtitle="Successfully live"
                            status={`${stats.live_deliveries ?? 0}`}
                        />

                        <ActivityRow
                            title="Failed Deliveries"
                            subtitle="Requires attention"
                            status={`${stats.failed_deliveries ?? 0}`}
                        />

                        <ActivityRow
                            title="Open Support Tickets"
                            subtitle="Customer support queue"
                            status={`${stats.open_tickets ?? 0}`}
                        />
                    </TableCard>
                </div>
            </div>
        </PanelLayout>
    );
}

function MetricCard({
    label,
    value,
    helper,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-3 text-2xl font-black text-slate-950">
                {value}
            </div>

            <div className="mt-1 text-xs text-slate-500">
                {helper}
            </div>
        </div>
    );
}

function SmallMetric({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4">
            <div className="text-xs font-semibold text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-black text-slate-900">
                {number(value)}
            </div>
        </div>
    );
}

function AnalyticsPanel({
    title,
    subtitle,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h3 className="font-bold text-slate-900">
                {title}
            </h3>

            <p className="mt-1 text-xs text-slate-500">
                {subtitle}
            </p>

            <div className="mt-4 divide-y divide-slate-100">
                {children}
            </div>
        </section>
    );
}

function AnalyticsRow({
    label,
    sublabel = '',
    primary,
    secondary,
}) {
    return (
        <div className="flex items-center justify-between gap-4 py-3">
            <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-slate-900">
                    {label || '—'}
                </div>

                {sublabel && (
                    <div className="truncate text-xs text-slate-500">
                        {sublabel}
                    </div>
                )}
            </div>

            <div className="shrink-0 text-right">
                <div className="text-sm font-semibold text-slate-900">
                    {primary}
                </div>

                <div className="text-xs font-semibold text-emerald-600">
                    {secondary}
                </div>
            </div>
        </div>
    );
}

function EmptyAnalytics() {
    return (
        <div className="py-10 text-center text-sm text-slate-500">
            Analytics will appear after revenue
            reports are imported.
        </div>
    );
}

function TableCard({
    title,
    href = null,
    children,
}) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 className="font-bold text-slate-900">
                    {title}
                </h3>

                {href && (
                    <Link
                        href={href}
                        className="text-sm font-bold text-violet-600"
                    >
                        View all
                    </Link>
                )}
            </div>

            <div className="divide-y divide-slate-100 px-5">
                {children}
            </div>
        </section>
    );
}

function ActivityRow({
    title,
    subtitle,
    status,
    href = null,
}) {
    const content = (
        <div className="flex items-center justify-between gap-4 py-4">
            <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-slate-900">
                    {title}
                </div>

                <div className="mt-1 truncate text-xs text-slate-500">
                    {subtitle}
                </div>
            </div>

            <span
                className={`shrink-0 rounded-full px-3 py-1 text-xs font-bold capitalize ${
                    statusClasses[status] ??
                    'bg-slate-100 text-slate-700'
                }`}
            >
                {status ?? '—'}
            </span>
        </div>
    );

    return href ? (
        <Link href={href}>
            {content}
        </Link>
    ) : (
        content
    );
}

function EmptyRow({ text }) {
    return (
        <div className="py-10 text-center text-sm text-slate-500">
            {text}
        </div>
    );
}
