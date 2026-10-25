import { Head, Link } from '@inertiajs/react';
import {
    AlertTriangle,
    ArrowRight,
    BadgeIndianRupee,
    CheckCircle2,
    ClipboardCheck,
    Disc3,
    FileQuestion,
    FileUp,
    FolderTree,
    Headphones,
    History,
    IdCard,
    Landmark,
    Library,
    Music2,
    Radio,
    RotateCcw,
    Send,
    ShieldCheck,
    TrendingDown,
    TrendingUp,
    UserX,
    Users,
    WalletCards,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import AgreementAlert from '@/V2/Shared/Components/AgreementAlert';

/*
 * W69 MIXXTUNE_DASHBOARD_V2
 * One clear page: what needs action now -> money -> catalogue pipeline ->
 * revenue trend -> latest activity -> quick links. Every number is a link
 * to the page where it can be worked on.
 */

const number = (value) => Number(value ?? 0).toLocaleString('en-IN');

const plural = (value, word, many = `${word}s`) => `${number(value)} ${Number(value) === 1 ? word : many}`;

const money = (value, currency = 'INR', digits = 2) => {
    try {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency: currency || 'INR',
            maximumFractionDigits: digits,
        }).format(Number(value ?? 0));
    } catch {
        return `₹${number(value)}`;
    }
};

const shortMoney = (value) => {
    const n = Number(value ?? 0);
    const abs = Math.abs(n);
    if (abs >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
    if (abs >= 1e5) return `₹${(n / 1e5).toFixed(2)} L`;
    if (abs >= 1e3) return `₹${(n / 1e3).toFixed(1)}K`;
    return `₹${n.toFixed(0)}`;
};

const monthLabel = (value) => {
    if (!value) return '—';
    const date = new Date(`${value}-01T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', { month: 'short', year: '2-digit' }).format(date);
};

const when = (value) => {
    if (!value) return '';
    const date = new Date(String(value).replace(' ', 'T'));
    if (Number.isNaN(date.getTime())) return String(value);
    const days = Math.floor((Date.now() - date.getTime()) / 86400000);
    if (days <= 0) return 'Today';
    if (days === 1) return 'Yesterday';
    if (days < 7) return `${days} days ago`;
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

const STATUS_STYLE = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-amber-100 text-amber-800',
    changes_requested: 'bg-orange-100 text-orange-800',
    approved: 'bg-sky-100 text-sky-800',
    processing: 'bg-blue-100 text-blue-800',
    delivered: 'bg-indigo-100 text-indigo-800',
    live: 'bg-emerald-100 text-emerald-800',
    rejected: 'bg-rose-100 text-rose-700',
    failed: 'bg-rose-100 text-rose-700',
    pending: 'bg-amber-100 text-amber-800',
    completed: 'bg-emerald-100 text-emerald-800',
    imported: 'bg-emerald-100 text-emerald-800',
    paid: 'bg-emerald-100 text-emerald-800',
};

const STATUS_LABEL = {
    submitted: 'In review',
    changes_requested: 'Changes requested',
};

function StatusBadge({ status }) {
    const key = String(status || 'draft').toLowerCase();
    return (
        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-bold ${STATUS_STYLE[key] ?? 'bg-slate-100 text-slate-700'}`}>
            {STATUS_LABEL[key] ?? key.replaceAll('_', ' ').replace(/^\w/, (c) => c.toUpperCase())}
        </span>
    );
}

