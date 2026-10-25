import { router, usePage } from "@inertiajs/react";
import { CalendarClock, FileText } from "lucide-react";
import { useEffect, useMemo, useState } from "react";

import { daysText, daysTone } from "@/V2/Shared/Components/AgreementAlert";

/*
 * W94 MIXXTUNE_ACCOUNT_AGREEMENTS
 * Users > Edit (Label / Artist): when the agreement was signed and for how
 * long. Ends on is worked out (signed 23 Oct 2026 for 3 years -> 22 Oct 2029).
 * Edit, Renew (new agreement continues the old one) or "Not renewing".
 */
const TERMS = [
    [12, "1 year"],
    [24, "2 years"],
    [36, "3 years"],
    [48, "4 years"],
    [60, "5 years"],
    [84, "7 years"],
    [120, "10 years"],
];

const fmt = (d) => (d ? new Intl.DateTimeFormat("en-IN", { day: "2-digit", month: "short", year: "numeric" }).format(new Date(`${d}T00:00:00`)) : "—");

/* same rule as the server: + months (no overflow: 31 Jan + 1 month = 28/29 Feb), then - 1 day */
export function endsOn(signedOn, months) {
    const m = String(signedOn || "").match(/^(\d{4})-(\d{2})-(\d{2})$/);
    const n = Number(months);
    if (!m || !n) return null;
    const y = Number(m[1]);
    const mo = Number(m[2]) - 1 + n;
    const ty = y + Math.floor(mo / 12);
    const tm = ((mo % 12) + 12) % 12;
    const last = new Date(Date.UTC(ty, tm + 1, 0)).getUTCDate();
    const end = new Date(Date.UTC(ty, tm, Math.min(Number(m[3]), last)));
    end.setUTCDate(end.getUTCDate() - 1);
    return end.toISOString().slice(0, 10);
}

const addDay = (d) => {
    const x = new Date(`${d}T00:00:00Z`);
    x.setUTCDate(x.getUTCDate() + 1);
    return x.toISOString().slice(0, 10);
};

const between = (from, to) => Math.round((new Date(`${to}T00:00:00Z`) - new Date(`${from}T00:00:00Z`)) / 86400000);

