import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, CheckCircle2, Clock3, Download, FileText, Hourglass, Search, ShieldCheck, Wallet, X } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

import RightsRoyaltyUploadPanel from './RightsRoyaltyUploadPanel';

/*
 * W72 MIXXTUNE_ROYALTIES_V3 (artist / label view)
 * One summary card (total -> deductions -> net) with clickable status chips,
 * a real 12-month chart (from all statements, not only this page), and a
 * server-side status filter + month search. "Download" is a plain link
 * because it returns a PDF (an Inertia link cannot open a file).
 */

const formatAmount = (value, currency = 'INR') => {
    const n = Number(value ?? 0);
    try {
        return new Intl.NumberFormat('en-IN', { style: 'currency', currency: currency || 'INR', maximumFractionDigits: 2 }).format(n);
    } catch {
        return `${currency} ${n.toFixed(2)}`;
    }
};

const shortAmount = (value) => {
    const n = Number(value ?? 0);
    const abs = Math.abs(n);
    if (abs >= 1e7) return `₹${(n / 1e7).toFixed(2)} Cr`;
    if (abs >= 1e5) return `₹${(n / 1e5).toFixed(2)} L`;
    if (abs >= 1e3) return `₹${(n / 1e3).toFixed(1)}K`;
    return `₹${n.toFixed(0)}`;
};

const formatMonth = (value, style = 'long') => {
    if (!value) return 'Unknown month';
    /* statement_month can be "2026-07" or a date "2026-07-01" */
    const ym = String(value).slice(0, 7);
    const date = new Date(`${ym}-01T00:00:00`);
    if (Number.isNaN(date.getTime())) return value;
    return new Intl.DateTimeFormat('en-IN', style === 'short' ? { month: 'short', year: '2-digit' } : { month: 'long', year: 'numeric' }).format(date);
};

const formatDate = (value) => {
    if (!value) return '';
    const date = new Date(value);
    if (Number.isNaN(date.getTime())) return String(value);
    return new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric' }).format(date);
};

/* the status a statement really has (import statements keep "pending" while already credited / held) */
function effectiveStatus(item) {
    const s = item?.import_posting_status;
    if (!s) return item?.status || 'pending';
    if (s === 'posted' || s === 'completed') return 'import_paid';
    if (s === 'held_unclaimed') return 'import_held';
    return 'import_scheduled';
}

const STATUS = {
    pending: { label: 'Waiting for approval', short: 'Pending', className: 'bg-amber-50 text-amber-800 ring-amber-200', dot: 'bg-amber-400', icon: Hourglass },
    approved: { label: 'Approved', short: 'Approved', className: 'bg-sky-50 text-sky-800 ring-sky-200', dot: 'bg-sky-500', icon: ShieldCheck },
    available: { label: 'In your wallet', short: 'In wallet', className: 'bg-emerald-50 text-emerald-800 ring-emerald-200', dot: 'bg-emerald-500', icon: Wallet },
    paid: { label: 'Paid to you', short: 'Paid', className: 'bg-green-50 text-green-800 ring-green-200', dot: 'bg-green-600', icon: CheckCircle2 },
    import_paid: { label: 'Credited to wallet', short: 'Credited via report', className: 'bg-emerald-50 text-emerald-800 ring-emerald-200', dot: 'bg-teal-500', icon: Wallet },
    import_held: { label: 'Held · account not linked yet', short: 'Held', className: 'bg-orange-50 text-orange-800 ring-orange-200', dot: 'bg-orange-400', icon: Clock3 },
    import_scheduled: { label: 'Scheduled · will be credited', short: 'Scheduled', className: 'bg-slate-100 text-slate-700 ring-slate-200', dot: 'bg-slate-400', icon: Clock3 },
    cancelled: { label: 'Cancelled', short: 'Cancelled', className: 'bg-rose-50 text-rose-700 ring-rose-200', dot: 'bg-rose-400', icon: X },
};

const CHIPS = [
    { key: 'pending', amount: 'pending' },
    { key: 'approved', amount: 'approved' },
    { key: 'import_paid', amount: 'paid_via_import' },
    { key: 'available', amount: 'available' },
    { key: 'paid', amount: 'paid' },
    { key: 'import_held', amount: 'held_via_import' },
];

