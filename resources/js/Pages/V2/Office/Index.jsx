import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W127 MIXXTUNE_OFFICE: Super Admin overview - today's board + review queue */
const STATE = {
    working: ['Working', 'bg-emerald-100 text-emerald-700', '🟢'],
    idle: ['Idle', 'bg-amber-100 text-amber-700', '🟡'],
    break: ['Break', 'bg-blue-100 text-blue-700', '☕'],
    done: ['Clocked out', 'bg-slate-100 text-slate-500', '✔'],
    off: ['Not in', 'bg-slate-100 text-slate-400', '⚫'],
};

export default function Index({ board = [], review = [], counts = {} }) {
    const [note, setNote] = useState({});
    const post = (url, data = {}) => router.post(url, data, { preserveScroll: true });

    const Card = ({ label, value, href }) => (
        <Link href={href || '#'} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm hover:border-violet-300">
            <div className="text-sm text-slate-500">{label}</div>
            <div className="mt-1 text-2xl font-bold text-slate-900">{value}</div>
        </Link>
    );

    return (
        <PanelLayout role="super_admin" title="Office" subtitle="Who is working, and the proofs waiting for your review">
            <Head title="Office" />
            <PageMessages />
            <div className="space-y-6">
                <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <Card label="Staff" value={counts.staff ?? 0} href="/office/admin/staff" />
                    <Card label="Open tasks" value={counts.open ?? 0} />
                    <Card label="Waiting for review" value={counts.review ?? 0} />
                    <Card label="Overdue" value={counts.overdue ?? 0} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 p-4 font-bold text-slate-900">Today&apos;s board</div>
                    {board.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">No staff yet. <Link href="/office/admin/staff" className="font-semibold text-violet-700">Add staff →</Link></div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {board.map((s) => {
                                const [label, cls, dot] = STATE[s.state] ?? STATE.off;
                                return (
                                    <div key={s.id} className="flex items-center justify-between gap-3 p-4">
                                        <div className="min-w-0">
                                            <div className="font-semibold text-slate-900">{dot} {s.name}</div>
                                            <div className="text-xs text-slate-500">{s.clock_in ? `in ${s.clock_in} · ` : ''}active {s.active} · {s.open_tasks} open task{s.open_tasks === 1 ? '' : 's'}</div>
                                        </div>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${cls}`}>{label}</span>
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-100 p-4 font-bold text-slate-900">Waiting for review ({review.length})</div>
                    {review.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">Nothing to review right now.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {review.map((t) => (
                                <div key={t.id} className="p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <div className="font-semibold text-slate-900">{t.title}</div>
                                            <div className="text-xs text-slate-500">{t.who} · submitted {t.submitted}</div>
                                            {t.proofs.map((p, i) => (
                                                <div key={i} className="mt-1 text-xs">
                                                    {p.link && <a href={p.link} target="_blank" rel="noreferrer" className="font-semibold text-violet-700 hover:underline">Open link ↗</a>}
                                                    {p.path && <a href={p.path} target="_blank" rel="noreferrer" className="font-semibold text-violet-700 hover:underline">Open file ↗</a>}
                                                    {p.note && <span className="text-slate-600"> {p.note}</span>}
                                                </div>
                                            ))}
                                        </div>
                                        <div className="flex gap-2">
                                            <button onClick={() => post(`/office/admin/tasks/${t.id}/verify`)} className="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700">Approve</button>
                                        </div>
                                    </div>
                                    <div className="mt-2 flex gap-2">
                                        <input value={note[t.id] || ''} onChange={(e) => setNote({ ...note, [t.id]: e.target.value })} placeholder="Reason to send back…" className="flex-1 rounded-lg border border-slate-200 px-3 py-1.5 text-xs" />
                                        <button onClick={() => note[t.id]?.trim() && post(`/office/admin/tasks/${t.id}/changes`, { note: note[t.id] })} className="rounded-lg border border-orange-200 bg-orange-50 px-3 py-1.5 text-xs font-semibold text-orange-800 hover:bg-orange-100">Ask changes</button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </PanelLayout>
    );
}
