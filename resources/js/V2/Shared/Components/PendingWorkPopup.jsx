import { Link, usePage } from "@inertiajs/react";
import axios from "axios";
import { AlarmClock, CheckCircle2, ClipboardList, X } from "lucide-react";
import { useCallback, useEffect, useRef, useState } from "react";

/*
 * W204 MIXXTUNE_PENDING_WORK
 * Opens right after login on the admin dashboard with everything that is waiting
 * (KYC, release reviews, withdrawals, posters, approvals, tickets, join requests...).
 * Every row has an "Open" button to clear it now; "Remind later" hides it for
 * 15 min / 1 hr / 3 hrs / until tomorrow and it comes back by itself.
 * Super Admin sees everything; a Manager only what belongs to their labels/artists.
 * Counts are fetched from /admin/pending-work each time it opens (never stale).
 */

const TONES = {
    amber: "bg-amber-100 text-amber-800",
    violet: "bg-violet-100 text-violet-800",
    green: "bg-emerald-100 text-emerald-800",
    pink: "bg-pink-100 text-pink-800",
    blue: "bg-blue-100 text-blue-800",
    emerald: "bg-emerald-100 text-emerald-800",
    cyan: "bg-cyan-100 text-cyan-800",
    slate: "bg-slate-200 text-slate-800",
};

const MIN = 60 * 1000;
const SNOOZES = [
    ["10 min", 10 * MIN],
    ["15 min", 15 * MIN],
    ["30 min", 30 * MIN],
    ["1 hr", 60 * MIN],
    ["2 hrs", 120 * MIN],
    ["3 hrs", 180 * MIN],
    ["Today 6 pm", "evening"],
    ["Tomorrow", "tomorrow"],
];

const storageKey = (uid) => `mt-pending-work:${uid || "anon"}`;

const readPref = (uid) => {
    try {
        return JSON.parse(localStorage.getItem(storageKey(uid)) || "{}") || {};
    } catch (e) {
        return {};
    }
};

const writePref = (uid, patch) => {
    try {
        localStorage.setItem(storageKey(uid), JSON.stringify({ ...readPref(uid), ...patch }));
    } catch (e) {
        /* private window etc. – just no memory */
    }
};

/* auto-open once per login (tab session) and not again within 4 hours - navigating between pages
 * re-mounts the layout, which must NOT re-open it; a snooze timer or the button still can */
const SHOWN_GAP = 4 * 60 * 60 * 1000;
const sessionShown = (uid) => {
    try {
        return sessionStorage.getItem(storageKey(uid) + ":shown") === "1";
    } catch (e) {
        return false;
    }
};
const markShown = (uid) => {
    try {
        sessionStorage.setItem(storageKey(uid) + ":shown", "1");
    } catch (e) {
        /* ignore */
    }
    writePref(uid, { shownAt: Date.now() });
};

const tomorrow9 = () => {
    const d = new Date();
    d.setDate(d.getDate() + 1);
    d.setHours(9, 0, 0, 0);
    return d.getTime();
};

const today18 = () => {
    const d = new Date();
    d.setHours(18, 0, 0, 0);
    return d.getTime() > Date.now() + MIN ? d.getTime() : Date.now() + 60 * MIN;
};

const untilText = (until) => {
    const d = new Date(until);
    const sameDay = d.toDateString() === new Date().toDateString();
    return (sameDay ? "" : d.toLocaleDateString("en-IN", { day: "2-digit", month: "short" }) + " ") + d.toLocaleTimeString("en-IN", { hour: "2-digit", minute: "2-digit" });
};

