import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const nf = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

function money(value, currency) {
    const n = Number(value ?? 0);
    const amount = Number.isFinite(n) ? nf.format(n) : '0';
    return `${currency || 'INR'} ${amount}`;
}

function statusPill(status) {
    const v = String(status || '').toLowerCase();
    if (v === 'paid') return ['Paid', 'bg-emerald-50 text-emerald-700 ring-emerald-200'];
    if (v === 'wallet_credited') return ['In wallet', 'bg-blue-50 text-blue-700 ring-blue-200'];
    if (v === 'cancelled') return ['Cancelled', 'bg-red-50 text-red-700 ring-red-200'];
    return ['Pending credit', 'bg-amber-50 text-amber-700 ring-amber-200'];
}

export default function Publishing({
    role = 'artist',
    isOwner = true,
    summary = {},
    monthly = [],
    rows = {},
    filters = {},
}) {
    const [month, setMonth] = useState(filters.month || '');
    const currency = summary.currency || 'INR';

    const applyMonth = (event) => {
        event.preventDefault();
        router.get(
            '/publishing',
            { month: month.trim() || undefined },
            { preserveState: true, preserveScroll: true, replace: true }
        );
    };

    const maxMonthly = Math.max(1, ...(monthly || []).map((m) => Number(m.payable) || 0));

    return (
        <PanelLayout
            role={role}
            title="Publishing"
            subtitle="Performing-rights royalties (IPRS / PRS / TPL) matched to your catalogue by ISRC"
        >
            <Head title="Publishing" />

            {!isOwner ? (
                <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-600 shadow-sm">
                    This dashboard is for labels and artists. As an admin, review and publish
                    rights royalty reports under <span className="font-semibold">Financial → Rights Royalties</span>.
                </div>
            ) : (
                <div className="space-y-6">
                    <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                        <Card label="Performing Royalty (Payable)" value={money(summary.total_payable, currency)} strong />
                        <Card label="Gross (Before splits)" value={money(summary.total_gross, currency)} />
                        <Card label="Matched Lines" value={nf.format(summary.lines || 0)} />
                    </section>

                    {(monthly || []).length > 0 && (
                        <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                            <div className="mb-4 text-sm font-semibold text-slate-900">
                                Payable by statement month
                            </div>
                            <div className="flex items-end gap-2 overflow-x-auto">
                                {monthly.map((m) => (
                                    <div key={m.month} className="flex min-w-[48px] flex-1 flex-col items-center gap-1">
                                        <div className="text-[10px] tabular-nums text-slate-500">
                                            {nf.format(m.payable)}
                                        </div>
                                        <div
                                            className="w-full rounded-t bg-violet-500"
                                            style={{ height: `${Math.max(4, (Number(m.payable) / maxMonthly) * 120)}px` }}
                                            title={`${m.month}: ${money(m.payable, currency)}`}
                                        />
                                        <div className="text-[10px] text-slate-500">{m.month}</div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    )}

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <div>
                                <div className="font-semibold text-slate-900">Published rights lines</div>
                                <div className="mt-1 text-xs text-slate-500">
                                    Each line is one ISRC match published to your royalty ledger. The amount
                                    reaches your wallet after the admin credits it.
                                </div>
                            </div>
                            <form onSubmit={applyMonth} className="flex items-center gap-2">
                                <input
                                    type="month"
                                    value={month}
                                    onChange={(e) => setMonth(e.target.value)}
                                    className="h-9 rounded-lg border border-slate-300 px-2 text-sm"
                                />
                                <button type="submit" className="h-9 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                                    Filter
                                </button>
                                {month && (
                                    <button
                                        type="button"
                                        onClick={() => { setMonth(''); router.get('/publishing', {}, { preserveState: true, preserveScroll: true, replace: true }); }}
                                        className="h-9 rounded-lg border border-slate-300 px-3 text-sm font-semibold text-slate-700"
                                    >
                                        Clear
                                    </button>
                                )}
                            </form>
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-[960px] w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {['Month', 'Track', 'ISRC', 'Territory', 'Gross', 'Payable', 'Status'].map((h) => (
                                            <th key={h} className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500">
                                                {h}
                                            </th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-100">
                                    {(rows.data ?? []).map((r) => {
                                        const [label, cls] = statusPill(r.status);
                                        return (
                                            <tr key={r.public_id} className="hover:bg-slate-50/70">
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">{r.statement_month || '—'}</td>
                                                <td className="px-4 py-3 text-sm text-slate-700">{r.track_title || '—'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 font-mono text-xs text-slate-600">{r.track_isrc || '—'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-600">{r.territory || '—'}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm tabular-nums text-slate-600">{nf.format(r.gross_amount ?? 0)}</td>
                                                <td className="whitespace-nowrap px-4 py-3 text-right text-sm font-semibold tabular-nums text-slate-900">{nf.format(r.payable_amount ?? 0)}</td>
                                                <td className="whitespace-nowrap px-4 py-3">
                                                    <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${cls}`}>{label}</span>
                                                </td>
                                            </tr>
                                        );
                                    })}
                                    {(rows.data ?? []).length === 0 && (
                                        <tr>
                                            <td colSpan="7" className="px-6 py-14 text-center text-sm text-slate-500">
                                                No published rights royalties yet. They appear here once a rights report
                                                (IPRS / PRS / TPL) is matched to your catalogue by ISRC and published.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        <Pagination links={rows.links ?? []} from={rows.from} to={rows.to} total={rows.total} />
                    </section>
                </div>
            )}
        </PanelLayout>
    );
}

function Card({ label, value, strong = false }) {
    return (
        <div className={`rounded-2xl border p-5 shadow-sm ${strong ? 'border-violet-200 bg-violet-50' : 'border-slate-200 bg-white'}`}>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{label}</div>
            <div className={`mt-2 text-2xl font-bold ${strong ? 'text-violet-900' : 'text-slate-900'}`}>{value}</div>
        </div>
    );
}

function Pagination({ links = [], from, to, total }) {
    if (!links.length) return null;
    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
            <div className="text-sm text-slate-500">{from ?? 0}–{to ?? 0} of {nf.format(total ?? 0)}</div>
            <div className="flex flex-wrap gap-1">
                {links.map((link, i) => (
                    <button
                        key={`${link.label}-${i}`}
                        type="button"
                        disabled={!link.url}
                        onClick={() => link.url && router.visit(link.url, { preserveState: true, preserveScroll: true })}
                        className={[
                            'min-w-9 rounded-lg border px-3 py-2 text-sm',
                            link.active ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            !link.url ? 'cursor-not-allowed opacity-40' : '',
                        ].join(' ')}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ))}
            </div>
        </div>
    );
}
