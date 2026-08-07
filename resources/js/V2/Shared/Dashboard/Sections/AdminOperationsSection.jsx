import { Link } from '@inertiajs/react';
import {
    Activity,
    AlertTriangle,
    ArrowRight,
    FileUp,
    Headphones,
    Landmark,
    PackageCheck,
} from 'lucide-react';
import DashboardPanel from '@/V2/Shared/Dashboard/Widgets/DashboardPanel';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

function StatusBadge({
    status = 'pending',
}) {
    const styles = {
        completed: 'bg-emerald-100 text-emerald-700',
        imported: 'bg-emerald-100 text-emerald-700',
        paid: 'bg-emerald-100 text-emerald-700',
        live: 'bg-green-100 text-green-700',
        pending: 'bg-amber-100 text-amber-700',
        processing: 'bg-blue-100 text-blue-700',
        submitted: 'bg-violet-100 text-violet-700',
        failed: 'bg-rose-100 text-rose-700',
        rejected: 'bg-red-100 text-red-700',
        approved: 'bg-emerald-100 text-emerald-700',
    };

    const normalized =
        String(status || 'pending').toLowerCase();

    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-bold capitalize ${
                styles[normalized] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {normalized.replaceAll('_', ' ')}
        </span>
    );
}

function ActivityRow({
    title,
    subtitle,
    status,
    href = null,
    amount = null,
    currency = 'INR',
}) {
    const content = (
        <div className="flex items-center justify-between gap-4 py-4">
            <div className="min-w-0">
                <p className="truncate font-bold text-slate-900">
                    {title || '—'}
                </p>

                {subtitle && (
                    <p className="mt-1 truncate text-xs text-slate-500">
                        {subtitle}
                    </p>
                )}

                {amount !== null && (
                    <p className="mt-1 text-sm font-black text-slate-950">
                        {money(amount, currency)}
                    </p>
                )}
            </div>

            <StatusBadge
                status={status}
            />
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

function EmptyRow({
    text,
}) {
    return (
        <div className="py-10 text-center text-sm text-slate-500">
            {text}
        </div>
    );
}

export default function AdminOperationsSection({
    stats = {},
    recentReleases = [],
    recentImports = [],
    recentWithdrawals = [],
}) {
    return (
        <>
            <section className="mt-6 grid gap-6 xl:grid-cols-2">
                <DashboardPanel
                    title="Recent Releases"
                    subtitle="Latest release activity in your scope."
                    action={
                        <Link
                            href="/admin/release-reviews"
                            className="inline-flex items-center gap-2 text-sm font-bold text-violet-700"
                        >
                            Review Queue
                            <ArrowRight size={16} />
                        </Link>
                    }
                >
                    <div className="divide-y divide-slate-100 px-6">
                        {recentReleases.length > 0 ? (
                            recentReleases.map((release) => (
                                <ActivityRow
                                    key={release.id}
                                    title={
                                        release.title ||
                                        'Untitled Release'
                                    }
                                    subtitle={
                                        release.primary_artist_name ||
                                        release.upc ||
                                        'Release'
                                    }
                                    status={
                                        release.status ||
                                        'draft'
                                    }
                                    href="/admin/release-reviews"
                                />
                            ))
                        ) : (
                            <EmptyRow text="No recent releases." />
                        )}
                    </div>
                </DashboardPanel>

                <DashboardPanel
                    title="Revenue Imports"
                    subtitle="Latest DSP report import activity."
                    action={
                        <Link
                            href="/admin/reports/imports"
                            className="inline-flex items-center gap-2 text-sm font-bold text-violet-700"
                        >
                            Import Revenue
                            <ArrowRight size={16} />
                        </Link>
                    }
                >
                    <div className="divide-y divide-slate-100 px-6">
                        {recentImports.length > 0 ? (
                            recentImports.map((item) => (
                                <ActivityRow
                                    key={item.id}
                                    title={
                                        item.original_filename ||
                                        'Revenue Import'
                                    }
                                    subtitle={`${Number(
                                        item.imported_rows || 0
                                    ).toLocaleString()} imported • ${Number(
                                        item.failed_rows || 0
                                    ).toLocaleString()} failed`}
                                    status={
                                        item.status ||
                                        'pending'
                                    }
                                    href="/admin/reports/imports"
                                />
                            ))
                        ) : (
                            <EmptyRow text="No report imports yet." />
                        )}
                    </div>
                </DashboardPanel>
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-2">
                <DashboardPanel
                    title="Recent Withdrawals"
                    subtitle="Latest artist and label payout requests."
                    action={
                        <Link
                            href="/admin/withdrawals"
                            className="inline-flex items-center gap-2 text-sm font-bold text-violet-700"
                        >
                            View Withdrawals
                            <ArrowRight size={16} />
                        </Link>
                    }
                >
                    <div className="divide-y divide-slate-100 px-6">
                        {recentWithdrawals.length > 0 ? (
                            recentWithdrawals.map((item) => (
                                <ActivityRow
                                    key={item.id}
                                    title={
                                        item.withdrawal_number ||
                                        `Withdrawal #${item.id}`
                                    }
                                    subtitle={
                                        item.requested_at ||
                                        'Payout request'
                                    }
                                    amount={
                                        item.amount
                                    }
                                    currency={
                                        item.currency ||
                                        'INR'
                                    }
                                    status={
                                        item.status ||
                                        'pending'
                                    }
                                    href="/admin/withdrawals"
                                />
                            ))
                        ) : (
                            <EmptyRow text="No withdrawal requests." />
                        )}
                    </div>
                </DashboardPanel>

                <DashboardPanel
                    title="Operations Health"
                    subtitle="Important workflow indicators."
                >
                    <div className="grid gap-4 p-6 sm:grid-cols-2">
                        {[
                            {
                                label: 'In Review',
                                value: stats.submitted || 0,
                                icon: PackageCheck,
                            },
                            {
                                label: 'Failed Deliveries',
                                value:
                                    stats.failed_deliveries ||
                                    0,
                                icon: AlertTriangle,
                            },
                            {
                                label: 'Pending Withdrawals',
                                value:
                                    stats.pending_withdrawals ||
                                    0,
                                icon: Landmark,
                            },
                            {
                                label: 'Open Support Tickets',
                                value:
                                    stats.open_tickets ||
                                    0,
                                icon: Headphones,
                            },
                            {
                                label: 'Report Imports',
                                value:
                                    stats.report_imports ||
                                    0,
                                icon: FileUp,
                            },
                            {
                                label: 'Processing Deliveries',
                                value:
                                    stats.processing_deliveries ||
                                    0,
                                icon: Activity,
                            },
                        ].map(
                            ({
                                label,
                                value,
                                icon: Icon,
                            }) => (
                                <div
                                    key={label}
                                    className="rounded-2xl bg-slate-50 p-4"
                                >
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-white text-violet-700 shadow-sm">
                                        <Icon size={18} />
                                    </div>

                                    <p className="mt-4 text-2xl font-black text-slate-950">
                                        {Number(
                                            value
                                        ).toLocaleString()}
                                    </p>

                                    <p className="mt-1 text-xs font-semibold text-slate-500">
                                        {label}
                                    </p>
                                </div>
                            )
                        )}
                    </div>
                </DashboardPanel>
            </section>
        </>
    );
}
