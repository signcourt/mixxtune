import axios from 'axios';
import { useRef, useState } from 'react';

/*
 * MIXXTUNE_LABEL_OWNER_BULK_IMPORT_V1
 * Labels page (super admin): assign owners to many labels from one CSV.
 * 1 Download template → 2 Upload & preview (nothing saved) →
 * 3 E-mail code → 4 Confirm → runs in small batches with progress.
 */
const BASE = '/admin/labels/ownership/bulk';

const errorText = (exception, fallback) => {
    const data = exception?.response?.data;
    const first = data?.errors ? Object.values(data.errors).flat()[0] : null;

    return first || data?.message || fallback;
};

export default function LabelOwnershipBulkImport({ onDone }) {
    const fileRef = useRef(null);
    const [open, setOpen] = useState(false);
    const [file, setFile] = useState(null);
    const [busy, setBusy] = useState('');
    const [error, setError] = useState('');
    const [preview, setPreview] = useState(null);
    const [otpSentTo, setOtpSentTo] = useState('');
    const [otp, setOtp] = useState('');
    const [progress, setProgress] = useState(null);

    const reset = () => {
        setFile(null);
        setPreview(null);
        setOtpSentTo('');
        setOtp('');
        setProgress(null);
        setError('');

        if (fileRef.current) {
            fileRef.current.value = '';
        }
    };

    const upload = async () => {
        if (!file) {
            setError('Choose the filled CSV file first.');

            return;
        }

        setBusy('preview');
        setError('');
        setPreview(null);
        setOtpSentTo('');
        setOtp('');
        setProgress(null);

        try {
            const form = new FormData();
            form.append('csv', file);
            const response = await axios.post(`${BASE}/preview`, form);
            setPreview(response.data);
        } catch (exception) {
            setError(errorText(exception, 'Could not read the file.'));
        } finally {
            setBusy('');
        }
    };

    const sendOtp = async () => {
        setBusy('otp');
        setError('');

        try {
            const response = await axios.post(`${BASE}/otp`, { token: preview.token });
            setOtpSentTo(response.data.sent_to);
        } catch (exception) {
            setError(errorText(exception, 'Could not send the code.'));
        } finally {
            setBusy('');
        }
    };

    const confirmAndRun = async () => {
        setBusy('run');
        setError('');

        try {
            await axios.post(`${BASE}/confirm`, { token: preview.token, otp });

            let state = { done: false, processed: 0, total: preview.summary.owners, results: [] };
            setProgress(state);

            while (!state.done) {
                const response = await axios.post(`${BASE}/run`, { token: preview.token });
                state = response.data;
                setProgress(state);
            }

            onDone?.();
        } catch (exception) {
            setError(errorText(exception, 'The import stopped. Owners already done stay done; upload the file again to continue.'));
        } finally {
            setBusy('');
        }
    };

    const s = preview?.summary;
    const errors = preview?.errors ?? [];
    const finished = progress?.done;

    return (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50/50 p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">
                        Bulk assign owners (CSV)
                    </div>
                    <div className="mt-1 text-xs text-slate-500">
                        Download the list, fill the owner details in Excel, upload it, check the preview, then confirm with the e-mailed code.
                    </div>
                </div>

                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-800 hover:bg-emerald-50"
                >
                    {open ? 'Close' : 'Open bulk import'}
                </button>
            </div>

            {open && (
                <div className="mt-4 space-y-4 rounded-xl border border-emerald-100 bg-white p-4">
                    {/* Step 1 */}
                    <div>
                        <div className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                            1. Download the list
                        </div>
                        <div className="mt-2 flex flex-wrap gap-2">
                            <a
                                href={`${BASE}/template`}
                                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                Labels without owner (.csv)
                            </a>
                            <a
                                href={`${BASE}/template?scope=all`}
                                className="rounded-lg border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                All labels (.csv)
                            </a>
                        </div>
                        <ul className="mt-2 list-disc space-y-0.5 pl-5 text-xs leading-5 text-slate-500">
                            <li>Fill <b>owner_email</b> for each label. Leave it empty to skip that label.</li>
                            <li>Same e-mail on many rows = one owner for all those labels. Owner details are needed once (any one row).</li>
                            <li>An e-mail that already has a Label account is used as is. A new e-mail creates a new Label user (Pending + invitation).</li>
                            <li>Do not change <b>label_id</b>. Each label keeps its own revenue %. state_code = 2 letters (e.g. HR, DL, UP).</li>
                        </ul>
                    </div>

                    {/* Step 2 */}
                    <div>
                        <div className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                            2. Upload the filled file
                        </div>
                        <div className="mt-2 flex flex-wrap items-center gap-2">
                            <input
                                ref={fileRef}
                                type="file"
                                accept=".csv,text/csv"
                                onChange={(event) => {
                                    setFile(event.target.files?.[0] ?? null);
                                    setPreview(null);
                                    setProgress(null);
                                    setOtpSentTo('');
                                }}
                                className="text-sm"
                            />
                            <button
                                type="button"
                                disabled={!file || busy !== '' || (progress && !finished)}
                                onClick={upload}
                                className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                            >
                                {busy === 'preview' ? 'Checking...' : 'Check file (preview)'}
                            </button>
                            {(preview || progress) && (
                                <button
                                    type="button"
                                    disabled={busy !== ''}
                                    onClick={reset}
                                    className="text-xs font-semibold text-slate-500 hover:text-slate-900"
                                >
                                    Start again
                                </button>
                            )}
                        </div>
                    </div>

                    {error && (
                        <div className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm font-medium text-red-700">
                            {error}
                        </div>
                    )}

                    {/* Preview */}
                    {preview && (
                        <div className="space-y-3">
                            <div className="grid gap-2 sm:grid-cols-3 xl:grid-cols-6">
                                {[
                                    ['Owners', s.owners],
                                    ['New owners', s.new_owners],
                                    ['Labels', s.labels],
                                    ['Assign', s.assign],
                                    ['Transfer', s.transfer],
                                    ['Skipped rows', s.skipped_rows],
                                ].map(([label, value]) => (
                                    <div key={label} className="rounded-lg border border-slate-200 px-3 py-2">
                                        <div className="text-[11px] font-semibold uppercase tracking-wide text-slate-500">{label}</div>
                                        <div className={['text-lg font-semibold', label === 'Transfer' && value > 0 ? 'text-amber-700' : 'text-slate-900'].join(' ')}>{value}</div>
                                    </div>
                                ))}
                            </div>

                            {errors.length > 0 && (
                                <div className="rounded-lg border border-red-200 bg-red-50 p-3">
                                    <div className="text-sm font-semibold text-red-800">
                                        {errors.length} problem(s) found. Nothing was saved. Fix the file and upload again.
                                    </div>
                                    <ul className="mt-2 max-h-56 space-y-1 overflow-y-auto text-xs text-red-700">
                                        {errors.map((item, index) => (
                                            <li key={index}>
                                                <b>Line {item.line}:</b> {item.message}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}

                            {preview.groups.length > 0 && (
                                <div className="max-h-72 overflow-y-auto rounded-lg border border-slate-200">
                                    <table className="min-w-full text-xs">
                                        <thead className="sticky top-0 bg-slate-50 text-left text-slate-500">
                                            <tr>
                                                <th className="px-3 py-2">Owner</th>
                                                <th className="px-3 py-2">Account</th>
                                                <th className="px-3 py-2">Labels</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-100">
                                            {preview.groups.map((group) => (
                                                <tr key={group.email} className="align-top">
                                                    <td className="px-3 py-2">
                                                        <div className="font-semibold text-slate-900">{group.owner_name}</div>
                                                        <div className="text-slate-500">{group.email}</div>
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {group.mode === 'new' ? (
                                                            <span className="rounded-full bg-violet-100 px-2 py-0.5 font-semibold text-violet-800">New (Pending)</span>
                                                        ) : (
                                                            <span className="rounded-full bg-slate-100 px-2 py-0.5 font-semibold text-slate-700">Existing · {group.owner_status}</span>
                                                        )}
                                                    </td>
                                                    <td className="px-3 py-2 text-slate-700">
                                                        {group.labels.map((label) => (
                                                            <div key={label.id}>
                                                                #{label.id} {label.name}
                                                                {label.current_owner_email && label.current_owner_email.toLowerCase() !== group.email && (
                                                                    <span className="ml-1 font-semibold text-amber-700">(transfer from {label.current_owner_email})</span>
                                                                )}
                                                            </div>
                                                        ))}
                                                    </td>
                                                </tr>
                                            ))}
                                        </tbody>
                                    </table>
                                </div>
                            )}

                            {/* Step 3 + 4 */}
                            {preview.token && !progress && (
                                <div className="flex flex-wrap items-end gap-3 rounded-lg border border-violet-200 bg-violet-50/60 p-3">
                                    <button
                                        type="button"
                                        disabled={busy !== ''}
                                        onClick={sendOtp}
                                        className="rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                                    >
                                        {busy === 'otp' ? 'Sending...' : otpSentTo ? 'Send code again' : '3. Send confirmation code'}
                                    </button>

                                    {otpSentTo && (
                                        <>
                                            <label className="text-xs text-slate-600">
                                                Code sent to {otpSentTo}
                                                <input
                                                    value={otp}
                                                    onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
                                                    inputMode="numeric"
                                                    placeholder="6-digit code"
                                                    className="mt-1 block w-40 rounded-lg border border-slate-300 px-3 py-2 text-sm tracking-widest"
                                                />
                                            </label>
                                            <button
                                                type="button"
                                                disabled={otp.length !== 6 || busy !== ''}
                                                onClick={confirmAndRun}
                                                className="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700 disabled:opacity-50"
                                            >
                                                4. Confirm and assign {s.labels} label(s)
                                            </button>
                                        </>
                                    )}
                                </div>
                            )}
                        </div>
                    )}

                    {/* Progress + results */}
                    {progress && (
                        <div className="space-y-2">
                            <div className="text-sm font-semibold text-slate-900">
                                {finished ? 'Finished' : 'Working... keep this page open'} · {progress.processed} / {progress.total} owner(s)
                                {finished && (
                                    <span className="ml-2 text-xs font-normal text-slate-500">
                                        {progress.succeeded ?? 0} done, {progress.failed ?? 0} need attention
                                    </span>
                                )}
                            </div>
                            <div className="h-2 overflow-hidden rounded-full bg-slate-100">
                                <div
                                    className="h-2 rounded-full bg-emerald-500 transition-all"
                                    style={{ width: `${progress.total ? Math.round((progress.processed / progress.total) * 100) : 0}%` }}
                                />
                            </div>
                            {(progress.results ?? []).length > 0 && (
                                <ul className="max-h-56 space-y-1 overflow-y-auto text-xs">
                                    {progress.results.map((result) => (
                                        <li key={result.email} className={result.status === 'done' ? 'text-slate-700' : 'font-medium text-red-700'}>
                                            {result.status === 'done' ? '✓' : '✗'} {result.email}
                                            {result.status === 'done' && (
                                                <span className="text-slate-500">
                                                    {' '}— {result.created ? 'new account, ' : ''}{result.assigned} assigned, {result.transferred} transferred
                                                </span>
                                            )}
                                            {result.message && <span> — {result.message}</span>}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
