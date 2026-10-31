import { Head, router } from '@inertiajs/react';
import axios from 'axios';
import { CheckCircle2, Clock, Coffee, Pause, Play, Send, Ban } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import PageMessages from '@/V2/Shared/Components/PageMessages';
import { hm, hms } from '@/V2/Shared/Office/time';

/*
 * W127 MIXXTUNE_OFFICE
 * A staff member's day: Clock In, do the tasks (timer + checklist + proof), Break, and the
 * active-time heartbeat. Logout (in the top bar) shows the day summary and must be confirmed.
 */
const PRIORITY = {
    urgent: 'bg-rose-100 text-rose-700',
    high: 'bg-orange-100 text-orange-700',
    normal: 'bg-slate-100 text-slate-600',
    low: 'bg-slate-100 text-slate-500',
};
const STATUS = {
    assigned: ['New', 'bg-blue-100 text-blue-700'],
    accepted: ['Accepted', 'bg-indigo-100 text-indigo-700'],
    in_progress: ['Working', 'bg-amber-100 text-amber-700'],
    blocked: ['Blocked', 'bg-rose-100 text-rose-700'],
    changes_requested: ['Changes asked', 'bg-orange-100 text-orange-700'],
    submitted: ['In review', 'bg-emerald-100 text-emerald-700'],
};

export default function MyOffice({ role = 'staff', attendance, tasks = [], runningTaskId = null, summary, monitoring = false, device = null }) {
    const clockedIn = attendance.clocked_in;
    const onBreak = attendance.on_break;
    const [activeSecs, setActiveSecs] = useState(summary?.active_seconds || 0);

    /* heartbeat: tell the server the tab is active every 60s, and tick the counter each second */
    const tick = useRef(null);
    useEffect(() => {
        if (!clockedIn) return undefined;
        const ping = () => {
            if (document.visibilityState !== 'visible' || onBreak) return;
            axios.post('/office/ping').then((r) => setActiveSecs(r.data.active_seconds)).catch(() => {});
        };
        ping();
        const pingId = window.setInterval(ping, 60000);
        tick.current = window.setInterval(() => {
            if (document.visibilityState === 'visible' && !onBreak) setActiveSecs((s) => s + 1);
        }, 1000);
        return () => {
            window.clearInterval(pingId);
            window.clearInterval(tick.current);
        };
    }, [clockedIn, onBreak]);

    const post = (url, data = {}) => router.post(url, data, { preserveScroll: true });

    return (
        <PanelLayout role={role} title="My Office" subtitle={attendance.work_date}>
            <Head title="My Office" />
            <PageMessages />

            <div className="space-y-5">
                {/* attendance bar */}
                <div className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex items-center gap-4">
                        <div className={`grid h-14 w-14 place-items-center rounded-2xl ${clockedIn ? (onBreak ? 'bg-amber-100 text-amber-700' : 'bg-emerald-100 text-emerald-700') : 'bg-slate-100 text-slate-400'}`}>
                            <Clock className="h-7 w-7" />
                        </div>
                        <div>
                            <div className="text-sm text-slate-500">
                                {clockedIn ? (onBreak ? 'On break' : `Clocked in · ${attendance.clock_in_at}`) : 'Not clocked in'}
                            </div>
                            <div className="font-mono text-2xl font-bold text-slate-900">{hms(activeSecs)}</div>
                            <div className="text-xs text-slate-400">active on Mixx Tune today</div>
                        </div>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {!clockedIn && (
                            <button onClick={() => post('/office/clock-in')} className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700">Clock In</button>
                        )}
                        {clockedIn && !onBreak && (
                            <button onClick={() => post('/office/break/start')} className="inline-flex items-center gap-2 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 hover:bg-amber-100"><Coffee className="h-4 w-4" /> Break</button>
                        )}
                        {clockedIn && onBreak && (
                            <button onClick={() => post('/office/break/end')} className="rounded-xl bg-amber-500 px-5 py-3 text-sm font-semibold text-white hover:bg-amber-600">End break</button>
                        )}
                    </div>
                </div>

                {!clockedIn && (
                    <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-6 text-center text-sm text-slate-500">
                        Clock In to start your day and see the timer run. Your tasks are below.
                    </div>
                )}

                {monitoring && <ConnectComputer device={device} />}

                {/* tasks */}
                <div className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold text-slate-900">Today&apos;s tasks</h2>
                        <span className="text-sm text-slate-500">{tasks.length} task{tasks.length === 1 ? '' : 's'}</span>
                    </div>
                    {tasks.length === 0 && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-8 text-center text-sm text-slate-500">No tasks right now. Nice and clear!</div>
                    )}
                    {tasks.map((t) => (
                        <TaskCard key={t.id} t={t} running={runningTaskId === t.id} clockedIn={clockedIn} post={post} />
                    ))}
                </div>
            </div>
        </PanelLayout>
    );
}

