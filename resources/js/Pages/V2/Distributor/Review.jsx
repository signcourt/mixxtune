import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W224 - the distributor's review page.
 * A sub-label's release waits here. Approve -> it goes to Mixx Tune for review.
 * Ask for changes / Reject -> back to the sub-label with your note.
 */
const fmt = (v) => {
    if (!v) return '—';
    const d = new Date(String(v).replace(' ', 'T') + (String(v).includes('T') || String(v).includes('Z') ? '' : 'Z'));
    return Number.isNaN(d.getTime()) ? String(v) : d.toLocaleString('en-GB', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true });
};
const BADGE = {
    pending: ['Waiting for you', 'bg-amber-100 text-amber-800'],
    approved: ['Approved - with Mixx Tune', 'bg-emerald-100 text-emerald-700'],
    changes: ['Changes asked', 'bg-sky-100 text-sky-700'],
    rejected: ['Rejected', 'bg-red-100 text-red-700'],
    closed: ['Closed', 'bg-slate-100 text-slate-600'],
};

export default function Review({ role = 'label', rows = [], pending = 0 }) {
    const { errors = {} } = usePage().props;
    const [dialog, setDialog] = useState(null); /* { row, action } */
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);

    const send = (row, action, text = '') => {
        if (busy) return;
        setBusy(true);
        router.post(`/label/distributor/review/${row.id}`, { action, note: text }, {
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setBusy(false),
            onSuccess: (page) => { if (!Object.keys(page?.props?.errors || {}).length) { setDialog(null); setNote(''); } },
        });
    };

    const waiting = rows.filter((r) => r.status === 'pending');
    const done = rows.filter((r) => r.status !== 'pending');

    const Card = ({ r }) => {
        const [label, cls] = BADGE[r.status] || BADGE.closed;
        return (
            <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 shadow-sm md:flex-row md:items-center">
                <div className="grid h-20 w-20 shrink-0 place-items-center overflow-hidden rounded-xl border border-slate-200 bg-slate-50">
                    {r.artwork ? <img src={r.artwork} alt="" className="h-full w-full object-cover" onError={(e) => { e.currentTarget.style.display = 'none'; }} /> : <span className="text-[10px] text-slate-400">No poster</span>}
                </div>
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="truncate text-base font-bold text-slate-900">{r.title}</span>
                        <span className={`rounded-full px-2.5 py-0.5 text-[11px] font-semibold ${cls}`}>{label}</span>
                    </div>
                    <div className="mt-1 text-sm text-slate-600">{r.artist || '—'} · {r.label || '—'} · {r.type || 'release'} · {r.tracks} track{r.tracks === 1 ? '' : 's'}</div>
                    <div className="mt-1 text-xs text-slate-500">Sent {fmt(r.sent_at)}{r.sent_by ? ` by ${r.sent_by}` : ''}{r.release_date ? ` · release date ${r.release_date}` : ''}{r.upc ? ` · UPC ${r.upc}` : ''}</div>
                    {r.note && <div className="mt-2 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-700"><b>Your note:</b> {r.note}</div>}
                </div>
                <div className="flex shrink-0 flex-wrap gap-2">
                    <Link href={`/releases/${r.release_id}`} className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50">Open release</Link>
                    {r.status === 'pending' && (
                        <>
                            <button type="button" disabled={busy} onClick={() => send(r, 'approve')} className="rounded-lg bg-emerald-600 px-3 py-2 text-xs font-bold text-white hover:bg-emerald-700 disabled:opacity-50">Approve & forward</button>
                            <button type="button" disabled={busy} onClick={() => { setDialog({ row: r, action: 'changes' }); setNote(''); }} className="rounded-lg border border-sky-300 px-3 py-2 text-xs font-semibold text-sky-700 hover:bg-sky-50">Ask for changes</button>
                            <button type="button" disabled={busy} onClick={() => { setDialog({ row: r, action: 'reject' }); setNote(''); }} className="rounded-lg border border-rose-300 px-3 py-2 text-xs font-semibold text-rose-700 hover:bg-rose-50">Reject</button>
                        </>
                    )}
                </div>
            </div>
        );
    };

    return (
        <PanelLayout role={role} title="Review" subtitle="Releases your sub-labels sent - you approve first, then Mixx Tune reviews">
            <Head title="Review" />

            <div className="space-y-6">
                <section>
                    <h2 className="mb-3 text-lg font-semibold text-slate-900">Waiting for you <span className="ml-1 text-sm font-normal text-slate-500">{pending}</span></h2>
                    {waiting.length === 0
                        ? <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-5 py-12 text-center text-sm text-slate-500">Nothing is waiting. New releases from your sub-labels appear here.</div>
                        : <div className="space-y-3">{waiting.map((r) => <Card key={r.id} r={r} />)}</div>}
                </section>

                {done.length > 0 && (
                    <section>
                        <h2 className="mb-3 text-lg font-semibold text-slate-900">Already decided</h2>
                        <div className="space-y-3">{done.map((r) => <Card key={r.id} r={r} />)}</div>
                    </section>
                )}
            </div>

            {dialog && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-2xl">
                        <div className="text-base font-bold text-slate-900">{dialog.action === 'changes' ? 'Ask for changes' : 'Reject'}: "{dialog.row.title}"</div>
                        <p className="mt-1 text-sm text-slate-600">
                            {dialog.action === 'changes'
                                ? 'The release goes back to the sub-label. They correct it and send it again.'
                                : 'The release is rejected for good. The sub-label sees your reason.'}
                        </p>
                        <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={4} maxLength={2000} placeholder={dialog.action === 'changes' ? 'What must be corrected?' : 'Why is it rejected?'} className="mt-3 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900" />
                        {errors.note && <p className="mt-1 text-xs font-semibold text-rose-600">{errors.note}</p>}
                        <div className="mt-4 flex justify-end gap-2">
                            <button type="button" onClick={() => setDialog(null)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" disabled={busy || note.trim().length < 3} onClick={() => send(dialog.row, dialog.action, note)} className={`rounded-xl px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50 ${dialog.action === 'changes' ? 'bg-sky-600 hover:bg-sky-700' : 'bg-rose-600 hover:bg-rose-700'}`}>
                                {dialog.action === 'changes' ? 'Send back' : 'Reject release'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
