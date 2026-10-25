import { usePage } from "@inertiajs/react";
import { useState } from "react";

/*
 * W108 MIXXTUNE_SIGNATURE_DESIGNER
 * Make the e-mail signature inside the panel: a form for the details, the logo / photo,
 * layout, lines, fonts, colours and background. The same function makes the preview and
 * the HTML that is sent (tables + inline styles only, so Gmail / Outlook / phones show
 * it the same way). No code is pasted any more.
 */
export const SIG_FONTS = [
    ["Arial", "Arial, Helvetica, sans-serif"],
    ["Helvetica", "Helvetica, Arial, sans-serif"],
    ["Verdana", "Verdana, Geneva, sans-serif"],
    ["Tahoma", "Tahoma, Verdana, sans-serif"],
    ["Trebuchet MS", "'Trebuchet MS', Arial, sans-serif"],
    ["Georgia", "Georgia, 'Times New Roman', serif"],
    ["Times New Roman", "'Times New Roman', Times, serif"],
    ["Courier New", "'Courier New', Courier, monospace"],
];

export const defaultDesign = (account = {}, brandLogo = "") => ({
    name: "",
    title: "",
    company: "Mixx Tune Entertainments",
    title_sep: ", ",
    email: account.address ?? "",
    phone: "",
    phone2: "",
    website: "www.mixxtune.com",
    address: "",
    socials: { instagram: "", youtube: "", facebook: "", x: "", linkedin: "", spotify: "" },
    note: "",
    logo_url: brandLogo,
    logo_ratio: 0.8,
    logo_width: 125,
    logo_shape: "rounded",
    layout: "left",
    logo_valign: "top",
    gap: 14,
    line_title: true,
    line_side: false,
    line_color: "#4f4bdc",
    line_width: 2,
    font: "Arial",
    name_size: 20,
    title_size: 14,
    text_size: 13,
    name_color: "#111111",
    title_color: "#111111",
    text_color: "#444444",
    link_color: "#4f4bdc",
    labels: "none",
    bold_title: true,
    bg: "box",
    bg_color: "#ffffff",
    bg_padding: 12,
    bg_radius: 8,
    border: false,
    border_color: "#e2e4ea",
});

/*
 * W109: settings read back from the server - an empty box is saved as null (Laravel turns ""
 * into null), so every value is put back in its own type here. Without this, Edit of a mailbox
 * with a designer signature crashed ("Cannot read properties of null").
 */
export function normalizeDesign(raw, account = {}, brandLogo = "") {
    const base = defaultDesign(account, brandLogo);
    const src = raw && typeof raw === "object" ? raw : {};
    const out = {};
    Object.keys(base).forEach((k) => {
        const def = base[k];
        const has = Object.prototype.hasOwnProperty.call(src, k);
        const v = src[k];
        if (k === "socials") {
            const so = v && typeof v === "object" ? v : {};
            out.socials = {};
            Object.keys(def).forEach((n) => (out.socials[n] = so[n] == null ? "" : String(so[n])));
        } else if (typeof def === "string") {
            out[k] = !has ? def : v == null ? "" : String(v);
        } else if (typeof def === "number") {
            const n = Number(v);
            out[k] = v == null || v === "" || Number.isNaN(n) ? def : n;
        } else if (typeof def === "boolean") {
            out[k] = v == null ? def : v === true || v === 1 || v === "1" || v === "true";
        } else {
            out[k] = v == null ? def : v;
        }
    });
    return out;
}