function ConnectComputer({ device }) {
    const [code, setCode] = useState(null);
    const [busy, setBusy] = useState(false);
    const make = () => {
        setBusy(true);
        axios.post('/office/device/pair-code', { platform: /Mac/.test(navigator.platform) ? 'mac' : 'windows', device_name: navigator.platform })
            .then((r) => setCode(r.data.code)).catch(() => {}).finally(() => setBusy(false));
    };
    if (device?.online) {
        return (
            <div className="rounded-2xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-900">
                ✔ This computer is connected{device.platform ? ` (${device.platform})` : ''}. Your active/idle time is being recorded during work hours. A screenshot is taken about every {device.screenshot_minutes} minutes — you will see a small note when it happens.
            </div>
        );
    }
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
            <div className="font-semibold text-slate-900">Connect this computer</div>
            <p className="mt-1 text-sm text-slate-600">
                Your office has turned on activity monitoring. Install the Mixx Tune Office app on this computer and enter the code below once. It records how long the keyboard/mouse is active or idle, the app in front, and a screenshot every few minutes — only during work hours. It never reads what you type, your passwords, camera or mic.
            </p>
            {!code ? (
                <button onClick={make} disabled={busy} className="mt-3 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">{busy ? '…' : 'Get connect code'}</button>
            ) : (
                <div className="mt-3 inline-flex items-center gap-3 rounded-xl border border-violet-200 bg-violet-50 px-4 py-2.5">
                    <span className="font-mono text-xl font-bold tracking-widest text-violet-800">{code}</span>
                    <span className="text-xs text-violet-700">enter this in the app · valid 15 min</span>
                </div>
            )}
        </div>
    );
}