function AgreementForm({ mode, userId, agreement, today, onDone }) {
    const current = agreement?.current;
    const start = mode === "renew" && current ? addDay(current.ends_on) : mode === "edit" && current ? current.signed_on : today;
    const startTerm = current ? current.term_months : 36;
    const [signedOn, setSignedOn] = useState(start);
    const [preset, setPreset] = useState(TERMS.some(([v]) => v === startTerm) ? String(startTerm) : "other");
    const [months, setMonths] = useState(String(startTerm));
    const [notes, setNotes] = useState(mode === "edit" ? current?.notes ?? "" : "");
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState(false);
    const errors = usePage().props.errors ?? {};

    const term = preset === "other" ? Number(months) : Number(preset);
    const end = useMemo(() => endsOn(signedOn, term), [signedOn, term]);
    const left = end ? between(today, end) : null;

    const url =
        mode === "add" ? `/admin/users/${userId}/agreement` : mode === "renew" ? `/admin/users/${userId}/agreement/renew` : `/admin/agreements/${current.id}`;

    const save = () => {
        if (busy) return;
        setBusy(true);
        router.post(
            url,
            { signed_on: signedOn, term_months: term || "", notes, document: file },
            { forceFormData: true, preserveScroll: true, onSuccess: onDone, onFinish: () => setBusy(false) },
        );
    };

    return (
        <div className="mt-3 rounded-xl border border-violet-200 bg-violet-50/50 p-4">
            <div className="grid gap-3 sm:grid-cols-3">
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">{mode === "renew" ? "New agreement signed on *" : "Signed on *"}</span>
                    <input type="date" value={signedOn} onChange={(e) => setSignedOn(e.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" />
                    {errors.signed_on && <span className="mt-1 block text-xs text-red-600">{errors.signed_on}</span>}
                </label>
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">For how long *</span>
                    <select value={preset} onChange={(e) => setPreset(e.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm">
                        {TERMS.map(([v, t]) => (
                            <option key={v} value={String(v)}>
                                {t}
                            </option>
                        ))}
                        <option value="other">Other (months)</option>
                    </select>
                    {preset === "other" && (
                        <input type="number" min="1" max="120" value={months} onChange={(e) => setMonths(e.target.value)} placeholder="Months, e.g. 18" className="mt-2 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" />
                    )}
                    {errors.term_months && <span className="mt-1 block text-xs text-red-600">{errors.term_months}</span>}
                </label>
                <div className="rounded-lg border border-slate-200 bg-white px-3 py-2">
                    <span className="text-xs font-semibold text-slate-500">Ends on</span>
                    <div className="text-base font-black text-slate-900">{fmt(end)}</div>
                    {left !== null && <div className="text-[11px] text-slate-500">{left >= 0 ? `${left} days from today` : `${Math.abs(left)} days ago`} · reminder from 90 days before</div>}
                </div>
            </div>
            <div className="mt-3 grid gap-3 sm:grid-cols-2">
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">Signed copy (PDF / photo){mode === "edit" && current?.has_document ? " — leave empty to keep" : ""}</span>
                    <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => setFile(e.target.files?.[0] ?? null)} className="mt-1 block w-full rounded-lg border border-slate-300 bg-white px-2 py-1.5 text-xs" />
                    {errors.document && <span className="mt-1 block text-xs text-red-600">{errors.document}</span>}
                </label>
                <label className="block">
                    <span className="text-xs font-semibold text-slate-700">Note (optional)</span>
                    <input type="text" value={notes} onChange={(e) => setNotes(e.target.value)} maxLength={2000} placeholder="e.g. 80% share, exclusive" className="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" />
                </label>
            </div>
            <div className="mt-3 flex flex-wrap justify-end gap-2">
                <button type="button" onClick={onDone} disabled={busy} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700">
                    Cancel
                </button>
                <button type="button" onClick={save} disabled={busy || !end} className="rounded-lg bg-violet-600 px-4 py-2 text-xs font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                    {busy ? "Saving..." : mode === "renew" ? "Save renewal" : "Save agreement"}
                </button>
            </div>
        </div>
    );
}

function EndForm({ userId, onDone }) {
    const [reason, setReason] = useState("");
    const [busy, setBusy] = useState(false);
    const errors = usePage().props.errors ?? {};
    const save = () => {
        setBusy(true);
        router.post(`/admin/users/${userId}/agreement/end`, { reason }, { preserveScroll: true, onSuccess: onDone, onFinish: () => setBusy(false) });
    };

    return (
        <div className="mt-3 rounded-xl border border-red-200 bg-red-50/60 p-4">
            <label className="block">
                <span className="text-xs font-semibold text-slate-700">Why is it not renewed? *</span>
                <input type="text" value={reason} onChange={(e) => setReason(e.target.value)} className="mt-1 h-10 w-full rounded-lg border border-slate-300 bg-white px-3 text-sm" placeholder="e.g. label moved to another distributor" />
                {errors.reason && <span className="mt-1 block text-xs text-red-600">{errors.reason}</span>}
            </label>
            <p className="mt-2 text-[11px] text-slate-500">The account is not changed or blocked — only the reminder stops.</p>
            <div className="mt-3 flex justify-end gap-2">
                <button type="button" onClick={onDone} className="rounded-lg border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700">
                    Cancel
                </button>
                <button type="button" onClick={save} disabled={busy} className="rounded-lg bg-red-600 px-4 py-2 text-xs font-semibold text-white disabled:opacity-50">
                    {busy ? "Saving..." : "Mark as not renewing"}
                </button>
            </div>
        </div>
    );
}

export default function AgreementPanel({ userId, agreement }) {
    const [mode, setMode] = useState(null);
    const errors = usePage().props.errors ?? {};

    useEffect(() => {
        if (typeof window !== "undefined" && window.location.hash === "#agreement") {
            document.getElementById("agreement")?.scrollIntoView({ behavior: "smooth", block: "start" });
        }
    }, []);

    if (!agreement) return null;
    const current = agreement.current;
    const history = agreement.history ?? [];
    const done = () => setMode(null);

    return (
        <section id="agreement" className="mx-auto mt-4 max-w-[1200px] scroll-mt-24 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="flex items-center gap-2 text-[15px] font-bold text-slate-900">
                        <CalendarClock size={17} className="text-violet-600" />
                        Agreement
                    </h2>
                    <p className="mt-0.5 text-xs text-slate-500">The Super Admin dashboard reminds every day from 90 days before the end.</p>
                </div>
                {!mode && (
                    <div className="flex flex-wrap gap-2">
                        {current ? (
                            <>
                                <button type="button" onClick={() => setMode("renew")} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                                    Renew
                                </button>
                                <button type="button" onClick={() => setMode("edit")} className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700">
                                    Edit
                                </button>
                                <button type="button" onClick={() => setMode("end")} className="rounded-lg border border-red-200 bg-white px-3 py-1.5 text-xs font-semibold text-red-700">
                                    Not renewing
                                </button>
                            </>
                        ) : (
                            <button type="button" onClick={() => setMode("add")} className="rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700">
                                + Add agreement
                            </button>
                        )}
                    </div>
                )}
            </div>

            {errors.agreement && <p className="mt-2 text-xs font-semibold text-red-600">{errors.agreement}</p>}

            {current ? (
                <div className="mt-3 grid gap-3 sm:grid-cols-4">
                    <Info label="Signed on" value={fmt(current.signed_on)} />
                    <Info label="Term" value={current.term} />
                    <Info label="Ends on" value={fmt(current.ends_on)} />
                    <div className="rounded-lg border border-slate-200 px-3 py-2">
                        <span className="text-xs font-semibold text-slate-500">Status</span>
                        <div className="mt-1">
                            <span className={`rounded-full px-2.5 py-1 text-xs font-black ${current.days_left > current.notice_days ? "bg-emerald-50 text-emerald-700 ring-1 ring-inset ring-emerald-200" : daysTone(current.days_left)}`}>
                                {daysText(current.days_left)}
                            </span>
                        </div>
                    </div>
                    {(current.notes || current.has_document) && (
                        <div className="flex flex-wrap items-center gap-3 text-xs text-slate-600 sm:col-span-4">
                            {current.notes && <span>Note: {current.notes}</span>}
                            {current.has_document && (
                                <a href={`/admin/agreements/${current.id}/document`} className="inline-flex items-center gap-1 font-semibold text-violet-700 hover:text-violet-900">
                                    <FileText size={13} /> Signed copy ↓
                                </a>
                            )}
                        </div>
                    )}
                </div>
            ) : (
                !mode && <p className="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-500">No agreement saved for this account yet.</p>
            )}

            {(mode === "add" || mode === "edit" || mode === "renew") && <AgreementForm mode={mode} userId={userId} agreement={agreement} today={agreement.today} onDone={done} />}
            {mode === "end" && <EndForm userId={userId} onDone={done} />}

            {history.length > 0 && (
                <details className="mt-3 text-xs">
                    <summary className="cursor-pointer font-semibold text-slate-600">Earlier agreements ({history.length})</summary>
                    <ul className="mt-2 divide-y divide-slate-200 rounded-lg border border-slate-200">
                        {history.map((h) => (
                            <li key={h.id} className="flex flex-wrap items-center justify-between gap-2 px-3 py-2">
                                <span>
                                    <b className="text-slate-800">
                                        {fmt(h.signed_on)} → {fmt(h.ends_on)}
                                    </b>
                                    <span className="text-slate-500">
                                        {" "}
                                        · {h.term} · {h.status === "renewed" ? `renewed ${h.closed_at ?? ""}` : `not renewed${h.close_reason ? `: ${h.close_reason}` : ""}`}
                                    </span>
                                </span>
                                {h.has_document && (
                                    <a href={`/admin/agreements/${h.id}/document`} className="font-semibold text-violet-700">
                                        Signed copy ↓
                                    </a>
                                )}
                            </li>
                        ))}
                    </ul>
                </details>
            )}
        </section>
    );
}

function Info({ label, value }) {
    return (
        <div className="rounded-lg border border-slate-200 px-3 py-2">
            <span className="text-xs font-semibold text-slate-500">{label}</span>
            <div className="text-sm font-bold text-slate-900">{value}</div>
        </div>
    );
}