const esc = (s) => String(s ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
const href = (v) => {
    const s = String(v ?? "").trim();
    if (!s) return "";
    return /^https?:\/\//i.test(s) ? s : `https://${s.replace(/^\/+/, "")}`;
};
const SOCIAL = [
    ["instagram", "Instagram"],
    ["youtube", "YouTube"],
    ["facebook", "Facebook"],
    ["x", "X"],
    ["linkedin", "LinkedIn"],
    ["spotify", "Spotify"],
];

/* the signature HTML - used for the preview and saved as the signature that is sent */
export function renderSignature(raw) {
    const d = normalizeDesign(raw);
    const font = (SIG_FONTS.find(([n]) => n === d.font) || SIG_FONTS[0])[1];
    const ts = Number(d.text_size) || 13;
    const lab = (short, word) => (d.labels === "letters" ? `<b style="color:${d.title_color}">${short}</b>&nbsp; ` : d.labels === "words" ? `<span style="color:${d.title_color}">${word}:</span> ` : "");
    const row = (inner) => `<div style="font-size:${ts}px;line-height:${Math.round(ts * 1.65)}px;color:${d.text_color}">${inner}</div>`;
    const link = (url, text, colored = true) => `<a href="${esc(url)}" style="color:${colored ? d.link_color : d.text_color};text-decoration:none">${esc(text)}</a>`;

    const titleLine = [d.title, d.company].map((s) => String(s || "").trim()).filter(Boolean).map(esc).join(esc(d.title_sep || ", "));
    let details = "";
    if (String(d.name).trim()) details += `<div style="font-size:${d.name_size}px;line-height:${Math.round(d.name_size * 1.3)}px;font-weight:bold;color:${d.name_color}">${esc(d.name.trim())}</div>`;
    if (titleLine) details += `<div style="font-size:${d.title_size}px;line-height:${Math.round(d.title_size * 1.4)}px;${d.bold_title ? "font-weight:bold;" : ""}color:${d.title_color};margin-top:4px">${titleLine}</div>`;
    if (d.line_title && (details !== "")) details += `<div style="border-top:${d.line_width}px solid ${d.line_color};margin:8px 0 8px 0;font-size:0;line-height:0">&nbsp;</div>`;
    else if (details !== "") details += `<div style="height:6px;font-size:0;line-height:0">&nbsp;</div>`;
    if (String(d.email).trim()) details += row(lab("E", "Email") + link(`mailto:${d.email.trim()}`, d.email.trim(), false));
    const phones = [d.phone, d.phone2].map((p) => String(p || "").trim()).filter(Boolean);
    if (phones.length) details += row(lab("P", "Phone") + phones.map((p) => link(`tel:${p.replace(/[^\d+]/g, "")}`, p, false)).join(" &nbsp;|&nbsp; "));
    if (String(d.address).trim()) details += row(lab("A", "Address") + esc(d.address.trim()));
    if (String(d.website).trim()) details += row(lab("W", "Web") + link(href(d.website), d.website.trim().replace(/^https?:\/\//i, ""), true));
    const soc = SOCIAL.filter(([k]) => String(d.socials[k] || "").trim()).map(([k, n]) => link(href(d.socials[k]), n, true));
    if (soc.length) details += `<div style="font-size:${ts}px;line-height:${Math.round(ts * 1.65)}px;margin-top:4px">${soc.join(` <span style="color:${d.text_color}">&nbsp;·&nbsp;</span> `)}</div>`;
    if (String(d.note).trim()) details += `<div style="font-size:${Math.max(10, ts - 2)}px;line-height:${Math.round(Math.max(10, ts - 2) * 1.5)}px;color:#888888;margin-top:8px">${esc(d.note.trim()).replace(/\n/g, "<br>")}</div>`;

    const w = Math.max(30, Math.min(220, Number(d.logo_width) || 125));
    const h = Math.max(1, Math.round(w * (Number(d.logo_ratio) || 1)));
    const radius = d.logo_shape === "circle" ? Math.round(Math.min(w, h) / 2) : d.logo_shape === "rounded" ? Math.round(w * 0.08) : 0;
    const logo = d.layout !== "none" && String(d.logo_url || "").trim()
        ? `<img src="${esc(d.logo_url.trim())}" width="${w}" height="${h}" alt="${esc(d.company || d.name || "logo")}" style="display:block;border:0;width:${w}px;height:${h}px;border-radius:${radius}px">`
        : "";

    let body;
    if (logo && d.layout === "left") {
        const side = d.line_side ? `border-right:${d.line_width}px solid ${d.line_color};` : "";
        body = `<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-family:${font};border-collapse:collapse"><tr>`
            + `<td style="vertical-align:${d.logo_valign === "middle" ? "middle" : "top"};padding-right:${d.gap}px;${side}">${logo}</td>`
            + `<td style="vertical-align:top;${d.line_side ? `padding-left:${d.gap}px;` : ""}">${details}</td></tr></table>`;
    } else if (logo && d.layout === "top") {
        body = `<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-family:${font};border-collapse:collapse"><tr><td style="padding-bottom:${d.gap}px;${d.line_side ? `border-bottom:${d.line_width}px solid ${d.line_color};` : ""}">${logo}</td></tr>`
            + `<tr><td style="${d.line_side ? `padding-top:${d.gap}px;` : ""}">${details}</td></tr></table>`;
    } else {
        body = `<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="font-family:${font};border-collapse:collapse"><tr><td>${details}</td></tr></table>`;
    }
    if (d.bg === "box") {
        const bc = /^#[0-9a-f]{6}$/i.test(d.bg_color) ? d.bg_color : "#ffffff";
        const border = d.border ? `border:1px solid ${d.border_color};` : "";
        body = `<table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="${bc}" style="background-color:${bc};border-radius:${d.bg_radius}px;border-collapse:separate;${border}"><tr><td style="padding:${d.bg_padding}px">${body}</td></tr></table>`;
    }

    return body;
}

/* ---------- the form ---------- */
const input = "h-9 w-full rounded-lg border border-slate-300 px-2.5 text-sm";
const Field = ({ label, children, hint }) => (
    <label className="block">
        <span className="text-[11px] font-semibold text-slate-600">{label}</span>
        <div className="mt-0.5">{children}</div>
        {hint && <span className="mt-0.5 block text-[10px] text-slate-400">{hint}</span>}
    </label>
);
/* a group of choice buttons - a <div>, not a <label> (a label would press its first button) */
const Group = ({ label, children }) => (
    <div role="group" aria-label={label}>
        <span className="text-[11px] font-semibold text-slate-600">{label}</span>
        <div className="mt-0.5">{children}</div>
    </div>
);
const Section = ({ n, title, children }) => (
    <div className="rounded-xl border border-slate-200 bg-white">
        <div className="border-b border-slate-100 px-3 py-2 text-xs font-bold text-slate-800">
            <span className="mr-1.5 inline-flex h-5 w-5 items-center justify-center rounded-full bg-violet-600 text-[11px] text-white">{n}</span>
            {title}
        </div>
        <div className="space-y-2.5 p-3">{children}</div>
    </div>
);
const Pick = ({ value, options, onChange }) => (
    <div className="flex flex-wrap gap-1">
        {options.map(([v, n]) => (
            <button key={v} type="button" onClick={() => onChange(v)} className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ${value === v ? "bg-violet-600 text-white ring-violet-600" : "bg-white text-slate-600 ring-slate-300 hover:bg-slate-50"}`}>
                {n}
            </button>
        ))}
    </div>
);
const Slider = ({ label, value, min, max, onChange, unit = "px" }) => (
    <label className="flex items-center gap-2 text-[11px] font-semibold text-slate-600">
        <span className="w-24 shrink-0">{label}</span>
        <input type="range" min={min} max={max} value={value} onChange={(e) => onChange(Number(e.target.value))} aria-label={label} className="min-w-0 flex-1 accent-violet-600" />
        <span className="w-12 text-right text-violet-700">
            {value}
            {unit}
        </span>
    </label>
);
const Colour = ({ label, value, onChange }) => (
    <label className="flex items-center gap-2 text-[11px] font-semibold text-slate-600">
        <input type="color" value={value} onChange={(e) => onChange(e.target.value)} aria-label={label} className="h-7 w-9 cursor-pointer rounded border border-slate-300 bg-white p-0.5" />
        {label}
    </label>
);
const sigDoc = (html, back) =>
    `<!doctype html><html><head><meta charset="utf-8"><base target="_blank"><style>html,body{margin:0;background:${back}}body{padding:12px;font-family:Arial,sans-serif;font-size:14px;color:${back === "#1f1f1f" ? "#e8e8e8" : "#333"}}</style></head><body><div style="margin-bottom:10px">Hello, this is how your mail ends:</div>${html}</body></html>`;

export default function SignatureDesigner({ design, onChange }) {
    const { brandLogo } = usePage().props;
    const d = design;
    const set = (k, v) => onChange({ ...d, [k]: v });
    const setSoc = (k, v) => onChange({ ...d, socials: { ...d.socials, [k]: v } });
    const [back, setBack] = useState("#eef0f4");
    const [up, setUp] = useState(null);
    const html = renderSignature(d);

    const upload = async (file) => {
        if (!file) return;
        setUp({ busy: true });
        const fd = new FormData();
        fd.append("picture", file);
        try {
            const { data: r } = await window.axios.post("/admin/mail-accounts/signature-picture", fd);
            onChange({ ...d, logo_url: r.url, logo_ratio: r.width && r.height ? r.height / r.width : d.logo_ratio });
            setUp({ ok: "Picture uploaded - kept on our own website." });
        } catch (e) {
            const er = e?.response?.data;
            setUp({ error: er?.errors ? Object.values(er.errors).flat()[0] : er?.message || "Upload did not work." });
        }
    };
    const useBrand = () => {
        onChange({ ...d, logo_url: brandLogo, logo_ratio: 0.8 });
        setUp(null);
    };

    return (
        <div className="grid gap-3 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
            <div className="space-y-3">
                <Section n="1" title="Details">
                    <div className="grid gap-2 sm:grid-cols-2">
                        <Field label="Full name *">
                            <input value={d.name} onChange={(e) => set("name", e.target.value)} placeholder="Kapil Koli" className={input} />
                        </Field>
                        <Field label="Job title / post">
                            <input value={d.title} onChange={(e) => set("title", e.target.value)} placeholder="Operations Manager" className={input} />
                        </Field>
                        <Field label="Company">
                            <input value={d.company} onChange={(e) => set("company", e.target.value)} className={input} />
                        </Field>
                        <Group label="Between title and company">
                            <Pick value={d.title_sep} onChange={(v) => set("title_sep", v)} options={[[", ", "Comma ,"], [" | ", "Line |"], [" - ", "Dash -"], [" · ", "Dot ·"]]} />
                        </Group>
                        <Field label="E-mail">
                            <input value={d.email} onChange={(e) => set("email", e.target.value)} className={input} />
                        </Field>
                        <Field label="Phone">
                            <input value={d.phone} onChange={(e) => set("phone", e.target.value)} placeholder="+91 ..." className={input} />
                        </Field>
                        <Field label="Second phone (optional)">
                            <input value={d.phone2} onChange={(e) => set("phone2", e.target.value)} className={input} />
                        </Field>
                        <Field label="Website">
                            <input value={d.website} onChange={(e) => set("website", e.target.value)} className={input} />
                        </Field>
                    </div>
                    <Field label="Address">
                        <input value={d.address} onChange={(e) => set("address", e.target.value)} placeholder="Office address" className={input} />
                    </Field>
                    <div>
                        <span className="text-[11px] font-semibold text-slate-600">Social links (optional - only the filled ones are shown)</span>
                        <div className="mt-0.5 grid gap-2 sm:grid-cols-3">
                            {SOCIAL.map(([k, n]) => (
                                <input key={k} value={d.socials[k]} onChange={(e) => setSoc(k, e.target.value)} placeholder={`${n} link`} aria-label={`${n} link`} className={input} />
                            ))}
                        </div>
                    </div>
                    <Field label="Small note at the end (optional)" hint="For example: Please do not print this mail unless needed.">
                        <textarea rows={2} value={d.note} onChange={(e) => set("note", e.target.value)} className="w-full rounded-lg border border-slate-300 p-2 text-sm" />
                    </Field>
                </Section>

                <Section n="2" title="Logo / photo">
                    <div className="flex flex-wrap items-center gap-2">
                        <div className="flex h-14 w-16 items-center justify-center overflow-hidden rounded border border-slate-200 bg-[repeating-conic-gradient(#eef0f4_0_25%,#fff_0_50%)] bg-[length:12px_12px]">
                            {d.logo_url ? <img src={d.logo_url} alt="" className="max-h-14 max-w-16" /> : <span className="text-[10px] text-slate-400">none</span>}
                        </div>
                        <button type="button" onClick={useBrand} className="rounded-lg border border-violet-300 bg-violet-50 px-2.5 py-1.5 text-[11px] font-semibold text-violet-800 hover:bg-violet-100">
                            Use the Mixx Tune logo
                        </button>
                        <label className="cursor-pointer rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-[11px] font-semibold text-slate-700 hover:bg-slate-50">
                            {up?.busy ? "Uploading..." : "Upload logo / photo"}
                            <input type="file" accept="image/png,image/jpeg,image/gif,image/webp" className="hidden" onChange={(e) => { const f = e.target.files?.[0]; e.target.value = ""; upload(f); }} />
                        </label>
                        {d.logo_url && (
                            <button type="button" onClick={() => set("logo_url", "")} className="text-[11px] font-semibold text-red-600">
                                Remove picture
                            </button>
                        )}
                    </div>
                    {up?.ok && <p className="text-[11px] font-semibold text-emerald-700">{up.ok}</p>}
                    {up?.error && <p className="text-[11px] font-semibold text-red-600">{up.error}</p>}
                    <p className="text-[10px] text-slate-400">PNG, JPG, GIF or WEBP, max 2 MB. A PNG with a see-through background looks best. Kept on our own website, so it never breaks.</p>
                    <Slider label="Picture width" value={d.logo_width} min={40} max={220} onChange={(v) => set("logo_width", v)} />
                    <Group label="Picture shape">
                        <Pick value={d.logo_shape} onChange={(v) => set("logo_shape", v)} options={[["square", "Square"], ["rounded", "Round corners"], ["circle", "Circle (photo)"]]} />
                        <span className="mt-0.5 block text-[10px] text-slate-400">Circle looks right with a square photo.</span>
                    </Group>
                </Section>

                <Section n="3" title="Layout and lines">
                    <Group label="Layout">
                        <Pick value={d.layout} onChange={(v) => set("layout", v)} options={[["left", "Picture left, details right"], ["top", "Picture on top"], ["none", "No picture"]]} />
                    </Group>
                    {d.layout === "left" && (
                        <Group label="Picture next to the details">
                            <Pick value={d.logo_valign} onChange={(v) => set("logo_valign", v)} options={[["top", "At the top"], ["middle", "In the middle"]]} />
                        </Group>
                    )}
                    <Slider label="Space picture / text" value={d.gap} min={4} max={40} onChange={(v) => set("gap", v)} />
                    <div className="space-y-1.5">
                        <span className="text-[11px] font-semibold text-slate-600">Where a line goes</span>
                        <label className="flex items-center gap-2 text-xs text-slate-700">
                            <input type="checkbox" checked={d.line_title} onChange={(e) => set("line_title", e.target.checked)} /> Under the name and job title
                        </label>
                        {d.layout !== "none" && (
                            <label className="flex items-center gap-2 text-xs text-slate-700">
                                <input type="checkbox" checked={d.line_side} onChange={(e) => set("line_side", e.target.checked)} /> Between the picture and the details
                            </label>
                        )}
                    </div>
                    {(d.line_title || d.line_side) && (
                        <div className="flex flex-wrap items-center gap-4">
                            <Colour label="Line colour" value={d.line_color} onChange={(v) => set("line_color", v)} />
                            <div className="min-w-[14rem] flex-1">
                                <Slider label="Line thickness" value={d.line_width} min={1} max={5} onChange={(v) => set("line_width", v)} />
                            </div>
                        </div>
                    )}
                    <Group label="Before e-mail / phone / web">
                        <Pick value={d.labels} onChange={(v) => set("labels", v)} options={[["none", "Nothing"], ["letters", "E  P  A  W"], ["words", "Email: Phone: ..."]]} />
                    </Group>
                </Section>

                <Section n="4" title="Font and colours">
                    <Field label="Font (all mail apps have these)">
                        <select value={d.font} onChange={(e) => set("font", e.target.value)} className={input}>
                            {SIG_FONTS.map(([n, f]) => (
                                <option key={n} value={n} style={{ fontFamily: f }}>
                                    {n}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Slider label="Name size" value={d.name_size} min={14} max={30} onChange={(v) => set("name_size", v)} />
                    <Slider label="Title size" value={d.title_size} min={11} max={20} onChange={(v) => set("title_size", v)} />
                    <Slider label="Text size" value={d.text_size} min={10} max={17} onChange={(v) => set("text_size", v)} />
                    <label className="flex items-center gap-2 text-xs text-slate-700">
                        <input type="checkbox" checked={d.bold_title} onChange={(e) => set("bold_title", e.target.checked)} /> Job title in bold
                    </label>
                    <div className="flex flex-wrap gap-x-5 gap-y-2">
                        <Colour label="Name" value={d.name_color} onChange={(v) => set("name_color", v)} />
                        <Colour label="Job title" value={d.title_color} onChange={(v) => set("title_color", v)} />
                        <Colour label="Details" value={d.text_color} onChange={(v) => set("text_color", v)} />
                        <Colour label="Links" value={d.link_color} onChange={(v) => set("link_color", v)} />
                    </div>
                </Section>

                <Section n="5" title="Background">
                    <Pick value={d.bg} onChange={(v) => set("bg", v)} options={[["box", "Box only as big as the signature"], ["none", "None (see-through)"]]} />
                    {d.bg === "box" && (
                        <>
                            <div className="flex flex-wrap items-center gap-4">
                                <Colour label="Box colour" value={d.bg_color} onChange={(v) => set("bg_color", v)} />
                                <label className="flex items-center gap-2 text-xs text-slate-700">
                                    <input type="checkbox" checked={d.border} onChange={(e) => set("border", e.target.checked)} /> Thin border
                                </label>
                                {d.border && <Colour label="Border colour" value={d.border_color} onChange={(v) => set("border_color", v)} />}
                            </div>
                            <Slider label="Space around" value={d.bg_padding} min={0} max={40} onChange={(v) => set("bg_padding", v)} />
                            <Slider label="Round corners" value={d.bg_radius} min={0} max={30} onChange={(v) => set("bg_radius", v)} />
                        </>
                    )}
                    {d.bg === "none" && <p className="text-[11px] text-slate-500">The signature sits on the receiver's own mailbox colour. Dark text can be hard to read in dark mode.</p>}
                </Section>
            </div>

            <div className="lg:sticky lg:top-20 lg:self-start">
                <div className="rounded-xl border border-slate-200 bg-white">
                    <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 px-3 py-2">
                        <span className="text-xs font-bold text-slate-800">Preview</span>
                        <span className="text-[11px] text-slate-500">exactly what receivers get, on a mailbox that is</span>
                        {[
                            ["White", "#ffffff"],
                            ["Grey", "#eef0f4"],
                            ["Dark", "#1f1f1f"],
                        ].map(([n, bg]) => (
                            <button key={n} type="button" onClick={() => setBack(bg)} className={`rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1 ${back === bg ? "bg-slate-800 text-white ring-slate-800" : "bg-white text-slate-600 ring-slate-300"}`}>
                                {n}
                            </button>
                        ))}
                    </div>
                    <iframe title="Signature preview" sandbox="" srcDoc={sigDoc(html, back)} className="h-[26rem] w-full rounded-b-xl" style={{ background: back }} />
                </div>
                {!String(d.name).trim() && <p className="mt-2 text-[11px] font-semibold text-amber-700">Write the full name to save this signature.</p>}
            </div>
        </div>
    );
}
