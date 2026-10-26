import { Head, Link, router, usePage } from "@inertiajs/react";
import { ALargeSmall, ArrowLeft, CheckSquare, ChevronDown, Clock, CornerUpLeft, CornerUpRight, FileText, ImagePlus, Inbox, Link2, Mail, Maximize2, Minimize2, Minus, Paperclip, PenLine, PenSquare, RefreshCw, ReplyAll, Search, Send, Smile, Square, Star, Trash2, X } from "lucide-react";
import { useEffect, useMemo, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import AddressInput from "@/V2/Shared/Mail/AddressInput";
import { MailButtons, MoreFolders, untilText } from "@/V2/Shared/Mail/MailActionsUi";
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
    const [bcc, setBcc] = useState(draft.bcc ?? "");
    const [showBcc, setShowBcc] = useState(Boolean(draft.bcc));
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
    const [sigOn, setSigOn] = useState(draft.signature !== false);
    const [sigOpen, setSigOpen] = useState(false);
    /* one line of the signature for the folded view: "Kapil Koli · Operations Manager ..." */
    const sigLine = useMemo(() => {
        const raw = account.signature_html
            ? String(account.signature_html).replace(/<(br|\/div|\/p|\/td|\/tr|\/li|\/h\d)\b[^>]*>/gi, " · ").replace(/<[^>]+>/g, " ")
            : String(account.signature || "").replace(/\n/g, " · ");
        const t = raw.replace(/&nbsp;/g, " ").replace(/&amp;/g, "&").replace(/&[a-z]+;/g, " ").replace(/\s+/g, " ");
        return t.split("·").map((x) => x.trim()).filter(Boolean).join(" · ").slice(0, 140);
    }, [account.signature_html, account.signature]);
    /* W110: drafts (saved by themselves while writing) and the address suggestions */
    const [contacts, setContacts] = useState([]);
    const [savedAt, setSavedAt] = useState(draft.draft_id ? "kept" : null);
    const draftId = useRef(draft.draft_id ?? null);
    const chain = useRef(Promise.resolve());
    const changed = useRef(false);
    const sending = useRef(false);
    const [pop, setPop] = useState(null);
    const [link, setLink] = useState({ text: "", url: "" });
    const [askDiscard, setAskDiscard] = useState(false);
    const rich = useRich();
    const picture = useRef(null);
    const total = files.reduce((s, f) => s + f.size, 0) + kept.reduce((s, a) => s + (a.size ?? 0), 0) + html.length;
    const hasImage = /<img\b/i.test(html);
    const first = useRef(true);
    const sync = () => {
        const el = rich.ref.current;
        if (!el) return;
        if (first.current) first.current = false;
        else changed.current = true;
        setHtml(el.innerHTML);
        setText(el.innerText ?? "");
    };
    const fromLabel = (f) => (f.name ? `${f.name} <${f.address}>` : f.address);

    useEffect(() => {
        let live = true;
        window.axios
            .get(`/mail/accounts/${account.id}/contacts`)
            .then(({ data: r }) => live && setContacts(r.contacts ?? []))
            .catch(() => {});
        return () => {
            live = false;
        };
    }, [account.id]);

    const hasContent = () => Boolean(to.trim() || cc.trim() || bcc.trim() || subject.trim() || (rich.ref.current?.innerText ?? text).trim() || /<img\b/i.test(rich.ref.current?.innerHTML ?? html));
    const saveDraft = () => {
        chain.current = chain.current.then(async () => {
            if (sending.current || !hasContent()) return;
            const el = rich.ref.current;
            setSavedAt("saving");
            try {
                const { data: r } = await window.axios.post(`/mail/accounts/${account.id}/drafts`, {
                    draft_id: draftId.current, from, to, cc, bcc, subject,
                    body: (el?.innerText ?? text).replace(/\u00a0/g, " "), html: el?.innerHTML ?? html, signature: sigOn ? 1 : 0,
                    reply_to_id: draft.reply_to_id ?? null, forward_of: draft.forward_of ?? null, keep_attachments: kept.map((a) => a.id),
                });
                draftId.current = r.id;
                changed.current = false;
                setSavedAt(new Intl.DateTimeFormat("en-IN", { hour: "2-digit", minute: "2-digit", hour12: true }).format(new Date()));
            } catch {
                setSavedAt("failed");
            }
        });
        return chain.current;
    };
    /* a few seconds after the last change the draft is saved */
    useEffect(() => {
        if (!changed.current) return undefined;
        const t = setTimeout(saveDraft, 2500);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [to, cc, bcc, subject, html, from, sigOn, kept]);
    const touch = (fn) => (v) => {
        changed.current = true;
        fn(v);
    };
    const close = async () => {
        if (changed.current && hasContent()) await saveDraft();
        else await chain.current;
        onClose(Boolean(draftId.current));
    };
    const discard = async () => {
        sending.current = true;
        await chain.current;
        if (draftId.current) await window.axios.post(`/mail/drafts/${draftId.current}/delete`).catch(() => {});
        onClose(true);
    };

    const send = async () => {
        if (busy || !canSend) return;
        setBusy(true);
        await chain.current;
        sending.current = true;
        const el = rich.ref.current;
        router.post(
            `/mail/accounts/${account.id}/send`,
            { from, to, cc, bcc, subject, body: (el?.innerText ?? text).replace(/\u00a0/g, " "), html: el?.innerHTML ?? html, signature: sigOn ? 1 : 0, files, reply_to_id: draft.reply_to_id ?? "", forward_of: draft.forward_of ?? "", keep_attachments: kept.map((a) => a.id), draft_id: draftId.current ?? "" },
            {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: () => onClose(false),
                onError: () => {
                    sending.current = false;
                },
                onFinish: () => setBusy(false),
            },
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
                        <button type="button" onClick={close} disabled={busy} className={icon} title="Close - the draft is kept in Drafts" aria-label="Close">
                            <X size={17} />
                        </button>
                    </div>
                </div>
                <div className={`${small ? "hidden" : "flex"} min-h-0 flex-1 flex-col`}>
                    <div className="px-4">
                        <div className={row}>
                            <span className="w-14 shrink-0 text-sm text-slate-500">From</span>
                            {fromList.length > 1 ? (
                                <select value={from} onChange={(e) => touch(setFrom)(e.target.value)} aria-label="From" className="h-8 min-w-0 flex-1 border-0 bg-transparent px-0 text-sm text-slate-800 focus:ring-0">
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
                            <AddressInput value={to} onChange={touch(setTo)} contacts={contacts} autoFocus={!draft.to} label="To" placeholder="name@example.com, other@example.com" />
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
                                <AddressInput value={cc} onChange={touch(setCc)} contacts={contacts} label="Cc" />
                            </div>
                        )}
                        {showBcc && (
                            <div className={row}>
                                <span className="w-14 shrink-0 text-sm text-slate-500">Bcc</span>
                                <AddressInput value={bcc} onChange={touch(setBcc)} contacts={contacts} label="Bcc" placeholder="hidden copy - others do not see these addresses" />
                            </div>
                        )}
                        <div className={row}>
                            <input value={subject} onChange={(e) => touch(setSubject)(e.target.value)} aria-label="Subject" placeholder="Subject" className="h-8 min-w-0 flex-1 border-0 px-0 text-sm focus:ring-0" />
                        </div>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto px-4" onClick={(e) => e.target === e.currentTarget && rich.restore()}>
                        <Editor rich={rich} initialHtml={draft.html ?? textToHtml(draft.body ?? "")} caretAtStart={Boolean(draft.html)} autoFocus={Boolean(draft.to)} onChange={sync} />
                        {(kept.length > 0 || files.length > 0) && (
                            <div className="mb-2 flex flex-wrap items-center gap-2">
                                {kept.map((a) => (
                                    <span key={`k${a.id}`} className="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs text-slate-800">
                                        <Paperclip size={12} /> {a.filename} · {size(a.size ?? 0)}
                                        <button type="button" onClick={() => touch(setKept)(kept.filter((k) => k.id !== a.id))} aria-label="Remove">
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
                        {/* W102: the signature that goes under this mail (W103: can be switched off for one mail).
                            W111/W112: a thin bar at the very bottom, just above the toolbar; "Show" slides the
                            signature open upwards, "Hide" folds it down again - the writing space stays free */}
                        {sigOn && (account.signature_html || account.signature) && (
                            <div className="rounded-lg border border-dashed border-slate-300">
                                <div className={`grid transition-[grid-template-rows] duration-300 ease-out ${sigOpen ? "grid-rows-[1fr]" : "grid-rows-[0fr]"}`}>
                                    <div className="overflow-hidden">
                                        {account.signature_html ? (
                                            <iframe title="Signature" sandbox="" tabIndex={sigOpen ? 0 : -1} srcDoc={`<!doctype html><html><head><meta charset="utf-8"><style>html{zoom:0.8}body{margin:6px 12px;font-family:Arial,sans-serif;font-size:14px}img{max-width:100%}</style></head><body>${account.signature_html}</body></html>`} className="h-40 w-full rounded-t-lg bg-white" />
                                        ) : (
                                            <p className="whitespace-pre-line px-3 pt-2 text-sm text-slate-500">-- {"\n"}{account.signature}</p>
                                        )}
                                    </div>
                                </div>
                                <button type="button" onClick={() => setSigOpen(!sigOpen)} aria-expanded={sigOpen} title={sigOpen ? "Hide the signature" : "Show the signature"} className="flex w-full min-w-0 items-center gap-2 rounded-lg px-3 py-1.5 text-left text-[11px] hover:bg-slate-50">
                                    <PenLine size={13} className="shrink-0 text-slate-400" />
                                    <span className="shrink-0 font-semibold text-slate-500">Signature (added automatically)</span>
                                    {!sigOpen && sigLine && <span className="min-w-0 truncate text-slate-400">· {sigLine}</span>}
                                    <span className="ml-auto flex shrink-0 items-center gap-0.5 font-semibold text-violet-700">
                                        {sigOpen ? "Hide" : "Show"}
                                        <ChevronDown size={13} className={`transition-transform duration-300 ${sigOpen ? "" : "rotate-180"}`} />
                                    </span>
                                </button>
                            </div>
                        )}
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
                                <button type="button" onClick={() => touch(setSigOn)(!sigOn)} className={tool(sigOn)} title={sigOn ? "Signature on - click to send this mail without it" : "Signature off - click to add it"} aria-label="Signature on or off">
                                    <PenLine size={18} />
                                </button>
                            )}
                            <div className="flex-1 truncate px-1 text-right text-[11px] text-slate-400">
                                {savedAt === "saving" ? "Saving draft..." : savedAt === "failed" ? <span className="text-red-600">Draft not saved</span> : savedAt === "kept" ? "Draft" : savedAt ? `Draft saved ${savedAt}` : ""}
                                {files.length > 0 && savedAt && savedAt !== "saving" && <span className="block text-amber-700">New attachments are not kept in a draft</span>}
                            </div>
                            {askDiscard ? (
                                <span className="flex items-center gap-1 text-xs text-slate-600">
                                    Discard?
                                    <button type="button" onClick={discard} className="rounded-md bg-red-600 px-2 py-1 font-semibold text-white">Yes</button>
                                    <button type="button" onClick={() => setAskDiscard(false)} className="rounded-md border border-slate-300 px-2 py-1 font-semibold">No</button>
                                </span>
                            ) : (
                                <button type="button" onClick={() => (hasContent() || files.length || draftId.current ? setAskDiscard(true) : discard())} disabled={busy} className={tool()} title="Discard this mail (the draft is deleted)" aria-label="Discard">
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

export default function MailIndex({ role = "admin", accounts = [], current = null, folder = "inbox", search = "", messages = null, open = null, isSuperAdmin = false, counts = { drafts: 0, starred: 0 }, trashDays = 30 }) {
    const { flash = {}, errors = {} } = usePage().props;
    const account = accounts.find((a) => a.id === current) ?? null;
    const [q, setQ] = useState(search);
    const [draft, setDraft] = useState(null);
    const [busy, setBusy] = useState(false);
    const [plain, setPlain] = useState(false);
    const rows = messages?.data ?? [];
    /* W113: ticked mails (many at once, like Gmail) */
    const [sel, setSel] = useState(() => new Set());
    const [askEmpty, setAskEmpty] = useState(false);
    const pageKey = `${current}|${folder}|${search}|${messages?.current_page ?? 1}`;
    useEffect(() => {
        setSel(new Set());
        setAskEmpty(false);
    }, [pageKey]);
    useEffect(() => {
        setSel((s) => {
            const ids = new Set(rows.map((m) => m.id));
            const next = new Set([...s].filter((id) => ids.has(id)));
            return next.size === s.size ? s : next;
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [messages]);
    const picked = rows.filter((m) => sel.has(m.id));
    const tick = (id) =>
        setSel((s) => {
            const n = new Set(s);
            n.has(id) ? n.delete(id) : n.add(id);
            return n;
        });
    const tickAll = () => setSel(picked.length === rows.length ? new Set() : new Set(rows.map((m) => m.id)));
    /* do one action on one or many mails; a mail open in the reader that leaves the list closes */
    const act = (items, action, until) => {
        if (!account || items.length === 0) return;
        const ids = items.map((m) => m.id);
        const closeOpen = open && ids.includes(open.id) && action !== "read";
        router.post(
            `/mail/accounts/${account.id}/act`,
            { ids, action, until },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSel(new Set());
                    if (closeOpen) go({ m: undefined });
                },
            },
        );
    };
    const emptyTrash = () => {
        setAskEmpty(false);
        router.post(`/mail/accounts/${account.id}/trash/empty`, {}, { preserveScroll: true });
    };

    useEffect(() => setPlain(false), [open?.id]);
    /* W110: a draft opens again in the Write window */
    useEffect(() => {
        if (open?.draft) setDraft({ ...open.draft, draft_id: open.draft.id, title: "Draft" });
    }, [open?.draft?.id]);
    const star = (m, on) => router.post(`/mail/messages/${m.id}/star`, { starred: on ? 1 : 0 }, { preserveScroll: true, preserveState: true });
    const closeCompose = (saved) => {
        setDraft(null);
        if (saved || folder === "drafts") router.reload({ only: ["messages", "counts", "open"], preserveScroll: true });
    };

    const go = (params) => router.get("/mail", { account: current, folder, q: q || undefined, ...params }, { preserveScroll: true, preserveState: true });
    const refresh = () => {
        if (!account || busy) return;
        setBusy(true);
        router.post(`/mail/accounts/${account.id}/refresh`, {}, { preserveScroll: true, onFinish: () => setBusy(false) });
    };

    /* W103: the earlier mail goes under the reply as a grey quote, as in Gmail */
    const who = (m) => (m.from_name ? `${m.from_name} <${m.from_email}>` : m.from_email);
    const quote = (m) => `<br><br><div>On ${esc(when(m.at, true))}, ${esc(who(m))} wrote:</div><blockquote style="margin:0 0 0 0.8ex;border-left:1px solid #ccc;padding-left:1ex">${m.html || textToHtml(m.text ?? "")}</blockquote>`;
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
            html: `<br><br><div>---------- Forwarded message ---------<br>From: ${esc(who(open))}<br>Date: ${esc(when(open.at, true))}<br>Subject: ${esc(open.subject)}<br>To: ${esc(open.to)}</div><br>${open.html || textToHtml(open.text ?? "")}`,
            forward_of: open.id,
            carried: open.attachments ?? [],
            note: open.attachments?.length ? "The attachments of the original mail go along - remove any you do not want to send." : null,
        });

    const listTitle = useMemo(() => ({ sent: "Sent", drafts: "Drafts", starred: "Starred", snoozed: "Snoozed", archive: "Archive", spam: "Spam", trash: "Trash" })[folder] ?? "Inbox", [folder]);

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

            {(() => {
                const bad = errors.mail || errors.send || errors.until || errors.ids || errors.action;
                if (!flash.success && !bad) return null;
                return <div className={`mb-3 rounded-xl px-4 py-2.5 text-sm font-semibold ${bad ? "bg-red-50 text-red-700" : "bg-emerald-50 text-emerald-700"}`}>{bad || flash.success}</div>;
            })()}

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
                        ["starred", "Starred", Star],
                        ["sent", "Sent", Send],
                        ["drafts", "Drafts", FileText],
                    ].map(([key, label, Icon]) => (
                        <button key={key} type="button" onClick={() => router.get("/mail", { account: current, folder: key })} className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-semibold ${folder === key ? "bg-violet-600 text-white" : "text-slate-600 hover:bg-slate-50"}`}>
                            <Icon size={15} /> {label}
                            {key === "inbox" && account?.unread > 0 && <span className={`rounded-full px-1.5 text-[11px] font-black ${folder === key ? "bg-white text-violet-700" : "bg-violet-600 text-white"}`}>{account.unread}</span>}
                            {(key === "drafts" || key === "starred") && counts[key] > 0 && <span className={`text-[11px] font-bold ${folder === key ? "text-violet-100" : "text-slate-400"}`}>{counts[key]}</span>}
                        </button>
                    ))}
                    <MoreFolders folder={folder} counts={counts} onGo={(key) => router.get("/mail", { account: current, folder: key })} />
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
                <section className={`rounded-2xl border border-slate-200 bg-white shadow-sm ${open ? "hidden lg:block" : ""}`}>
                    {/* W113: tick all + the buttons for the ticked mails */}
                    {rows.length > 0 && (
                        <div className="flex min-h-[44px] flex-wrap items-center gap-1 rounded-t-2xl border-b border-slate-100 bg-slate-50/60 px-2 py-1">
                            <button type="button" onClick={tickAll} title={picked.length === rows.length ? "Untick all" : "Tick all on this page"} aria-label="Tick all" className="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-500 hover:bg-slate-100">
                                {picked.length === rows.length ? <CheckSquare size={18} className="text-violet-600" /> : picked.length > 0 ? <CheckSquare size={18} className="text-violet-400" /> : <Square size={18} />}
                            </button>
                            {picked.length > 0 ? (
                                <>
                                    <span className="mr-1 text-xs font-semibold text-slate-600">{picked.length} ticked</span>
                                    <MailButtons items={picked} act={(a, until) => act(picked, a, until)} />
                                </>
                            ) : (
                                <span className="text-xs text-slate-400">Tick mails to delete, archive, snooze… many at once</span>
                            )}
                        </div>
                    )}
                    {folder === "trash" && rows.length > 0 && (
                        <div className="flex flex-wrap items-center gap-2 border-b border-slate-100 bg-amber-50 px-4 py-2 text-xs text-amber-900">
                            <span>Mails in Trash are deleted for ever after {trashDays} days.</span>
                            {askEmpty ? (
                                <span className="ml-auto inline-flex items-center gap-1 font-semibold">
                                    Delete all for ever?
                                    <button type="button" onClick={emptyTrash} className="rounded bg-red-600 px-2 py-0.5 text-white">Yes</button>
                                    <button type="button" onClick={() => setAskEmpty(false)} className="rounded border border-amber-300 bg-white px-2 py-0.5">No</button>
                                </span>
                            ) : (
                                <button type="button" onClick={() => setAskEmpty(true)} className="ml-auto font-bold text-red-700 hover:underline">
                                    Empty Trash now
                                </button>
                            )}
                        </div>
                    )}
                    {rows.length === 0 ? (
                        <p className="px-5 py-16 text-center text-sm text-slate-500">
                            {search
                                ? "Nothing found."
                                : {
                                      sent: "No sent mail yet.",
                                      drafts: "No drafts. A mail you start writing is kept here until it is sent.",
                                      starred: "No starred mail. Press the star on a mail to keep it here.",
                                      snoozed: "No snoozed mail. Press the clock on a mail to hide it until later.",
                                      archive: "Nothing archived. Archive takes a mail out of the Inbox but keeps it.",
                                      spam: "No spam. Hooray!",
                                      trash: `Trash is empty. Deleted mails stay here for ${trashDays} days.`,
                                  }[folder] ?? "No mail yet."}
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {rows.map((m) => (
                                <li key={m.id} className={`group relative flex items-stretch ${open?.id === m.id ? "bg-violet-50" : sel.has(m.id) ? "bg-sky-50" : ""}`}>
                                    <button type="button" onClick={() => tick(m.id)} aria-label={sel.has(m.id) ? "Untick" : "Tick"} aria-pressed={sel.has(m.id)} className="shrink-0 pl-2.5 pr-0.5 text-slate-300 hover:text-slate-600">
                                        {sel.has(m.id) ? <CheckSquare size={17} className="text-violet-600" /> : <Square size={17} />}
                                    </button>
                                    {m.folder !== "drafts" && m.folder !== "trash" && m.folder !== "spam" ? (
                                        <button type="button" onClick={() => star(m, !m.starred)} title={m.starred ? "Starred - click to remove the star" : "Star this mail"} aria-label={m.starred ? "Remove star" : "Star"} aria-pressed={m.starred} className="shrink-0 pl-1.5 pr-1 text-slate-300 hover:text-amber-500">
                                            <Star size={17} className={m.starred ? "fill-amber-400 text-amber-500" : ""} />
                                        </button>
                                    ) : (
                                        <span className="w-6 shrink-0" />
                                    )}
                                    <button type="button" onClick={() => go({ m: m.id })} className="block min-w-0 flex-1 py-3 pl-1 pr-4 text-left hover:bg-slate-50">
                                        <div className="flex items-center justify-between gap-2">
                                            <span className={`truncate text-sm ${m.seen ? "text-slate-700" : "font-black text-slate-950"}`}>
                                                {m.folder === "drafts" && <span className="mr-1 font-semibold text-red-600">Draft</span>}
                                                {folder === "starred" && m.folder === "sent" && <span className="mr-1 text-slate-400">To:</span>}
                                                {m.who || "—"}
                                            </span>
                                            <span className="shrink-0 text-[11px] text-slate-400 lg:group-hover:invisible">{when(m.at)}</span>
                                        </div>
                                        <div className={`flex items-center gap-1 truncate text-sm ${m.seen ? "text-slate-600" : "font-bold text-slate-900"}`}>
                                            {m.has_attachments && <Paperclip size={12} className="shrink-0 text-slate-400" />}
                                            <span className="truncate">{m.subject}</span>
                                        </div>
                                        <div className="truncate text-xs text-slate-400">{m.snippet}</div>
                                        {(m.snooze_until || m.woke || (folder !== m.folder && ["archive", "trash", "spam"].includes(m.folder))) && (
                                            <div className="mt-1 flex flex-wrap gap-1">
                                                {m.snooze_until && new Date(m.snooze_until) > new Date() && (
                                                    <span className="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-800">
                                                        <Clock size={10} /> until {untilText(m.snooze_until)}
                                                    </span>
                                                )}
                                                {m.woke && folder === "inbox" && <span className="inline-flex items-center gap-1 rounded-full bg-violet-100 px-2 py-0.5 text-[10px] font-semibold text-violet-800"><Clock size={10} /> back from snooze</span>}
                                                {folder !== m.folder && ["archive", "trash", "spam"].includes(m.folder) && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[10px] font-semibold capitalize text-slate-600">{m.folder}</span>}
                                            </div>
                                        )}
                                        {folder === "trash" && m.trashed_at && <div className="mt-0.5 text-[10px] text-slate-400">Deleted {when(m.trashed_at)} · from {m.orig_folder || "inbox"}</div>}
                                    </button>
                                    {/* W113: quick buttons when the mouse is on the row (like Gmail) */}
                                    <div className="absolute right-2 top-1.5 hidden rounded-full bg-white/95 shadow-sm ring-1 ring-slate-200 lg:group-hover:flex">
                                        <MailButtons quick align="right" items={[m]} act={(a, until) => act([m], a, until)} btn="inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100" />
                                    </div>
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
                    ) : open.folder === "drafts" ? (
                        <div className="px-5 py-20 text-center">
                            <p className="text-sm text-slate-500">This draft opens in the Write window.</p>
                            <button type="button" onClick={() => setDraft({ ...open.draft, draft_id: open.draft.id, title: "Draft" })} className="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700">
                                <PenSquare size={15} /> Open the draft
                            </button>
                        </div>
                    ) : (
                        <article className="flex h-full flex-col">
                            <div className="border-b border-slate-100 px-5 py-4">
                                {/* W113: Archive / Spam / Delete / Unread / Snooze for the open mail */}
                                <div className="-mx-2 mb-2 flex flex-wrap items-center gap-1 border-b border-slate-100 pb-2">
                                    <button type="button" onClick={() => go({ m: undefined })} title="Back to the list" aria-label="Back" className="inline-flex h-8 w-8 items-center justify-center rounded-full text-slate-600 hover:bg-slate-100">
                                        <ArrowLeft size={17} />
                                    </button>
                                    <MailButtons items={[open]} act={(a, until) => act([open], a, until)} />
                                    {open.folder !== "inbox" && open.folder !== "sent" && <span className="ml-auto rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-semibold capitalize text-slate-600">{open.folder}</span>}
                                    {open.snooze_until && new Date(open.snooze_until) > new Date() && (
                                        <span className="ml-auto inline-flex items-center gap-1 rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-semibold text-amber-800">
                                            <Clock size={11} /> Snoozed until {untilText(open.snooze_until)}
                                        </span>
                                    )}
                                </div>
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
                                    <span className="flex items-center gap-2 text-xs text-slate-400">
                                        {when(open.at, true)}
                                        {!["drafts", "trash", "spam"].includes(open.folder) && (
                                            <button type="button" onClick={() => star(open, !open.starred)} title={open.starred ? "Starred - click to remove the star" : "Star this mail"} aria-label={open.starred ? "Remove star" : "Star"} aria-pressed={open.starred} className="rounded-full p-1 hover:bg-amber-50">
                                                <Star size={18} className={open.starred ? "fill-amber-400 text-amber-500" : "text-slate-400"} />
                                            </button>
                                        )}
                                    </span>
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

            {draft && account && <Compose key={`${account.id}-${draft.draft_id ?? "x"}-${draft.title ?? "new"}-${draft.reply_to_id ?? ""}-${draft.forward_of ?? ""}`} account={account} draft={draft} onClose={closeCompose} />}
        </PanelLayout>
    );
}
