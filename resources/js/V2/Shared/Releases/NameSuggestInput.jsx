import { useMemo, useState } from "react";

/*
 * W91 MIXXTUNE_NAME_SUGGESTIONS
 * Text box that suggests names already used on this account while typing
 * ("ar" -> Arijit Singh, Arun ...). Arrow keys + Enter or a click picks one.
 */
export function matchNames(value, suggestions) {
    const q = String(value ?? "").trim().toLowerCase();
    if (q.length < 1 || !Array.isArray(suggestions)) {
        return [];
    }
    const starts = [];
    const wordStarts = [];
    const contains = [];
    for (const name of suggestions) {
        const n = String(name).toLowerCase();
        if (n === q) {
            continue;
        }
        if (n.startsWith(q)) {
            starts.push(name);
        } else if (n.split(/\s+/).some((w) => w.startsWith(q))) {
            wordStarts.push(name);
        } else if (q.length >= 3 && n.includes(q)) {
            contains.push(name);
        }
        if (starts.length >= 8) {
            break;
        }
    }
    return [...starts, ...wordStarts, ...contains].slice(0, 8);
}

export default function NameSuggestInput({ value, onChange, suggestions = [], className = "", ...rest }) {
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const matches = useMemo(() => matchNames(value, suggestions), [value, suggestions]);

    const pick = (name) => {
        onChange(name);
        setOpen(false);
    };

    return (
        <div className="relative min-w-0 flex-1">
            <input
                {...rest}
                className={className}
                value={value}
                autoComplete="off"
                onChange={(event) => {
                    onChange(event.target.value);
                    setActive(0);
                    setOpen(true);
                }}
                onFocus={() => setOpen(true)}
                onBlur={() => window.setTimeout(() => setOpen(false), 120)}
                onKeyDown={(event) => {
                    if (!open || matches.length === 0) {
                        return;
                    }
                    if (event.key === "ArrowDown") {
                        event.preventDefault();
                        setActive((i) => Math.min(i + 1, matches.length - 1));
                    } else if (event.key === "ArrowUp") {
                        event.preventDefault();
                        setActive((i) => Math.max(i - 1, 0));
                    } else if (event.key === "Enter") {
                        event.preventDefault();
                        pick(matches[active] ?? matches[0]);
                    } else if (event.key === "Escape") {
                        setOpen(false);
                    }
                }}
            />
            {open && matches.length > 0 && (
                <div className="absolute left-0 right-0 top-[calc(100%+4px)] z-[120] max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                    <div className="px-3 pb-1 pt-0.5 text-[10px] font-bold uppercase tracking-wide text-slate-400">Used before</div>
                    {matches.map((name, i) => (
                        <button
                            type="button"
                            key={name}
                            onMouseDown={(event) => event.preventDefault()}
                            onClick={() => pick(name)}
                            className={`block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold transition ${i === active ? "bg-violet-50 text-violet-700" : "text-slate-700 hover:bg-slate-50"}`}
                        >
                            {name}
                        </button>
                    ))}
                </div>
            )}
        </div>
    );
}