function TaskCard({ t, running, clockedIn, post }) {
    const [badge, badgeClass] = STATUS[t.status] ?? [t.status, 'bg-slate-100 text-slate-600'];
    const closed = t.status === 'submitted';
    const [submitOpen, setSubmitOpen] = useState(false);
    const [blockOpen, setBlockOpen] = useState(false);

    return (
        <div className={`rounded-2xl border bg-white p-4 shadow-sm ${t.overdue ? 'border-rose-200' : 'border-slate-200'}`}>
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-bold ${badgeClass}`}>{badge}</span>
                        <span className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ${PRIORITY[t.priority] || PRIORITY.normal}`}>{t.priority}</span>
                        {t.due_text && <span className={`text-xs ${t.overdue ? 'font-semibold text-rose-600' : 'text-slate-500'}`}>{t.overdue ? 'Overdue · ' : 'Due '}{t.due_text}</span>}
                    </div>
                    <div className="mt-1 font-semibold text-slate-900">{t.title}</div>
                    {t.description && <p className="mt-0.5 text-sm text-slate-600">{t.description}</p>}
                    {t.review_note && <p className="mt-1 rounded-lg bg-orange-50 px-3 py-2 text-xs text-orange-800">Changes asked: {t.review_note}</p>}
                </div>
                <div className="text-right text-xs text-slate-500">
                    <div className="font-mono font-semibold text-slate-700">{hm(t.worked_seconds)}</div>
                    <div>on this task</div>
                </div>
            </div>

            {t.items.length > 0 && (
                <div className="mt-3 space-y-1.5">
                    {t.items.map((i) => (
                        <label key={i.id} className={`flex cursor-pointer items-center gap-2 text-sm ${i.is_done ? 'text-slate-400 line-through' : 'text-slate-700'}`}>
                            <input type="checkbox" checked={i.is_done} disabled={closed || !clockedIn} onChange={(e) => post(`/office/tasks/${t.id}/items/${i.id}`, { done: e.target.checked })} className="h-4 w-4 rounded border-slate-300 text-violet-600" />
                            {i.text}
                        </label>
                    ))}
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-slate-200">
                        <div className="h-full rounded-full bg-violet-600" style={{ width: `${t.progress}%` }} />
                    </div>
                </div>
            )}

            {!closed && (
                <div className="mt-3 flex flex-wrap gap-2">
                    {t.status === 'assigned' && <button onClick={() => post(`/office/tasks/${t.id}/accept`)} className="rounded-lg border border-slate-200 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Accept</button>}
                    {running ? (
                        <button onClick={() => post(`/office/tasks/${t.id}/timer-pause`)} className="inline-flex items-center gap-1.5 rounded-lg bg-amber-500 px-3 py-1.5 text-xs font-semibold text-white hover:bg-amber-600"><Pause className="h-3.5 w-3.5" /> Pause</button>
                    ) : (
                        <button onClick={() => post(`/office/tasks/${t.id}/timer-start`)} disabled={!clockedIn} className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-40"><Play className="h-3.5 w-3.5" /> Start</button>
                    )}
                    <button onClick={() => setBlockOpen(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 px-3 py-1.5 text-xs font-semibold text-rose-700 hover:bg-rose-50"><Ban className="h-3.5 w-3.5" /> Blocked</button>
                    <button onClick={() => setSubmitOpen(true)} className="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-emerald-700"><Send className="h-3.5 w-3.5" /> Submit</button>
                </div>
            )}
            {closed && <div className="mt-3 inline-flex items-center gap-1.5 text-sm font-semibold text-emerald-700"><CheckCircle2 className="h-4 w-4" /> Sent for review</div>}

            {submitOpen && <SubmitModal t={t} post={post} onClose={() => setSubmitOpen(false)} />}
            {blockOpen && <BlockModal t={t} post={post} onClose={() => setBlockOpen(false)} />}
        </div>
    );
}

function SubmitModal({ t, post, onClose }) {
    const [kind, setKind] = useState('link');
    const [link, setLink] = useState('');
    const [note, setNote] = useState('');
    const send = () => {
        post(`/office/tasks/${t.id}/submit`, { kind, link: kind === 'link' ? link : null, note });
        onClose();
    };
    return (
        <Modal title={`Submit "${t.title}"`} onClose={onClose} onOk={send} okText="Send for review">
            <label className="block text-sm font-semibold text-slate-700">Proof</label>
            <select value={kind} onChange={(e) => setKind(e.target.value)} className="mt-1 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm">
                <option value="link">A link (Drive, YouTube, file URL)</option>
                <option value="note">Just a note</option>
            </select>
            {kind === 'link' && <input value={link} onChange={(e) => setLink(e.target.value)} placeholder="https://…" className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />}
            <textarea value={note} onChange={(e) => setNote(e.target.value)} rows={2} placeholder="Note for the reviewer (optional)" className="mt-2 w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </Modal>
    );
}

function BlockModal({ t, post, onClose }) {
    const [why, setWhy] = useState('');
    const send = () => {
        if (!why.trim()) return;
        post(`/office/tasks/${t.id}/block`, { why });
        onClose();
    };
    return (
        <Modal title="What is blocking you?" onClose={onClose} onOk={send} okText="Mark blocked">
            <textarea value={why} onChange={(e) => setWhy(e.target.value)} rows={3} placeholder="e.g. waiting for the final audio from the editor" className="w-full rounded-xl border border-slate-200 px-3 py-2 text-sm" />
        </Modal>
    );
}

function Modal({ title, children, onClose, onOk, okText }) {
    return (
        <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true">
            <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                <div className="text-base font-bold text-slate-900">{title}</div>
                <div className="mt-3">{children}</div>
                <div className="mt-5 flex justify-end gap-2">
                    <button onClick={onClose} className="rounded-xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Cancel</button>
                    <button onClick={onOk} className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">{okText}</button>
                </div>
            </div>
        </div>
    );
}
