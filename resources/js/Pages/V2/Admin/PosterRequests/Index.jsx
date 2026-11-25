import { Head, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/*
 * W195 MIXXTUNE_POSTER_CHANGE_REQUEST: super-admin / admin review pending poster changes
 * that labels & artists requested. Approve swaps the live poster; Reject drops the request.
 */
export default function Index({ role = 'admin', requests = [] }) {
    const { props } = usePage();
    const flash = props?.flash ?? {};
    const [busyId, setBusyId] = useState(null);

    const act = (id, kind) => {
        if (busyId) return;
        setBusyId(id);
        router.post(
            `/admin/poster-requests/${id}/${kind}`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setBusyId(null),
            }
        );
    };

    return (
        <PanelLayout role={role} title="Poster change requests" subtitle="Labels & artists waiting for approval">
            <Head title="Poster requests" />

            {flash.success && (
                <div className="mb-3 rounded-xl bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-700">
                    {flash.success}
                </div>
            )}

            {requests.length === 0 ? (
                <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500 shadow-sm">
                    No poster change requests waiting.
                </div>
            ) : (
                <div className="space-y-4">
                    {requests.map((r) => (
                        <div key={r.id} className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
                            <div className="flex flex-col gap-4 sm:flex-row sm:items-center">
                                <div className="flex items-center gap-4">
                                    <figure className="text-center">
                                        <div className="mb-1 text-[11px] font-bold uppercase tracking-wide text-slate-400">Current</div>
                                        {r.current_path ? (
                                            <img src={`/storage/${r.current_path}`} alt="Current poster" className="h-24 w-24 rounded-xl border border-slate-200 object-cover" />
                                        ) : (
                                            <div className="flex h-24 w-24 items-center justify-center rounded-xl border border-dashed border-slate-300 bg-slate-50 text-[10px] font-semibold uppercase text-slate-400">None</div>
                                        )}
                                    </figure>
                                    <div className="text-2xl text-slate-300">→</div>
                                    <figure className="text-center">
                                        <div className="mb-1 text-[11px] font-bold uppercase tracking-wide text-violet-500">Proposed</div>
                                        <img src={`/storage/${r.proposed_path}`} alt="Proposed poster" className="h-24 w-24 rounded-xl border-2 border-violet-300 object-cover" />
                                    </figure>
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="truncate text-base font-bold text-slate-900">{r.release_title || `Release #${r.release_id}`}</div>
                                    <div className="mt-0.5 text-sm text-slate-500">Requested by {r.requested_by || 'a user'}</div>
                                    {r.created_at && <div className="mt-0.5 text-xs text-slate-400">{r.created_at}</div>}
                                </div>

                                {role !== 'super_admin' && <span className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-800">Waiting for a Super Admin</span>}{/* W215 */}
                                {role === 'super_admin' && <div className="flex items-center gap-2">
                                    <button
                                        type="button"
                                        onClick={() => act(r.id, 'approve')}
                                        disabled={busyId === r.id}
                                        className="inline-flex items-center rounded-xl bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                    >
                                        {busyId === r.id ? '...' : 'Approve'}
                                    </button>
                                    <button
                                        type="button"
                                        onClick={() => act(r.id, 'reject')}
                                        disabled={busyId === r.id}
                                        className="inline-flex items-center rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                                    >
                                        Reject
                                    </button>
                                </div>}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </PanelLayout>
    );
}
