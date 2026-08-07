import {
    Activity,
    AlertTriangle,
    Building2,
    Disc3,
    FileUp,
    Headphones,
    Landmark,
    Music2,
    Radio,
    Users,
} from 'lucide-react';
import DashboardPanel from '@/V2/Shared/Dashboard/Widgets/DashboardPanel';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

export default function SuperAdminExecutiveSection({
    stats = {},
}) {
    const platformStats = [
        {
            label: 'Total Artists',
            value: stats.artists,
            icon: Users,
        },
        {
            label: 'Total Labels',
            value: stats.labels,
            icon: Building2,
        },
        {
            label: 'Total Releases',
            value: stats.total_releases,
            icon: Disc3,
        },
        {
            label: 'Total Tracks',
            value: stats.tracks,
            icon: Music2,
        },
        {
            label: 'Live Releases',
            value: stats.live,
            icon: Radio,
        },
        {
            label: 'Report Imports',
            value: stats.report_imports,
            icon: FileUp,
        },
    ];

    const operationalHealth = [
        {
            label: 'Failed Deliveries',
            value: stats.failed_deliveries,
            icon: AlertTriangle,
        },
        {
            label: 'Processing Deliveries',
            value: stats.processing_deliveries,
            icon: Activity,
        },
        {
            label: 'Pending Withdrawals',
            value: stats.pending_withdrawals,
            icon: Landmark,
        },
        {
            label: 'Open Support Tickets',
            value: stats.open_tickets,
            icon: Headphones,
        },
    ];

    return (
        <section className="mt-6 grid gap-6 xl:grid-cols-2">
            <DashboardPanel
                title="Platform Overview"
                subtitle="Global catalogue and account footprint."
            >
                <div className="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                    {platformStats.map(
                        ({
                            label,
                            value,
                            icon: Icon,
                        }) => (
                            <div
                                key={label}
                                className="rounded-2xl bg-slate-50 p-5"
                            >
                                <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-white text-violet-700 shadow-sm">
                                    <Icon size={20} />
                                </div>

                                <p className="mt-4 text-2xl font-black text-slate-950">
                                    {number(value)}
                                </p>

                                <p className="mt-1 text-xs font-semibold text-slate-500">
                                    {label}
                                </p>
                            </div>
                        )
                    )}
                </div>
            </DashboardPanel>

            <DashboardPanel
                title="Executive Operations"
                subtitle="High-priority platform health indicators."
            >
                <div className="grid gap-4 p-6 sm:grid-cols-2">
                    {operationalHealth.map(
                        ({
                            label,
                            value,
                            icon: Icon,
                        }) => (
                            <div
                                key={label}
                                className="rounded-2xl border border-slate-200 p-5"
                            >
                                <div className="flex items-center justify-between gap-4">
                                    <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                                        <Icon size={20} />
                                    </div>

                                    <span className="text-2xl font-black text-slate-950">
                                        {number(value)}
                                    </span>
                                </div>

                                <p className="mt-4 text-sm font-bold text-slate-700">
                                    {label}
                                </p>
                            </div>
                        )
                    )}
                </div>
            </DashboardPanel>
        </section>
    );
}
