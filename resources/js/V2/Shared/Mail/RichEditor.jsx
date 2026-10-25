import { AlignCenter, AlignLeft, AlignRight, Baseline, Bold, ChevronDown, IndentDecrease, IndentIncrease, Italic, List, ListOrdered, Quote, Redo2, RemoveFormatting, Strikethrough, Type, Underline, Undo2 } from "lucide-react";
import { useEffect, useRef, useState } from "react";

/*
 * W103 MIXXTUNE_MAIL_EDITOR
 * The mail text box: a rich editor like the one in Gmail (fonts, size, bold,
 * colours, align, lists, indent, quote, links, pictures). Uses the browser's own
 * editing (contentEditable) - no extra package. The server cleans the HTML again.
 */
export const FONTS = [
    ["Sans Serif", "Arial, Helvetica, sans-serif"],
    ["Serif", "'Times New Roman', Times, serif"],
    ["Fixed width", "'Courier New', Courier, monospace"],
    ["Wide", "'Arial Black', Arial, sans-serif"],
    ["Narrow", "'Arial Narrow', Arial, sans-serif"],
    ["Comic Sans MS", "'Comic Sans MS', cursive"],
    ["Garamond", "Garamond, serif"],
    ["Georgia", "Georgia, serif"],
    ["Tahoma", "Tahoma, sans-serif"],
    ["Trebuchet MS", "'Trebuchet MS', sans-serif"],
    ["Verdana", "Verdana, sans-serif"],
];
const SIZES = [
    ["Small", "2", "text-xs"],
    ["Normal", "3", "text-sm"],
    ["Large", "5", "text-lg"],
    ["Huge", "6", "text-2xl"],
];
const COLORS = [
    "#000000", "#444444", "#666666", "#999999", "#cccccc", "#eeeeee", "#f3f3f3", "#ffffff",
    "#ff0000", "#ff9900", "#ffff00", "#00ff00", "#00ffff", "#4a86e8", "#0000ff", "#9900ff",
    "#f4cccc", "#fce5cd", "#fff2cc", "#d9ead3", "#d0e0e3", "#c9daf8", "#d9d2e9", "#ead1dc",
    "#e06666", "#f6b26b", "#ffd966", "#93c47d", "#76a5af", "#6d9eeb", "#8e7cc3", "#c27ba0",
    "#cc0000", "#e69138", "#f1c232", "#6aa84f", "#45818e", "#3c78d8", "#674ea7", "#a64d79",
    "#990000", "#b45f06", "#bf9000", "#38761d", "#134f5c", "#1155cc", "#4f4bdc", "#741b47",
];

export const esc = (s) => String(s ?? "").replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
export const textToHtml = (s) => esc(s).replace(/\r\n|\r|\n/g, "<br>");

const BLOCK = ["insertUnorderedList", "insertOrderedList", "indent", "outdent", "justifyLeft", "justifyCenter", "justifyRight", "formatBlock"];

