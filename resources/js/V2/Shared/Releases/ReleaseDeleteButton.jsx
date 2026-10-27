import { router } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';

/*
 * W120 MIXXTUNE_RELEASE_TRASH: Super Admin "Delete release" (any status) -> Trash.
 * Nothing is removed at once: the release waits 90 days in Administration -> Trash.
 */
export default function ReleaseDeleteButton({ release, compact = false }) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const send = () => {
        setBusy(true);
        router.post(`/admin/releases/${release.id}/trash`, { reason }, {
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => setOpen(false),
        });
    };

    return (
        <>
            {compact ? (
                <button type="button" onClick={() => setOpen(true)} title="Delete release (Super Admin)" aria-label="Delete release" className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-rose-200 bg-rose-50 text-rose-600 transition hover:bg-rose-100">
                    <Trash2 className="h-4 w-4" />
                </button>
            ) : (
                <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-semibold text-rose-700 transition hover:bg-rose-100">
                    <Trash2 className="h-4 w-4" /> Delete release
                </button>
            )}

            {open && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-label="Delete release">
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-2xl">
                        <div className="flex items-start gap-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-rose-100 text-rose-600"><Trash2 className="h-5 w-5" /></span>
                            <div>
                                <div className="text-base font-bold text-slate-900">Delete "{release.title || 'Untitled'}"?</div>
                                <p className="mt-1 text-sm leading-6 text-slate-600">
                                    It moves to <b>Trash</b> and disappears from every list, the catalogue and the owner&apos;s panel.
                                    You can restore it for 90 days; after that it is removed for good (tracks, files, everything).
                                </p>
                            </div>
                        </div>
                        <label className="mt-4 block text-sm font-semibold text-slate-700">
                            Reason (optional)
                            <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} maxLength={1000} placeholder="e.g. trial upload" className="mt-1 w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-900 outline-none focus:border-violet-400" />
                        </label>
                        <p className="mt-2 text-xs text-slate-500">A code is e-mailed to you to confirm. A release with revenue / royalty lines cannot be deleted.</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" disabled={busy} onClick={send} className="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50">{busy ? 'Moving…' : 'Move to Trash'}</button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
