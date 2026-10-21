import { Head } from '@inertiajs/react';
import axios from 'axios';
import {
    CheckCircle2,
    Download,
    ImagePlus,
    Loader2,
    SearchCheck,
    ShieldCheck,
    UploadCloud,
    XCircle,
} from 'lucide-react';
import { useMemo, useRef, useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

/* MIXXTUNE_BULK_POSTERS_V1 */

const RESULT = {
    ready: { label: 'Ready', cls: 'bg-sky-50 text-sky-700' },
    saved: { label: 'Saved', cls: 'bg-emerald-50 text-emerald-700' },
    has_poster: { label: 'Kept', cls: 'bg-slate-100 text-slate-600' },
    not_found: { label: 'No match', cls: 'bg-amber-50 text-amber-700' },
    ambiguous: { label: 'Unclear', cls: 'bg-amber-50 text-amber-700' },
    draft: { label: 'Draft', cls: 'bg-slate-100 text-slate-600' },
    invalid: { label: 'Wrong file', cls: 'bg-red-50 text-red-700' },
    error: { label: 'Error', cls: 'bg-red-50 text-red-700' },
    waiting: { label: 'Not checked', cls: 'bg-slate-100 text-slate-500' },
};

const number = (value) => new Intl.NumberFormat('en-IN').format(Number(value || 0));

function Pill({ result }) {
    const r = RESULT[result] || RESULT.waiting;

    return (
        <span className={`inline-flex shrink-0 items-center rounded-full px-2.5 py-1 text-xs font-bold ${r.cls}`}>
            {r.label}
        </span>
    );
}

export default function Index({ counts = {}, limits = {} }) {
    const input = useRef(null);
    const [files, setFiles] = useState([]);
    const [rows, setRows] = useState({});
    const [replace, setReplace] = useState(false);
    const [busy, setBusy] = useState('');
    const [done, setDone] = useState(0);
    const [notice, setNotice] = useState('');
    const [savedCount, setSavedCount] = useState(0);

    const pick = (event) => {
        const seen = new Set();
        const list = Array.from(event.target.files || [])
            .filter((f) => (seen.has(f.name) ? false : seen.add(f.name)))
            .slice(0, 1000);
        setFiles(list);
        setRows({});
        setDone(0);
        setSavedCount(0);
        setNotice(list.length ? `${list.length} file(s) selected. Press "Check" first.` : '');
    };

    const summary = useMemo(() => {
        const s = {};
        files.forEach((f) => {
            const r = rows[f.name]?.result || 'waiting';
            s[r] = (s[r] || 0) + 1;
        });
        return s;
    }, [files, rows]);

    const check = async () => {
        if (!files.length || busy) return;
        setBusy('check');
        setNotice('');

        try {
            const next = {};
            for (let i = 0; i < files.length; i += 200) {
                const names = files.slice(i, i + 200).map((f) => f.name);
                const { data } = await axios.post('/super-admin/posters/match', { names, replace });
                (data.results || []).forEach((r) => {
                    next[r.file] = r;
                });
            }
            setRows(next);
            const ready = Object.values(next).filter((r) => r.result === 'ready').length;
            setNotice(`${ready} of ${files.length} file(s) are ready. Nothing has been saved yet.`);
        } catch (e) {
            setNotice('Check failed. Please reload the page and try again.');
        } finally {
            setBusy('');
        }
    };

    const upload = async () => {
        const ready = files.filter((f) => rows[f.name]?.result === 'ready');

        if (!ready.length || busy) return;

        if (!window.confirm(`Save ${ready.length} poster(s)${replace ? ' (existing posters will be replaced)' : ''}?`)) {
            return;
        }

        setBusy('upload');
        setDone(0);
        let saved = 0;

        for (let i = 0; i < ready.length; i += 1) {
            const file = ready[i];
            const form = new FormData();
            form.append('poster', file);
            form.append('replace', replace ? '1' : '0');

            let result;
            try {
                const { data } = await axios.post('/super-admin/posters/upload', form);
                result = data;
            } catch (e) {
                const status = e?.response?.status;
                result = {
                    ...rows[file.name],
                    result: 'error',
                    message: status === 413
                        ? 'File is too large for the server.'
                        : status === 429
                          ? 'Too many uploads in one minute. Press Upload again for the rest.'
                          : status === 419
                            ? 'Session expired. Reload the page and upload the rest.'
                            : `Upload failed (${status || 'network'}).`,
                };
            }

            if (result.result === 'saved') saved += 1;

            setRows((prev) => ({ ...prev, [file.name]: result }));
            setDone(i + 1);
        }

        setSavedCount((prev) => prev + saved);
        setNotice(`${saved} poster(s) saved. Reload the page to see the new counts.`);
        setBusy('');
    };

    const readyCount = summary.ready || 0;

    return (
        <PanelLayout role="super_admin" title="Bulk Posters" subtitle="Add posters to many releases at once">
            <Head title="Bulk Posters" />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex items-center gap-2">
                        <ShieldCheck className="h-5 w-5 text-violet-600" />
                        <span className="text-xs font-black uppercase tracking-widest text-violet-600">Super Admin Only</span>
                    </div>

                    <div className="mt-4 grid grid-cols-3 gap-3">
                        {[
                            ['Releases', counts.total],
                            ['With poster', counts.with],
                            ['Without poster', counts.without],
                        ].map(([label, value]) => (
                            <div key={label} className="rounded-2xl bg-slate-50 p-3 sm:p-4">
                                <div className="text-xs font-semibold text-slate-500">{label}</div>
                                <div className="mt-1 text-lg font-black text-slate-900 sm:text-2xl">{number(value)}</div>
                            </div>
                        ))}
                    </div>

                    <div className="mt-5 space-y-2 text-sm text-slate-600">
                        <p>
                            <b>Name each image after the release&apos;s UPC or catalogue number</b>, for example{' '}
                            <code className="rounded bg-slate-100 px-1">8905285012345.jpg</code> or{' '}
                            <code className="rounded bg-slate-100 px-1">MXT0012.png</code>. Only an exact match is used.
                        </p>
                        <p>
                            JPG or PNG, at least {limits.min_px || 500} × {limits.min_px || 500} px, up to{' '}
                            {Math.round((limits.max_kb || 20480) / 1024)} MB. Stores need {limits.store_px || 3000} ×{' '}
                            {limits.store_px || 3000} px. Drafts are never changed. Old poster files are kept and every save is in the audit log.
                        </p>
                    </div>

                    <a
                        href="/super-admin/posters/missing.csv"
                        className="mt-4 inline-flex h-11 items-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50"
                    >
                        <Download className="h-4 w-4" />
                        Releases without poster (CSV)
                    </a>
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <input ref={input} type="file" accept=".jpg,.jpeg,.png,image/jpeg,image/png" multiple className="hidden" onChange={pick} />

                    <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center">
                        <button
                            type="button"
                            disabled={!!busy}
                            onClick={() => input.current?.click()}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 text-sm font-bold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            <ImagePlus className="h-4 w-4" />
                            Choose images
                        </button>

                        <label className="inline-flex h-11 items-center gap-2 text-sm font-semibold text-slate-700">
                            <input
                                type="checkbox"
                                checked={replace}
                                disabled={!!busy}
                                onChange={(e) => {
                                    setReplace(e.target.checked);
                                    setRows({});
                                }}
                                className="h-4 w-4 rounded border-slate-300"
                            />
                            Replace existing posters
                        </label>

                        <button
                            type="button"
                            disabled={!files.length || !!busy}
                            onClick={check}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl border border-slate-200 px-4 text-sm font-bold text-slate-700 hover:bg-slate-50 disabled:opacity-50"
                        >
                            {busy === 'check' ? <Loader2 className="h-4 w-4 animate-spin" /> : <SearchCheck className="h-4 w-4" />}
                            Check
                        </button>

                        <button
                            type="button"
                            disabled={!readyCount || !!busy}
                            onClick={upload}
                            className="inline-flex h-11 items-center justify-center gap-2 rounded-xl bg-emerald-600 px-4 text-sm font-bold text-white hover:bg-emerald-700 disabled:opacity-50"
                        >
                            {busy === 'upload' ? <Loader2 className="h-4 w-4 animate-spin" /> : <UploadCloud className="h-4 w-4" />}
                            Upload {readyCount ? `(${readyCount})` : ''}
                        </button>
                    </div>

                    {busy === 'upload' && (
                        <div className="mt-4">
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-full bg-emerald-500 transition-all"
                                    style={{ width: `${readyCount ? Math.round((done / Math.max(1, done + readyCount)) * 100) : 100}%` }}
                                />
                            </div>
                            <div className="mt-1 text-xs text-slate-500">Uploading… {done} done. Keep this page open.</div>
                        </div>
                    )}

                    {notice && (
                        <div className="mt-4 flex items-start gap-2 rounded-2xl bg-slate-50 p-3 text-sm text-slate-700">
                            {savedCount ? <CheckCircle2 className="mt-0.5 h-4 w-4 shrink-0 text-emerald-600" /> : <SearchCheck className="mt-0.5 h-4 w-4 shrink-0 text-slate-500" />}
                            <span>{notice}</span>
                        </div>
                    )}

                    {files.length > 0 && (
                        <>
                            <div className="mt-4 flex flex-wrap gap-2">
                                {Object.entries(summary).map(([k, v]) => (
                                    <span key={k} className="inline-flex items-center gap-1 text-xs text-slate-600">
                                        <Pill result={k} /> {v}
                                    </span>
                                ))}
                            </div>

                            <ul className="mt-4 divide-y divide-slate-100 rounded-2xl border border-slate-100">
                                {files.map((f) => {
                                    const r = rows[f.name];
                                    return (
                                        <li key={f.name} className="flex items-start gap-3 p-3">
                                            {r?.result === 'invalid' || r?.result === 'error' ? (
                                                <XCircle className="mt-0.5 h-4 w-4 shrink-0 text-red-500" />
                                            ) : (
                                                <ImagePlus className="mt-0.5 h-4 w-4 shrink-0 text-slate-400" />
                                            )}
                                            <div className="min-w-0 flex-1">
                                                <div className="break-all text-sm font-bold text-slate-900">{f.name}</div>
                                                {r?.release && (
                                                    <div className="mt-0.5 text-xs text-slate-600">
                                                        → #{r.release.id} {r.release.title}
                                                        {r.release.artist ? ` · ${r.release.artist}` : ''} · {r.release.status}
                                                        {r.release.matched_by ? ` (by ${r.release.matched_by})` : ''}
                                                    </div>
                                                )}
                                                {r?.message && <div className="mt-0.5 text-xs text-slate-500">{r.message}</div>}
                                            </div>
                                            <Pill result={r?.result || 'waiting'} />
                                        </li>
                                    );
                                })}
                            </ul>
                        </>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
