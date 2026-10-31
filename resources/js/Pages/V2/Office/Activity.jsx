import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W128 MIXXTUNE_OFFICE_MONITOR: live board, screenshots and the "Live screen" button */
const STATE = {
    active: ['Active', 'bg-emerald-100 text-emerald-700'],
    idle: ['Idle', 'bg-amber-100 text-amber-700'],
    offline: ['App off', 'bg-slate-100 text-slate-500'],
    no_agent: ['No agent', 'bg-slate-100 text-slate-400'],
};

export default function Activity({ rows = [], settings = {} }) {
    const [shots, setShots] = useState(null);
    const [editId, setEditId] = useState(null);
    const [rd, setRd] = useState('');

    const live = (r) => {
        if (!r.rustdesk_id) {
            alert('No RustDesk ID for this computer yet. Add it with "Set ID", or install the agent + RustDesk first.');
            return;
        }
        window.open(`rustdesk://${r.rustdesk_id}`, '_blank');
    };

    return (
        <PanelLayout role="super_admin" title="Live & Activity" subtitle="Who is active, the screenshots, and live screen">
            <Head title="Office — Live & Activity" />
            <PageMessages />

            {!settings.monitoring_enabled && (
                <div className="mb-4 rounded-2xl border border-amber-200 bg-amber-50 px-5 py-4 text-sm text-amber-900">
                    Desktop monitoring is OFF. Turn it on in <Link href="/office/admin/settings" className="font-semibold underline">Settings</Link>, and install the office agent on each computer, to see live activity and screenshots here.
                </div>
            )}

            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                {rows.length === 0 ? (
                    <div className="p-8 text-center text-sm text-slate-500">No staff yet.</div>
                ) : (
                    <div className="divide-y divide-slate-100">
                        {rows.map((r) => {
                            const [label, cls] = STATE[r.state] ?? STATE.no_agent;
                            return (
                                <div key={r.id} className="p-4">
                                    <div className="flex flex-wrap items-center justify-between gap-3">
                                        <div className="min-w-0">
                                            <div className="flex items-center gap-2">
                                                <span className="font-semibold text-slate-900">{r.name}</span>
                                                <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${cls}`}>{label}</span>
                                                {r.platform && <span className="text-[11px] text-slate-400">{r.platform}</span>}
                                            </div>
                                            <div className="text-xs text-slate-500">
                                                {r.foreground_app ? `On: ${r.foreground_app}` : 'app unknown'}
                                                {r.last_active ? ` · active till ${r.last_active}` : ''}
                                                {' · '}computer active {r.comp_active} · idle {r.comp_idle}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {r.shots.length > 0 && <button onClick={() => setShots(r)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{r.shots.length} shots</button>}
                                            {r.device_id && <button onClick={() => { setEditId(r.device_id); setRd(r.rustdesk_id || ''); }} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50">Set ID</button>}
                                            <button onClick={() => live(r)} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">Live screen</button>
                                        </div>
                                    </div>
                                </div>
                            );
                        })}
                    </div>
                )}
            </div>
            <p className="mt-3 text-xs text-slate-500">&quot;Live screen&quot; opens RustDesk to that computer. A bar shows on the staff member&apos;s screen while you view. Idle ≠ not working — a render or a meeting also looks idle.</p>

            {shots && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" onClick={() => setShots(null)}>
                    <div className="max-h-[90vh] w-full max-w-3xl overflow-y-auto rounded-2xl bg-white p-5" onClick={(e) => e.stopPropagation()}>
                        <div className="mb-3 flex items-center justify-between"><div className="font-bold text-slate-900">{shots.name} — today&apos;s screenshots</div><button onClick={() => setShots(null)} className="text-slate-500">✕</button></div>
                        <div className="grid grid-cols-2 gap-3 sm:grid-cols-3">
                            {shots.shots.map((s, i) => (
                                <a key={i} href={s.url} target="_blank" rel="noreferrer" className="block overflow-hidden rounded-lg border border-slate-200">
                                    <img src={s.url} alt="" className="h-32 w-full object-cover" loading="lazy" />
                                    <div className="px-2 py-1 text-[11px] text-slate-500">{s.at}{s.app ? ` · ${s.app}` : ''}</div>
                                </a>
                            ))}
                        </div>
                    </div>
                </div>
            )}

            {editId && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
                    <div className="w-full max-w-sm rounded-2xl bg-white p-5 shadow-2xl">
                        <div className="font-bold text-slate-900">RustDesk ID for this computer</div>
                        <input value={rd} onChange={(e) => setRd(e.target.value)} placeholder="e.g. 123 456 789" className="mt-3 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
                        <div className="mt-4 flex justify-end gap-2">
                            <button onClick={() => setEditId(null)} className="rounded-xl border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">Cancel</button>
                            <button onClick={() => { router.post(`/office/admin/devices/${editId}/rustdesk`, { rustdesk_id: rd }, { preserveScroll: true, onSuccess: () => setEditId(null) }); }} className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white">Save</button>
                        </div>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}
