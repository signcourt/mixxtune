import { Link, router } from '@inertiajs/react';
import { Ban } from 'lucide-react';
import { useState } from 'react';

/*
 * W210 - Takedown.
 *  super_admin  -> "Take down" (reason + e-mailed code) : status taken_down, stores marked takedown, details stay.
 *  admin (Manager) -> "Request takedown" (reason) : Super Admin decides it under Administration -> Approvals.
 *  everybody else -> nothing is rendered (labels / artists write to their Manager).
 */
const CAN = ['approved', 'processing', 'delivered', 'live', 'failed', 'takedown_requested'];

export default function ReleaseTakedownButton({ release, role = '', compact = false }) {
    const [open, setOpen] = useState(false);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);

    const status = String(release?.status || '');
    const isSuper = role === 'super_admin';
    const isManager = role === 'admin';
    if (!isSuper && !isManager) return null;
    if (!CAN.includes(status)) return null;

    const requested = status === 'takedown_requested';
    const url = isSuper ? `/admin/releases/${release.id}/takedown` : `/admin/releases/${release.id}/takedown-request`;
    const label = isSuper ? 'Take down' : 'Request takedown';

    const send = () => {
        if (busy || reason.trim().length < 3) return;
        setBusy(true);
        router.post(url, { reason }, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: () => setOpen(false) });
    };

    /* a Manager already asked: Super Admin decides it in Approvals, the Manager just sees it is waiting */
    if (requested && !isSuper) {
        return compact ? (
            <span title="Takedown requested - waiting for a Super Admin" className="inline-flex h-9 items-center rounded-lg border border-orange-200 bg-orange-50 px-2 text-[11px] font-semibold text-orange-800">Requested</span>
        ) : (
            <span className="inline-flex items-center gap-2 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm font-semibold text-orange-800"><Ban className="h-4 w-4" /> Takedown requested - waiting for a Super Admin</span>
        );
    }

    return (
        <>
            {compact ? (
                <button type="button" onClick={() => setOpen(true)} title={`${label}${requested ? ' (a Manager asked for it)' : ''}`} aria-label={label} className={`inline-flex h-9 w-9 items-center justify-center rounded-lg border ${requested ? 'border-orange-300 bg-orange-100 text-orange-800' : 'border-amber-200 bg-amber-50 text-amber-700'} transition hover:bg-amber-100`}>
                    <Ban className="h-4 w-4" />
                </button>
            ) : (
                <button type="button" onClick={() => setOpen(true)} className={`inline-flex items-center gap-2 rounded-xl border px-4 py-3 text-sm font-semibold transition ${requested ? 'border-orange-300 bg-orange-100 text-orange-900 hover:bg-orange-200' : 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100'}`}>
                    <Ban className="h-4 w-4" /> {label}{requested && isSuper ? ' (requested by a Manager)' : ''}
                </button>
            )}

            {open && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-label={label}>
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-2xl">
                        <div className="flex items-start gap-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-amber-100 text-amber-700"><Ban className="h-5 w-5" /></span>
                            <div>
                                <div className="text-base font-bold text-slate-900">{label}: "{release.title || 'Untitled'}"</div>
                                <p className="mt-1 text-sm leading-6 text-slate-600">
                                    {isSuper
                                        ? 'The release is taken down from every store and marked Taken Down. Nothing is deleted - tracks, UPC/ISRC, earnings and history stay on the release.'
                                        : 'A Super Admin gets your request under Administration → Approvals. If approved, the release is taken down from every store; its details stay. You get a bell either way.'}
                                </p>
                            </div>
                        </div>
                        <label className="mt-4 block text-sm font-semibold text-slate-700">
                            Reason <span className="text-rose-600">*</span>
                            <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={3} maxLength={1000} placeholder="e.g. rights dispute / owner asked by e-mail on 25 Nov" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-amber-400 focus:outline-none" />
                        </label>
                        {isSuper && <p className="mt-2 text-xs text-slate-500">A code is e-mailed to you to confirm. Enter it in the box that opens, then press the button again.</p>}
                        {requested && isSuper && <p className="mt-2 text-xs text-orange-700">Tip: this release has an open Manager request - deciding it under Administration → Approvals keeps the request record tidy. Taking it down here works too.</p>}
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setOpen(false)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" disabled={busy || reason.trim().length < 3} onClick={send} className="rounded-xl bg-amber-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-amber-700 disabled:cursor-not-allowed disabled:opacity-50">
                                {isSuper ? 'Take down now' : 'Send request'}
                            </button>
                        </div>
                        {isSuper && requested && (
                            <div className="mt-3 text-right text-xs"><Link href="/admin/approvals" className="font-semibold text-indigo-700 hover:underline">Open Approvals instead →</Link></div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
