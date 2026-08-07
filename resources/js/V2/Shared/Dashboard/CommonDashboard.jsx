import { Head, Link } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import DashboardStatCard from '@/V2/Shared/Components/DashboardStatCard';
import PageHeader from '@/V2/Shared/Components/PageHeader';
import StatusBadge from '@/V2/Shared/Components/StatusBadge';
import EmptyState from '@/V2/Shared/Components/EmptyState';
import { getPanelRoutes } from '@/V2/Shared/Config/panelRoutes';

export default function CommonDashboard({
    role = 'artist',
    panelName = 'Artist',
    stats = {},
    analytics = {},
    recentReleases = [],
    quickActions = [],
}) {
    const routes = getPanelRoutes(role);

    return (
        <PanelLayout
            role={role}
            title={`${panelName} Dashboard`}
            subtitle="Shared V2 dashboard architecture"
        >
            <Head title={`${panelName} Dashboard`} />

            <div className="space-y-6">
                <PageHeader
                    title={`Welcome to ${panelName} Dashboard`}
                    subtitle="Common dashboard UI with role-based data and actions."
                    actions={
                        quickActions.length > 0 ? (
                            <div className="flex flex-wrap gap-2">
                                {quickActions.map((action) => (
                                    <Link
                                        key={action.href}
                                        href={action.href}
                                        className="rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                                    >
                                        {action.label}
                                    </Link>
                                ))}
                            </div>
                        ) : null
                    }
                />

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <DashboardStatCard
                        label="Total Releases"
                        value={stats.totalReleases ?? 0}
                        helper="All releases visible to this account"
                    />

                    <DashboardStatCard
                        label="Submitted"
                        value={stats.submittedReleases ?? 0}
                        helper="Waiting for review"
                    />

                    <DashboardStatCard
                        label="Approved"
                        value={stats.approvedReleases ?? 0}
                        helper="Approved catalogue"
                    />

                    <DashboardStatCard
                        label="Wallet Balance"
                        value={stats.walletBalance ?? '₹0.00'}
                        helper="Current available balance"
                    />
                </section>


                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Performance Analytics
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Streams, earnings and DSP performance from mapped reports.
                            </p>
                        </div>

                        <Link
                            href={routes.reports}
                            className="text-sm font-semibold text-violet-600 hover:text-violet-700"
                        >
                            Open reports
                        </Link>
                    </div>

                    <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <DashboardStatCard
                            label="Total Streams"
                            value={Number(
                                analytics?.summary?.streams ?? 0
                            ).toLocaleString()}
                            helper="Mapped DSP streams"
                        />

                        <DashboardStatCard
                            label="Report Earnings"
                            value={`₹${Number(
                                analytics?.summary?.earnings ?? 0
                            ).toLocaleString(
                                'en-IN',
                                {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2,
                                }
                            )}`}
                            helper="Imported report earnings"
                        />

                        <DashboardStatCard
                            label="Report Rows"
                            value={Number(
                                analytics?.summary?.rows ?? 0
                            ).toLocaleString()}
                            helper="Processed report rows"
                        />

                        <DashboardStatCard
                            label={
                                role === 'label'
                                    ? 'Active Artists'
                                    : 'Sale Units'
                            }
                            value={
                                role === 'label'
                                    ? Number(
                                          stats.activeArtists ?? 0
                                      ).toLocaleString()
                                    : Number(
                                          analytics?.summary?.sale_units ?? 0
                                      ).toLocaleString()
                            }
                            helper={
                                role === 'label'
                                    ? 'Artists under this label'
                                    : 'Reported sale units'
                            }
                        />
                    </div>

                    {!analytics?.hasData ? (
                        <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center">
                            <div className="font-semibold text-slate-900">
                                No analytics available yet
                            </div>

                            <p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                                Analytics will appear automatically when mapped DSP reports are imported.
                            </p>

                            {(role === 'admin' ||
                                role === 'super_admin') && (
                                <Link
                                    href="/admin/reports/imports"
                                    className="mt-4 inline-flex rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                                >
                                    Import revenue report
                                </Link>
                            )}
                        </div>
                    ) : (
                        <div className="mt-6 grid gap-6 xl:grid-cols-2">
                            <AnalyticsPanel
                                title="Monthly Performance"
                            >
                                {(analytics?.monthly ?? []).map(
                                    (item) => (
                                        <AnalyticsRow
                                            key={item.month}
                                            label={item.month}
                                            streams={item.streams}
                                            earnings={item.earnings}
                                        />
                                    )
                                )}
                            </AnalyticsPanel>

                            <AnalyticsPanel
                                title="Top Platforms"
                            >
                                {(analytics?.topPlatforms ?? []).map(
                                    (item) => (
                                        <AnalyticsRow
                                            key={item.name}
                                            label={item.name}
                                            streams={item.streams}
                                            earnings={item.earnings}
                                        />
                                    )
                                )}
                            </AnalyticsPanel>

                            <AnalyticsPanel
                                title="Top Tracks"
                            >
                                {(analytics?.topTracks ?? []).map(
                                    (item) => (
                                        <AnalyticsRow
                                            key={`${item.track_id}-${item.isrc}`}
                                            label={item.title}
                                            sublabel={[
                                                item.artist,
                                                item.isrc,
                                            ]
                                                .filter(Boolean)
                                                .join(' • ')}
                                            streams={item.streams}
                                            earnings={item.earnings}
                                        />
                                    )
                                )}
                            </AnalyticsPanel>

                            <AnalyticsPanel
                                title="Top Countries"
                            >
                                {(analytics?.topCountries ?? []).map(
                                    (item) => (
                                        <AnalyticsRow
                                            key={item.name}
                                            label={item.name}
                                            streams={item.streams}
                                            earnings={item.earnings}
                                        />
                                    )
                                )}
                            </AnalyticsPanel>
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Recent Releases
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Latest release activity for this panel.
                            </p>
                        </div>

                        <Link
                            href={routes.releasesAll || routes.releases}
                            className="text-sm font-semibold text-violet-600 hover:text-violet-700"
                        >
                            View all
                        </Link>
                    </div>

                    {recentReleases.length === 0 ? (
                        <EmptyState
                            title="No releases found"
                            description="Recent releases will appear here once they are created."
                        />
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-200">
                                <thead>
                                    <tr className="text-left text-xs uppercase tracking-wide text-slate-400">
                                        <th className="px-3 py-3">Title</th>
                                        <th className="px-3 py-3">Artist</th>
                                        <th className="px-3 py-3">Status</th>
                                        <th className="px-3 py-3">Date</th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {recentReleases.map((release) => (
                                        <tr key={release.id}>
                                            <td className="px-3 py-4 font-semibold text-slate-900">
                                                {release.title}
                                            </td>

                                            <td className="px-3 py-4 text-sm text-slate-600">
                                                {release.primary_artist_name || '—'}
                                            </td>

                                            <td className="px-3 py-4">
                                                <StatusBadge status={release.status} />
                                            </td>

                                            <td className="px-3 py-4 text-sm text-slate-500">
                                                {release.created_at || '—'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}


function AnalyticsPanel({
    title,
    children,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 p-5">
            <h3 className="font-semibold text-slate-900">
                {title}
            </h3>

            <div className="mt-4 divide-y divide-slate-100">
                {children}
            </div>
        </div>
    );
}

function AnalyticsRow({
    label,
    sublabel = '',
    streams = 0,
    earnings = 0,
}) {
    return (
        <div className="flex items-center justify-between gap-4 py-3">
            <div className="min-w-0">
                <div className="truncate text-sm font-semibold text-slate-900">
                    {label || '—'}
                </div>

                {sublabel && (
                    <div className="mt-0.5 truncate text-xs text-slate-500">
                        {sublabel}
                    </div>
                )}
            </div>

            <div className="shrink-0 text-right">
                <div className="text-sm font-semibold text-slate-900">
                    {Number(
                        streams ?? 0
                    ).toLocaleString()}
                </div>

                <div className="text-xs font-medium text-emerald-600">
                    ₹
                    {Number(
                        earnings ?? 0
                    ).toLocaleString(
                        'en-IN',
                        {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2,
                        }
                    )}
                </div>
            </div>
        </div>
    );
}
