import { router, usePage } from '@inertiajs/react';
import { Radio } from 'lucide-react';
import { useState } from 'react';

/*
 * W218 - one-click Live (Mixx Tune team only).
 * Shown on approved / processing / delivered / failed releases. One press -> e-mailed code -> press again:
 * every store row is marked live and the release goes live. Nothing is deleted.
 */
const CAN = ['approved', 'processing', 'delivered', 'failed'];

export default function ReleaseGoLiveButton({ release, role = '', compact = false }) {
    const [open, setOpen] = useState(false);
    const [note, setNote] = useState('');
    const [busy, setBusy] = useState(false);
    const { errors = {} } = usePage().props;
    const err = errors.go_live || errors.note || '';

    const status = String(release?.status || '');
    if (!['super_admin', 'admin'].includes(role)) return null;
    if (!CAN.includes(status)) return null;

    const hasUpc = Boolean(release?.upc);

    const send = () => {
        if (busy) return;
        setBusy(true);
        router.post(`/admin/releases/${release.id}/go-live`, { note }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setOpen(false) });
    };

    return (
        <>
            {compact ? (
                <button type="button" onClick={() => setOpen(true)} title={hasUpc ? 'Mark live (one click)' : 'Mark live - enter the UPC first'} aria-label="Mark live" className={`inline-flex h-9 w-9 items-center justify-center rounded-lg border transition ${hasUpc ? 'border-emerald-200 bg-emerald-50 text-emerald-700 hover:bg-emerald-100' : 'border-slate-200 bg-slate-50 text-slate-400 hover:bg-slate-100'}`}>
                    <Radio className="h-4 w-4" />
                </button>
            ) : (
                <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-800 transition hover:bg-emerald-100">
                    <Radio className="h-4 w-4" /> Mark live
                </button>
            )}

            {open && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-label="Mark live">
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-2xl">
                        <div className="flex items-start gap-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-emerald-100 text-emerald-700"><Radio className="h-5 w-5" /></span>
                            <div>
                                <div className="text-base font-bold text-slate-900">Mark live: "{release.title || 'Untitled'}"</div>
                                <p className="mt-1 text-sm leading-6 text-slate-600">
                                    All chosen stores are marked live in one go and the release turns <b>Live</b>; the owner gets a bell. The store rows stay on the release for later. Nothing is deleted.
                                </p>
                            </div>
                        </div>

                        <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                            <div>UPC: <b>{release.upc || 'not entered yet'}</b>{!hasUpc && <span className="ml-1 font-semibold text-rose-600">- enter it first (ISRC &amp; UPC → Pending UPC)</span>}</div>
                            <div className="mt-0.5">ISRC: new releases get one automatically; old (WordPress) releases need it typed first.</div>
                        </div>

                        <label className="mt-3 block text-sm font-semibold text-slate-700">
                            Note (optional)
                            <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} maxLength={1000} placeholder="e.g. delivered through the aggregator on 25 Nov" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-emerald-400 focus:outline-none" />
                        </label>
                        {err && <p className="mt-2 text-xs font-semibold text-rose-600">{err}</p>}
                        <p className="mt-2 text-xs text-slate-500">A code is e-mailed to you to confirm. Enter it in the box that opens, then press the button again.</p>

                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" disabled={busy} onClick={send} className="rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50">
                                Mark live now
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
