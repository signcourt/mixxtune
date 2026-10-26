import { AlarmClockOff, Archive, CalendarClock, ChevronDown, ChevronUp, Clock, Inbox, Mail, MailOpen, OctagonAlert, RotateCcw, ShieldCheck, Trash2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";

/*
 * W113 MIXXTUNE_MAIL_ACTIONS - the buttons like Gmail: Archive, Spam, Delete, Mark unread, Snooze,
 * Move to Inbox / Not spam / Put back / Delete for ever. The same set is used for one mail (reader,
 * row on hover) and for many ticked mails.
 */
const fmt = (d) => new Intl.DateTimeFormat("en-IN", { weekday: "short", day: "2-digit", month: "short", hour: "numeric", minute: "2-digit", hour12: true }).format(d);
export const untilText = (iso) => (iso ? fmt(new Date(iso)) : "");

function usePop() {
    const [open, setOpen] = useState(false);
    const box = useRef(null);
    useEffect(() => {
        if (!open) return undefined;
        const off = (e) => {
            if (!box.current?.contains(e.target)) setOpen(false);
        };
        document.addEventListener("mousedown", off);
        return () => document.removeEventListener("mousedown", off);
    }, [open]);
    return [open, setOpen, box];
}

const at = (base, days, hour) => {
    const d = new Date(base);
    d.setDate(d.getDate() + days);
    d.setHours(hour, 0, 0, 0);
    return d;
};

export function snoozeChoices(now = new Date()) {
    const out = [];
    if (now.getHours() < 17) out.push(["Later today", at(now, 0, 18)]);
    out.push(["Tomorrow", at(now, 1, 8)]);
    const day = now.getDay();
    if (day >= 1 && day <= 4) out.push(["This weekend", at(now, 6 - day, 8)]);
    const next = at(now, ((8 - day) % 7) || 7, 8);
    if (!out.some(([, d]) => d.getTime() === next.getTime())) out.push(["Next week", next]);
    else out.push(["Next week", at(now, (((8 - day) % 7) || 7) + 7, 8)]);
    return out;
}

export function SnoozeMenu({ onPick, btn, align = "left" }) {
    const [open, setOpen, box] = usePop();
    const [custom, setCustom] = useState("");
    const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const pick = (d) => {
        setOpen(false);
        onPick(d.toISOString());
    };

    return (
        <div ref={box} className="relative">
            <button type="button" onClick={() => setOpen(!open)} title="Snooze - hide it and bring it back to the top of the Inbox later" aria-label="Snooze" aria-expanded={open} className={btn}>
                <Clock size={17} />
            </button>
            {open && (
                <div className={`absolute top-full z-40 mt-1 w-64 rounded-xl border border-slate-200 bg-white py-1 text-left shadow-xl ${align === "right" ? "right-0" : "left-0"}`}>
                    <p className="px-3 pb-1 pt-1.5 text-[11px] font-bold uppercase text-slate-400">Snooze until...</p>
                    {snoozeChoices().map(([n, d]) => (
                        <button key={n} type="button" onClick={() => pick(d)} className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-sm text-slate-800 hover:bg-slate-50">
                            <span>{n}</span>
                            <span className="text-xs text-slate-500">{fmt(d)}</span>
                        </button>
                    ))}
                    <div className="mt-1 border-t border-slate-100 px-3 pb-2 pt-2">
                        <label className="block text-[11px] font-semibold text-slate-500">
                            Pick date and time
                            <input type="datetime-local" value={custom} min={local(new Date())} onChange={(e) => setCustom(e.target.value)} className="mt-1 h-8 w-full rounded-md border border-slate-300 px-2 text-sm font-normal text-slate-800" />
                        </label>
                        <button type="button" disabled={!custom || new Date(custom) <= new Date()} onClick={() => pick(new Date(custom))} className="mt-2 w-full rounded-md bg-violet-600 py-1.5 text-xs font-semibold text-white disabled:opacity-40">
                            Snooze
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

/* a button that asks once more before something that cannot be undone */
function Sure({ label, ask, onYes, btn, children }) {
    const [asking, setAsking] = useState(false);
    if (asking) {
        return (
            <span className="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2 py-1 text-[11px] font-semibold text-red-700">
                {ask}
                <button type="button" onClick={() => { setAsking(false); onYes(); }} className="rounded bg-red-600 px-1.5 py-0.5 text-white">Yes</button>
                <button type="button" onClick={() => setAsking(false)} className="rounded border border-red-200 bg-white px-1.5 py-0.5">No</button>
            </span>
        );
    }
    return (
        <button type="button" onClick={() => setAsking(true)} title={label} aria-label={label} className={btn}>
            {children}
        </button>
    );
}

/**
 * items: the mails the buttons work on (one or many); act(action, until?) does it.
 * quick: only the 4 hover buttons of a list row.
 */
export function MailButtons({ items, act, quick = false, align = "left", btn = "inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" }) {
    if (!items || items.length === 0) return null;
    const any = (fs) => items.some((m) => fs.includes(m.folder));
    const real = items.filter((m) => m.folder !== "drafts");
    const anyUnread = real.some((m) => !m.seen);
    const snoozed = items.some((m) => m.snooze_until && new Date(m.snooze_until) > new Date());
    const B = ({ action, label, icon: I }) => (
        <button key={action} type="button" onClick={(e) => { e.stopPropagation(); act(action); }} title={label} aria-label={label} className={btn}>
            <I size={17} />
        </button>
    );

    return (
        <div className="flex items-center gap-0.5">
            {any(["inbox"]) && !snoozed && <B action="archive" label="Archive" icon={Archive} />}
            {any(["archive"]) && <B action="inbox" label="Move to Inbox" icon={Inbox} />}
            {!quick && any(["inbox", "archive"]) && <B action="spam" label="Report spam" icon={OctagonAlert} />}
            {any(["spam"]) && <B action="not_spam" label="Not spam - move to Inbox" icon={ShieldCheck} />}
            {any(["trash"]) && <B action="restore" label="Put back (out of Trash)" icon={RotateCcw} />}
            {any(["inbox", "sent", "archive", "drafts"]) && <B action="trash" label={items.every((m) => m.folder === "drafts") ? "Discard draft" : "Delete (to Trash)"} icon={Trash2} />}
            {!quick && any(["trash", "spam"]) && (
                <Sure label="Delete for ever" ask="Delete for ever?" onYes={() => act("delete_forever")} btn={`${btn} text-red-600`}>
                    <Trash2 size={17} />
                </Sure>
            )}
            {real.length > 0 && !any(["trash"]) && (anyUnread ? <B action="read" label="Mark as read" icon={MailOpen} /> : <B action="unread" label="Mark as unread" icon={Mail} />)}
            {snoozed ? <B action="unsnooze" label="Unsnooze - back to the Inbox now" icon={AlarmClockOff} /> : any(["inbox", "archive"]) && <SnoozeMenu onPick={(iso) => act("snooze", iso)} btn={btn} align={align} />}
        </div>
    );
}

/* the less used folders, behind "More" */
export function MoreFolders({ folder, counts, onGo }) {
    const [open, setOpen, box] = usePop();
    const list = [
        ["scheduled", "Scheduled", CalendarClock, counts.scheduled],
        ["snoozed", "Snoozed", Clock, counts.snoozed],
        ["archive", "Archive", Archive, null],
        ["spam", "Spam", OctagonAlert, counts.spam],
        ["trash", "Trash", Trash2, counts.trash],
    ];
    const cur = list.find(([k]) => k === folder);
    const CurIcon = cur ? cur[2] : null;

    return (
        <div ref={box} className="relative">
            <button type="button" onClick={() => setOpen(!open)} aria-expanded={open} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ${cur ? "bg-violet-600 text-white" : "text-slate-600 hover:bg-slate-50"}`}>
                {cur ? (
                    <>
                        <CurIcon size={15} /> {cur[1]}
                    </>
                ) : (
                    "More"
                )}
                <ChevronDown size={14} />
            </button>
            {open && (
                <div className="absolute left-0 top-full z-40 mt-1 w-48 rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    {list.map(([k, n, I, c]) => (
                        <button key={k} type="button" onClick={() => { setOpen(false); onGo(k); }} className={`flex w-full items-center gap-2 px-3 py-1.5 text-sm hover:bg-slate-50 ${folder === k ? "font-bold text-violet-700" : "text-slate-700"}`}>
                            <I size={15} /> {n}
                            {c > 0 && <span className="ml-auto text-[11px] font-bold text-slate-400">{c}</span>}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}

/* W117: "Schedule send" - the small arrow next to Send, like Gmail */
export function scheduleChoices(now = new Date()) {
    const out = [];
    out.push(["Tomorrow morning", at(now, 1, 8)]);
    out.push(["Tomorrow afternoon", at(now, 1, 13)]);
    const day = now.getDay();
    const monday = at(now, ((8 - day) % 7) || 7, 8);
    if (monday.getTime() !== out[0][1].getTime()) out.push(["Monday morning", monday]);
    return out;
}

export function ScheduleMenu({ onPick, disabled }) {
    const [open, setOpen, box] = usePop();
    const [custom, setCustom] = useState("");
    const local = (d) => new Date(d.getTime() - d.getTimezoneOffset() * 60000).toISOString().slice(0, 16);
    const pick = (d) => {
        setOpen(false);
        onPick(d.toISOString());
    };
    const soon = new Date(Date.now() + 3 * 60000);

    return (
        <div ref={box} className="relative">
            <button type="button" disabled={disabled} onClick={() => setOpen(!open)} title="Schedule send" aria-label="Schedule send" aria-expanded={open} className="inline-flex h-full items-center rounded-r-full border-l border-violet-400/60 px-2.5 hover:bg-violet-700 disabled:opacity-50">
                <ChevronUp size={15} />
            </button>
            {open && (
                <div className="absolute bottom-full left-0 z-50 mb-2 w-72 rounded-xl border border-slate-200 bg-white py-1 text-left text-slate-800 shadow-xl">
                    <p className="px-3 pb-1 pt-1.5 text-[11px] font-bold uppercase text-slate-400">Schedule send</p>
                    {scheduleChoices().map(([n, d]) => (
                        <button key={n} type="button" onClick={() => pick(d)} className="flex w-full items-center justify-between gap-2 px-3 py-1.5 text-sm hover:bg-slate-50">
                            <span>{n}</span>
                            <span className="text-xs text-slate-500">{fmt(d)}</span>
                        </button>
                    ))}
                    <div className="mt-1 border-t border-slate-100 px-3 pb-2 pt-2">
                        <label className="block text-[11px] font-semibold text-slate-500">
                            Pick date and time
                            <input type="datetime-local" value={custom} min={local(soon)} onChange={(e) => setCustom(e.target.value)} className="mt-1 h-8 w-full rounded-md border border-slate-300 px-2 text-sm font-normal text-slate-800" />
                        </label>
                        <button type="button" disabled={!custom || new Date(custom) <= soon} onClick={() => pick(new Date(custom))} className="mt-2 w-full rounded-md bg-violet-600 py-1.5 text-xs font-semibold text-white disabled:opacity-40">
                            Schedule send
                        </button>
                        <p className="mt-1.5 text-[10px] text-slate-400">It waits in "Scheduled" (More) and goes out by itself. Your computer can be off.</p>
                    </div>
                </div>
            )}
        </div>
    );
}
