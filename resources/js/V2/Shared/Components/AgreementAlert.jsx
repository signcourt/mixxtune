import { Link } from "@inertiajs/react";
import axios from "axios";
import { CalendarClock, X } from "lucide-react";
import { useEffect, useState } from "react";

/*
 * W94 MIXXTUNE_ACCOUNT_AGREEMENTS
 * Super Admin dashboard: a strip with the agreements that end within 90 days
 * (or have ended and are not renewed), and a pop-up that opens once a day.
 * The days left count down by themselves (worked out on the server every day).
 */
const fmt = (d) => new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(`${d}T00:00:00`));

export const daysText = (left) => {
    if (left > 1) return `${left} days left`;
    if (left === 1) return "1 day left";
    if (left === 0) return "Ends today";
    return `Ended ${Math.abs(left)} day${left === -1 ? "" : "s"} ago`;
};

export const daysTone = (left) =>
    left < 0 ? "bg-red-600 text-white" : left <= 30 ? "bg-red-50 text-red-700 ring-1 ring-inset ring-red-200" : "bg-amber-50 text-amber-800 ring-1 ring-inset ring-amber-200";

function Row({ item }) {
    return (
        <li className="flex flex-col gap-2 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="min-w-0">
                <div className="flex flex-wrap items-center gap-2">
                    <span className="truncate text-sm font-bold text-slate-900">{item.account}</span>
                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-bold uppercase text-slate-600">{item.role}</span>
                </div>
                {item.labels?.length > 0 && (
                    <div className="truncate text-xs text-slate-500">
                        {item.labels.join(", ")}
                        {item.more_labels > 0 ? ` +${item.more_labels} more` : ""}
                    </div>
                )}
                <div className="text-[11px] text-slate-500">
                    Signed {fmt(item.signed_on)} · {item.term} · ends <b>{fmt(item.ends_on)}</b>
                </div>
            </div>
            <div className="flex shrink-0 items-center gap-2">
                <span className={`rounded-full px-2.5 py-1 text-xs font-black ${daysTone(item.days_left)}`}>{daysText(item.days_left)}</span>
                <Link href={`/admin/users/${item.user_id}/edit#agreement`} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                    Renew / open
                </Link>
            </div>
        </li>
    );
}

export default function AgreementAlert({ agreements }) {
    const due = agreements?.due ?? [];
    const [open, setOpen] = useState(false);

    useEffect(() => {
        if (agreements?.popup && due.length > 0) setOpen(true);
    }, []);

    if (due.length === 0) return null;

    const ended = due.filter((i) => i.days_left < 0).length;
    const close = () => {
        setOpen(false);
        axios.post("/admin/agreements/popup-seen").catch(() => {});
    };

    return (
        <>
            <button
                type="button"
                onClick={() => setOpen(true)}
                className="mt-5 flex w-full flex-wrap items-center justify-between gap-2 rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-left text-amber-900 shadow-sm hover:bg-amber-100"
            >
                <span className="flex items-center gap-2 text-sm font-bold">
                    <CalendarClock size={18} />
                    {due.length} agreement{due.length === 1 ? "" : "s"} to renew
                    {ended > 0 && <span className="rounded-full bg-red-600 px-2 py-0.5 text-[11px] font-black text-white">{ended} already ended</span>}
                </span>
                <span className="text-xs font-semibold">
                    Next: {due[0].account} — {daysText(due[0].days_left)} · see all →
                </span>
            </button>

            {open && (
                <div className="fixed inset-0 z-50 flex items-end justify-center bg-slate-950/50 sm:items-center sm:p-4" onClick={close}>
                    <div onClick={(e) => e.stopPropagation()} className="flex max-h-[88vh] w-full max-w-2xl flex-col rounded-t-3xl bg-white shadow-2xl sm:rounded-2xl" role="dialog" aria-modal="true" aria-labelledby="agreement-alert-title">
                        <div className="flex items-start justify-between gap-3 border-b border-slate-100 px-5 py-4">
                            <div>
                                <h3 id="agreement-alert-title" className="flex items-center gap-2 text-base font-black text-slate-950">
                                    <CalendarClock size={18} className="text-amber-600" />
                                    Agreements ending soon
                                </h3>
                                <p className="mt-0.5 text-xs text-slate-500">
                                    Shown every day from 90 days before an agreement ends, until it is renewed or marked "not renewing".
                                </p>
                            </div>
                            <button type="button" onClick={close} className="rounded-lg p-1.5 text-slate-400 hover:bg-slate-100" aria-label="Close">
                                <X size={18} />
                            </button>
                        </div>
                        <ul className="divide-y divide-slate-100 overflow-y-auto">
                            {due.map((item) => (
                                <Row key={item.id} item={item} />
                            ))}
                        </ul>
                        <div className="flex justify-end border-t border-slate-100 px-5 py-3">
                            <button type="button" onClick={close} className="rounded-xl bg-slate-900 px-5 py-2 text-sm font-semibold text-white hover:bg-slate-800">
                                OK, remind me tomorrow
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