function StatusBadge({ item }) {
    const key = effectiveStatus(item);
    const s = STATUS[key] ?? { label: String(key).replaceAll('_', ' '), className: 'bg-slate-100 text-slate-700 ring-slate-200' };
    return <span className={`inline-flex items-center whitespace-nowrap rounded-full px-2.5 py-1 text-[11px] font-bold ring-1 ring-inset ${s.className}`}>{s.label}</span>;
}

function Summary({ summary, counts, active, onPick }) {
    const net = Number(summary.net ?? 0);
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="grid gap-5 lg:grid-cols-[1.2fr_2fr] lg:items-center">
                <div>
                    <p className="text-sm font-semibold text-slate-500">Your royalty after deductions</p>
                    <p className="mt-1 text-[34px] font-black leading-tight tracking-tight text-slate-950">{formatAmount(net)}</p>
                    <div className="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-500">
                        <span>Total royalty {formatAmount(summary.gross)}</span>
                        <span>Deductions −{formatAmount(summary.deductions)}</span>
                        <span>{counts.all ?? 0} statement{(counts.all ?? 0) === 1 ? '' : 's'}</span>
                    </div>
                    <Link href="/wallet" className="mt-3 inline-flex items-center gap-1 text-xs font-bold text-violet-700 hover:text-violet-900">
                        Open wallet to request payment <ArrowRight size={13} />
                    </Link>
                </div>
                <div className="grid grid-cols-2 gap-2 sm:grid-cols-3">
                    {CHIPS.map(({ key, amount }) => {
                        const s = STATUS[key];
                        const Icon = s.icon;
                        const value = Number(summary[amount] ?? 0);
                        const count = Number(counts[key] ?? 0);
                        const on = active === key;
                        if (key === 'import_held' && count === 0) return null;
                        return (
                            <button
                                key={key}
                                type="button"
                                onClick={() => onPick(on ? '' : key)}
                                aria-pressed={on}
                                className={`rounded-xl border px-3 py-2.5 text-left transition ${on ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 hover:border-violet-200 hover:bg-slate-50'}`}
                            >
                                <span className="flex items-center gap-1.5 text-[11px] font-semibold text-slate-500">
                                    <Icon size={13} className="shrink-0" />
                                    <span className="truncate">{s.short}</span>
                                    <span className="ml-auto rounded-full bg-slate-100 px-1.5 text-[10px] font-bold text-slate-600">{count}</span>
                                </span>
                                <span className={`mt-1 block text-base font-black ${value > 0 ? 'text-slate-950' : 'text-slate-400'}`}>{formatAmount(value)}</span>
                            </button>
                        );
                    })}
                </div>
            </div>
        </section>
    );
}

function MonthlyChart({ rows }) {
    const max = Math.max(1, ...rows.map((r) => Math.max(0, Number(r.net || 0))));
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-base font-bold text-slate-900">Monthly royalty</h2>
                    <p className="mt-0.5 text-xs text-slate-500">After deductions, by statement month</p>
                </div>
                {rows.length > 0 && <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-600">Last {rows.length} month{rows.length === 1 ? '' : 's'}</span>}
            </div>
            {rows.length === 0 ? (
                <div className="mt-5 rounded-xl border border-dashed border-slate-300 bg-slate-50 px-4 py-10 text-center">
                    <FileText className="mx-auto h-7 w-7 text-slate-300" />
                    <p className="mt-2 text-sm font-semibold text-slate-700">No royalty statements yet</p>
                    <p className="mt-1 text-xs text-slate-500">When the team publishes a monthly report, your statement and this chart appear here.</p>
                </div>
            ) : (
                <div className="mt-5 flex h-44 items-end gap-2 overflow-x-auto">
                    {rows.map((r) => {
                        const v = Math.max(0, Number(r.net || 0));
                        return (
                            <div key={r.month} className="flex h-full min-w-[42px] flex-1 flex-col items-center justify-end gap-1" title={`${formatMonth(r.month)} · ${formatAmount(r.net)}`}>
                                <span className="text-[10px] font-bold text-slate-500">{shortAmount(v)}</span>
                                <div className="flex h-32 w-full max-w-[36px] items-end">
                                    <div className="w-full rounded-t-md bg-violet-500" style={{ height: `${Math.max(3, Math.round((v / max) * 100))}%` }} />
                                </div>
                                <span className="text-[10px] font-semibold text-slate-500">{formatMonth(r.month, 'short')}</span>
                            </div>
                        );
                    })}
                </div>
            )}
        </section>
    );
}

