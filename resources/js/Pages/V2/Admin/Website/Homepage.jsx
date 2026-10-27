import { Head, router, usePage } from "@inertiajs/react";
import { AlertTriangle, ArrowDown, ArrowUp, CheckCircle2, ExternalLink, Lock, Plus, Trash2 } from "lucide-react";
import { useMemo, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W121 MIXXTUNE_WEBSITE_HOMEPAGE + MIXXTUNE_WEBSITE_JOIN
 * Website -> Homepage (Super Admin): the homepage words, which sections show,
 * the stores, the footer contact and the Join form questions. Nothing goes
 * live until Publish.
 */
const TYPE_NAMES = {
    text: "Short answer",
    textarea: "Long answer",
    select: "Dropdown (choose one)",
    phone: "Phone number",
    email: "Email",
    number: "Number",
    date: "Date",
};

const SECTION_NAMES = {
    stores: "Moving stores strip",
    how: "How it works (4 steps)",
    features: "Features (100% Transparent …)",
    royalty: "Royalty path (₹ wallet scene)",
    audience: "For labels / For artists",
    cta: "Ready for lift-off? (Join)",
};

const toState = (content) => ({
    ...content,
    stores: (content.stores ?? []).join(", "),
    join: {
        ...content.join,
        fields: (content.join?.fields ?? []).map((f) => ({ ...f, options: (f.options ?? []).join(", ") })),
    },
});

const input = "h-11 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm text-slate-900 focus:border-violet-500 focus:outline-none focus:ring-2 focus:ring-violet-200";
const labelCls = "flex flex-col gap-1.5 text-xs font-semibold text-slate-600";

function Toggle({ on, onChange, label, disabled = false }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            aria-label={label}
            disabled={disabled}
            onClick={() => onChange(!on)}
            className={`relative h-7 w-12 shrink-0 rounded-full transition disabled:opacity-50 ${on ? "bg-violet-600" : "bg-slate-300"}`}
        >
            <span className={`absolute top-[3px] h-[22px] w-[22px] rounded-full bg-white shadow transition-all ${on ? "left-[23px]" : "left-[3px]"}`} />
        </button>
    );
}

let nextKey = 1;
const newId = () => `q_${Date.now().toString(36).slice(-5)}${(nextKey++).toString(36)}`;

