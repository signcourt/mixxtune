import { router } from '@inertiajs/react';
import axios from 'axios';
import { useEffect, useState } from 'react';
import { hm } from '@/V2/Shared/Office/time';

/*
 * W127 MIXXTUNE_OFFICE
 * The logout day-summary. A staff member cannot log out without seeing today's time and pressing
 * "Yes, log out" — that also clocks them out and saves the time.
 */
export default function OfficeLogoutConfirm({ open, onCancel }) {
    const [s, setS] = useState(null);
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState(false);

    useEffect(() => {
        if (!open) return;
        setS(null);
        setErr(false);
        axios.get('/office/summary').then((r) => setS(r.data)).catch(() => setErr(true));
    }, [open]);

    if (!open) return null;

    const confirm = () => {
        setBusy(true);
        router.post('/office/clock-out', {}, {
            preserveScroll: true,
            onFinish: () => router.post('/logout'),
        });
    };

    const Row = ({ label, value, strong }) => (
        <div className="flex items-center justify-between py-1.5 text-sm">
            <span className="text-slate-500">{label}</span>
            <span className={strong ? 'font-bold text-slate-900' : 'font-semibold text-slate-700'}>{value}</span>
        </div>
    );

    return (
        <div className="fixed inset-0 z-[90] flex items-center justify-center bg-slate-900/60 p-4" role="dialog" aria-modal="true" aria-label="Log out">
            <div className="w-full max-w-sm rounded-2xl border border-slate-200 bg-white p-6 shadow-2xl">
                <div className="text-lg font-bold text-slate-900">Logging out for today</div>
                {err && <p className="mt-3 text-sm text-rose-600">Could not load your time. Please try again.</p>}
                {!s && !err && <p className="mt-4 text-sm text-slate-500">Loading your time…</p>}
                {s && (
                    <div className="mt-4 divide-y divide-slate-100">
                        <Row label="Clock in" value={s.clock_in || '—'} />
                        <Row label="Worked today" value={hm(s.work_seconds)} strong />
                        <Row label="Active on Mixx Tune" value={hm(s.active_seconds)} />
                        <Row label="Break" value={hm(s.break_seconds)} />
                        <Row label="Time on tasks" value={hm(s.task_seconds)} />
                        <Row label="Tasks done / sent" value={`${s.tasks_done} / ${s.tasks_submitted}`} />
                    </div>
                )}
                <p className="mt-4 text-xs leading-5 text-slate-500">
                    This is saved as your attendance for today. You cannot log out without confirming.
                </p>
                <div className="mt-5 flex justify-end gap-2">
                    <button type="button" onClick={onCancel} disabled={busy} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50">Go back</button>
                    <button type="button" onClick={confirm} disabled={busy || (!s && !err)} className="rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">{busy ? 'Saving…' : 'Yes, log out'}</button>
                </div>
            </div>
        </div>
    );
}
