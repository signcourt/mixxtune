import { CalendarDays, ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';

/*
 * W118 MIXXTUNE_DATE_PICKER
 * A bigger, modern calendar instead of the browser's small date box.
 * value / min / max are 'YYYY-MM-DD' strings (local dates, no time zone shift).
 * Days before `min` (or after `max`) are faded, struck through and cannot be picked.
 */
const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June', 'July', 'August', 'September', 'October', 'November', 'December'];
const WEEKDAYS = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

const pad = (n) => String(n).padStart(2, '0');
export const toIso = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
export const todayIso = () => toIso(new Date());

const parse = (iso) => {
    const m = /^(\d{4})-(\d{2})-(\d{2})/.exec(String(iso ?? ''));
    if (!m) return null;
    const d = new Date(Number(m[1]), Number(m[2]) - 1, Number(m[3]));
    return Number.isNaN(d.getTime()) ? null : d;
};

const addDays = (iso, n) => {
    const d = parse(iso);
    d.setDate(d.getDate() + n);
    return toIso(d);
};

const pretty = (iso) => {
    const d = parse(iso);
    if (!d) return '';
    return new Intl.DateTimeFormat('en-IN', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric' }).format(d);
};

export default function DatePicker({
    value = '',
    onChange,
    min = null,
    max = null,
    placeholder = 'Pick a date',
    hasError = false,
    quickPicks = true,
    note = null,
}) {
    const [open, setOpen] = useState(false);
    const box = useRef(null);
    const selected = parse(value);
    const start = selected ?? parse(min) ?? new Date();
    const [view, setView] = useState({ y: start.getFullYear(), m: start.getMonth() });
    const today = todayIso();

    useEffect(() => {
        if (!open) return undefined;
        const s = parse(value) ?? parse(min) ?? new Date();
        setView({ y: s.getFullYear(), m: s.getMonth() });
        const away = (e) => {
            if (box.current && !box.current.contains(e.target)) setOpen(false);
        };
        const key = (e) => e.key === 'Escape' && setOpen(false);
        document.addEventListener('mousedown', away);
        document.addEventListener('keydown', key);

        return () => {
            document.removeEventListener('mousedown', away);
            document.removeEventListener('keydown', key);
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    const blocked = (iso) => (min && iso < min) || (max && iso > max);

    const cells = useMemo(() => {
        const first = new Date(view.y, view.m, 1);
        const lead = (first.getDay() + 6) % 7; /* Monday first */
        const days = new Date(view.y, view.m + 1, 0).getDate();
        const out = [];
        for (let i = 0; i < lead; i++) out.push(null);
        for (let d = 1; d <= days; d++) out.push(toIso(new Date(view.y, view.m, d)));
        while (out.length % 7) out.push(null);

        return out;
    }, [view]);

    const minD = parse(min);
    const maxD = parse(max);
    const thisYear = new Date().getFullYear();
    const fromYear = Math.min(minD ? minD.getFullYear() : thisYear - 30, selected ? selected.getFullYear() : thisYear);
    const toYear = Math.max(maxD ? maxD.getFullYear() : thisYear + 5, selected ? selected.getFullYear() : thisYear);
    const years = [];
    for (let y = toYear; y >= fromYear; y--) years.push(y);

    const move = (n) => setView((v) => {
        const d = new Date(v.y, v.m + n, 1);
        return { y: d.getFullYear(), m: d.getMonth() };
    });
    /* no going to a month that is all before min */
    const canBack = !minD || new Date(view.y, view.m, 1) > new Date(minD.getFullYear(), minD.getMonth(), 1);
    const canNext = !maxD || new Date(view.y, view.m, 1) < new Date(maxD.getFullYear(), maxD.getMonth(), 1);

    const pick = (iso) => {
        if (!iso || blocked(iso)) return;
        onChange?.(iso);
        setOpen(false);
    };

    const quick = [
        ['Today', today],
        ['Tomorrow', addDays(today, 1)],
        ['In 1 week', addDays(today, 7)],
        ['In 2 weeks', addDays(today, 14)],
    ].filter(([, iso]) => !blocked(iso));

    return (
        <div ref={box} className="relative" data-date-picker>
            <button
                type="button"
                onClick={() => setOpen((o) => !o)}
                aria-haspopup="dialog"
                aria-expanded={open}
                className={[
                    'flex w-full items-center gap-3 rounded-xl border bg-white px-3.5 py-3 text-left text-sm outline-none transition',
                    hasError ? 'border-rose-400 ring-2 ring-rose-100' : open ? 'border-violet-400 ring-2 ring-violet-100' : 'border-slate-200 hover:border-violet-300',
                ].join(' ')}
            >
                <CalendarDays className="h-5 w-5 shrink-0 text-violet-600" />
                <span className={value ? 'font-semibold text-slate-900' : 'text-slate-400'}>{value ? pretty(value) : placeholder}</span>
            </button>

            {open && (
                <div role="dialog" aria-label="Choose a date" className="absolute left-0 z-50 mt-2 w-[24rem] max-w-[calc(100vw-2rem)] rounded-2xl border border-slate-200 bg-white p-5 shadow-2xl">
                    <div className="flex items-center gap-2">
                        <button type="button" onClick={() => canBack && move(-1)} disabled={!canBack} className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-700 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-30" aria-label="Previous month">
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <select value={view.m} onChange={(e) => setView((v) => ({ ...v, m: Number(e.target.value) }))} className="flex-1 cursor-pointer rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm font-semibold text-slate-900">
                            {MONTHS.map((name, i) => <option key={name} value={i}>{name}</option>)}
                        </select>
                        <select value={view.y} onChange={(e) => setView((v) => ({ ...v, y: Number(e.target.value) }))} className="w-24 cursor-pointer rounded-xl border border-slate-200 bg-white px-2 py-2 text-sm font-semibold text-slate-900">
                            {years.map((y) => <option key={y} value={y}>{y}</option>)}
                        </select>
                        <button type="button" onClick={() => canNext && move(1)} disabled={!canNext} className="grid h-9 w-9 place-items-center rounded-xl border border-slate-200 text-slate-700 hover:bg-violet-50 disabled:cursor-not-allowed disabled:opacity-30" aria-label="Next month">
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    <div className="mt-3 grid grid-cols-7 gap-1 text-center text-[11px] font-bold uppercase tracking-wide text-slate-400">
                        {WEEKDAYS.map((d) => <div key={d} className="py-1">{d}</div>)}
                    </div>
                    <div className="grid grid-cols-7 gap-1">
                        {cells.map((iso, i) => {
                            if (!iso) return <div key={`e${i}`} className="h-12" />;
                            const off = blocked(iso);
                            const isSel = iso === value;
                            const isToday = iso === today;

                            return (
                                <button
                                    key={iso}
                                    type="button"
                                    disabled={off}
                                    onClick={() => pick(iso)}
                                    title={off ? 'This date cannot be picked' : pretty(iso)}
                                    className={[
                                        'h-12 rounded-xl text-base font-semibold transition',
                                        off ? 'cursor-not-allowed text-slate-400 line-through opacity-40 blur-[0.6px]'
                                            : isSel ? 'bg-violet-600 text-white shadow-md shadow-violet-200'
                                                : 'text-slate-800 hover:bg-violet-50',
                                        isToday && !isSel ? 'ring-2 ring-violet-300' : '',
                                    ].join(' ')}
                                >
                                    {Number(iso.slice(8))}
                                </button>
                            );
                        })}
                    </div>

                    {quickPicks && quick.length > 0 && (
                        <div className="mt-3 flex flex-wrap gap-2 border-t border-slate-100 pt-3">
                            {quick.map(([label, iso]) => (
                                <button key={label} type="button" onClick={() => pick(iso)} className={`rounded-full border px-3 py-1.5 text-xs font-semibold ${iso === value ? 'border-violet-600 bg-violet-600 text-white' : 'border-violet-200 bg-violet-50 text-violet-800 hover:bg-violet-100'}`}>
                                    {label}
                                </button>
                            ))}
                        </div>
                    )}
                    {note && <p className="mt-3 text-xs leading-5 text-slate-500">{note}</p>}
                </div>
            )}
        </div>
    );
}
