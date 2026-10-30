import { Head, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';

/* W127 MIXXTUNE_OFFICE: time report per staff */
export default function Report({ range = 'week', rows = [] }) {
    const go = (r) => router.get('/office/admin/report', { range: r }, { preserveState: true, replace: true });
    const max = Math.max(1, ...rows.map((r) => r.active_seconds));
    return (
        <PanelLayout role="super_admin" title="Time Report" subtitle="Active time, break and tasks done per staff">
            <Head title="Office Time Report" />
            <PageMessages />
            <div className="space-y-4">
                <div className="flex gap-2">
                    {[['today', 'Today'], ['week', 'Last 7 days'], ['month', 'This month']].map(([k, l]) => (
                        <button key={k} onClick={() => go(k)} className={`rounded-xl px-4 py-2 text-sm font-semibold ${range === k ? 'bg-violet-600 text-white' : 'border border-slate-200 bg-white text-slate-700'}`}>{l}</button>
                    ))}
                </div>
                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {rows.length === 0 ? (
                        <div className="p-8 text-center text-sm text-slate-500">No attendance in this period yet.</div>
                    ) : (
                        <table className="min-w-full">
                            <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                <tr><th className="px-5 py-3">Staff</th><th className="px-5 py-3">Days</th><th className="px-5 py-3">Active</th><th className="px-5 py-3">Break</th><th className="px-5 py-3">On tasks</th><th className="px-5 py-3">Done</th></tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {rows.map((r) => (
                                    <tr key={r.name}>
                                        <td className="px-5 py-3">
                                            <div className="font-semibold text-slate-900">{r.name}</div>
                                            <div className="mt-1 h-1.5 w-40 overflow-hidden rounded-full bg-slate-200"><div className="h-full rounded-full bg-emerald-500" style={{ width: `${Math.round((r.active_seconds / max) * 100)}%` }} /></div>
                                        </td>
                                        <td className="px-5 py-3 text-sm text-slate-600">{r.days}</td>
                                        <td className="px-5 py-3 text-sm font-semibold text-slate-800">{r.active}</td>
                                        <td className="px-5 py-3 text-sm text-slate-600">{r.break}</td>
                                        <td className="px-5 py-3 text-sm text-slate-600">{r.task}</td>
                                        <td className="px-5 py-3 text-sm font-semibold text-emerald-700">{r.done}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    )}
                </div>
                <p className="text-xs text-slate-500">&quot;Active&quot; is the time the Mixx Tune tab was open and in use. When the Windows app is added later, it will also show computer-wide active and idle time.</p>
            </div>
        </PanelLayout>
    );
}
