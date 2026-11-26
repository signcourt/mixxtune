import { Head, Link } from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W226 - "My Earnings" of a sub-label under a distributor: its own share month by month, the payments its
 * distributor has written for it, and what is still due. No withdrawal here - the distributor pays.
 */
const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v) => Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const monthName = (m) => {
    if (!m || m === '0000-00') return 'No month';
    const [y, mo] = String(m).split('-').map(Number);
    return MON[mo - 1] + ' ' + y;
};
const day = (d) => {
    if (!d) return '—';
    const [y, m, dd] = String(d).slice(0, 10).split('-').map(Number);
    return `${String(dd).padStart(2, '0')} ${MON[m - 1]} ${y}`;
};
const METHOD = { bank_transfer: 'Bank transfer', upi: 'UPI', cash: 'Cash', cheque: 'Cheque', other: 'Other' };

export default function MyEarnings({ role = 'label', label = '', paidBy = 'your distributor', mine = {}, month = null, songs = [] }) {
    const months = mine.months || [];
    const payments = mine.payments || [];
    const balance = Number(mine.balance || 0);

    const Tile = ({ title, value, hint, tone = 'text-slate-900' }) => (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</div>
            <div className={`mt-2 text-2xl font-bold ${tone}`}>{value}</div>
            {hint && <div className="mt-1 text-xs text-slate-500">{hint}</div>}
        </div>
    );

    return (
        <PanelLayout role={role} title="My Earnings" subtitle={`Earnings of ${label}. You are paid by ${paidBy}.`}>
            <Head title="My Earnings" />

            <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Tile title="Total earned" value={inr(mine.share)} hint="From all published reports" />
                    <Tile title="Received" value={inr(mine.paid)} hint={`Payments written by ${paidBy}`} tone="text-emerald-700" />
                    <Tile title={balance < 0 ? 'Advance with you' : 'Still to receive'} value={inr(Math.abs(balance))} tone={balance > 0 ? 'text-amber-700' : 'text-slate-900'} />
                    <Tile title="Streams" value={num(mine.streams)} />
                </div>

                <div className="rounded-2xl border border-sky-200 bg-sky-50 px-5 py-4 text-sm text-sky-900">
                    You are paid by <b>{paidBy}</b>, not through a wallet here. For a payment or a question about an amount, write to {paidBy}.
                </div>

                <div className="grid gap-5 xl:grid-cols-2">
                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4"><h2 className="text-lg font-semibold text-slate-900">Month by month</h2></div>
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-slate-50"><tr className="text-xs uppercase text-slate-500"><th className="px-5 py-3 text-left">Report month</th><th className="px-5 py-3 text-right">Streams</th><th className="px-5 py-3 text-right">Your earning</th><th className="px-5 py-3" /></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {months.length === 0 && <tr><td colSpan="4" className="px-5 py-12 text-center text-slate-500">No published report yet. Your first earning shows here when a report is published.</td></tr>}
                                {months.map((m) => (
                                    <tr key={m.month} className={month === m.month ? 'bg-violet-50' : ''}>
                                        <td className="px-5 py-3 font-semibold text-slate-800">{monthName(m.month)}</td>
                                        <td className="px-5 py-3 text-right text-slate-600">{num(m.streams)}</td>
                                        <td className="px-5 py-3 text-right font-semibold text-slate-900">{inr(m.share)}</td>
                                        <td className="px-5 py-3 text-right">{m.month !== '0000-00' && <Link href={`/label/my-earnings?month=${m.month}`} preserveScroll className="text-xs font-semibold text-violet-700 hover:underline">Songs</Link>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4"><h2 className="text-lg font-semibold text-slate-900">Payments received</h2></div>
                        <table className="min-w-full divide-y divide-slate-100 text-sm">
                            <thead className="bg-slate-50"><tr className="text-xs uppercase text-slate-500"><th className="px-5 py-3 text-left">Date</th><th className="px-5 py-3 text-left">How</th><th className="px-5 py-3 text-left">Reference / note</th><th className="px-5 py-3 text-right">Amount</th></tr></thead>
                            <tbody className="divide-y divide-slate-100">
                                {payments.length === 0 && <tr><td colSpan="4" className="px-5 py-12 text-center text-slate-500">No payment written yet.</td></tr>}
                                {payments.map((p) => (
                                    <tr key={p.id}>
                                        <td className="px-5 py-3 text-slate-700">{day(p.paid_on)}</td>
                                        <td className="px-5 py-3 text-slate-600">{METHOD[p.method] || p.method || '—'}</td>
                                        <td className="px-5 py-3 text-slate-600">{[p.reference, p.note].filter(Boolean).join(' · ') || '—'}</td>
                                        <td className="px-5 py-3 text-right font-semibold text-emerald-700">{inr(p.amount)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </section>
                </div>

                {month && (
                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 px-5 py-4">
                            <h2 className="text-lg font-semibold text-slate-900">Songs - {monthName(month)}</h2>
                            <a href={`/label/my-earnings/export?month=${month}`} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Download CSV</a>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                <thead className="bg-slate-50"><tr className="text-xs uppercase text-slate-500"><th className="px-5 py-3 text-left">Song</th><th className="px-5 py-3 text-left">ISRC</th><th className="px-5 py-3 text-left">Store</th><th className="px-5 py-3 text-right">Streams</th><th className="px-5 py-3 text-right">Your earning</th></tr></thead>
                                <tbody className="divide-y divide-slate-100">
                                    {songs.length === 0 && <tr><td colSpan="5" className="px-5 py-10 text-center text-slate-500">Nothing for this month.</td></tr>}
                                    {songs.map((s, i) => (
                                        <tr key={i}><td className="px-5 py-2.5 text-slate-800">{s.title}</td><td className="px-5 py-2.5 text-slate-500">{s.isrc || '—'}</td><td className="px-5 py-2.5 text-slate-600">{s.platform}</td><td className="px-5 py-2.5 text-right text-slate-600">{num(s.streams)}</td><td className="px-5 py-2.5 text-right font-semibold text-slate-900">{inr(s.share)}</td></tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        {songs.length >= 300 && <p className="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">Showing the top 300 lines. The CSV has all of them.</p>}
                    </section>
                )}
            </div>
        </PanelLayout>
    );
}