function Statements({ statements, filters, counts }) {
    const rows = statements.data ?? [];
    const [search, setSearch] = useState(filters.search ?? '');
    const first = useRef(true);

    const go = (patch) =>
        router.get(window.location.pathname, { status: filters.status || undefined, search: search.trim() || undefined, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ search: search.trim() || undefined, page: undefined }), 400);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const activeLabel = filters.status ? STATUS[filters.status]?.short : null;

    return (
        <section id="statements" className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-3 border-b border-slate-100 px-5 py-4 lg:flex-row lg:items-center lg:justify-between">
                <div className="min-w-0">
                    <h2 className="text-base font-bold text-slate-900">Royalty statements</h2>
                    <p className="mt-0.5 text-xs text-slate-500">
                        {statements.total ?? rows.length} statement{(statements.total ?? rows.length) === 1 ? '' : 's'}
                        {activeLabel ? ` · ${activeLabel}` : ''}
                        {filters.search ? ` · “${filters.search}”` : ''}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <label className="relative">
                        <Search className="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                        <input
                            type="search"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Search month or ID"
                            aria-label="Search statements"
                            className="h-10 w-64 max-w-full rounded-lg border border-slate-300 pl-9 pr-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                        />
                    </label>
                    <select
                        value={filters.status || ''}
                        onChange={(e) => go({ status: e.target.value || undefined, page: undefined })}
                        aria-label="Filter by status"
                        className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-medium text-slate-700 outline-none focus:border-violet-500"
                    >
                        <option value="">All ({counts.all ?? 0})</option>
                        {['pending', 'approved', 'import_paid', 'available', 'paid', 'import_held', 'cancelled'].map((k) => (
                            <option key={k} value={k}>
                                {STATUS[k].short} ({counts[k] ?? 0})
                            </option>
                        ))}
                    </select>
                    {(filters.status || filters.search) && (
                        <button
                            type="button"
                            onClick={() => {
                                setSearch('');
                                router.get(window.location.pathname, {}, { preserveScroll: true, replace: true });
                            }}
                            className="inline-flex h-10 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-600 hover:bg-slate-50"
                        >
                            <X size={13} /> Clear
                        </button>
                    )}
                </div>
            </div>

            {rows.length === 0 ? (
                <div className="px-5 py-12 text-center">
                    <FileText className="mx-auto h-8 w-8 text-slate-300" />
                    <p className="mt-2 text-sm font-semibold text-slate-700">{filters.status || filters.search ? 'No statement matches this filter' : 'No royalty statements yet'}</p>
                    <p className="mt-1 text-xs text-slate-500">{filters.status || filters.search ? 'Try another status or clear the filter.' : 'Statements appear here once a monthly report is published.'}</p>
                </div>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] border-collapse text-sm">
                        <thead>
                            <tr className="border-b border-slate-200 bg-slate-50/80 text-left text-[10px] font-bold uppercase tracking-[0.12em] text-slate-500">
                                <th className="px-5 py-3">Month</th>
                                <th className="px-5 py-3 text-right">Total royalty</th>
                                <th className="px-5 py-3 text-right">Deductions</th>
                                <th className="px-5 py-3 text-right">You get</th>
                                <th className="px-5 py-3">Status</th>
                                <th className="px-5 py-3 text-right">Statement</th>
                            </tr>
                        </thead>
                        <tbody>
                            {rows.map((item) => {
                                const commission = Number(item.commission_amount ?? 0);
                                const tax = Number(item.tax_amount ?? 0);
                                const other = Number(item.other_deductions ?? 0);
                                const deductions = commission + tax + other;
                                return (
                                    <tr key={item.id} className="border-b border-slate-100 last:border-0 hover:bg-violet-50/30">
                                        <td className="px-5 py-3">
                                            <div className="font-bold text-slate-900">{formatMonth(item.statement_month)}</div>
                                            <div className="text-[11px] text-slate-400">
                                                {item.public_id || `Statement #${item.id}`} · {formatDate(item.created_at)}
                                            </div>
                                        </td>
                                        <td className="px-5 py-3 text-right font-semibold text-slate-900">{formatAmount(item.gross_earnings, item.currency)}</td>
                                        <td className="px-5 py-3 text-right text-slate-600" title={`Commission ${formatAmount(commission, item.currency)} · Tax ${formatAmount(tax, item.currency)} · Other ${formatAmount(other, item.currency)}`}>
                                            {deductions > 0 ? `−${formatAmount(deductions, item.currency)}` : formatAmount(0, item.currency)}
                                        </td>
                                        <td className="px-5 py-3 text-right text-base font-black text-emerald-700">{formatAmount(item.net_payable, item.currency)}</td>
                                        <td className="px-5 py-3">
                                            <StatusBadge item={item} />
                                        </td>
                                        <td className="px-5 py-3 text-right">
                                            <a
                                                href={`/statements/${item.id}/download`}
                                                className="inline-flex items-center gap-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-bold text-slate-700 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                                            >
                                                <Download size={13} /> PDF
                                            </a>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}

            {(statements.links?.length ?? 0) > 3 && (
                <nav className="flex flex-wrap items-center justify-center gap-1.5 border-t border-slate-100 px-5 py-3" aria-label="Statement pages">
                    {statements.links.map((link, index) => (
                        <Link
                            key={`${link.label}-${index}`}
                            href={link.url ?? '#'}
                            preserveScroll
                            preserveState
                            className={[
                                'inline-flex h-9 min-w-9 items-center justify-center rounded-lg border px-3 text-xs font-bold',
                                link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 bg-white text-slate-600 hover:border-violet-300',
                                !link.url ? 'pointer-events-none opacity-40' : '',
                            ].join(' ')}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ))}
                </nav>
            )}
        </section>
    );
}

export default function Index({ role = 'artist', summary = {}, counts = {}, monthly = [], filters = {}, statements = {}, rightsImports = [] }) {
    const staff = ['admin', 'super_admin'].includes(role);
    const title = role === 'artist' ? 'My Royalties' : role === 'label' ? 'Label Royalties' : staff ? 'Rights Royalties' : 'Royalties';

    const pick = (status) => {
        router.get(window.location.pathname, { status: status || undefined, search: filters.search || undefined }, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            onSuccess: () => document.getElementById('statements')?.scrollIntoView({ behavior: 'smooth', block: 'start' }),
        });
    };

    return (
        <PanelLayout role={role} title={title} subtitle={staff ? 'IPRS / PRS / TPL rights statements - upload, match, approve, publish' : 'Monthly royalty statements, deductions and payment status'}>
            <Head title={title} />

            {/* MIXXTUNE_RIGHTS_ROYALTY_PANEL_V1 */}
            {role === 'super_admin' && <RightsRoyaltyUploadPanel role={role} imports={rightsImports} />}

            {/* MIXXTUNE_FINANCE_CLEANUP_V1: admins already have the statement list on
                Finance & Royalties (actions) and Statements (PDF + e-mail); no third copy here */}
            {staff ? (
                <div className="mt-6 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-bold text-slate-900">Where are the royalty statements?</h2>
                    <p className="mt-2 text-sm leading-6 text-slate-600">This page is for rights royalty files (IPRS / PRS / TPL). Monthly statements are in one place each:</p>
                    <div className="mt-4 flex flex-wrap gap-3">
                        <Link href="/admin/finance" className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700">
                            Finance &amp; Royalties · approve and release
                        </Link>
                        <Link href="/admin/statements" className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                            Statements · PDF and e-mail to owners
                        </Link>
                    </div>
                </div>
            ) : (
                <div className="mx-auto w-full max-w-[1500px] space-y-5">
                    <Summary summary={summary} counts={counts} active={filters.status || ''} onPick={pick} />
                    <MonthlyChart rows={monthly} />
                    <Statements statements={statements} filters={filters} counts={counts} />
                </div>
            )}
        </PanelLayout>
    );
}