export default function PendingWorkPopup() {
    const page = usePage();
    const role = page.props?.role;
    const url = page.url;
    const uid = page.props?.auth?.user?.id;
    const allowed = role === "super_admin" || role === "admin";
    const onDashboard = /dashboard|^\/admin\/?$/.test(url || "");
    const [data, setData] = useState(null);
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const timer = useRef(null);

    const load = useCallback(async () => {
        setLoading(true);
        try {
            const r = await axios.get("/admin/pending-work", { headers: { Accept: "application/json" } });
            setData(r.data);
            return r.data;
        } catch (e) {
            setData(null);
            return null;
        } finally {
            setLoading(false);
        }
    }, []);

    const scheduleReopen = useCallback((until) => {
        if (timer.current) clearTimeout(timer.current);
        const wait = Math.max(1000, until - Date.now());
        if (wait > 24 * 60 * 60 * 1000) return;
        timer.current = setTimeout(async () => {
            const d = await load();
            if (d && d.total > 0 && d.popup_enabled !== false /* W233 global-off-1 */ && readPref(uid).auto !== false) {
                writePref(uid, { until: 0 });
                markShown(uid);
                setOpen(true);
            }
        }, wait);
    }, [load, uid]);

    /* first visit after login: open if something is waiting and not snoozed */
    useEffect(() => {
        if (!allowed) return undefined;
        let alive = true;
        (async () => {
            const d = await load();
            if (!alive || !d || d.total === 0) return;
            if (d.popup_enabled === false) return; /* W233 global-off-2: switched off for everyone by a Super Admin */
            const pref = readPref(uid);
            if (pref.auto === false) return;
            if (pref.until && pref.until > Date.now()) {
                scheduleReopen(pref.until);
                return;
            }
            if (sessionShown(uid) || (pref.shownAt && Date.now() - pref.shownAt < SHOWN_GAP)) return;
            markShown(uid);
            setOpen(true);
        })();
        return () => {
            alive = false;
            if (timer.current) clearTimeout(timer.current);
        };
    }, []);

    const [custom, setCustom] = useState("");
    const [toast, setToast] = useState("");

    const snooze = (ms) => {
        const until = ms === "tomorrow" ? tomorrow9() : ms === "evening" ? today18() : Date.now() + ms;
        writePref(uid, { until });
        setOpen(false);
        scheduleReopen(until);
        setToast(`Pending work will pop up again at ${untilText(until)}`);
        setTimeout(() => setToast(""), 4000);
    };

    const snoozeCustom = () => {
        const m = parseInt(custom, 10);
        if (!m || m < 1 || m > 1440) return;
        snooze(m * MIN);
        setCustom("");
    };

    const autoOn = readPref(uid).auto !== false;
    const toggleAuto = () => {
        writePref(uid, { auto: !autoOn });
        setData((d) => (d ? { ...d } : d)); // re-render
    };

    /* W233 switch: Super Admin's on/off for everybody */
    const globalOn = data?.popup_enabled !== false;
    const [switching, setSwitching] = useState(false);
    const toggleGlobal = async () => {
        if (switching) return;
        setSwitching(true);
        try {
            const r = await axios.post("/admin/pending-work/setting", { enabled: !globalOn }, { headers: { Accept: "application/json" } });
            setData((d) => (d ? { ...d, popup_enabled: r.data?.popup_enabled !== false } : d));
        } catch (e) {
            setToast("Could not change the setting. Try again.");
            setTimeout(() => setToast(""), 4000);
        } finally {
            setSwitching(false);
        }
    };

    const items = data?.items ?? [];
    const total = data?.total ?? 0;

    if (!allowed) return null;

    return (
        <>
            {toast && (
                <div className="fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-full bg-slate-900 px-4 py-2 text-xs font-semibold text-white shadow-lg">
                    {toast}
                </div>
            )}
            {/* small always-there button on the dashboard */}
            {total > 0 && !open && onDashboard && (
                <button
                    type="button"
                    onClick={async () => {
                        await load();
                        setOpen(true);
                    }}
                    className="fixed bottom-5 right-5 z-40 flex items-center gap-2 rounded-full bg-violet-600 px-4 py-2.5 text-sm font-bold text-white shadow-lg ring-2 ring-white/70 hover:bg-violet-700"
                    title="Pending work"
                >
                    <ClipboardList className="h-4 w-4" />
                    Pending work
                    <span className="rounded-full bg-white/20 px-2 py-0.5 text-xs font-black">{total}</span>
                </button>
            )}

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-900/60 p-3 sm:items-center" onClick={() => setOpen(false)}>
                    <div
                        role="dialog"
                        aria-modal="true"
                        className="w-full max-w-lg overflow-hidden rounded-2xl bg-white shadow-2xl"
                        onClick={(e) => e.stopPropagation()}
                    >
                        <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <div className="flex items-center gap-2 text-base font-black text-slate-900">
                                    <ClipboardList className="h-5 w-5 text-violet-600" />
                                    Pending work
                                    <span className="rounded-full bg-violet-600 px-2 py-0.5 text-xs font-black text-white">{total}</span>
                                </div>
                                <div className="mt-0.5 text-xs text-slate-500">
                                    {data?.role === "admin" ? "Only your labels and artists." : "Everything waiting on the panel."}
                                    {loading ? " Refreshing…" : ""}
                                </div>
                            </div>
                            <button type="button" onClick={() => setOpen(false)} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100 hover:text-slate-700" aria-label="Close">
                                <X className="h-5 w-5" />
                            </button>
                        </div>

                        <ul className="max-h-[55vh] divide-y divide-slate-100 overflow-y-auto">
                            {items.length === 0 && (
                                <li className="flex items-center gap-2 px-5 py-6 text-sm text-slate-600">
                                    <CheckCircle2 className="h-5 w-5 text-emerald-600" /> All clear — nothing is waiting.
                                </li>
                            )}
                            {items.map((it) => (
                                <li key={it.key} className="flex items-center justify-between gap-3 px-5 py-3">
                                    <div className="min-w-0">
                                        <div className="flex items-center gap-2">
                                            <span className={`rounded-full px-2 py-0.5 text-xs font-black ${TONES[it.tone] || TONES.slate}`}>{it.count}</span>
                                            <span className="truncate text-sm font-bold text-slate-900">{it.label}</span>
                                        </div>
                                        <div className="truncate text-[11px] text-slate-500">{it.hint}</div>
                                    </div>
                                    <Link
                                        href={it.url}
                                        onClick={() => setOpen(false)}
                                        className="shrink-0 rounded-lg bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-700"
                                    >
                                        Open
                                    </Link>
                                </li>
                            ))}
                        </ul>

                        <div className="flex flex-col gap-3 border-t border-slate-100 bg-slate-50 px-5 py-3 sm:flex-row sm:items-center sm:justify-between">
                            <div className="flex flex-wrap items-center gap-1.5">
                                <span className="mr-1 flex items-center gap-1 text-xs font-bold text-slate-600">
                                    <AlarmClock className="h-4 w-4" /> Remind later:
                                </span>
                                {SNOOZES.map(([label, ms]) => (
                                    <button key={label} type="button" onClick={() => snooze(ms)} className="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                        {label}
                                    </button>
                                ))}
                                <span className="flex items-center gap-1">
                                    <input
                                        type="number"
                                        min="1"
                                        max="1440"
                                        value={custom}
                                        onChange={(e) => setCustom(e.target.value)}
                                        onKeyDown={(e) => e.key === "Enter" && snoozeCustom()}
                                        placeholder="min"
                                        className="h-7 w-16 rounded-md border border-slate-300 px-2 text-xs"
                                    />
                                    <button type="button" onClick={snoozeCustom} className="rounded-md bg-slate-900 px-2.5 py-1 text-xs font-bold text-white hover:bg-slate-700">
                                        Set
                                    </button>
                                </span>
                            </div>
                            <label className="flex cursor-pointer items-center gap-2 text-[11px] text-slate-500">
                                <input type="checkbox" checked={autoOn} onChange={toggleAuto} className="h-3.5 w-3.5 rounded border-slate-300" />
                                Show on login
                            </label>
                        </div>
                        {/* W233 row */}
                        {data?.can_switch ? (
                            <div className="flex items-center justify-between gap-3 border-t border-slate-100 bg-white px-5 py-2.5 text-[11px] text-slate-600">
                                <span>
                                    Automatic pop-up for every Super Admin and Manager: <b className={globalOn ? "text-emerald-700" : "text-rose-700"}>{globalOn ? "ON" : "OFF"}</b>
                                </span>
                                <button type="button" onClick={toggleGlobal} disabled={switching} className="rounded-md border border-slate-300 bg-white px-2.5 py-1 text-xs font-bold text-slate-800 hover:bg-slate-100 disabled:opacity-50">
                                    {switching ? "Saving…" : globalOn ? "Turn off for everyone" : "Turn on for everyone"}
                                </button>
                            </div>
                        ) : !globalOn ? (
                            <div className="border-t border-slate-100 bg-white px-5 py-2.5 text-[11px] text-slate-500">The automatic pop-up is switched off by a Super Admin. The Pending work button still opens it.</div>
                        ) : null}
                    </div>
                </div>
            )}
        </>
    );
}
