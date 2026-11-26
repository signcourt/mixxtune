import { Head, router, usePage } from '@inertiajs/react';
import { Fragment, useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W226 - the distributor's "Sub-label payouts".
 * Mixx Tune pays the distributor for the whole tree. This page shows what each sub-label has earned
 * (its share), what the distributor has paid it and what is still due. A payment is a register entry only.
 */
const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v) => Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
const monthName = (m) => {
    if (!m || m === '0000-00') return 'No month';
    const [y, mo] = String(m).split('-').map(Number);
    return ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][mo - 1] + ' ' + y;
};
const day = (d) => {
    if (!d) return '—';
    const [y, m, dd] = String(d).slice(0, 10).split('-').map(Number);
    return `${String(dd).padStart(2, '0')} ${['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'][m - 1]} ${y}`;
};
const METHOD = { bank_transfer: 'Bank transfer', upi: 'UPI', cash: 'Cash', cheque: 'Cheque', other: 'Other' };
const input = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900';

export default function Payouts({ role = 'label', ledger = { labels: [], totals: {} }, methods = [], today = '' }) {
    const { errors = {} } = usePage().props;
    const [open, setOpen] = useState(null); /* sub-label id whose details are open */
    const [pay, setPay] = useState(null); /* { label, amount, paid_on, method, reference, note } */
    const [cancel, setCancel] = useState(null); /* { payment, reason } */
    const [busy, setBusy] = useState(false);

    const t = ledger.totals || {};
    const labels = ledger.labels || [];

    const savePayment = () => {
        if (busy || !pay) return;
        setBusy(true);
        router.post('/label/distributor/payouts', { label_id: pay.label.id, amount: pay.amount, paid_on: pay.paid_on, method: pay.method, reference: pay.reference, note: pay.note }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length) setPay(null); },
        });
    };
    const saveCancel = () => {
        if (busy || !cancel) return;
        setBusy(true);
        router.post(`/label/distributor/payouts/${cancel.payment.id}/cancel`, { reason: cancel.reason }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length) setCancel(null); },
        });
    };
    const err = (k) => (errors[k] ? <span className="mt-1 block text-xs font-semibold text-rose-600">{errors[k]}</span> : null);

    const Tile = ({ title, value, hint, tone = 'text-slate-900' }) => (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{title}</div>
            <div className={`mt-2 text-2xl font-bold ${tone}`}>{value}</div>
            {hint && <div className="mt-1 text-xs text-slate-500">{hint}</div>}
        </div>
    );

    return (
        <PanelLayout role={role} title="Sub-label Payouts" subtitle="What each sub-label has earned, what you have paid it, and what is still due.">
            <Head title="Sub-label Payouts" />

            <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Tile title="Your earning on sub-label songs" value={inr(t.earning)} hint="Already in your wallet from Mixx Tune" />
                    <Tile title="Sub-labels' share" value={inr(t.share)} hint="What you owe them in total" />
                    <Tile title="Paid by you" value={inr(t.paid)} hint="From your register below" tone="text-emerald-700" />
                    <Tile title="Still due" value={inr(t.balance)} hint={`You keep ${inr(t.kept)}`} tone={Number(t.balance) > 0 ? 'text-amber-700' : 'text-slate-900'} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-semibold text-slate-900">Sub-labels</h2>
                        <p className="mt-1 text-sm text-slate-500">Mixx Tune pays you for the whole tree. You pay your sub-labels yourself and write each payment here, so both of you see the same account.</p>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200">
                            <thead className="bg-slate-50">
                                <tr>
                                    {['Sub-label', 'Share now', 'Your earning', 'Its share', 'Paid', 'Due', ''].map((h, i) => (
                                        <th key={i} className={`px-5 py-4 text-xs font-semibold uppercase tracking-wide text-slate-500 ${i >= 2 && i <= 5 ? 'text-right' : 'text-left'}`}>{h}</th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {labels.length === 0 && <tr><td colSpan="7" className="px-5 py-14 text-center text-sm text-slate-500">No sub-label yet. Add one on the Sub-labels page.</td></tr>}
                                {labels.map((l) => (
                                    <Fragment key={l.id}>
                                        <tr>
                                            <td className="px-5 py-4">
                                                <div className="font-semibold text-slate-900">{l.name}</div>
                                                <div className="text-xs text-slate-500">{num(l.streams)} streams</div>
                                            </td>
                                            <td className="px-5 py-4 text-sm text-slate-700">{l.percent !== null ? `${l.percent}%` : <span className="font-semibold text-rose-600">not set</span>}</td>
                                            <td className="px-5 py-4 text-right text-sm text-slate-700">{inr(l.earning)}</td>
                                            <td className="px-5 py-4 text-right text-sm font-semibold text-slate-900">{inr(l.share)}</td>
                                            <td className="px-5 py-4 text-right text-sm text-emerald-700">{inr(l.paid)}</td>
                                            <td className={`px-5 py-4 text-right text-sm font-bold ${l.balance > 0 ? 'text-amber-700' : l.balance < 0 ? 'text-sky-700' : 'text-slate-500'}`}>{l.balance < 0 ? `${inr(-l.balance)} advance` : inr(l.balance)}</td>
                                            <td className="px-5 py-4">
                                                <div className="flex flex-wrap justify-end gap-2">
                                                    <button type="button" onClick={() => setOpen(open === l.id ? null : l.id)} className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{open === l.id ? 'Hide' : 'Details'}</button>
                                                    <button type="button" onClick={() => setPay({ label: l, amount: l.balance > 0 ? String(l.balance) : '', paid_on: today, method: methods[0] || 'bank_transfer', reference: '', note: '' })} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-violet-700">Write a payment</button>
                                                </div>
                                            </td>
                                        </tr>
                                        {open === l.id && (
                                            <tr>
                                                <td colSpan="7" className="bg-slate-50 px-5 py-5">
                                                    <div className="grid gap-5 xl:grid-cols-2">
                                                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                            <div className="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900">Month by month</div>
                                                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                                                <thead><tr className="text-xs uppercase text-slate-500"><th className="px-4 py-2 text-left">Report month</th><th className="px-4 py-2 text-right">Streams</th><th className="px-4 py-2 text-right">Your earning</th><th className="px-4 py-2 text-right">Share %</th><th className="px-4 py-2 text-right">Its share</th></tr></thead>
                                                                <tbody className="divide-y divide-slate-100">
                                                                    {l.months.length === 0 && <tr><td colSpan="5" className="px-4 py-6 text-center text-slate-500">No published report for this sub-label yet.</td></tr>}
                                                                    {l.months.map((m) => (
                                                                        <tr key={m.month}><td className="px-4 py-2 text-slate-700">{monthName(m.month)}</td><td className="px-4 py-2 text-right text-slate-600">{num(m.streams)}</td><td className="px-4 py-2 text-right text-slate-600">{inr(m.earning)}</td><td className="px-4 py-2 text-right text-slate-600">{m.percent !== null ? `${m.percent}%` : '—'}</td><td className="px-4 py-2 text-right font-semibold text-slate-900">{inr(m.share)}</td></tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                        <div className="overflow-hidden rounded-xl border border-slate-200 bg-white">
                                                            <div className="border-b border-slate-200 px-4 py-3 text-sm font-semibold text-slate-900">Payment register</div>
                                                            <table className="min-w-full divide-y divide-slate-100 text-sm">
                                                                <thead><tr className="text-xs uppercase text-slate-500"><th className="px-4 py-2 text-left">Date</th><th className="px-4 py-2 text-left">How</th><th className="px-4 py-2 text-left">Reference / note</th><th className="px-4 py-2 text-right">Amount</th><th className="px-4 py-2" /></tr></thead>
                                                                <tbody className="divide-y divide-slate-100">
                                                                    {l.payments.length === 0 && <tr><td colSpan="5" className="px-4 py-6 text-center text-slate-500">No payment written yet.</td></tr>}
                                                                    {l.payments.map((p) => (
                                                                        <tr key={p.id} className={p.cancelled ? 'text-slate-400' : 'text-slate-700'}>
                                                                            <td className="px-4 py-2">{day(p.paid_on)}</td>
                                                                            <td className="px-4 py-2">{METHOD[p.method] || p.method || '—'}</td>
                                                                            <td className="px-4 py-2">{[p.reference, p.note].filter(Boolean).join(' · ') || '—'}{p.cancelled && <div className="text-xs font-semibold text-rose-500">Cancelled: {p.cancel_reason}</div>}</td>
                                                                            <td className={`px-4 py-2 text-right font-semibold ${p.cancelled ? 'line-through' : ''}`}>{inr(p.amount)}</td>
                                                                            <td className="px-4 py-2 text-right">{!p.cancelled && <button type="button" onClick={() => setCancel({ payment: p, reason: '' })} className="text-xs font-semibold text-rose-600 hover:underline">Cancel entry</button>}</td>
                                                                        </tr>
                                                                    ))}
                                                                </tbody>
                                                            </table>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        )}
                                    </Fragment>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <p className="border-t border-slate-200 px-5 py-3 text-xs text-slate-500">A month keeps the share % it had when its report came. Changing a share later does not move old months. An entry is never deleted - a wrong one is cancelled and stays visible.</p>
                </section>
            </div>

            {pay && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4">
                    <div className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-bold text-slate-900">Write a payment to {pay.label.name}</h3>
                        <p className="mt-1 text-sm text-slate-500">This only writes it in the register. Pay the sub-label yourself (bank, UPI…). Due now: <b>{inr(Math.max(0, pay.label.balance))}</b></p>
                        <div className="mt-4 grid gap-4 md:grid-cols-2">
                            <label className="text-sm font-semibold text-slate-700">Amount (₹) <span className="text-rose-600">*</span>
                                <input type="number" min="0.01" step="0.01" value={pay.amount} onChange={(e) => setPay({ ...pay, amount: e.target.value })} className={input} />{err('amount')}
                            </label>
                            <label className="text-sm font-semibold text-slate-700">Paid on <span className="text-rose-600">*</span>
                                <input type="date" max={today} value={pay.paid_on} onChange={(e) => setPay({ ...pay, paid_on: e.target.value })} className={input} />{err('paid_on')}
                            </label>
                            <label className="text-sm font-semibold text-slate-700">How <span className="text-rose-600">*</span>
                                <select value={pay.method} onChange={(e) => setPay({ ...pay, method: e.target.value })} className={input}>
                                    {methods.map((m) => <option key={m} value={m}>{METHOD[m] || m}</option>)}
                                </select>{err('method')}
                            </label>
                            <label className="text-sm font-semibold text-slate-700">UTR / reference
                                <input value={pay.reference} onChange={(e) => setPay({ ...pay, reference: e.target.value })} maxLength={120} className={input} />{err('reference')}
                            </label>
                            <label className="text-sm font-semibold text-slate-700 md:col-span-2">Note
                                <input value={pay.note} onChange={(e) => setPay({ ...pay, note: e.target.value })} maxLength={500} placeholder="e.g. for Sep + Oct 2026" className={input} />{err('note')}
                            </label>
                        </div>
                        {Number(pay.amount) > Math.max(0, pay.label.balance) && Number(pay.amount) > 0 && <p className="mt-3 text-xs font-semibold text-amber-700">This is more than what is due now - the extra will show as an advance.</p>}
                        {err('label_id')}
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setPay(null)} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Close</button>
                            <button type="button" disabled={busy || !(Number(pay.amount) > 0) || !pay.paid_on} onClick={savePayment} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{busy ? 'Saving…' : 'Save in register'}</button>
                        </div>
                    </div>
                </div>
            )}

            {cancel && (
                <div className="fixed inset-0 z-50 grid place-items-center bg-slate-900/50 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl">
                        <h3 className="text-lg font-bold text-slate-900">Cancel this entry?</h3>
                        <p className="mt-1 text-sm text-slate-500">{inr(cancel.payment.amount)} on {day(cancel.payment.paid_on)}. It stays in the register, marked as cancelled, and no longer counts as paid.</p>
                        <label className="mt-4 block text-sm font-semibold text-slate-700">Reason <span className="text-rose-600">*</span>
                            <input value={cancel.reason} onChange={(e) => setCancel({ ...cancel, reason: e.target.value })} maxLength={300} placeholder="e.g. wrong amount typed" className={input} />{err('reason')}
                        </label>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setCancel(null)} className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700">Close</button>
                            <button type="button" disabled={busy || cancel.reason.trim().length < 3} onClick={saveCancel} className="rounded-xl bg-rose-600 px-5 py-2.5 text-sm font-bold text-white disabled:opacity-50">{busy ? 'Saving…' : 'Cancel entry'}</button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