export default function Homepage({ content, types = Object.keys(TYPE_NAMES), locked = ["name", "phone"], maxFields = 20, published = null }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [state, setState] = useState(() => toState(content));
    const [tab, setTab] = useState("form");
    const [selId, setSelId] = useState("role");
    const [saving, setSaving] = useState(false);
    const [dirty, setDirty] = useState(false);

    const fields = state.join.fields;
    const sel = fields.find((f) => f.id === selId) ?? null;
    const isLocked = (id) => locked.includes(id);

    const change = (fn) => {
        setState((s) => {
            const copy = JSON.parse(JSON.stringify(s));
            fn(copy);
            return copy;
        });
        setDirty(true);
    };
    const setField = (id, key, value) => change((s) => {
        const f = s.join.fields.find((x) => x.id === id);
        if (f) f[key] = value;
    });
    const move = (i, d) => change((s) => {
        const j = i + d;
        if (j < 0 || j >= s.join.fields.length) return;
        [s.join.fields[i], s.join.fields[j]] = [s.join.fields[j], s.join.fields[i]];
    });
    const remove = (id) => {
        if (isLocked(id)) return;
        change((s) => {
            s.join.fields = s.join.fields.filter((f) => f.id !== id);
        });
        if (selId === id) setSelId(null);
    };
    const add = (type) => {
        if (fields.length >= maxFields) return;
        const id = newId();
        change((s) => {
            s.join.fields.push({ id, label: "New question", type, required: false, show: true, options: type === "select" ? "Option 1, Option 2" : "", hint: "" });
        });
        setSelId(id);
    };

    const publish = () => {
        if (!window.confirm("Publish these changes to the homepage now?")) return;
        const payload = {
            ...state,
            stores: state.stores.split(",").map((s) => s.trim()).filter(Boolean),
            join: { ...state.join, fields: state.join.fields.map((f) => ({ ...f, options: String(f.options ?? "").split(",").map((o) => o.trim()).filter(Boolean) })) },
        };
        setSaving(true);
        router.post("/admin/website", { content: payload }, {
            preserveScroll: true,
            onSuccess: (page) => {
                setState(toState(page.props.content));
                setDirty(false);
            },
            onFinish: () => setSaving(false),
        });
    };

    const preview = useMemo(() => fields.filter((f) => f.show), [fields]);
    const shown = preview.length;
    const errorList = Object.entries(errors);

    const tabBtn = (key, text) => (
        <button
            type="button"
            role="tab"
            aria-selected={tab === key}
            onClick={() => setTab(key)}
            className={`h-10 rounded-full border px-5 text-sm font-semibold ${tab === key ? "border-violet-600 bg-violet-600 text-white" : "border-slate-300 bg-white text-slate-700"}`}
        >
            {text}
        </button>
    );

    return (
        <PanelLayout role="super_admin" title="Homepage" subtitle="The words on mixxtune.com and the questions of the Join form">
            <Head title="Homepage" />

            {flash.success && (
                <div role="status" className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {errorList.length > 0 && (
                <div role="alert" className="mb-4 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <div className="flex items-center gap-2">
                        <AlertTriangle className="h-4 w-4 shrink-0" /> Not published:
                    </div>
                    <ul className="mt-1 list-disc pl-6 font-normal">
                        {errorList.map(([k, v]) => (
                            <li key={k}>{v}</li>
                        ))}
                    </ul>
                </div>
            )}

            <div className="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div role="tablist" aria-label="Homepage parts" className="flex flex-wrap gap-2">
                    {tabBtn("form", "Join form")}
                    {tabBtn("text", "Homepage text")}
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <span className="text-xs text-slate-500">
                        {dirty ? <b className="text-amber-700">Changes not published yet</b> : published?.at ? `Last published ${published.at}${published.by ? ` by ${published.by}` : ""}` : "Showing the standard words"}
                    </span>
                    <a href="/" target="_blank" rel="noreferrer" className="inline-flex h-11 items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-violet-800">
                        <ExternalLink className="h-4 w-4" /> Open website
                    </a>
                    <button type="button" onClick={publish} disabled={saving} className="h-11 rounded-xl bg-violet-600 px-5 text-sm font-bold text-white disabled:opacity-50">
                        {saving ? "Publishing…" : "Publish"}
                    </button>
                </div>
            </div>

            {tab === "text" && (
                <div className="flex flex-wrap items-start gap-5">
                    <section className="min-w-0 flex-[1_1_520px] space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-base font-bold text-slate-900">Top of the page</h2>
                        <label className={labelCls}>
                            Small line above the headline
                            <input className={input} maxLength={120} value={state.hero.eyebrow} onChange={(e) => change((s) => { s.hero.eyebrow = e.target.value; })} />
                        </label>
                        <label className={labelCls}>
                            Headline (press Enter for a second line)
                            <textarea rows={2} maxLength={120} className={`${input} h-auto py-2`} value={state.hero.title} onChange={(e) => change((s) => { s.hero.title = e.target.value; })} />
                        </label>
                        <label className={labelCls}>
                            Text under the headline
                            <textarea rows={3} maxLength={400} className={`${input} h-auto py-2`} value={state.hero.text} onChange={(e) => change((s) => { s.hero.text = e.target.value; })} />
                        </label>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className={labelCls}>
                                Join button text
                                <input className={input} maxLength={40} value={state.hero.join_button} onChange={(e) => change((s) => { s.hero.join_button = e.target.value; })} />
                            </label>
                            <label className={labelCls}>
                                Second button text
                                <input className={input} maxLength={40} value={state.hero.second_button} onChange={(e) => change((s) => { s.hero.second_button = e.target.value; })} />
                            </label>
                        </div>
                        <label className={labelCls}>
                            Stores in the orbit and the moving strip (comma separated, up to 20; the first 12 go round the logo)
                            <textarea rows={2} className={`${input} h-auto py-2`} value={state.stores} onChange={(e) => change((s) => { s.stores = e.target.value; })} />
                        </label>

                        <h2 className="pt-2 text-base font-bold text-slate-900">Contact in the footer</h2>
                        <p className="-mt-2 text-xs text-slate-500">Empty boxes are not shown on the website.</p>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <label className={labelCls}>
                                Email
                                <input type="email" className={input} maxLength={190} value={state.contact.email} onChange={(e) => change((s) => { s.contact.email = e.target.value; })} />
                            </label>
                            <label className={labelCls}>
                                Phone
                                <input type="tel" className={input} maxLength={40} value={state.contact.phone} onChange={(e) => change((s) => { s.contact.phone = e.target.value; })} />
                            </label>
                        </div>
                        <label className={labelCls}>
                            Office address
                            <textarea rows={2} maxLength={300} className={`${input} h-auto py-2`} value={state.contact.address} onChange={(e) => change((s) => { s.contact.address = e.target.value; })} />
                        </label>
                    </section>

                    <section className="min-w-0 flex-[1_1_320px] rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <h2 className="text-base font-bold text-slate-900">Sections on the page</h2>
                        <p className="mb-3 mt-1 text-xs text-slate-500">Switch a section off to hide it from the homepage.</p>
                        <ul className="space-y-2">
                            {Object.entries(SECTION_NAMES).map(([key, name]) => (
                                <li key={key} className="flex items-center gap-3 rounded-xl border border-slate-100 px-3 py-2.5">
                                    <span className="flex-1 text-sm font-semibold text-slate-800">{name}</span>
                                    <Toggle on={!!state.sections[key]} label={`Show ${name}`} onChange={(v) => change((s) => { s.sections[key] = v; })} />
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            )}

            {tab === "form" && (
                <div className="flex flex-wrap items-start gap-5">
                    <div className="min-w-0 flex-[1_1_560px] space-y-5">
                        <section className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-base font-bold text-slate-900">Form wording</h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <label className={labelCls}>
                                    Title
                                    <input className={input} maxLength={80} value={state.join.title} onChange={(e) => change((s) => { s.join.title = e.target.value; })} />
                                </label>
                                <label className={labelCls}>
                                    Send button text
                                    <input className={input} maxLength={40} value={state.join.button} onChange={(e) => change((s) => { s.join.button = e.target.value; })} />
                                </label>
                            </div>
                            <label className={labelCls}>
                                Text under the title
                                <input className={input} maxLength={300} value={state.join.sub} onChange={(e) => change((s) => { s.join.sub = e.target.value; })} />
                            </label>
                            <label className={labelCls}>
                                Thank-you message after sending
                                <input className={input} maxLength={300} value={state.join.thanks} onChange={(e) => change((s) => { s.join.thanks = e.target.value; })} />
                            </label>
                        </section>

                        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <h2 className="text-base font-bold text-slate-900">Questions in the form</h2>
                                <span className="text-xs text-slate-500">
                                    {fields.length} questions · {shown} shown · up to {maxFields}
                                </span>
                            </div>
                            <ul className="mt-3 space-y-2">
                                {fields.map((f, i) => (
                                    <li key={f.id} className={`flex flex-wrap items-center gap-2 rounded-xl border px-2.5 py-2 ${f.id === selId ? "border-violet-500 bg-violet-50" : "border-slate-100 bg-white"} ${f.show ? "" : "opacity-60"}`}>
                                        <span className="flex flex-col gap-0.5">
                                            <button type="button" onClick={() => move(i, -1)} disabled={i === 0} aria-label={`Move ${f.label} up`} className="flex h-5 w-8 items-center justify-center rounded border border-slate-300 bg-white disabled:opacity-30">
                                                <ArrowUp className="h-3 w-3" />
                                            </button>
                                            <button type="button" onClick={() => move(i, 1)} disabled={i === fields.length - 1} aria-label={`Move ${f.label} down`} className="flex h-5 w-8 items-center justify-center rounded border border-slate-300 bg-white disabled:opacity-30">
                                                <ArrowDown className="h-3 w-3" />
                                            </button>
                                        </span>
                                        <button type="button" onClick={() => setSelId(f.id)} className="flex min-h-[44px] min-w-0 flex-[1_1_180px] flex-col justify-center text-left">
                                            <span className="truncate text-sm font-bold text-slate-900">{f.label || "(no question text)"}</span>
                                            <span className="text-xs text-slate-500">
                                                {TYPE_NAMES[f.type] ?? f.type}
                                                {f.show ? "" : " · hidden"}
                                                {isLocked(f.id) ? " · always asked" : ""}
                                            </span>
                                        </button>
                                        {isLocked(f.id) ? (
                                            <span className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-3 py-1.5 text-xs font-semibold text-slate-500" title="Needed to call the person back">
                                                <Lock className="h-3 w-3" /> Required
                                            </span>
                                        ) : (
                                            <>
                                                <button
                                                    type="button"
                                                    aria-pressed={f.required}
                                                    onClick={() => setField(f.id, "required", !f.required)}
                                                    className={`h-9 rounded-full border px-3 text-xs font-bold ${f.required ? "border-violet-600 bg-violet-100 text-violet-800" : "border-slate-300 bg-white text-slate-500"}`}
                                                >
                                                    Required
                                                </button>
                                                <Toggle on={f.show} label={`Show ${f.label} on the form`} onChange={(v) => setField(f.id, "show", v)} />
                                                <button type="button" onClick={() => window.confirm(`Remove the question "${f.label}"?`) && remove(f.id)} aria-label={`Remove ${f.label}`} className="flex h-10 w-10 items-center justify-center rounded-lg border border-red-200 bg-red-50 text-red-700">
                                                    <Trash2 className="h-4 w-4" />
                                                </button>
                                            </>
                                        )}
                                    </li>
                                ))}
                            </ul>

                            <p className="mb-2 mt-5 text-xs font-bold text-slate-700">Add a question</p>
                            <div className="flex flex-wrap gap-2">
                                {types.map((t) => (
                                    <button key={t} type="button" disabled={fields.length >= maxFields} onClick={() => add(t)} className="inline-flex h-10 items-center gap-1 rounded-full border border-dashed border-violet-300 bg-violet-50/50 px-4 text-xs font-semibold text-violet-800 disabled:opacity-40">
                                        <Plus className="h-3.5 w-3.5" /> {TYPE_NAMES[t] ?? t}
                                    </button>
                                ))}
                            </div>
                        </section>

                        {sel && (
                            <section className="space-y-4 rounded-2xl border-2 border-violet-500 bg-white p-5 shadow-sm">
                                <h2 className="text-base font-bold text-slate-900">Edit question</h2>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <label className={labelCls}>
                                        Question
                                        <input className={input} maxLength={120} value={sel.label} onChange={(e) => setField(sel.id, "label", e.target.value)} />
                                    </label>
                                    <label className={labelCls}>
                                        Answer type
                                        <select className={input} value={sel.type} disabled={isLocked(sel.id)} onChange={(e) => setField(sel.id, "type", e.target.value)}>
                                            {types.map((t) => (
                                                <option key={t} value={t}>
                                                    {TYPE_NAMES[t] ?? t}
                                                </option>
                                            ))}
                                        </select>
                                    </label>
                                </div>
                                {sel.type === "select" && (
                                    <label className={labelCls}>
                                        Choices (comma separated)
                                        <input className={input} value={sel.options} onChange={(e) => setField(sel.id, "options", e.target.value)} />
                                    </label>
                                )}
                                <label className={labelCls}>
                                    Hint inside the box (optional)
                                    <input className={input} maxLength={120} value={sel.hint} onChange={(e) => setField(sel.id, "hint", e.target.value)} />
                                </label>
                            </section>
                        )}

                        <section className="space-y-3 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h2 className="text-base font-bold text-slate-900">When someone sends the form</h2>
                            <p className="text-xs text-slate-500">The request always appears in Website → Join Requests.</p>
                            <label className="flex min-h-[32px] items-center gap-3 text-sm">
                                <input type="checkbox" className="h-4 w-4 rounded" checked={!!state.join.notify_bell} onChange={(e) => change((s) => { s.join.notify_bell = e.target.checked; })} />
                                Bell notification to Super Admins
                            </label>
                            <label className="flex min-h-[32px] items-center gap-3 text-sm">
                                <input type="checkbox" className="h-4 w-4 rounded" checked={!!state.join.notify_email} onChange={(e) => change((s) => { s.join.notify_email = e.target.checked; })} />
                                E-mail to Super Admins
                            </label>
                        </section>
                    </div>

                    <aside aria-label="Live preview" className="min-w-0 flex-[1_1_360px] lg:sticky lg:top-4">
                        <p className="mb-2 text-xs font-bold text-slate-700">Live preview, as visitors see it</p>
                        <div className="rounded-3xl border border-indigo-900 bg-[#110F38] p-6 text-[#ECEAFF] shadow-xl">
                            <h3 className="text-xl font-black text-white">{state.join.title}</h3>
                            <p className="mb-4 mt-1 text-xs leading-5 text-[#B9B5DE]">{state.join.sub}</p>
                            <div className="grid grid-cols-2 gap-2.5">
                                {preview.map((f) => (
                                    <div key={f.id} className={`flex flex-col gap-1 text-xs font-semibold text-[#D9D5FF] ${f.type === "textarea" ? "col-span-2" : ""}`}>
                                        <span>
                                            {f.label || "Question"}
                                            {f.required || isLocked(f.id) ? " *" : ""}
                                        </span>
                                        {f.type === "textarea" ? (
                                            <div className="h-14 rounded-lg border border-white/20 bg-[#0A0926] px-2 py-1.5 font-normal text-white/40">{f.hint}</div>
                                        ) : f.type === "select" ? (
                                            <div className="flex h-9 items-center justify-between rounded-lg border border-white/20 bg-[#0A0926] px-2 font-normal text-white/60">
                                                <span className="truncate">Select · {String(f.options).split(",").map((o) => o.trim()).filter(Boolean).join(" / ")}</span>
                                                <span aria-hidden="true">▾</span>
                                            </div>
                                        ) : (
                                            <div className="flex h-9 items-center rounded-lg border border-white/20 bg-[#0A0926] px-2 font-normal text-white/40">{f.hint}</div>
                                        )}
                                    </div>
                                ))}
                            </div>
                            <div className="mt-4 flex h-11 items-center justify-center rounded-full bg-[#4F4BDC] text-sm font-bold text-white">{state.join.button}</div>
                            <p className="mt-3 text-[11px] text-[#A9A5D2]">After sending: “{state.join.thanks}”</p>
                        </div>
                    </aside>
                </div>
            )}
        </PanelLayout>
    );
}
