import { Head, router } from '@inertiajs/react';
import { RotateCcw, Trash2 } from 'lucide-react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W120 MIXXTUNE_RELEASE_TRASH: Releases -> Trash (Super Admin) */
const art = (p) => (!p ? null : /^(https?:)?\//.test(p) ? p : `/storage/${p}`);
const STATUS = { draft: 'Draft', submitted: 'In Review', changes_requested: 'Changes Requested', approved: 'Approved', processing: 'Processing', delivered: 'Delivered', live: 'Live', rejected: 'Rejected', failed: 'Failed' };

export default function Index({ rows = [], keepDays = 90 }) {
    const [confirm, setConfirm] = useState(null);
    const [busy, setBusy] = useState(false);
    const post = (url, after) => {
        setBusy(true);
        router.post(url, {}, { preserveScroll: true, onFinish: () => setBusy(false), onSuccess: after });
    };

    return (
        <PanelLayout role="super_admin" title="Trash" subtitle={`Deleted releases wait here ${keepDays} days - restore them or delete them for good`}>
            <Head title="Trash - Releases" />
            <PageMessages />
            <div className="space-y-4">
                <div className="rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    A release in the Trash is hidden everywhere (lists, catalogue, owner&apos;s panel). After {keepDays} days it is deleted for good by itself:
                    the release, its tracks, credits, deliveries, catalogue rows, poster and audio files. A UPC / ISRC from the Code lists goes back to the list if the song never reached the stores.
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {rows.length === 0 ? (
                        <div className="px-5 py-14 text-center text-sm text-slate-500">The Trash is empty.</div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((r) => (
                                <div key={r.id} className="flex flex-col gap-4 p-4 md:flex-row md:items-center">
                                    <div className="flex min-w-0 flex-1 items-start gap-3">
                                        {art(r.artwork_path) ? (
                                            <img src={art(r.artwork_path)} alt="" className="h-16 w-16 shrink-0 rounded-xl border border-slate-200 object-cover opacity-80 grayscale" />
                                        ) : (
                                            <div className="grid h-16 w-16 shrink-0 place-items-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-[10px] text-slate-400">No poster</div>
                                        )}
                                        <div className="min-w-0">
                                            <div className="font-semibold text-slate-900">{r.title || 'Untitled'}</div>
                                            <div className="text-xs text-slate-600">{r.artist || '—'} · {r.label || 'No label'}</div>
                                            <div className="mt-0.5 text-xs text-slate-500">
                                                {r.upc ? `UPC ${r.upc}` : 'No UPC'} · {r.catalog_number || 'No catalogue no.'} · {r.tracks} track{r.tracks === 1 ? '' : 's'} · was <b>{STATUS[r.status_before] || r.status_before}</b>
                                            </div>
                                            <div className="mt-0.5 text-xs text-slate-500">Deleted {r.at}{r.by ? ` by ${r.by}` : ''}{r.reason ? ` - "${r.reason}"` : ''}</div>
                                        </div>
                                    </div>
                                    <div className="w-full md:w-44">
                                        <div className="text-xs font-semibold text-slate-700">{r.days_left} day{r.days_left === 1 ? '' : 's'} left</div>
                                        <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                                            <div className={`h-full rounded-full ${r.days_left <= 7 ? 'bg-rose-500' : 'bg-amber-500'}`} style={{ width: `${Math.max(3, Math.round((r.days_left / keepDays) * 100))}%` }} />
                                        </div>
                                        <div className="mt-1 text-[11px] text-slate-500">gone for good on {r.purge_on}</div>
                                    </div>
                                    <div className="flex shrink-0 gap-2">
                                        <button type="button" disabled={busy} onClick={() => post(`/admin/release-trash/${r.id}/restore`)} className="inline-flex items-center gap-1.5 rounded-xl border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-100 disabled:opacity-50">
                                            <RotateCcw className="h-4 w-4" /> Restore
                                        </button>
                                        <button type="button" disabled={busy} onClick={() => setConfirm(r)} className="inline-flex items-center gap-1.5 rounded-xl border border-rose-200 bg-rose-50 px-3 py-2 text-sm font-semibold text-rose-700 hover:bg-rose-100 disabled:opacity-50">
                                            <Trash2 className="h-4 w-4" /> Delete forever
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {confirm && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-label="Delete for good">
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                        <div className="text-base font-bold text-slate-900">Delete "{confirm.title}" for good?</div>
                        <p className="mt-2 text-sm leading-6 text-slate-600">The release, its {confirm.tracks} track{confirm.tracks === 1 ? '' : 's'}, credits, deliveries, poster and audio files are removed. <b>This cannot be undone.</b> A code is e-mailed to you to confirm.</p>
                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setConfirm(null)} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                            <button type="button" disabled={busy} onClick={() => post(`/admin/release-trash/${confirm.id}/purge`, () => setConfirm(null))} className="rounded-xl bg-rose-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-rose-700 disabled:opacity-50">{busy ? 'Deleting…' : 'Delete for good'}</button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
