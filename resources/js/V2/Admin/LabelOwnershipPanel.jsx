import axios from 'axios';
import { useEffect, useState } from 'react';

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1
 * Super admin: assign / transfer label ownership. Every change needs a
 * one-time code e-mailed to the acting super admin.
 */
export default function LabelOwnershipPanel({ selectedLabelIds = [], onDone }) {
    const [open, setOpen] = useState(false);
    const [mode, setMode] = useState('existing');
    const [query, setQuery] = useState('');
    const [users, setUsers] = useState([]);
    const [targetId, setTargetId] = useState('');
    const [newName, setNewName] = useState('');
    const [newEmail, setNewEmail] = useState('');
    const [newPhone, setNewPhone] = useState('');
    const [step, setStep] = useState('form');
    const [preview, setPreview] = useState(null);
    const [otp, setOtp] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);

    const count = selectedLabelIds.length;
    const selectionKey = selectedLabelIds.join(',');

    useEffect(() => {
        if (step === 'otp') {
            setStep('form');
            setOtp('');
            setPreview(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectionKey]);

    useEffect(() => {
        if (!open || mode !== 'existing') {
            return undefined;
        }

        const timer = setTimeout(() => {
            axios
                .get('/v2/admin/labels/ownership/users', { params: { q: query } })
                .then((response) => setUsers(response.data.users ?? []))
                .catch(() => setUsers([]));
        }, 300);

        return () => clearTimeout(timer);
    }, [query, mode, open]);

    const payload = () =>
        mode === 'existing'
            ? { label_ids: selectedLabelIds, mode, target_user_id: targetId ? Number(targetId) : null }
            : { label_ids: selectedLabelIds, mode, new_name: newName, new_email: newEmail, new_phone: newPhone };

    const errorMessage = (exception) => {
        const data = exception?.response?.data;

        if (data?.errors) {
            return Object.values(data.errors).flat().join(' ');
        }

        return data?.message || 'Something went wrong. Please try again.';
    };

    const sendOtp = () => {
        setError('');

        if (count === 0) {
            setError('Select at least one label in the table first.');
            return;
        }

        if (mode === 'existing' && !targetId) {
            setError('Choose the owner account.');
            return;
        }

        if (mode === 'new' && (!newName.trim() || !newEmail.trim())) {
            setError('Enter name and e-mail for the new owner.');
            return;
        }

        setBusy(true);
        axios
            .post('/v2/admin/labels/ownership/otp', payload())
            .then((response) => {
                setPreview(response.data);
                setOtp('');
                setStep('otp');
            })
            .catch((exception) => setError(errorMessage(exception)))
            .finally(() => setBusy(false));
    };

    const confirm = () => {
        setError('');

        if (!/^\d{6}$/.test(otp)) {
            setError('Enter the 6-digit code from your e-mail.');
            return;
        }

        setBusy(true);
        axios
            .post('/v2/admin/labels/ownership/apply', { ...payload(), otp })
            .then((response) => {
                setResult(response.data);
                setStep('done');
            })
            .catch((exception) => setError(errorMessage(exception)))
            .finally(() => setBusy(false));
    };

    const reset = () => {
        setStep('form');
        setPreview(null);
        setOtp('');
        setResult(null);
        setError('');
        setTargetId('');
        setNewName('');
        setNewEmail('');
        setNewPhone('');
    };

    const input = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700';
    const changeCount = (status) => (result?.changes ?? []).filter((change) => change.status === status).length;

    return (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">Assign / Transfer Label Owner</div>
                    <div className="mt-1 text-xs text-slate-500">
                        {count} label(s) selected · every change needs an e-mail OTP
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen(!open)}
                    className="rounded-xl border border-emerald-300 bg-white px-4 py-2 text-sm font-semibold text-emerald-700"
                >
                    {open ? 'Close' : 'Open'}
                </button>
            </div>

            {open && (
                <div className="mt-4 space-y-4">
                    {error && (
                        <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{error}</div>
                    )}

                    {step === 'form' && (
                        <div className="space-y-4">
                            <div className="flex flex-wrap gap-4 text-sm text-slate-700">
                                <label className="flex items-center gap-2">
                                    <input type="radio" checked={mode === 'existing'} onChange={() => setMode('existing')} />
                                    Existing owner account
                                </label>
                                <label className="flex items-center gap-2">
                                    <input type="radio" checked={mode === 'new'} onChange={() => setMode('new')} />
                                    Create new owner account
                                </label>
                            </div>

                            {mode === 'existing' && (
                                <div className="space-y-2">
                                    <input
                                        value={query}
                                        onChange={(event) => setQuery(event.target.value)}
                                        placeholder="Search label account by name or e-mail"
                                        className={input}
                                    />
                                    <select value={targetId} onChange={(event) => setTargetId(event.target.value)} className={input}>
                                        <option value="">Select owner account…</option>
                                        {users.map((user) => (
                                            <option key={user.id} value={user.id}>
                                                {user.name} — {user.email} ({user.account_status})
                                            </option>
                                        ))}
                                    </select>
                                    {users.length === 0 && (
                                        <div className="text-xs text-slate-500">No label accounts found. Use “Create new owner account”.</div>
                                    )}
                                </div>
                            )}

                            {mode === 'new' && (
                                <div className="grid gap-2 md:grid-cols-3">
                                    <input value={newName} onChange={(event) => setNewName(event.target.value)} placeholder="Owner name" className={input} />
                                    <input value={newEmail} onChange={(event) => setNewEmail(event.target.value)} placeholder="Owner e-mail" type="email" className={input} />
                                    <input value={newPhone} onChange={(event) => setNewPhone(event.target.value)} placeholder="Phone (optional)" className={input} />
                                    <div className="text-xs text-slate-500 md:col-span-3">
                                        The new account is created as <b>Pending</b> and receives an invitation e-mail. It becomes Active after
                                        the owner accepts the invitation; bank / PAN details can be added later from Users.
                                    </div>
                                </div>
                            )}

                            <button
                                type="button"
                                disabled={busy || count === 0}
                                onClick={sendOtp}
                                className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {busy ? 'Sending…' : 'Send OTP to my e-mail'}
                            </button>
                        </div>
                    )}

                    {step === 'otp' && preview && (
                        <div className="space-y-4">
                            <div className="text-sm text-slate-700">
                                Code sent to <b>{preview.sent_to}</b> (valid {preview.expires_minutes} min). New owner: <b>{preview.target}</b>
                            </div>
                            <div className="max-h-72 overflow-auto rounded-xl border border-slate-200 bg-white">
                                <table className="min-w-full text-sm">
                                    <thead className="bg-slate-50 text-left text-xs uppercase text-slate-500">
                                        <tr>
                                            <th className="px-4 py-2">Label</th>
                                            <th className="px-4 py-2">Current owner</th>
                                            <th className="px-4 py-2">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {(preview.preview ?? []).map((row) => (
                                            <tr key={row.id}>
                                                <td className="px-4 py-2 font-medium text-slate-900">{row.name}</td>
                                                <td className="px-4 py-2 text-slate-600">{row.current_owner ?? '—'}</td>
                                                <td className="px-4 py-2">
                                                    {row.current_owner ? (
                                                        <span className="rounded-lg bg-amber-100 px-2 py-1 text-xs font-semibold text-amber-800">Transfer</span>
                                                    ) : (
                                                        <span className="rounded-lg bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-800">Assign</span>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                            <div className="flex flex-wrap items-center gap-2">
                                <input
                                    value={otp}
                                    onChange={(event) => setOtp(event.target.value.replace(/\D/g, '').slice(0, 6))}
                                    inputMode="numeric"
                                    placeholder="6-digit code"
                                    className="w-44 rounded-xl border border-slate-300 bg-white px-4 py-3 text-center text-lg tracking-widest"
                                />
                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={confirm}
                                    className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                                >
                                    {busy ? 'Saving…' : 'Confirm change'}
                                </button>
                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={() => { setStep('form'); setOtp(''); setError(''); }}
                                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm text-slate-700"
                                >
                                    Cancel
                                </button>
                            </div>
                        </div>
                    )}

                    {step === 'done' && result && (
                        <div className="space-y-3">
                            <div className="rounded-xl border border-emerald-200 bg-white px-4 py-3 text-sm text-slate-700">
                                Done. Owner: <b>{result.owner?.name}</b> ({result.owner?.email}, {result.owner?.account_status}) ·
                                assigned {changeCount('assigned')} · transferred {changeCount('transferred')} · unchanged {changeCount('unchanged')}
                                {result.created && ' · new account created, invitation sent'}
                            </div>
                            {result.invitation_error && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Account created, but the invitation e-mail failed: {result.invitation_error}. Resend it from Users.
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={() => { reset(); if (onDone) { onDone(); } }}
                                className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white"
                            >
                                OK
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