/* keeps the place of the cursor, so toolbar buttons and pop-ups work on the right text */
export function useRich() {
    const ref = useRef(null);
    const range = useRef(null);
    const save = () => {
        const s = window.getSelection();
        if (s && s.rangeCount && ref.current && ref.current.contains(s.anchorNode)) range.current = s.getRangeAt(0).cloneRange();
    };
    const restore = () => {
        const el = ref.current;
        if (!el) return;
        const s = window.getSelection();
        /* the cursor is still in the text (toolbar buttons keep it there): leave it as it is */
        if (document.activeElement === el && s && s.rangeCount && el.contains(s.anchorNode)) return;
        el.focus();
        s.removeAllRanges();
        if (range.current && el.contains(range.current.startContainer)) {
            s.addRange(range.current);
        } else {
            const r = document.createRange();
            r.selectNodeContents(el);
            r.collapse(false);
            s.addRange(r);
        }
    };
    const exec = (cmd, val = null, css = false) => {
        restore();
        try {
            document.execCommand("styleWithCSS", false, css);
        } catch {
            /* old browsers */
        }
        /* list / indent / align: the browser rebuilds the line and puts the cursor at its start - put it back */
        const sel = window.getSelection();
        const el = ref.current;
        let keep = null;
        if (BLOCK.includes(cmd) && el && sel && sel.rangeCount && sel.isCollapsed && sel.anchorNode?.nodeType === 3 && el.contains(sel.anchorNode)) {
            const r = document.createRange();
            r.selectNodeContents(el);
            r.setEnd(sel.anchorNode, sel.anchorOffset);
            keep = { at: r.toString().length, end: sel.anchorOffset === sel.anchorNode.length };
        }
        document.execCommand(cmd, false, val);
        if (keep) {
            const walk = document.createTreeWalker(el, NodeFilter.SHOW_TEXT);
            let n;
            let done = 0;
            while ((n = walk.nextNode())) {
                const len = n.length;
                if (keep.end ? keep.at <= done + len : keep.at < done + len) {
                    const r = document.createRange();
                    r.setStart(n, keep.at - done);
                    r.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(r);
                    break;
                }
                done += len;
            }
        }
        save();
    };
    const selectedText = () => {
        const s = window.getSelection();
        return s && s.rangeCount && ref.current?.contains(s.anchorNode) ? s.toString() : "";
    };

    return { ref, save, restore, exec, insertHtml: (html) => exec("insertHTML", html), selectedText };
}

