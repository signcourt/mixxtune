import { router } from '@inertiajs/react';
import { FileAudio } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

/*
 * W211 - WAV replacement after the release left the panel.
 *  super_admin -> "Replace WAV": pick a track + new WAV + reason -> replaced at once (old file kept, QC re-runs).
 *  admin (Manager) -> "Request WAV change": same form -> a Super Admin listens and decides under Approvals.
 *  everybody else -> nothing. Drafts: nothing (the wizard handles files there).
 */
export default function ReleaseAudioButton({ release, role = '' }) {
    const [open, setOpen] = useState(false);
    const [tracks, setTracks] = useState(null);
    const [maxMb, setMaxMb] = useState(300);
    const [trackId, setTrackId] = useState('');
    const [file, setFile] = useState(null);
    const [reason, setReason] = useState('');
    const [busy, setBusy] = useState(false);
    const [err, setErr] = useState('');
    const fileRef = useRef(null);

    const isSuper = role === 'super_admin';
    const isManager = role === 'admin';
    const status = String(release?.status || '');
    const hidden = (!isSuper && !isManager) || ['draft', 'submitted', 'changes_requested', 'rejected'].includes(status);
    const label = isSuper ? 'Replace WAV' : 'Request WAV change';

    useEffect(() => {
        if (hidden || !open || tracks !== null) return;
        fetch(`/admin/releases/${release.id}/audio-request-tracks`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' })
            .then((r) => (r.ok ? r.json() : Promise.reject(new Error(`HTTP ${r.status}`))))
            .then((d) => {
                setTracks(d.tracks || []);
                setMaxMb(d.max_mb || 300);
                if ((d.tracks || []).length === 1) setTrackId(String(d.tracks[0].id));
            })
            .catch((e) => { setTracks([]); setErr(`Could not load the tracks (${e.message}).`); });
    }, [open]);

    if (hidden) return null;

    const chosen = (tracks || []).find((t) => String(t.id) === String(trackId));
    const ready = chosen && !chosen.pending && file && reason.trim().length >= 3 && !busy;

    const onFile = (e) => {
        const f = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!f) return;
        if (!/\.wav$/i.test(f.name)) { setErr('Only a WAV file can be uploaded.'); return; }
        if (f.size > maxMb * 1024 * 1024) { setErr(`The file is bigger than ${maxMb} MB.`); return; }
        setErr('');
        setFile(f);
    };

    const send = () => {
        if (!ready) return;
        setBusy(true);
        setErr('');
        const url = isSuper ? `/admin/tracks/${trackId}/audio-replace` : `/admin/tracks/${trackId}/audio-request`;
        router.post(url, { audio: file, reason }, {
            forceFormData: true,
            preserveScroll: true,
            onFinish: () => setBusy(false),
            onSuccess: () => { setOpen(false); setFile(null); setReason(''); setTracks(null); },
            onError: (errors) => setErr(Object.values(errors || {}).flat().join(' ') || 'Upload failed.'),
        });
    };

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} className="inline-flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm font-semibold text-sky-800 transition hover:bg-sky-100">
                <FileAudio className="h-4 w-4" /> {label}
            </button>

            {open && (
                <div className="fixed inset-0 z-[80] flex items-center justify-center bg-slate-900/50 p-4" role="dialog" aria-modal="true" aria-label={label}>
                    <div className="w-full max-w-md rounded-2xl border border-slate-200 bg-white p-6 text-left shadow-2xl">
                        <div className="flex items-start gap-3">
                            <span className="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-sky-100 text-sky-700"><FileAudio className="h-5 w-5" /></span>
                            <div>
                                <div className="text-base font-bold text-slate-900">{label}: "{release.title || 'Untitled'}"</div>
                                <p className="mt-1 text-sm leading-6 text-slate-600">
                                    {isSuper
                                        ? 'The new WAV replaces the track file at once. The old file stays on disk (kept on the record), audio QC runs again, nothing is re-sent to the stores by itself.'
                                        : 'A Super Admin listens to the new WAV and decides it under Administration → Approvals. Until then the current file stays live. You get a bell either way.'}
                                </p>
                            </div>
                        </div>

                        <label className="mt-4 block text-sm font-semibold text-slate-700">
                            Track <span className="text-rose-600">*</span>
                            <select value={trackId} onChange={(e) => setTrackId(e.target.value)} className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-sky-400 focus:outline-none">
                                <option value="">{tracks === null ? 'Loading tracks…' : (tracks.length ? 'Choose a track' : 'No tracks on this release')}</option>
                                {(tracks || []).map((t) => (
                                    <option key={t.id} value={t.id} disabled={t.pending}>
                                        {t.number ? `${t.number}. ` : ''}{t.title}{t.has_wav ? ` · ${t.file || 'WAV ✓'}` : ' · no WAV yet'}{t.pending ? ' · change already waiting' : ''}
                                    </option>
                                ))}
                            </select>
                        </label>

                        <div className="mt-3">
                            <div className="text-sm font-semibold text-slate-700">New WAV <span className="text-rose-600">*</span></div>
                            <input ref={fileRef} type="file" accept=".wav,audio/wav,audio/x-wav" className="hidden" onChange={onFile} />
                            <button type="button" onClick={() => fileRef.current && fileRef.current.click()} className="mt-1 w-full rounded-xl border border-dashed border-slate-300 bg-slate-50 px-3 py-3 text-sm text-slate-700 hover:bg-slate-100">
                                {file ? `${file.name} · ${(file.size / 1024 / 1024).toFixed(1)} MB` : `Choose the WAV master (16/24-bit, 44.1/48 kHz, max ${maxMb} MB)`}
                            </button>
                        </div>

                        <label className="mt-3 block text-sm font-semibold text-slate-700">
                            Reason <span className="text-rose-600">*</span>
                            <textarea value={reason} onChange={(e) => setReason(e.target.value)} rows={2} maxLength={1000} placeholder="e.g. wrong master uploaded / owner sent the corrected mix on 25 Nov" className="mt-1 w-full rounded-xl border border-slate-300 px-3 py-2 text-sm text-slate-900 focus:border-sky-400 focus:outline-none" />
                        </label>

                        {err && <p className="mt-2 text-xs font-semibold text-rose-600">{err}</p>}
                        {busy && <p className="mt-2 text-xs text-slate-500">Uploading… please keep this window open.</p>}

                        <div className="mt-5 flex justify-end gap-2">
                            <button type="button" onClick={() => setOpen(false)} disabled={busy} className="rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-60">Cancel</button>
                            <button type="button" disabled={!ready} onClick={send} className="rounded-xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-sky-700 disabled:cursor-not-allowed disabled:opacity-50">
                                {isSuper ? 'Replace now' : 'Send request'}
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </>
    );
}
