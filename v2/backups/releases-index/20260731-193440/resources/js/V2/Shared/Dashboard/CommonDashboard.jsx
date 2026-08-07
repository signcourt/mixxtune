import { Head, Link } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import DashboardStatCard from '@/V2/Shared/Components/DashboardStatCard';
import PageHeader from '@/V2/Shared/Components/PageHeader';
import StatusBadge from '@/V2/Shared/Components/StatusBadge';
import EmptyState from '@/V2/Shared/Components/EmptyState';

export default function CommonDashboard({
    role = 'artist',
    panelName = 'Artist',
    stats = {},
    recentReleases = [],
    quickActions = [],
}) {
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
                            href="/releases"
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