export function Editor({ rich, initialHtml = "", caretAtStart = false, autoFocus = false, onChange }) {
    useEffect(() => {
        const el = rich.ref.current;
        if (!el) return;
        el.innerHTML = initialHtml;
        onChange?.();
        if (autoFocus) {
            el.focus();
            const r = document.createRange();
            r.selectNodeContents(el);
            r.collapse(caretAtStart);
            const s = window.getSelection();
            s.removeAllRanges();
            s.addRange(r);
            rich.save();
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    return (
        <div
            ref={rich.ref}
            contentEditable
            suppressContentEditableWarning
            role="textbox"
            aria-multiline="true"
            aria-label="Mail text"
            data-placeholder="Write your mail..."
            onInput={onChange}
            onKeyUp={rich.save}
            onMouseUp={rich.save}
            onBlur={rich.save}
            style={{ fontFamily: "Arial, Helvetica, sans-serif" }}
            className="min-h-[200px] w-full break-words py-2 text-sm leading-relaxed text-slate-900 outline-none empty:before:pointer-events-none empty:before:text-slate-400 empty:before:content-[attr(data-placeholder)] [&_a]:text-blue-700 [&_a]:underline [&_blockquote]:my-1 [&_img]:inline [&_img]:max-w-full [&_ol]:list-decimal [&_ol]:pl-7 [&_ul]:list-disc [&_ul]:pl-7"
        />
    );
}

const btn = "inline-flex h-8 min-w-7 items-center justify-center rounded-md px-1 text-slate-700 hover:bg-white disabled:opacity-40";
const keep = (e) => e.preventDefault();

/* a small menu that opens upwards (the toolbar sits at the bottom, as in Gmail) */
function Menu({ title, label, children, panel = "", align = "left" }) {
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

    return (
        <div ref={box} className="relative">
            <button type="button" title={title} aria-label={title} aria-expanded={open} onMouseDown={keep} onClick={() => setOpen(!open)} className={`${btn} gap-0.5 ${open ? "bg-white" : ""}`}>
                {label}
                <ChevronDown size={12} />
            </button>
            {open && (
                <div onMouseDown={keep} onClick={() => setOpen(false)} className={`absolute bottom-full z-30 mb-1 rounded-xl border border-slate-200 bg-white p-1 shadow-xl ${align === "right" ? "right-0" : align === "center" ? "left-1/2 -translate-x-1/2" : "left-0"} ${panel}`}>
                    {children}
                </div>
            )}
        </div>
    );
}

function Item({ onClick, children, className = "", active = false }) {
    return (
        <button type="button" onMouseDown={keep} onClick={onClick} className={`flex w-full items-center gap-2 whitespace-nowrap rounded-lg px-3 py-1.5 text-left text-sm text-slate-800 hover:bg-slate-100 ${active ? "font-semibold" : ""} ${className}`}>
            {children}
        </button>
    );
}

function Swatches({ onPick }) {
    return (
        <div className="grid grid-cols-8 gap-1">
            {COLORS.map((c) => (
                <button key={c} type="button" title={c} aria-label={c} onMouseDown={keep} onClick={() => onPick(c)} style={{ background: c }} className="h-4 w-4 rounded-sm border border-slate-300 hover:scale-125" />
            ))}
        </div>
    );
}

const Sep = () => <span className="mx-0.5 h-5 w-px bg-slate-300" />;

export function Toolbar({ rich }) {
    const [font, setFont] = useState("Sans Serif");
    const B = ({ t, cmd, val = null, icon: I }) => (
        <button type="button" title={t} aria-label={t} onMouseDown={keep} onClick={() => rich.exec(cmd, val)} className={btn}>
            <I size={16} />
        </button>
    );

    return (
        <div className="flex flex-wrap items-center gap-0.5 rounded-2xl bg-slate-100 px-2 py-1" role="toolbar" aria-label="Formatting">
            <B t="Undo (Ctrl+Z)" cmd="undo" icon={Undo2} />
            <B t="Redo (Ctrl+Y)" cmd="redo" icon={Redo2} />
            <Sep />
            <Menu title="Font" label={<span className="max-w-24 truncate px-1 text-xs font-medium">{font}</span>} panel="max-h-72 w-52 overflow-y-auto">
                {FONTS.map(([n, f]) => (
                    <Item
                        key={n}
                        active={n === font}
                        onClick={() => {
                            setFont(n);
                            rich.exec("fontName", f);
                        }}
                    >
                        <span style={{ fontFamily: f }}>{n}</span>
                    </Item>
                ))}
            </Menu>
            <Sep />
            <Menu title="Size" label={<Type size={16} />} panel="w-36">
                {SIZES.map(([n, v, cls]) => (
                    <Item key={n} onClick={() => rich.exec("fontSize", v)} className={cls}>
                        {n}
                    </Item>
                ))}
            </Menu>
            <Sep />
            <B t="Bold (Ctrl+B)" cmd="bold" icon={Bold} />
            <B t="Italic (Ctrl+I)" cmd="italic" icon={Italic} />
            <B t="Underline (Ctrl+U)" cmd="underline" icon={Underline} />
            <Menu title="Text colour" label={<Baseline size={16} />} panel="w-[21rem]" align="center">
                <div className="grid grid-cols-2 gap-3 p-2">
                    <div>
                        <p className="mb-1 text-[11px] font-semibold text-slate-500">Background colour</p>
                        <Swatches onPick={(c) => rich.exec("hiliteColor", c, true)} />
                    </div>
                    <div>
                        <p className="mb-1 text-[11px] font-semibold text-slate-500">Text colour</p>
                        <Swatches onPick={(c) => rich.exec("foreColor", c)} />
                    </div>
                </div>
            </Menu>
            <Sep />
            <Menu title="Align" label={<AlignLeft size={16} />} panel="flex gap-0.5">
                <B t="Align left" cmd="justifyLeft" icon={AlignLeft} />
                <B t="Align centre" cmd="justifyCenter" icon={AlignCenter} />
                <B t="Align right" cmd="justifyRight" icon={AlignRight} />
            </Menu>
            <B t="Numbered list" cmd="insertOrderedList" icon={ListOrdered} />
            <B t="Bulleted list" cmd="insertUnorderedList" icon={List} />
            <B t="Indent less" cmd="outdent" icon={IndentDecrease} />
            <B t="Indent more" cmd="indent" icon={IndentIncrease} />
            <Sep />
            <Menu title="More formatting" label={null} panel="w-56" align="right">
                <Item onClick={() => rich.exec("strikeThrough")}>
                    <Strikethrough size={15} /> Strikethrough
                </Item>
                <Item onClick={() => rich.exec("formatBlock", "blockquote")}>
                    <Quote size={15} /> Quote
                </Item>
                <Item onClick={() => rich.exec("removeFormat")}>
                    <RemoveFormatting size={15} /> Remove formatting
                </Item>
            </Menu>
        </div>
    );
}

export const EMOJI = ["😀", "😃", "😄", "😁", "😊", "🙂", "😉", "😍", "🥰", "😘", "😎", "🤩", "🥳", "😇", "🤗", "🤔", "😅", "😂", "🤣", "😢", "😭", "😮", "😴", "🙏", "👍", "👎", "👏", "🙌", "👌", "✌️", "🤝", "💪", "👋", "❤️", "🧡", "💛", "💚", "💙", "💜", "🔥", "✨", "⭐", "🎉", "🎊", "🎵", "🎶", "🎤", "🎧", "🎸", "🎹", "🥁", "🎬", "📢", "📣", "📈", "💰", "✅", "❌", "⚠️", "📌", "📎", "📅", "⏰", "🚀"];

/* pictures put inside the text are made smaller (max 1200 px wide) before they go in */
export const shrinkImage = (file) =>
    new Promise((ok, bad) => {
        const r = new FileReader();
        r.onerror = bad;
        r.onload = () => {
            const img = new Image();
            img.onerror = bad;
            img.onload = () => {
                const k = Math.min(1, 1200 / img.width);
                if (k === 1 && file.size < 400 * 1024 && /^image\/(png|jpe?g|gif|webp)$/.test(file.type)) return ok(r.result);
                const c = document.createElement("canvas");
                c.width = Math.max(1, Math.round(img.width * k));
                c.height = Math.max(1, Math.round(img.height * k));
                const g = c.getContext("2d");
                g.fillStyle = "#ffffff";
                g.fillRect(0, 0, c.width, c.height);
                g.drawImage(img, 0, 0, c.width, c.height);
                return ok(c.toDataURL(file.type === "image/png" && file.size < 1.5 * 1048576 ? "image/png" : "image/jpeg", 0.85));
            };
            img.src = r.result;
        };
        r.readAsDataURL(file);
    });

/*
 * W104: the same size change the server makes before sending (px / pt in styles,
 * width / height / cellpadding / cellspacing), so the preview is exactly what receivers get.
 */
export function scaleHtml(html, percent) {
    const k = Math.max(30, Math.min(200, Number(percent) || 100)) / 100;
    if (!html || Math.abs(k - 1) < 0.001) return html || "";
    const doc = new DOMParser().parseFromString(`<div id="mxsig">${html}</div>`, "text/html");
    const root = doc.getElementById("mxsig");
    const num = (v) => {
        let r = Math.round(v * k * 10) / 10;
        if (v > 0 && r < 1) r = 1;
        return String(r);
    };
    root.querySelectorAll("*").forEach((el) => {
        const st = el.getAttribute("style");
        if (st) el.setAttribute("style", st.replace(/(\d*\.?\d+)(px|pt)\b/gi, (m, n, u) => num(parseFloat(n)) + u));
        ["width", "height", "cellpadding", "cellspacing"].forEach((a) => {
            const v = el.getAttribute(a);
            const m = v && /^\s*(\d+(?:\.\d+)?)\s*(px)?\s*$/i.exec(v);
            if (m) el.setAttribute(a, String(Math.max(1, Math.round(parseFloat(m[1]) * k))));
        });
    });
    return root.innerHTML;
}

/* pictures without a size in the code get their real size written in, so they can be made smaller too */
export async function fixPictureSizes(html) {
    if (!html || !/<img\b/i.test(html)) return html;
    const doc = new DOMParser().parseFromString(`<div id="mxsig">${html}</div>`, "text/html");
    const root = doc.getElementById("mxsig");
    const open = [...root.querySelectorAll("img")].filter((i) => !i.getAttribute("width") && !i.getAttribute("height") && !/(^|;)\s*(width|height)\s*:/i.test(i.getAttribute("style") || ""));
    if (open.length === 0) return html;
    await Promise.all(
        open.map(
            (i) =>
                new Promise((done) => {
                    const im = new Image();
                    const t = setTimeout(done, 5000);
                    im.onload = () => {
                        clearTimeout(t);
                        if (im.naturalWidth && im.naturalHeight) {
                            i.setAttribute("width", String(im.naturalWidth));
                            i.setAttribute("height", String(im.naturalHeight));
                        }
                        done();
                    };
                    im.onerror = () => {
                        clearTimeout(t);
                        done();
                    };
                    im.src = i.getAttribute("src") || "";
                }),
        ),
    );
    return root.innerHTML;
}

/* W105: the same white-background removal the server does (coloured parts stay) */
const WHITE = "(?:#fff(?:fff)?(?![0-9a-f])|\\bwhite\\b|rgba?\\(\\s*255\\s*,\\s*255\\s*,\\s*255\\s*(?:,\\s*[\\d.]+\\s*)?\\))";
export function seeThroughHtml(html) {
    if (!html || !/background|bgcolor/i.test(html)) return html || "";
    const doc = new DOMParser().parseFromString(`<div id="mxsig">${html}</div>`, "text/html");
    const root = doc.getElementById("mxsig");
    root.querySelectorAll("*").forEach((el) => {
        if (new RegExp(`^\\s*${WHITE}\\s*$`, "i").test(el.getAttribute("bgcolor") || "")) el.removeAttribute("bgcolor");
        const st = el.getAttribute("style");
        if (!st) return;
        const keep = [];
        st.split(";").forEach((d) => {
            const i = d.indexOf(":");
            if (i < 0) {
                if (d.trim()) keep.push(d.trim());
                return;
            }
            const prop = d.slice(0, i).trim();
            let val = d.slice(i + 1).trim();
            const p = prop.toLowerCase();
            if (p === "background-color" && new RegExp(`^${WHITE}(\\s*!important)?$`, "i").test(val)) return;
            if (p === "background") {
                val = val.replace(new RegExp(WHITE, "gi"), "transparent").trim();
                if (/^transparent(\s*!important)?$/i.test(val)) return;
            }
            keep.push(`${prop}:${val}`);
        });
        if (keep.length) el.setAttribute("style", keep.join(";"));
        else el.removeAttribute("style");
    });
    return root.innerHTML;
}

/* W106: the same card the server makes - background only as big as the signature */
export function boxHtml(html, color = "#ffffff", padding = 12, radius = 8) {
    const h = seeThroughHtml(html);
    if (!h) return "";
    const doc = new DOMParser().parseFromString(`<div id="mxsig">${h}</div>`, "text/html");
    const root = doc.getElementById("mxsig");
    const strip = (el, all) => {
        const w = (el.getAttribute("width") || "").trim();
        if (w && (all || w.endsWith("%"))) el.removeAttribute("width");
        const st = el.getAttribute("style");
        if (st) {
            const keep = st
                .split(";")
                .map((d) => d.trim())
                .filter((d) => {
                    if (!d) return false;
                    const i = d.indexOf(":");
                    const p = (i < 0 ? "" : d.slice(0, i)).trim().toLowerCase();
                    const v = (i < 0 ? "" : d.slice(i + 1)).replace(/!important$/i, "").trim();
                    return !((p === "width" || p === "min-width") && (all || v.endsWith("%")));
                });
            if (keep.length) el.setAttribute("style", keep.join(";"));
            else el.removeAttribute("style");
        }
    };
    root.querySelectorAll("*").forEach((el) => el.tagName.toLowerCase() !== "img" && strip(el, false));
    let node = root;
    for (let d = 0; d < 8; d++) {
        const k = [...node.children];
        if (k.length !== 1 || k[0].tagName.toLowerCase() === "img") {
            k.forEach((c) => ["table", "div", "center"].includes(c.tagName.toLowerCase()) && strip(c, true));
            break;
        }
        strip(k[0], true);
        node = k[0];
    }
    const c = /^#[0-9a-f]{6}$/i.test(color) ? color.toLowerCase() : "#ffffff";
    const p = Math.max(0, Math.min(40, Number(padding) || 0));
    const r = Math.max(0, Math.min(30, Number(radius) || 0));
    return `<table role="presentation" cellpadding="0" cellspacing="0" border="0" bgcolor="${c}" style="background-color:${c};border-radius:${r}px;border-collapse:separate"><tr><td style="padding:${p}px">${root.innerHTML}</td></tr></table>`;
}
