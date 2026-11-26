import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W229 - the distributor's "My Network": the whole tree on one page, and a notice to all sub-labels.
 */
const inr = (v) => '₹' + Number(v || 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
const num = (v) => Number(v || 0).toLocaleString('en-IN', { maximumFractionDigits: 0 });
const MON = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
const monthName = (m) => {
    if (!m || m === '0000-00') return 'No month';
    const [y, mo] = String(m).split('-').map(Number);
    return MON[mo - 1] + ' ' + y;
};
const when = (v) => {
    if (!v) return 'never';
    const d = new Date(String(v).replace(' ', 'T') + (String(v).includes('T') || String(v).includes('Z') ? '' : 'Z'));
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric' });
};
const STATE = {
    draft: ['Draft', 'bg-slate-100 text-slate-600'], submitted: ['In review', 'bg-amber-100 text-amber-800'],
    changes_requested: ['Changes asked', 'bg-sky-100 text-sky-700'], approved: ['Approved', 'bg-emerald-100 text-emerald-700'],
    processing: ['Processing', 'bg-violet-100 text-violet-700'], delivered: ['Delivered', 'bg-emerald-100 text-emerald-700'],
    live: ['Live', 'bg-emerald-600 text-white'], rejected: ['Rejected', 'bg-red-100 text-red-700'], taken_down: ['Taken down', 'bg-slate-200 text-slate-700'],
};
const input = 'mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm text-slate-900';

export default function Network({ role = 'label', tiles = {}, subLabels = [], trend = [], recent = [] }) {
    const { errors = {} } = usePage().props;
    const [title, setTitle] = useState('');
    const [message, setMessage] = useState('');
    const [to, setTo] = useState('');
    const [busy, setBusy] = useState(false);
    const maxEarning = Math.max(1, ...trend.map((t) => Number(t.earning) || 0));

    const send = () => {
        if (busy) return;
        setBusy(true);
        router.post('/label/distributor/network/notice', { title, message, label_id: to || null }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length && !page?.props?.flash?.error) { setTitle(''); setMessage(''); } },
        });
    };

    const Tile = ({ title: t, value, hint, href, tone = 'text-slate-900' }) => {
        const body = (
            <>
                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">{t}</div>
                <div className={`mt-2 text-2xl font-bold ${tone}`}>{value}</div>
                {hint && <div className="mt-1 text-xs text-slate-500">{hint}</div>}
            </>
        );
        const cls = 'block rounded-2xl border border-slate-200 bg-white p-5 shadow-sm';
        return href ? <Link href={href} className={`${cls} hover:border-violet-300`}>{body}</Link> : <div className={cls}>{body}</div>;
    };

    return (
        <PanelLayout role={role} title="My Network" subtitle="Your sub-labels, their releases and the money of your whole tree">
            <Head title="My Network" />

            <div className="space-y-5">
                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <Tile title="Sub-labels" value={num(tiles.sub_labels)} hint="Add or change them on Sub-labels" href="/label/distributor/sub-labels" />
                    <Tile title="Releases in your tree" value={num(tiles.releases)} hint={`${num(tiles.live)} live · ${num(tiles.own_releases)} on your own label`} />
                    <Tile title="Waiting for your approval" value={num(tiles.waiting_for_you)} hint="Sent by your sub-labels" href="/label/distributor/review" tone={tiles.waiting_for_you > 0 ? 'text-amber-700' : 'text-slate-900'} />
                    <Tile title="Due to sub-labels" value={inr(tiles.due)} hint={`Their share ${inr(tiles.share)} · you keep ${inr(tiles.kept)}`} href="/label/distributor/payouts" tone={tiles.due > 0 ? 'text-amber-700' : 'text-slate-900'} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4"><h2 className="text-lg font-semibold text-slate-900">Sub-labels</h2></div>
                    <div className="overflow-x-auto">
                        <table className="min-w-full divide-y divide-slate-200 text-sm">
                            <thead className="bg-slate-50">
                                <tr className="text-xs uppercase tracking-wide text-slate-500">
                                    <th className="px-5 py-3 text-left">Sub-label</th><th className="px-5 py-3 text-left">Releases</th><th className="px-5 py-3 text-right">Streams</th>
                                    <th className="px-5 py-3 text-right">Your earning</th><th className="px-5 py-3 text-right">Its share</th><th className="px-5 py-3 text-right">Due</th><th className="px-5 py-3 text-left">Last login</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {subLabels.length === 0 && <tr><td colSpan="7" className="px-5 py-12 text-center text-slate-500">No sub-label yet. <Link href="/label/distributor/sub-labels" className="font-semibold text-violet-700 hover:underline">Add the first one</Link>.</td></tr>}
                                {subLabels.map((s) => (
                                    <tr key={s.id}>
                                        <td className="px-5 py-3">
                                            <div className="font-semibold text-slate-900">{s.name}</div>
                                            <div className="text-xs text-slate-500">{s.owner || 'no login'}{s.percent !== null ? ` · ${s.percent}%` : ''}{!s.joined && s.owner ? ' · invitation not accepted' : ''}</div>
                                        </td>
                                        <td className="px-5 py-3 text-slate-700">{num(s.releases)} <span className="text-xs text-slate-500">({num(s.live)} live, {num(s.in_review)} in review, {num(s.drafts)} draft)</span></td>
                                        <td className="px-5 py-3 text-right text-slate-600">{num(s.streams)}</td>
                                        <td className="px-5 py-3 text-right text-slate-700">{inr(s.earning)}</td>
                                        <td className="px-5 py-3 text-right font-semibold text-slate-900">{inr(s.share)}</td>
                                        <td className={`px-5 py-3 text-right font-bold ${s.due > 0 ? 'text-amber-700' : 'text-slate-500'}`}>{s.due < 0 ? `${inr(-s.due)} advance` : inr(s.due)}</td>
                                        <td className="px-5 py-3 text-slate-600">{when(s.last_login)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-5 xl:grid-cols-2">
                    <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-lg font-semibold text-slate-900">Sub-label songs, month by month</h2>
                        <p className="mt-1 text-xs text-slate-500">Your earning on your sub-labels' songs, from published reports.</p>
                        <div className="mt-4 space-y-2">
                            {trend.length === 0 && <p className="py-8 text-center text-sm text-slate-500">No published report for your sub-labels yet.</p>}
                            {trend.map((t) => (
                                <div key={t.month} className="flex items-center gap-3 text-sm">
                                    <div className="w-20 shrink-0 text-slate-600">{monthName(t.month)}</div>
                                    <div className="h-3 flex-1 overflow-hidden rounded-full bg-slate-100"><div className="h-full rounded-full bg-violet-500" style={{ width: `${Math.max(2, (Number(t.earning) / maxEarning) * 100)}%` }} /></div>
                                    <div className="w-28 shrink-0 text-right font-semibold text-slate-900">{inr(t.earning)}</div>
                                    <div className="hidden w-24 shrink-0 text-right text-xs text-slate-500 md:block">{num(t.streams)} streams</div>
                                </div>
                            ))}
                        </div>
                    </section>

                    <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-200 px-5 py-4"><h2 className="text-lg font-semibold text-slate-900">Latest releases in your tree</h2></div>
                        <ul className="divide-y divide-slate-100">
                            {recent.length === 0 && <li className="px-5 py-10 text-center text-sm text-slate-500">No release yet.</li>}
                            {recent.map((r) => {
                                const [label, cls] = STATE[r.status] || [r.status, 'bg-slate-100 text-slate-600'];
                                return (
                                    <li key={r.id} className="flex items-center justify-between gap-3 px-5 py-3">
                                        <div className="min-w-0">
                                            <Link href={`/releases/${r.id}`} className="block truncate text-sm font-semibold text-slate-900 hover:text-violet-700">{r.title}</Link>
                                            <div className="truncate text-xs text-slate-500">{r.artist || '—'} · {r.label || '—'} · {when(r.at)}</div>
                                        </div>
                                        <span className={`shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${cls}`}>{label}</span>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">Send a notice to your sub-labels</h2>
                    <p className="mt-1 text-sm text-slate-500">It shows in their bell, with your name. Plain text only.</p>
                    <div className="mt-3 grid gap-3 lg:grid-cols-3">
                        <label className="text-sm font-semibold text-slate-700">To
                            <select value={to} onChange={(e) => setTo(e.target.value)} className={input}>
                                <option value="">All sub-labels</option>
                                {subLabels.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                            </select>{errors.label_id && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.label_id}</span>}
                        </label>
                        <label className="text-sm font-semibold text-slate-700 lg:col-span-2">Title
                            <input value={title} onChange={(e) => setTitle(e.target.value)} maxLength={120} placeholder="e.g. October payments sent" className={input} />{errors.title && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.title}</span>}
                        </label>
                        <label className="text-sm font-semibold text-slate-700 lg:col-span-3">Message
                            <textarea value={message} onChange={(e) => setMessage(e.target.value)} maxLength={1000} rows={3} className={input} />{errors.message && <span className="mt-1 block text-xs font-semibold text-rose-600">{errors.message}</span>}
                        </label>
                    </div>
                    <div className="mt-3 flex justify-end">
                        <button type="button" disabled={busy || title.trim().length < 3 || message.trim().length < 3 || subLabels.length === 0} onClick={send} className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50">{busy ? 'Sending…' : 'Send notice'}</button>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
