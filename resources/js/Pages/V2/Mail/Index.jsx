import { Head, Link, router, usePage } from "@inertiajs/react";
import { ALargeSmall, ArrowLeft, CornerUpLeft, CornerUpRight, ImagePlus, Inbox, Link2, Mail, Maximize2, Minimize2, Minus, Paperclip, PenLine, PenSquare, RefreshCw, ReplyAll, Search, Send, Smile, Trash2, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { EMOJI, Editor, esc, shrinkImage, textToHtml, Toolbar, useRich } from "@/V2/Shared/Mail/RichEditor";

/*
 * W95 MIXXTUNE_PANEL_MAIL
 * Inbox / Sent of the domain mailboxes given to this person (Super Admin: all).
 * New mail is read every 2 minutes; "Refresh" reads it now.
 */
const when = (v, long = false) => {
    if (!v) return "";
    const d = new Date(v);
    const today = new Date();
    if (!long && d.toDateString() === today.toDateString()) return new Intl.DateTimeFormat("en-IN", { hour: "2-digit", minute: "2-digit", hour12: true }).format(d);
    return new Intl.DateTimeFormat("en-IN", long ? { day: "2-digit", month: "short", year: "numeric", hour: "2-digit", minute: "2-digit", hour12: true } : { day: "2-digit", month: "short" }).format(d);
};
const size = (b) => (b > 1048576 ? `${(b / 1048576).toFixed(1)} MB` : `${Math.max(1, Math.round(b / 1024))} KB`);

const linkHref = (v) => {
    const s = String(v ?? "").trim();
    if (!s) return "";
    if (/^(https?:|mailto:|tel:)/i.test(s)) return s;
    if (/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(s)) return `mailto:${s}`;
    return `https://${s}`;
};

/*
 * W103: the Write window works like Gmail - a box at the bottom right that can be made small
 * or big, a full formatting toolbar, links, emoji, pictures inside the text, signature on/off.
 */
function Compose({ account, draft, onClose }) {
    const errors = usePage().props.errors ?? {};
    const [to, setTo] = useState(draft.to ?? "");
    const [cc, setCc] = useState(draft.cc ?? "");
    const [showCc, setShowCc] = useState(Boolean(draft.cc));
    /* W101: Bcc, and the From address (the mailbox or one of its aliases) */
    const [bcc, setBcc] = useState("");
    const [showBcc, setShowBcc] = useState(false);
    const fromList = account.from?.length ? account.from : [{ address: account.address, name: account.display_name || "" }];
    const [from, setFrom] = useState(draft.from && fromList.some((f) => f.address === draft.from) ? draft.from : fromList[0].address);
    const [subject, setSubject] = useState(draft.subject ?? "");
    const [text, setText] = useState("");
    const [html, setHtml] = useState("");
    const [files, setFiles] = useState([]);
    const [kept, setKept] = useState(draft.carried ?? []);
    const [busy, setBusy] = useState(false);
    const [mode, setMode] = useState("normal");
    const [tools, setTools] = useState(true);
    const hasSignature = Boolean(account.signature_html || account.signature);
    const [sigOn, setSigOn] = useState(true);
    const [pop, setPop] = useState(null);
    const [link, setLink] = useState({ text: "", url: "" });
    const [askDiscard, setAskDiscard] = useState(false);
    const rich = useRich();
    const picture = useRef(null);
    const total = files.reduce((s, f) => s + f.size, 0) + kept.reduce((s, a) => s + (a.size ?? 0), 0) + html.length;
    const hasImage = /<img\b/i.test(html);
    const sync = () => {
        const el = rich.ref.current;
        if (!el) return;
        setHtml(el.innerHTML);
        setText(el.innerText ?? "");
    };
    const fromLabel = (f) => (f.name ? `${f.name} <${f.address}>` : f.address);

    const send = () => {
        if (busy || !canSend) return;
        setBusy(true);
        const el = rich.ref.current;
        router.post(
            `/mail/accounts/${account.id}/send`,
            { from, to, cc, bcc, subject, body: (el?.innerText ?? text).replace(/ /g, " "), html: el?.innerHTML ?? html, signature: sigOn ? 1 : 0, files, reply_to_id: draft.reply_to_id ?? "", forward_of: draft.forward_of ?? "", keep_attachments: kept.map((a) => a.id) },
            { forceFormData: true, preserveScroll: true, onSuccess: onClose, onFinish: () => setBusy(false) },
        );
    };
    const canSend = !busy && to.trim() && subject.trim() && (text.trim() || hasImage) && total <= 24 * 1048576;

    const openLink = () => {
        rich.save();
        setLink({ text: rich.selectedText(), url: "" });
        setPop(pop === "link" ? null : "link");
    };
    const addLink = () => {
        const href = linkHref(link.url);
        if (!href) return;
        if (rich.selectedText()) {
            rich.exec("createLink", href);
        } else {
            rich.insertHtml(`<a href="${esc(href)}">${esc(link.text.trim() || link.url.trim())}</a>&nbsp;`);
        }
        setPop(null);
        sync();
    };
    const addPictures = async (list) => {
        for (const f of Array.from(list ?? []).slice(0, 10)) {
            if (!f.type.startsWith("image/")) continue;
            try {
                const src = await shrinkImage(f);
                rich.insertHtml(`<img src="${src}" alt="${esc(f.name)}" style="max-width:100%;height:auto">`);
            } catch {
                /* not a picture the browser can read */
            }
        }
        sync();
    };

    const err = errors.to || errors.to_list || errors["to_list.0"] || errors.cc_list || errors["cc_list.0"] || errors.bcc_list || errors["bcc_list.0"] || errors.from || errors.subject || errors.body || errors.html || errors.files || errors["files.0"] || errors.send;
    const big = mode === "max";
    const small = mode === "min";
    const icon = "rounded p-1 text-slate-600 hover:bg-slate-200";
    const tool = (on = false) => `inline-flex h-9 w-9 items-center justify-center rounded-full hover:bg-slate-100 ${on ? "bg-violet-100 text-violet-700" : "text-slate-600"}`;
    const row = "flex items-center gap-2 border-b border-slate-200 py-1.5";

    return (
        <div className={big ? "fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 sm:p-6" : "pointer-events-none fixed inset-x-0 bottom-0 z-50 flex justify-end sm:px-6"}>
            <div
                onKeyDown={(e) => {
                    if (e.key === "Enter" && (e.ctrlKey || e.metaKey)) {
                        e.preventDefault();
                        send();
                    }
                }}
                className={`pointer-events-auto flex flex-col overflow-hidden bg-white shadow-2xl ring-1 ring-slate-200 ${
                    big ? "h-[100dvh] w-full sm:h-[92vh] sm:max-w-5xl sm:rounded-2xl" : small ? "w-full sm:w-80 sm:rounded-t-xl" : "h-[100dvh] w-full sm:h-[min(680px,90vh)] sm:w-[640px] sm:rounded-t-2xl"
                }`}
            >
                <div className="flex cursor-pointer items-center justify-between bg-slate-100 px-4 py-2" onClick={() => small && setMode("normal")}>
                    <h3 className="truncate text-sm font-semibold text-slate-800">{subject.trim() || draft.title || "New Message"}</h3>
                    <div className="flex items-center gap-0.5" onClick={(e) => e.stopPropagation()}>
                        <button type="button" onClick={() => setMode(small ? "normal" : "min")} className={icon} title={small ? "Open" : "Make small"} aria-label={small ? "Open" : "Make small"}>
                            <Minus size={16} />
                        </button>
                        <button type="button" onClick={() => setMode(big ? "normal" : "max")} className={`${icon} hidden sm:block`} title={big ? "Exit full screen" : "Full screen"} aria-label={big ? "Exit full screen" : "Full screen"}>
                            {big ? <Minimize2 size={15} /> : <Maximize2 size={15} />}
                        </button>
                        <button type="button" onClick={onClose} disabled={busy} className={icon} title="Save nothing and close" aria-label="Close">
                            <X size={17} />
                        </button>
                    </div>
                </div>
                <div className={`${small ? "hidden" : "flex"} min-h-0 flex-1 flex-col`}>
                    <div className="px-4">
                        <div className={row}>
                            <span className="w-14 shrink-0 text-sm text-slate-500">From</span>
                            {fromList.length > 1 ? (
                                <select value={from} onChange={(e) => setFrom(e.target.value)} aria-label="From" className="h-8 min-w-0 flex-1 border-0 bg-transparent px-0 text-sm text-slate-800 focus:ring-0">
                                    {fromList.map((f) => (
                                        <option key={f.address} value={f.address}>
                                            {fromLabel(f)}
                                        </option>
                                    ))}
                                </select>
                            ) : (
                                <span className="truncate text-sm text-slate-800">{fromLabel(fromList[0])}</span>
                            )}
                        </div>
                        <div className={row}>
                            <span className="w-14 shrink-0 text-sm text-slate-500">To</span>
                            <input value={to} onChange={(e) => setTo(e.target.value)} autoFocus={!draft.to} aria-label="To" placeholder="name@example.com, other@example.com" className="h-8 min-w-0 flex-1 border-0 px-0 text-sm focus:ring-0" />
                            {!showCc && (
                                <button type="button" onClick={() => setShowCc(true)} className="text-sm text-slate-600 hover:text-slate-900 hover:underline">
                                    Cc
                                </button>
                            )}
                            {!showBcc && (
                                <button type="button" onClick={() => setShowBcc(true)} className="text-sm text-slate-600 hover:text-slate-900 hover:underline">
                                    Bcc
                                </button>
                            )}
                        </div>
                        {showCc && (
                            <div className={row}>
                                <span className="w-14 shrink-0 text-sm text-slate-500">Cc</span>
                                <input value={cc} onChange={(e) => setCc(e.target.value)} aria-label="Cc" className="h-8 min-w-0 flex-1 border-0 px-0 text-sm focus:ring-0" />
                            </div>
                        )}
                        {showBcc && (
                            <div className={row}>
                                <span className="w-14 shrink-0 text-sm text-slate-500">Bcc</span>
                                <input value={bcc} onChange={(e) => setBcc(e.target.value)} aria-label="Bcc" placeholder="hidden copy - others do not see these addresses" className="h-8 min-w-0 flex-1 border-0 px-0 text-sm focus:ring-0" />
                            </div>
                        )}
                        <div className={row}>
                            <input value={subject} onChange={(e) => setSubject(e.target.value)} aria-label="Subject" placeholder="Subject" className="h-8 min-w-0 flex-1 border-0 px-0 text-sm focus:ring-0" />
                        </div>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto px-4" onClick={(e) => e.target === e.currentTarget && rich.restore()}>
                        <Editor rich={rich} initialHtml={draft.html ?? textToHtml(draft.body ?? "")} caretAtStart={Boolean(draft.html)} autoFocus={Boolean(draft.to)} onChange={sync} />
                        {/* W102: the signature that goes under this mail (W103: can be switched off for one mail) */}
                        {sigOn && account.signature_html ? (
                            <div className="mb-2 rounded-lg border border-dashed border-slate-300">
                                <div className="px-3 pt-1.5 text-[11px] font-semibold text-slate-500">Signature (added automatically)</div>
                                <iframe title="Signature" sandbox="" srcDoc={`<!doctype html><html><head><meta charset="utf-8"><style>body{margin:8px 12px;font-family:Arial,sans-serif;font-size:14px}img{max-width:100%}</style></head><body>${account.signature_html}</body></html>`} className="h-64 w-full rounded-b-lg bg-white" />
                            </div>
                        ) : sigOn && account.signature ? (
                            <p className="mb-2 whitespace-pre-line text-sm text-slate-500">-- {"\n"}{account.signature}</p>
                        ) : null}
                        {(kept.length > 0 || files.length > 0) && (
                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                {kept.map((a) => (
                                    <span key={`k${a.id}`} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-800">
                                        <Paperclip size={12} /> {a.filename} · {size(a.size ?? 0)}
                                        <button type="button" onClick={() => setKept(kept.filter((k) => k.id !== a.id))} aria-label="Remove">
                                            <X size={12} />
                                        </button>
                                    </span>
                                ))}
                                {files.map((f, i) => (
                                    <span key={`${f.name}-${i}`} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-800">
                                        <Paperclip size={12} /> {f.name} · {size(f.size)}
                                        <button type="button" onClick={() => setFiles(files.filter((_, j) => j !== i))} aria-label="Remove">
                                            <X size={12} />
                                        </button>
                                    </span>
                                ))}
                            </div>
                        )}
                        {draft.note && <p className="mb-2 text-[11px] text-slate-500">{draft.note}</p>}
                    </div>
                    <div className="space-y-2 px-3 pb-3 pt-1">
                        {total > 24 * 1048576 && <p className="text-xs font-semibold text-red-600">Too big: attachments and pictures together can be at most 24 MB</p>}
                        {err && <p className="rounded-lg bg-red-50 px-3 py-2 text-xs font-semibold text-red-700">{err}</p>}
                        {tools && <Toolbar rich={rich} />}
                        <div className="relative flex items-center gap-1">
                            <button type="button" onClick={send} disabled={!canSend} title="Send (Ctrl+Enter)" className="inline-flex items-center gap-2 rounded-full bg-violet-600 px-6 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                                <Send size={15} /> {busy ? "Sending..." : "Send"}
                            </button>
                            <button type="button" onClick={() => setTools(!tools)} className={tool(tools)} title="Formatting options" aria-label="Formatting options">
                                <ALargeSmall size={19} />
                            </button>
                            <label className={`${tool()} cursor-pointer`} title="Attach files" aria-label="Attach files">
                                <Paperclip size={18} />
                                <input type="file" multiple className="hidden" onChange={(e) => { setFiles([...files, ...Array.from(e.target.files ?? [])].slice(0, 10)); e.target.value = ""; }} />
                            </label>
                            <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={openLink} className={tool(pop === "link")} title="Insert link" aria-label="Insert link">
                                <Link2 size={18} />
                            </button>
                            <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => { rich.save(); setPop(pop === "emoji" ? null : "emoji"); }} className={tool(pop === "emoji")} title="Insert emoji" aria-label="Insert emoji">
                                <Smile size={18} />
                            </button>
                            <button type="button" onMouseDown={(e) => e.preventDefault()} onClick={() => { rich.save(); picture.current?.click(); }} className={tool()} title="Insert photo in the text" aria-label="Insert photo">
                                <ImagePlus size={18} />
                            </button>
                            <input ref={picture} type="file" accept="image/png,image/jpeg,image/gif,image/webp" multiple className="hidden" onChange={(e) => { addPictures(e.target.files); e.target.value = ""; }} />
                            {hasSignature && (
                                <button type="button" onClick={() => setSigOn(!sigOn)} className={tool(sigOn)} title={sigOn ? "Signature on - click to send this mail without it" : "Signature off - click to add it"} aria-label="Signature on or off">
                                    <PenLine size={18} />
                                </button>
                            )}
                            <div className="flex-1" />
                            {askDiscard ? (
                                <span className="flex items-center gap-1 text-xs text-slate-600">
                                    Discard?
                                    <button type="button" onClick={onClose} className="rounded-md bg-red-600 px-2 py-1 font-semibold text-white">Yes</button>
                                    <button type="button" onClick={() => setAskDiscard(false)} className="rounded-md border border-slate-300 px-2 py-1 font-semibold">No</button>
                                </span>
                            ) : (
                                <button type="button" onClick={() => (text.trim() || hasImage || files.length ? setAskDiscard(true) : onClose())} disabled={busy} className={tool()} title="Discard this mail" aria-label="Discard">
                                    <Trash2 size={18} />
                                </button>
                            )}
                            {pop === "link" && (
                                <div className="absolute bottom-full left-24 z-30 mb-2 w-80 rounded-xl border border-slate-200 bg-white p-3 shadow-xl">
                                    <label className="block text-[11px] font-semibold text-slate-500">
                                        Text to show
                                        <input value={link.text} onChange={(e) => setLink({ ...link, text: e.target.value })} disabled={Boolean(rich.selectedText())} className="mt-0.5 h-8 w-full rounded-md border border-slate-300 px-2 text-sm font-normal text-slate-800" />
                                    </label>
                                    <label className="mt-2 block text-[11px] font-semibold text-slate-500">
                                        Web address or e-mail
                                        <input value={link.url} autoFocus onChange={(e) => setLink({ ...link, url: e.target.value })} onKeyDown={(e) => e.key === "Enter" && (e.preventDefault(), addLink())} placeholder="www.mixxtune.com" className="mt-0.5 h-8 w-full rounded-md border border-slate-300 px-2 text-sm font-normal text-slate-800" />
                                    </label>
                                    <div className="mt-2 flex justify-end gap-2">
                                        <button type="button" onClick={() => setPop(null)} className="rounded-md px-3 py-1 text-xs font-semibold text-slate-600">Cancel</button>
                                        <button type="button" onClick={addLink} disabled={!link.url.trim()} className="rounded-md bg-violet-600 px-3 py-1 text-xs font-semibold text-white disabled:opacity-50">OK</button>
                                    </div>
                                </div>
                            )}
                            {pop === "emoji" && (
                                <div className="absolute bottom-full left-32 z-30 mb-2 grid w-72 grid-cols-8 gap-0.5 rounded-xl border border-slate-200 bg-white p-2 shadow-xl" onMouseDown={(e) => e.preventDefault()}>
                                    {EMOJI.map((m) => (
                                        <button key={m} type="button" onClick={() => { rich.exec("insertText", m); sync(); }} className="rounded-md p-1 text-lg hover:bg-slate-100">
                                            {m}
                                        </button>
                                    ))}
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
}

export default function MailIndex({ role = "admin", accounts = [], current = null, folder = "inbox", search = "", messages = null, open = null, isSuperAdmin = false }) {
    const { flash = {}, errors = {} } = usePage().props;
    const account = accounts.find((a) => a.id === current) ?? null;
    const [q, setQ] = useState(search);
    const [draft, setDraft] = useState(null);
    const [busy, setBusy] = useState(false);
    const [plain, setPlain] = useState(false);
    const rows = messages?.data ?? [];

    useEffect(() => setPlain(false), [open?.id]);

    const go = (params) => router.get("/mail", { account: current, folder, q: q || undefined, ...params }, { preserveScroll: true, preserveState: true });
    const refresh = () => {
        if (!account || busy) return;
        setBusy(true);
        router.post(`/mail/accounts/${account.id}/refresh`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    /* W103: the earlier mail goes under the reply as a grey quote, as in Gmail */
    const who = (m) => (m.from_name ? `${m.from_name} <${m.from_email}>` : m.from_email);
    const quote = (m) => `<br><br><div>On ${esc(when(m.at, true))}, ${esc(who(m))} wrote:</div><blockquote style="margin:0 0 0 0.8ex;border-left:1px solid #ccc;padding-left:1ex">${textToHtml(m.text ?? "")}</blockquote>`;
    const re = (s) => (/^re:/i.test(s) ? s : `Re: ${s}`);
    const reply = (all) => {
        const own = account.address.toLowerCase();
        const back = open.folder === "sent" ? open.to_list.join(", ") : open.reply_to || open.from_email;
        const others = all ? [...open.to_list, ...open.cc_list].filter((e) => e && e.toLowerCase() !== own && e.toLowerCase() !== String(back).toLowerCase()) : [];
        const mineAddr = (account.from ?? []).map((f) => f.address);
        const hit = [...(open.to_list ?? []), ...(open.cc_list ?? [])].map((e) => String(e).toLowerCase()).find((e) => mineAddr.includes(e));
        setDraft({ title: all ? "Reply all" : "Reply", to: back, cc: others.filter((e) => !mineAddr.includes(String(e).toLowerCase())).join(", "), subject: re(open.subject), html: quote(open), reply_to_id: open.id, from: open.folder === "sent" ? open.from_email : hit });
    };
    const forward = () =>
        setDraft({
            title: "Forward",
            to: "",
            subject: /^fwd?:/i.test(open.subject) ? open.subject : `Fwd: ${open.subject}`,
            html: `<br><br><div>---------- Forwarded message ---------<br>From: ${esc(who(open))}<br>Date: ${esc(when(open.at, true))}<br>Subject: ${esc(open.subject)}<br>To: ${esc(open.to)}</div><br>${textToHtml(open.text ?? "")}`,
            forward_of: open.id,
            carried: open.attachments ?? [],
            note: open.attachments?.length ? "The attachments of the original mail go along - remove any you do not want to send." : null,
        });

    const listTitle = useMemo(() => (folder === "sent" ? "Sent" : "Inbox"), [folder]);

    if (accounts.length === 0) {
        return (
            <PanelLayout role={role} title="Mail">
                <Head title="Mail" />
                <div className="mx-auto max-w-xl rounded-2xl border border-slate-200 bg-white p-8 text-center shadow-sm">
                    <Mail className="mx-auto text-slate-400" size={36} />
                    <h2 className="mt-3 text-base font-bold text-slate-900">No mailbox yet</h2>
                    <p className="mt-1 text-sm text-slate-500">
                        {isSuperAdmin ? "Create the mailbox in Hostinger hPanel → Emails, then add it under Email accounts." : "Ask the Super Admin to give you a mailbox."}
                    </p>
                    {isSuperAdmin && (
                        <Link href="/admin/mail-accounts" className="mt-4 inline-flex rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white">
                            Email accounts
                        </Link>
                    )}
                </div>
            </PanelLayout>
        );
    }

    return (
        <PanelLayout role={role} title="Mail" subtitle={account ? `${account.display_name ? `${account.display_name} · ` : ""}${account.address}` : ""}>
            <Head title={`${listTitle} - Mail`} />

            {(flash.success || errors.mail || errors.send) && (
                <div className={`mb-3 rounded-xl px-4 py-2.5 text-sm font-semibold ${flash.success && !errors.mail && !errors.send ? "bg-emerald-50 text-emerald-700" : "bg-red-50 text-red-700"}`}>{errors.mail || errors.send || flash.success}</div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {/* W100: own mailbox first; a Super Admin also sees the team mailboxes */}
                {accounts.length > 1 && (
                    <select value={current ?? ""} onChange={(e) => router.get("/mail", { account: e.target.value, folder })} aria-label="Mailbox" className="h-10 max-w-full rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold">
                        {[
                            ["My mailbox", accounts.filter((a) => a.mine)],
                            [isSuperAdmin ? "Team mailboxes" : "Shared with me", accounts.filter((a) => !a.mine)],
                        ]
                            .filter(([, list]) => list.length > 0)
                            .map(([label, list]) => (
                                <optgroup key={label} label={label}>
                                    {list.map((a) => (
                                        <option key={a.id} value={a.id}>
                                            {a.address}
                                            {a.is_private ? " (private)" : ""}
                                            {a.unread ? ` · ${a.unread} new` : ""}
                                        </option>
                                    ))}
                                </optgroup>
                            ))}
                    </select>
                )}
                {account?.mine && isSuperAdmin && (
                    <span className="rounded-full bg-violet-100 px-2.5 py-1 text-[11px] font-bold text-violet-800">My mailbox{account.is_private ? " · private" : ""}</span>
                )}
                <div className="inline-flex rounded-xl border border-slate-200 bg-white p-1">
                    {[
                        ["inbox", "Inbox", Inbox],
                        ["sent", "Sent", Send],
                    ].map(([key, label, Icon]) => (
                        <button key={key} type="button" onClick={() => router.get("/mail", { account: current, folder: key })} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ${folder === key ? "bg-violet-600 text-white" : "text-slate-600 hover:bg-slate-50"}`}>
                            <Icon size={15} /> {label}
                            {key === "inbox" && account?.unread > 0 && <span className={`rounded-full px-1.5 text-[11px] font-black ${folder === key ? "bg-white text-violet-700" : "bg-violet-600 text-white"}`}>{account.unread}</span>}
                        </button>
                    ))}
                </div>
                <form onSubmit={(e) => { e.preventDefault(); go({ q: q || undefined, m: undefined, page: undefined }); }} className="relative min-w-[180px] flex-1">
                    <Search size={15} className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
                    <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search mail" className="h-10 w-full rounded-xl border border-slate-300 bg-white pl-9 pr-3 text-sm" />
                </form>
                <button type="button" onClick={refresh} disabled={busy} title="Read new mail now" className="inline-flex h-10 items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700 disabled:opacity-50">
                    <RefreshCw size={15} className={busy ? "animate-spin" : ""} /> Refresh
                </button>
                <button type="button" onClick={() => setDraft({})} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                    <PenSquare size={15} /> Write
                </button>
            </div>
            {account && (
                <p className={`mt-1.5 text-[11px] ${account.last_error ? "text-red-600" : "text-slate-400"}`}>
                    {account.last_error ? `Mailbox could not be read: ${account.last_error}` : `Updated ${account.last_synced_at ? when(account.last_synced_at, true) : "never"} · new mail comes in every 2 minutes`}
                </p>
            )}

            <div className="mt-3 grid min-h-[60vh] gap-3 lg:grid-cols-[380px_minmax(0,1fr)]">
                <section className={`overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm ${open ? "hidden lg:block" : ""}`}>
                    {rows.length === 0 ? (
                        <p className="px-5 py-16 text-center text-sm text-slate-500">{search ? "Nothing found." : folder === "sent" ? "No sent mail yet." : "No mail yet."}</p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {rows.map((m) => (
                                <li key={m.id}>
                                    <button type="button" onClick={() => go({ m: m.id })} className={`block w-full px-4 py-3 text-left hover:bg-slate-50 ${open?.id === m.id ? "bg-violet-50" : ""}`}>
                                        <div className="flex items-center justify-between gap-2">
                                            <span className={`truncate text-sm ${m.seen ? "text-slate-700" : "font-black text-slate-950"}`}>{m.who || "—"}</span>
                                            <span className="shrink-0 text-[11px] text-slate-400">{when(m.at)}</span>
                                        </div>
                                        <div className={`flex items-center gap-1 truncate text-sm ${m.seen ? "text-slate-600" : "font-bold text-slate-900"}`}>
                                            {m.has_attachments && <Paperclip size={12} className="shrink-0 text-slate-400" />}
                                            <span className="truncate">{m.subject}</span>
                                        </div>
                                        <div className="truncate text-xs text-slate-400">{m.snippet}</div>
                                    </button>
                                </li>
                            ))}
                        </ul>
                    )}
                    {messages?.last_page > 1 && (
                        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-2 text-xs">
                            {messages.prev_page_url ? <Link href={messages.prev_page_url} preserveScroll className="font-semibold text-violet-700">← Newer</Link> : <span />}
                            <span className="text-slate-400">
                                Page {messages.current_page} of {messages.last_page}
                            </span>
                            {messages.next_page_url ? <Link href={messages.next_page_url} preserveScroll className="font-semibold text-violet-700">Older →</Link> : <span />}
                        </div>
                    )}
                </section>

                <section className={`min-w-0 rounded-2xl border border-slate-200 bg-white shadow-sm ${open ? "" : "hidden lg:block"}`}>
                    {!open ? (
                        <p className="px-5 py-24 text-center text-sm text-slate-400">Choose a mail to read it.</p>
                    ) : (
                        <article className="flex h-full flex-col">
                            <div className="border-b border-slate-100 px-5 py-4">
                                <button type="button" onClick={() => go({ m: undefined })} className="mb-2 inline-flex items-center gap-1 text-xs font-semibold text-slate-500 lg:hidden">
                                    <ArrowLeft size={14} /> Back
                                </button>
                                <h2 className="break-words text-lg font-bold text-slate-950">{open.subject}</h2>
                                <div className="mt-2 flex flex-wrap items-start justify-between gap-2 text-sm">
                                    <div className="min-w-0">
                                        <div className="font-semibold text-slate-900">
                                            {open.from_name || open.from_email} {open.from_name && <span className="font-normal text-slate-500">&lt;{open.from_email}&gt;</span>}
                                        </div>
                                        <div className="break-words text-xs text-slate-500">To: {open.to || "—"}</div>
                                        {open.cc && <div className="break-words text-xs text-slate-500">Cc: {open.cc}</div>}
                                        {open.bcc && <div className="break-words text-xs text-slate-500">Bcc: {open.bcc}</div>}
                                    </div>
                                    <span className="text-xs text-slate-400">{when(open.at, true)}</span>
                                </div>
                                <div className="mt-3 flex flex-wrap gap-2">
                                    <button type="button" onClick={() => reply(false)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <CornerUpLeft size={14} /> Reply
                                    </button>
                                    <button type="button" onClick={() => reply(true)} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <ReplyAll size={14} /> Reply all
                                    </button>
                                    <button type="button" onClick={forward} className="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                        <CornerUpRight size={14} /> Forward
                                    </button>
                                    {open.has_html && open.text && (
                                        <button type="button" onClick={() => setPlain(!plain)} className="ml-auto text-xs font-semibold text-violet-700">
                                            {plain ? "Show formatted" : "Show plain text"}
                                        </button>
                                    )}
                                </div>
                            </div>
                            {open.attachments?.length > 0 && (
                                <div className="flex flex-wrap gap-2 border-b border-slate-100 px-5 py-3">
                                    {open.attachments.map((a) => (
                                        <a key={a.id} href={`/mail/attachments/${a.id}`} className="inline-flex max-w-full items-center gap-1.5 rounded-lg border border-slate-200 bg-slate-50 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-100">
                                            <Paperclip size={13} /> <span className="truncate">{a.filename}</span> <span className="font-normal text-slate-400">{size(a.size)}</span>
                                        </a>
                                    ))}
                                </div>
                            )}
                            <div className="min-h-[50vh] flex-1">
                                {open.has_html && !plain ? (
                                    <iframe key={open.id} title="Mail" src={`/mail/messages/${open.id}/html`} sandbox="allow-popups allow-popups-to-escape-sandbox" className="h-[70vh] w-full rounded-b-2xl bg-white" />
                                ) : (
                                    <pre className="whitespace-pre-wrap break-words px-5 py-4 font-sans text-sm leading-relaxed text-slate-800">{open.text || "(empty)"}</pre>
                                )}
                            </div>
                        </article>
                    )}
                </section>
            </div>

            {draft && account && <Compose key={`${account.id}-${draft.title ?? "new"}-${draft.reply_to_id ?? ""}-${draft.forward_of ?? ""}`} account={account} draft={draft} onClose={() => setDraft(null)} />}
        </PanelLayout>
    );
}