function Panel({ title, subtitle, action, children, className = '' }) {
    return (
        <section className={`min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm ${className}`}>
            <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                <div className="min-w-0">
                    <h2 className="text-[15px] font-bold text-slate-950">{title}</h2>
                    {subtitle && <p className="mt-0.5 text-xs text-slate-500">{subtitle}</p>}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}

function PanelLink({ href, children }) {
    return (
        <Link href={href} className="inline-flex shrink-0 items-center gap-1 text-xs font-bold text-violet-700 hover:text-violet-900">
            {children}
            <ArrowRight size={14} />
        </Link>
    );
}

function AttentionTile({ item }) {
    const Icon = item.icon;
    const active = Number(item.value || 0) > 0;
    const tone = !active
        ? 'border-slate-200 bg-white'
        : item.danger
          ? 'border-rose-200 bg-rose-50/70 hover:border-rose-300'
          : 'border-amber-200 bg-amber-50/70 hover:border-amber-300';
    const iconTone = !active ? 'bg-slate-100 text-slate-400' : item.danger ? 'bg-rose-100 text-rose-700' : 'bg-amber-100 text-amber-800';

    return (
        <Link href={item.href} className={`group flex items-start gap-3 rounded-xl border p-3.5 transition ${tone}`}>
            <span className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-lg ${iconTone}`}>
                <Icon size={18} />
            </span>
            <span className="min-w-0 flex-1">
                <span className="flex items-baseline gap-2">
                    <span className={`text-xl font-black ${active ? 'text-slate-950' : 'text-slate-400'}`}>{number(item.value)}</span>
                    {!active && <CheckCircle2 size={14} className="text-emerald-500" aria-label="Clear" />}
                </span>
                <span className="block text-[13px] font-semibold leading-tight text-slate-800">{item.label}</span>
                <span className="mt-0.5 block truncate text-[11px] text-slate-500">{active ? item.hint : item.clear}</span>
            </span>
            <ArrowRight size={15} className="mt-1 shrink-0 text-slate-300 transition group-hover:translate-x-0.5 group-hover:text-slate-600" />
        </Link>
    );
}

function Growth({ value, label }) {
    if (value === null || value === undefined) {
        return <span className="text-[11px] text-slate-400">No previous month to compare</span>;
    }
    const n = Number(value);
    const up = n >= 0;
    const Icon = up ? TrendingUp : TrendingDown;
    return (
        <span className={`inline-flex items-center gap-1 text-[11px] font-bold ${up ? 'text-emerald-700' : 'text-rose-700'}`}>
            <Icon size={13} />
            {up ? '+' : ''}
            {n.toFixed(1)}% {label}
        </span>
    );
}

function KpiCard({ title, value, lines = [], icon: Icon, href, tone }) {
    return (
        <Link href={href} className="group rounded-2xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-violet-200 hover:shadow">
            <div className="flex items-start justify-between gap-3">
                <p className="text-[13px] font-semibold text-slate-500">{title}</p>
                <span className={`flex h-9 w-9 items-center justify-center rounded-lg ${tone}`}>
                    <Icon size={18} />
                </span>
            </div>
            <p className="mt-2 text-[26px] font-black leading-tight tracking-tight text-slate-950">{value}</p>
            <div className="mt-2 space-y-0.5">
                {lines.map((line, index) => (
                    <div key={index} className="text-[11px] text-slate-500">
                        {line}
                    </div>
                ))}
            </div>
        </Link>
    );
}

function Pipeline({ stats }) {
    const steps = [
        { key: 'draft', label: 'Draft', href: '/releases?status=draft', color: 'bg-slate-300' },
        { key: 'submitted', label: 'In review', href: '/admin/release-reviews?status=submitted', color: 'bg-amber-400' },
        { key: 'changes_requested', label: 'Changes requested', href: '/admin/release-reviews?status=changes_requested', color: 'bg-orange-400' },
        { key: 'approved', label: 'Approved', href: '/admin/release-reviews?status=approved', color: 'bg-sky-400' },
        { key: 'processing', label: 'Processing', href: '/admin/release-reviews?status=processing', color: 'bg-blue-500' },
        { key: 'delivered', label: 'Delivered', href: '/admin/distribution?status=delivered', color: 'bg-indigo-500' },
        { key: 'live', label: 'Live', href: '/admin/distribution?status=live', color: 'bg-emerald-500' },
        { key: 'rejected', label: 'Rejected', href: '/admin/release-reviews?status=rejected', color: 'bg-rose-400' },
    ];
    const total = Math.max(1, Number(stats.total_releases || 0));

    return (
        <Panel
            title="Release pipeline"
            subtitle={`${plural(stats.total_releases, 'release')} · ${plural(stats.tracks, 'track')}`}
            action={<PanelLink href="/releases">All releases</PanelLink>}
        >
            <div className="px-5 py-4">
                <div className="flex h-3 w-full overflow-hidden rounded-full bg-slate-100" role="img" aria-label="Releases by status">
                    {steps.map((step) => {
                        const value = Number(stats[step.key] || 0);
                        if (value <= 0) return null;
                        return <span key={step.key} className={step.color} style={{ width: `${(value / total) * 100}%`, minWidth: '4px' }} title={`${step.label}: ${number(value)}`} />;
                    })}
                </div>
                <div className="mt-4 grid grid-cols-2 gap-2 sm:grid-cols-4 xl:grid-cols-8">
                    {steps.map((step) => (
                        <Link key={step.key} href={step.href} className="rounded-lg border border-slate-100 px-3 py-2 transition hover:border-violet-200 hover:bg-violet-50/40">
                            <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500">
                                <span className={`h-2 w-2 rounded-full ${step.color}`} />
                                {step.label}
                            </span>
                            <span className="mt-0.5 block text-lg font-black text-slate-950">{number(stats[step.key])}</span>
                        </Link>
                    ))}
                </div>
            </div>
        </Panel>
    );
}

function RevenueTrend({ rows = [], isSuperAdmin }) {
    const values = rows.map((row) => Math.max(0, Number(row.collected_revenue || 0)));
    const max = Math.max(1, ...values);

    return (
        <Panel
            title="Revenue by reporting month"
            subtitle={isSuperAdmin ? 'Collected revenue and the part Mixx Tune keeps' : 'Collected revenue in your scope'}
            action={<PanelLink href={isSuperAdmin ? '/admin/profit' : '/analytics'}>{isSuperAdmin ? 'Business profit' : 'Analytics'}</PanelLink>}
            className="xl:col-span-2"
        >
            {rows.length === 0 ? (
                <div className="px-5 py-12 text-center text-sm text-slate-500">No revenue imported yet. Import a DSP report to see the trend.</div>
            ) : (
                <div className="px-5 pb-4 pt-5">
                    <div className="flex h-48 items-end gap-2 overflow-x-auto">
                        {rows.map((row) => {
                            const collected = Math.max(0, Number(row.collected_revenue || 0));
                            const profit = Math.max(0, Number(row.profit || 0));
                            const height = Math.max(4, Math.round((collected / max) * 100));
                            const profitShare = collected > 0 ? Math.min(100, (profit / collected) * 100) : 0;
                            return (
                                <div key={row.month} className="flex min-w-[44px] flex-1 flex-col items-center justify-end gap-1.5" title={`${monthLabel(row.month)} · Collected ${money(row.collected_revenue)}${isSuperAdmin ? ` · Kept ${money(row.profit)}` : ''}`}>
                                    <span className="text-[10px] font-bold text-slate-500">{shortMoney(collected)}</span>
                                    <div className="relative flex w-full max-w-[38px] flex-col justify-end overflow-hidden rounded-t-md bg-violet-200" style={{ height: `${height}%` }}>
                                        {isSuperAdmin && <span className="block w-full bg-violet-600" style={{ height: `${profitShare}%` }} />}
                                    </div>
                                    <span className="text-[10px] font-semibold text-slate-500">{monthLabel(row.month)}</span>
                                </div>
                            );
                        })}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-4 text-[11px] text-slate-500">
                        <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-violet-200" />Collected revenue</span>
                        {isSuperAdmin && <span className="inline-flex items-center gap-1.5"><span className="h-2.5 w-2.5 rounded-sm bg-violet-600" />Kept by Mixx Tune</span>}
                    </div>
                </div>
            )}
        </Panel>
    );
}

function TopPlatforms({ rows = [] }) {
    const max = Math.max(1, ...rows.map((row) => Number(row.collected_revenue ?? row.earnings ?? 0)));
    return (
        <Panel title="Top platforms" subtitle="By collected revenue" action={<PanelLink href="/analytics">Analytics</PanelLink>}>
            {rows.length === 0 ? (
                <div className="px-5 py-12 text-center text-sm text-slate-500">No platform data yet.</div>
            ) : (
                <ul className="space-y-3 px-5 py-4">
                    {rows.map((row, index) => {
                        const value = Number(row.collected_revenue ?? row.earnings ?? 0);
                        return (
                            <li key={`${row.name || row.platform}-${index}`}>
                                <div className="flex items-center justify-between gap-3 text-[13px]">
                                    <span className="truncate font-semibold text-slate-800">{row.name || row.platform || 'Unknown'}</span>
                                    <span className="shrink-0 font-bold text-slate-950">{money(value, 'INR', 0)}</span>
                                </div>
                                <div className="mt-1 h-1.5 rounded-full bg-slate-100">
                                    <div className="h-1.5 rounded-full bg-violet-500" style={{ width: `${Math.max(2, (Math.max(0, value) / max) * 100)}%` }} />
                                </div>
                                <div className="mt-0.5 text-[11px] text-slate-500">{number(row.streams)} streams</div>
                            </li>
                        );
                    })}
                </ul>
            )}
        </Panel>
    );
}

function ActivityList({ title, subtitle, href, linkText, empty, items }) {
    return (
        <Panel title={title} subtitle={subtitle} action={<PanelLink href={href}>{linkText}</PanelLink>}>
            {items.length === 0 ? (
                <div className="px-5 py-10 text-center text-sm text-slate-500">{empty}</div>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {items.map((item) => (
                        <li key={item.key}>
                            <Link href={item.href} className="flex items-center gap-3 px-5 py-3 transition hover:bg-slate-50">
                                <span className="min-w-0 flex-1">
                                    <span className="block truncate text-[13px] font-bold text-slate-900">{item.title}</span>
                                    <span className="block truncate text-[11px] text-slate-500">{item.subtitle}</span>
                                </span>
                                {item.amount && <span className="shrink-0 text-[13px] font-black text-slate-950">{item.amount}</span>}
                                <StatusBadge status={item.status} />
                            </Link>
                        </li>
                    ))}
                </ul>
            )}
        </Panel>
    );
}

export default function Dashboard({
    role = 'admin',
    stats = {},
    setup = null,
    growth = {},
    revenueTrend = [],
    analytics = {},
    recentReleases = [],
    recentImports = [],
    recentWithdrawals = [],
    agreements = null,
}) {
    const isSuperAdmin = role === 'super_admin';
    const panelName = isSuperAdmin ? 'Super Admin' : 'Manager';
    const scopeWord = isSuperAdmin ? 'on the platform' : 'assigned to you';

    const waiting = stats.oldest_waiting_days;
    const attention = [
        {
            label: 'Releases waiting for review',
            value: stats.submitted,
            hint: waiting !== null && waiting !== undefined ? `Oldest waiting ${waiting} day${waiting === 1 ? '' : 's'}` : 'Open the review queue',
            clear: 'Review queue is empty',
            href: '/admin/release-reviews?status=submitted',
            icon: ClipboardCheck,
            danger: Number(waiting || 0) >= 3,
        },
        {
            label: 'Approved, not sent to stores',
            value: stats.approved,
            hint: 'Start processing to deliver',
            clear: 'Nothing waiting to send',
            href: '/admin/release-reviews?status=approved',
            icon: Send,
        },
        {
            label: 'Failed store deliveries',
            value: stats.failed_deliveries,
            hint: 'Retry or fix the release',
            clear: 'No failed deliveries',
            href: '/admin/distribution?status=store_failed',
            icon: AlertTriangle,
            danger: true,
        },
        {
            label: 'Withdrawals to approve',
            value: stats.pending_withdrawals,
            hint: `${money(stats.pending_withdrawals_amount)} requested`,
            clear: 'No new payout requests',
            href: '/admin/withdrawals?status=pending',
            icon: WalletCards,
        },
        {
            label: 'Approved payouts to pay',
            value: stats.withdrawals_to_pay,
            hint: `${money(stats.withdrawals_to_pay_amount)} to transfer`,
            clear: 'All approved payouts paid',
            href: '/admin/withdrawals?status=approved',
            icon: Landmark,
        },
        {
            label: 'Support tickets needing reply',
            value: stats.tickets_need_reply,
            hint: `${number(stats.open_tickets)} open in total`,
            clear: 'Inbox is clear',
            href: '/admin/support?status=needs_reply',
            icon: Headphones,
        },
        ...(isSuperAdmin && setup
            ? [
                  {
                      label: 'KYC to review',
                      value: setup.kyc_to_review,
                      hint: 'Payout details submitted',
                      clear: 'No KYC waiting',
                      href: '/admin/kyc?status=submitted',
                      icon: IdCard,
                  },
                  {
                      label: 'Unmapped revenue rows',
                      value: setup.unmapped_rows,
                      hint: `${money(setup.unmapped_revenue)} not linked to a label`,
                      clear: 'All revenue is mapped',
                      href: '/super-admin/unmapped-revenue',
                      icon: FileQuestion,
                      danger: true,
                  },
                  {
                      label: 'Sub-labels without %',
                      value: setup.sub_labels_without_share,
                      hint: 'Revenue split is not set',
                      clear: 'Every sub-label has a %',
                      href: '/super-admin/label-hierarchy?show=share_missing',
                      icon: FolderTree,
                  },
                  {
                      label: 'Master labels without login',
                      value: setup.masters_without_login,
                      hint: 'No owner account linked',
                      clear: 'Every master has a login',
                      href: '/super-admin/label-hierarchy?show=no_login',
                      icon: UserX,
                  },
              ]
            : []),
    ];
    const activeItems = attention
        .filter((item) => Number(item.value || 0) > 0)
        .sort((a, b) => Number(Boolean(b.danger)) - Number(Boolean(a.danger)));
    const clearItems = attention.filter((item) => Number(item.value || 0) <= 0);
    const openItems = activeItems.length;

    const kpis = [
        isSuperAdmin
            ? {
                  title: 'Profit kept by Mixx Tune',
                  value: money(stats.profit_earnings),
                  /* W92: published reports only; reports still in review are shown apart */
                  lines: [
                      `Collected ${money(stats.profit_collected)}`,
                      `Paid to accounts ${money(stats.profit_user_earning)}`,
                      ...(Number(stats.profit_in_review || 0) !== 0 ? [`In review, not counted ${money(stats.profit_in_review)}`] : []),
                  ],
                  icon: BadgeIndianRupee,
                  href: '/admin/profit',
                  tone: 'bg-emerald-100 text-emerald-700',
              }
            : {
                  title: 'Reported earnings',
                  value: money(stats.earnings),
                  lines: [<Growth key="g" value={growth.earnings_percent} label={`vs ${monthLabel(growth.previous_month)}`} />],
                  icon: BadgeIndianRupee,
                  href: '/analytics',
                  tone: 'bg-emerald-100 text-emerald-700',
              },
        {
            title: 'Reported streams',
            value: number(stats.streams),
            lines: [
                <Growth key="g" value={growth.streams_percent} label={growth.previous_month ? `${monthLabel(growth.month)} vs ${monthLabel(growth.previous_month)}` : ''} />,
                `${plural(stats.reported_tracks, 'track')} · ${plural(stats.active_platforms, 'platform')}`,
            ],
            icon: TrendingUp,
            href: '/analytics',
            tone: 'bg-violet-100 text-violet-700',
        },
        {
            title: 'Releases',
            value: number(stats.total_releases),
            lines: [`${number(stats.live)} live · ${number(stats.draft)} draft`, plural(stats.tracks, 'track')],
            icon: Disc3,
            href: '/releases',
            tone: 'bg-amber-100 text-amber-700',
        },
        {
            title: isSuperAdmin ? 'Accounts' : 'Your accounts',
            value: plural(stats.labels, 'label'),
            lines: [`${plural(stats.artists, 'artist')} ${scopeWord}`, plural(stats.report_imports, 'report import')],
            icon: Library,
            href: '/admin/labels',
            tone: 'bg-sky-100 text-sky-700',
        },
    ];

    const quickActions = [
        ...(isSuperAdmin ? [{ label: 'Business profit', href: '/admin/profit', icon: Landmark }] : []),
        { label: 'Review queue', href: '/admin/release-reviews', icon: ShieldCheck },
        { label: 'Import revenue', href: '/admin/reports/imports', icon: FileUp },
        { label: 'Distribution', href: '/admin/distribution', icon: Radio },
        { label: 'Withdrawals', href: '/admin/withdrawals', icon: WalletCards },
        { label: 'Label management', href: '/super-admin/label-hierarchy', icon: FolderTree },
        { label: 'Artists', href: '/admin/artists', icon: Music2 },
        ...(isSuperAdmin ? [{ label: 'Users', href: '/admin/users', icon: Users }] : []),
        { label: 'Support', href: '/admin/support', icon: Headphones },
        ...(isSuperAdmin ? [{ label: 'Audit logs', href: '/admin/audit-logs', icon: History }] : []),
    ];

    const releaseItems = recentReleases.map((release) => ({
        key: release.id,
        title: release.title || 'Untitled release',
        subtitle: [release.primary_artist_name, release.label_name, when(release.submitted_at || release.created_at)].filter(Boolean).join(' · '),
        status: release.status,
        href: `/admin/release-reviews/${release.id}`,
    }));

    const importItems = recentImports.map((item) => ({
        key: item.id,
        title: item.original_filename || 'Revenue import',
        subtitle: `${number(item.imported_rows)} rows imported${Number(item.failed_rows || 0) > 0 ? ` · ${number(item.failed_rows)} failed` : ''} · ${when(item.created_at)}`,
        status: item.status,
        href: `/admin/reports/imports/${item.id}/view`,
    }));

    const withdrawalItems = recentWithdrawals.map((item) => ({
        key: item.id,
        title: item.requester_name || item.withdrawal_number || `Withdrawal #${item.id}`,
        subtitle: [item.withdrawal_number, when(item.requested_at || item.created_at)].filter(Boolean).join(' · '),
        amount: money(item.amount, item.currency),
        status: item.status,
        href: `/admin/withdrawals?status=${['pending', 'approved', 'paid', 'rejected'].includes(item.status) ? item.status : 'all'}`,
    }));

    const today = new Intl.DateTimeFormat('en-IN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(new Date());

    return (
        <PanelLayout role={role} title={`${panelName} Dashboard`} subtitle="Operations, revenue and platform control">
            <Head title={`${panelName} Dashboard`} />

            <section className="rounded-2xl bg-gradient-to-r from-[#0f172a] to-[#2e1065] px-5 py-5 text-white shadow-lg sm:px-7">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div className="min-w-0">
                        <p className="text-[11px] font-bold uppercase tracking-[0.18em] text-violet-200">{panelName} control center · {today}</p>
                        <h1 className="mt-1 text-2xl font-black tracking-tight sm:text-3xl">Welcome, {panelName}</h1>
                        <p className="mt-1 text-sm text-slate-300">
                            {openItems === 0 ? 'Everything is up to date. Nothing needs your action right now.' : `${openItems} thing${openItems === 1 ? '' : 's'} need${openItems === 1 ? 's' : ''} your action — see below.`}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href="/admin/release-reviews?status=submitted" className="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-slate-950 shadow hover:bg-violet-50">
                            <ShieldCheck size={16} />
                            Review queue
                            {Number(stats.submitted || 0) > 0 && <span className="rounded-full bg-amber-400 px-2 py-0.5 text-[11px] font-black text-slate-950">{number(stats.submitted)}</span>}
                        </Link>
                        <Link href="/admin/reports/imports" className="inline-flex items-center gap-2 rounded-xl border border-white/20 bg-white/10 px-4 py-2.5 text-sm font-bold text-white hover:bg-white/20">
                            <FileUp size={16} />
                            Import revenue
                        </Link>
                    </div>
                </div>
            </section>

            {/* W94 MIXXTUNE_ACCOUNT_AGREEMENTS */}
            {isSuperAdmin && <AgreementAlert agreements={agreements} />}

            <section className="mt-5" aria-labelledby="attention-title">
                <div className="mb-2.5 flex items-center justify-between gap-3">
                    <h2 id="attention-title" className="text-[15px] font-bold text-slate-950">Needs your attention</h2>
                    {Number(stats.changes_requested || 0) > 0 && (
                        <Link href="/admin/release-reviews?status=changes_requested" className="inline-flex items-center gap-1 text-xs font-semibold text-slate-500 hover:text-violet-700">
                            <RotateCcw size={13} />
                            {number(stats.changes_requested)} release{Number(stats.changes_requested) === 1 ? '' : 's'} sent back to the owner
                        </Link>
                    )}
                </div>
                {activeItems.length > 0 ? (
                    <div className="grid gap-2.5 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                        {activeItems.map((item) => (
                            <AttentionTile key={item.label} item={item} />
                        ))}
                    </div>
                ) : (
                    <div className="flex items-center gap-3 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800">
                        <CheckCircle2 size={18} />
                        All clear — no releases, payouts, tickets or setup gaps waiting.
                    </div>
                )}
                {clearItems.length > 0 && activeItems.length > 0 && (
                    <div className="mt-2.5 flex flex-wrap items-center gap-1.5">
                        <span className="text-[11px] font-semibold text-slate-500">All clear:</span>
                        {clearItems.map((item) => (
                            <Link key={item.label} href={item.href} className="inline-flex items-center gap-1 rounded-full border border-emerald-200 bg-emerald-50 px-2.5 py-1 text-[11px] font-semibold text-emerald-800 hover:border-emerald-300">
                                <CheckCircle2 size={12} />
                                {item.clear}
                            </Link>
                        ))}
                    </div>
                )}
            </section>

            <section className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                {kpis.map((kpi) => (
                    <KpiCard key={kpi.title} {...kpi} />
                ))}
            </section>

            <div className="mt-5">
                <Pipeline stats={stats} />
            </div>

            <section className="mt-5 grid gap-5 xl:grid-cols-3">
                <RevenueTrend rows={revenueTrend} isSuperAdmin={isSuperAdmin} />
                <TopPlatforms rows={analytics?.topPlatforms || []} />
            </section>

            <section className="mt-5 grid gap-5 xl:grid-cols-3">
                <ActivityList title="Latest releases" subtitle={`Newest releases ${scopeWord}`} href="/admin/release-reviews" linkText="Review queue" empty="No releases yet." items={releaseItems} />
                <ActivityList title="Revenue imports" subtitle="Latest DSP reports" href="/admin/reports/imports" linkText="All imports" empty="No report imports yet." items={importItems} />
                <ActivityList title="Withdrawals" subtitle="Latest payout requests" href="/admin/withdrawals?status=all" linkText="All withdrawals" empty="No withdrawal requests." items={withdrawalItems} />
            </section>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 className="text-[15px] font-bold text-slate-950">Quick actions</h2>
                <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3 lg:grid-cols-5">
                    {quickActions.map(({ label, href, icon: Icon }) => (
                        <Link key={href} href={href} className="group flex items-center gap-2.5 rounded-xl border border-slate-200 px-3 py-2.5 text-[13px] font-semibold text-slate-800 transition hover:border-violet-200 hover:bg-violet-50/50">
                            <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-slate-600 group-hover:bg-violet-100 group-hover:text-violet-700">
                                <Icon size={16} />
                            </span>
                            <span className="truncate">{label}</span>
                        </Link>
                    ))}
                </div>
            </section>
        </PanelLayout>
    );
}
