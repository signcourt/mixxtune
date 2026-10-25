import { useMemo, useRef, useState } from "react";

/*
 * W110 MIXXTUNE_MAIL_ADDRESS_SUGGEST
 * To / Cc / Bcc box like Gmail: while typing, the people this mailbox has written to (and got
 * mail from) show up, the typed letters are marked, and one is picked with the mouse, Enter or Tab.
 */
const parts = (v) => String(v ?? "").split(/[,;]/);

function Mark({ text, q }) {
    const t = String(text ?? "");
    const i = q ? t.toLowerCase().indexOf(q) : -1;
    if (i < 0) return t;

    return (
        <>
            {t.slice(0, i)}
            <mark className="rounded-sm bg-amber-200 px-0 text-slate-900">{t.slice(i, i + q.length)}</mark>
            {t.slice(i + q.length)}
        </>
    );
}

export default function AddressInput({ value, onChange, contacts = [], placeholder = "", label, autoFocus = false }) {
    const [open, setOpen] = useState(false);
    const [pos, setPos] = useState(0);
    const box = useRef(null);

    const list = parts(value);
    const q = (list[list.length - 1] || "").trim().toLowerCase();
    const taken = new Set(list.slice(0, -1).map((p) => p.trim().toLowerCase().replace(/^.*<|>$/g, "")).filter(Boolean));
    const hits = useMemo(() => {
        if (q.length < 1) return [];
        const out = [];
        for (const c of contacts) {
            if (taken.has(c.email)) continue;
            const e = c.email.toLowerCase();
            const n = String(c.name || "").toLowerCase();
            let rank = -1;
            if (e.startsWith(q) || n.startsWith(q)) rank = 0;
            else if (n.split(/\s+/).some((w) => w.startsWith(q)) || e.split(/[@._-]/).some((w) => w.startsWith(q))) rank = 1;
            else if (e.includes(q) || n.includes(q)) rank = 2;
            if (rank >= 0) out.push([rank, c]);
            if (out.length > 200) break;
        }
        return out.sort((a, b) => a[0] - b[0] || (b[1].sent || 0) - (a[1].sent || 0)).slice(0, 8).map(([, c]) => c);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [q, contacts, value]);

    const pick = (c) => {
        const keep = list.slice(0, -1).map((p) => p.trim()).filter(Boolean);
        onChange([...keep, c.email].join(", ") + ", ");
        setOpen(false);
        setPos(0);
        requestAnimationFrame(() => box.current?.focus());
    };

    const show = open && hits.length > 0;

    return (
        <div className="relative min-w-0 flex-1">
            <input
                ref={box}
                value={value}
                autoFocus={autoFocus}
                aria-label={label}
                aria-autocomplete="list"
                aria-expanded={show}
                placeholder={placeholder}
                autoComplete="off"
                onChange={(e) => {
                    onChange(e.target.value);
                    setOpen(true);
                    setPos(0);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => setTimeout(() => setOpen(false), 150)}
                onKeyDown={(e) => {
                    if (!show) return;
                    if (e.key === "ArrowDown") {
                        e.preventDefault();
                        setPos((p) => (p + 1) % hits.length);
                    } else if (e.key === "ArrowUp") {
                        e.preventDefault();
                        setPos((p) => (p - 1 + hits.length) % hits.length);
                    } else if (e.key === "Enter" || e.key === "Tab") {
                        e.preventDefault();
                        pick(hits[pos] ?? hits[0]);
                    } else if (e.key === "Escape") {
                        setOpen(false);
                    }
                }}
                className="h-8 w-full min-w-0 border-0 px-0 text-sm focus:ring-0"
            />
            {show && (
                <ul role="listbox" className="absolute left-0 right-0 top-full z-40 mt-1 max-h-72 overflow-y-auto rounded-xl border border-slate-200 bg-white py-1 shadow-xl">
                    {hits.map((c, i) => (
                        <li key={c.email} role="option" aria-selected={i === pos}>
                            <button
                                type="button"
                                onMouseDown={(e) => e.preventDefault()}
                                onMouseEnter={() => setPos(i)}
                                onClick={() => pick(c)}
                                className={`flex w-full items-center gap-2.5 px-3 py-1.5 text-left ${i === pos ? "bg-violet-50" : "hover:bg-slate-50"}`}
                            >
                                <span className="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-violet-100 text-[11px] font-bold uppercase text-violet-700">{(c.name || c.email).slice(0, 1)}</span>
                                <span className="min-w-0">
                                    <span className="block truncate text-sm text-slate-900">{c.name ? <Mark text={c.name} q={q} /> : <Mark text={c.email} q={q} />}</span>
                                    {c.name && (
                                        <span className="block truncate text-[11px] text-slate-500">
                                            <Mark text={c.email} q={q} />
                                        </span>
                                    )}
                                </span>
                                {c.sent > 0 && <span className="ml-auto shrink-0 text-[10px] text-slate-400">written {c.sent}×</span>}
                            </button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
