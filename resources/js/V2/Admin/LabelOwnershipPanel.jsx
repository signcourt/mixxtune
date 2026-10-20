import axios from 'axios';
import { useEffect, useState } from 'react';

/*
 * MIXXTUNE_LABEL_OWNERSHIP_OTP_V1 (+ label picker basket, full new-owner form)
 * Super admin: search & tick labels (ticks kept across searches), then
 * assign / transfer them to one Label account. A new owner is created with
 * the same details as Users > Create (role = Label). E-mail OTP required.
 */
const indiaStates = [
    ["AN", "Andaman and Nicobar Islands"],
    ["AP", "Andhra Pradesh"],
    ["AR", "Arunachal Pradesh"],
    ["AS", "Assam"],
    ["BR", "Bihar"],
    ["CH", "Chandigarh"],
    ["CG", "Chhattisgarh"],
    ["DN", "Dadra and Nagar Haveli and Daman and Diu"],
    ["DL", "Delhi"],
    ["GA", "Goa"],
    ["GJ", "Gujarat"],
    ["HR", "Haryana"],
    ["HP", "Himachal Pradesh"],
    ["JK", "Jammu and Kashmir"],
    ["JH", "Jharkhand"],
    ["KA", "Karnataka"],
    ["KL", "Kerala"],
    ["LA", "Ladakh"],
    ["LD", "Lakshadweep"],
    ["MP", "Madhya Pradesh"],
    ["MH", "Maharashtra"],
    ["MN", "Manipur"],
    ["ML", "Meghalaya"],
    ["MZ", "Mizoram"],
    ["NL", "Nagaland"],
    ["OD", "Odisha"],
    ["PY", "Puducherry"],
    ["PB", "Punjab"],
    ["RJ", "Rajasthan"],
    ["SK", "Sikkim"],
    ["TN", "Tamil Nadu"],
    ["TS", "Telangana"],
    ["TR", "Tripura"],
    ["UP", "Uttar Pradesh"],
    ["UK", "Uttarakhand"],
    ["WB", "West Bengal"],
];

const emptyNewUser = {
    name: '', username: '', email: '', phone: '',
    country: 'India', country_code: 'IN', address_line_1: '', address_line_2: '', state_code: '', state: '', city: '', postal_code: '',
    revenue_share_percentage: '',
    account_holder_name: '', bank_account_number: '', bank_name: '', ifsc_code: '', branch_name: '',
    pan_number: '', gst_number: '',
};

const requiredNewUser = {
    name: 'Owner name', email: 'Owner e-mail', address_line_1: 'Address line 1', state_code: 'State', city: 'City', postal_code: 'Pincode',
    revenue_share_percentage: 'Revenue %', account_holder_name: 'Account holder name',
    bank_account_number: 'Bank account number', bank_name: 'Bank name', ifsc_code: 'IFSC code', pan_number: 'PAN',
};

export default function LabelOwnershipPanel({ selectedLabelIds = [], pageLabels = [], onDone }) {
    const [open, setOpen] = useState(false);
    const [basket, setBasket] = useState([]);
    const [labelQuery, setLabelQuery] = useState('');
    const [onlyUnowned, setOnlyUnowned] = useState(true);
    const [labelResults, setLabelResults] = useState([]);
    const [labelsLoading, setLabelsLoading] = useState(false);
    const [mode, setMode] = useState('existing');
    const [query, setQuery] = useState('');
    const [users, setUsers] = useState([]);
    const [targetId, setTargetId] = useState('');
    const [newUser, setNewUser] = useState(emptyNewUser);
    const [step, setStep] = useState('form');
    const [preview, setPreview] = useState(null);
    const [otp, setOtp] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState('');
    const [result, setResult] = useState(null);

    const basketIds = basket.map((label) => label.id);
    const basketKey = basketIds.join(',');
    const count = basket.length;

    useEffect(() => {
        if (step === 'otp') {
            setStep('form');
            setOtp('');
            setPreview(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [basketKey]);

    useEffect(() => {
        if (!open || step !== 'form') {
            return undefined;
        }

        setLabelsLoading(true);
        const timer = setTimeout(() => {
            axios
                .get('/v2/admin/labels/ownership/labels', { params: { q: labelQuery, unowned: onlyUnowned ? 1 : 0 } })
                .then((response) => setLabelResults(response.data.labels ?? []))
                .catch(() => setLabelResults([]))
                .finally(() => setLabelsLoading(false));
        }, 300);

        return () => clearTimeout(timer);
    }, [labelQuery, onlyUnowned, open, step]);

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

    const setField = (key, value) => setNewUser((current) => ({ ...current, [key]: value }));

    const inBasket = (id) => basketIds.includes(Number(id));

    const toggleLabel = (label) => {
        setBasket((current) =>
            current.some((item) => item.id === label.id)
                ? current.filter((item) => item.id !== label.id)
                : [...current, { id: label.id, name: label.name, owner: label.owner ?? null }]
        );
    };

    const tickAllShown = () => {
        setBasket((current) => {
            const have = new Set(current.map((item) => item.id));

            return [
                ...current,
                ...labelResults
                    .filter((label) => !have.has(label.id))
                    .map((label) => ({ id: label.id, name: label.name, owner: label.owner ?? null })),
            ];
        });
    };

    const addTableSelection = () => {
        setBasket((current) => {
            const have = new Set(current.map((item) => item.id));
            const additions = selectedLabelIds
                .map((id) => Number(id))
                .filter((id) => !have.has(id))
                .map((id) => {
                    const row = pageLabels.find((item) => Number(item.id) === id);

                    return { id, name: row?.name ?? `Label #${id}`, owner: null };
                });

            return [...current, ...additions];
        });
    };

    const payload = () =>
        mode === 'existing'
            ? { label_ids: basketIds, mode, target_user_id: targetId ? Number(targetId) : null }
            : { label_ids: basketIds, mode, new_user: newUser };

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
            setError('Search and tick at least one label first.');
            return;
        }

        if (mode === 'existing' && !targetId) {
            setError('Choose the owner account.');
            return;
        }

        if (mode === 'new') {
            const missing = Object.entries(requiredNewUser)
                .filter(([key]) => !String(newUser[key] ?? '').trim())
                .map(([, label]) => label);

            if (missing.length) {
                setError(`Please fill: ${missing.join(', ')}.`);
                return;
            }
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
        setBasket([]);
        setTargetId('');
        setNewUser(emptyNewUser);
    };

    const input = 'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-700';
    const changeCount = (status) => (result?.changes ?? []).filter((change) => change.status === status).length;

    const field = (key, label, extra = {}) => (
        <label className="block">
            <span className="mb-1 block text-xs font-medium text-slate-600">
                {label}
                {requiredNewUser[key] ? ' *' : ''}
            </span>
            <input value={newUser[key]} onChange={(event) => setField(key, event.target.value)} className={input} {...extra} />
        </label>
    );

    return (
        <div className="rounded-2xl border border-emerald-200 bg-emerald-50/60 p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">Assign / Transfer Label Owner</div>
                    <div className="mt-1 text-xs text-slate-500">
                        {count} label(s) picked · search, tick, search again — ticks are kept · e-mail OTP required
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
                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <div className="mb-2 text-sm font-semibold text-slate-800">1. Pick labels</div>
                                <div className="flex flex-col gap-2 md:flex-row md:items-center">
                                    <input
                                        value={labelQuery}
                                        onChange={(event) => setLabelQuery(event.target.value)}
                                        placeholder="Search labels by name…"
                                        className={input}
                                    />
                                    <label className="flex shrink-0 items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" checked={onlyUnowned} onChange={(event) => setOnlyUnowned(event.target.checked)} />
                                        Only labels without owner
                                    </label>
                                </div>

                                <div className="mt-2 flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        onClick={tickAllShown}
                                        disabled={labelResults.length === 0}
                                        className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700 disabled:opacity-50"
                                    >
                                        Tick all shown ({labelResults.length})
                                    </button>
                                    {selectedLabelIds.length > 0 && (
                                        <button
                                            type="button"
                                            onClick={addTableSelection}
                                            className="rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs text-slate-700"
                                        >
                                            + Add {selectedLabelIds.length} ticked in table below
                                        </button>
                                    )}
                                </div>

                                <div className="mt-2 max-h-64 overflow-auto rounded-lg border border-slate-100">
                                    {labelsLoading && <div className="px-3 py-2 text-xs text-slate-500">Searching…</div>}
                                    {!labelsLoading && labelResults.length === 0 && (
                                        <div className="px-3 py-2 text-xs text-slate-500">No labels found.</div>
                                    )}
                                    {labelResults.map((label) => (
                                        <label
                                            key={label.id}
                                            className={`flex cursor-pointer items-center gap-3 border-b border-slate-50 px-3 py-2 text-sm ${inBasket(label.id) ? 'bg-emerald-50' : ''}`}
                                        >
                                            <input type="checkbox" checked={inBasket(label.id)} onChange={() => toggleLabel(label)} />
                                            <span className="font-medium text-slate-900">{label.name}</span>
                                            <span className="ml-auto text-xs text-slate-500">{label.owner ? `owner: ${label.owner}` : 'no owner'}</span>
                                        </label>
                                    ))}
                                </div>

                                {count > 0 && (
                                    <div className="mt-3">
                                        <div className="mb-1 flex items-center justify-between text-xs text-slate-600">
                                            <span><b>{count}</b> picked</span>
                                            <button type="button" onClick={() => setBasket([])} className="text-rose-600">Clear all</button>
                                        </div>
                                        <div className="flex max-h-40 flex-wrap gap-2 overflow-auto">
                                            {basket.map((label) => (
                                                <span key={label.id} className="flex items-center gap-1 rounded-full bg-emerald-100 px-3 py-1 text-xs text-emerald-900">
                                                    {label.name}
                                                    <button type="button" onClick={() => toggleLabel(label)} className="ml-1 font-bold text-emerald-700" aria-label={`Remove ${label.name}`}>
                                                        ×
                                                    </button>
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            <div className="rounded-xl border border-slate-200 bg-white p-3">
                                <div className="mb-2 text-sm font-semibold text-slate-800">2. Choose owner</div>
                                <div className="flex flex-wrap gap-4 text-sm text-slate-700">
                                    <label className="flex items-center gap-2">
                                        <input type="radio" checked={mode === 'existing'} onChange={() => setMode('existing')} />
                                        Existing owner account
                                    </label>
                                    <label className="flex items-center gap-2">
                                        <input type="radio" checked={mode === 'new'} onChange={() => setMode('new')} />
                                        Create new owner account (Label user)
                                    </label>
                                </div>

                                {mode === 'existing' && (
                                    <div className="mt-2 space-y-2">
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
                                    <div className="mt-3 space-y-4">
                                        <div>
                                            <div className="mb-2 text-xs font-semibold uppercase text-slate-500">Account</div>
                                            <div className="grid gap-3 md:grid-cols-2">
                                                {field('name', 'Owner name (account name)')}
                                                {field('username', 'Username (optional)')}
                                                {field('email', 'Owner e-mail', { type: 'email' })}
                                                {field('phone', 'Phone')}
                                            </div>
                                        </div>

                                        <div>
                                            <div className="mb-2 text-xs font-semibold uppercase text-slate-500">Address</div>
                                            <div className="grid gap-3 md:grid-cols-3">
                                                <label className="block">
                                                    <span className="mb-1 block text-xs font-medium text-slate-600">State *</span>
                                                    <select
                                                        value={newUser.state_code}
                                                        onChange={(event) => {
                                                            const code = event.target.value;
                                                            const found = indiaStates.find(([stateCode]) => stateCode === code);
                                                            setNewUser((current) => ({ ...current, state_code: code, state: found ? found[1] : '' }));
                                                        }}
                                                        className={input}
                                                    >
                                                        <option value="">Select state…</option>
                                                        {indiaStates.map(([code, name]) => (
                                                            <option key={code} value={code}>{name}</option>
                                                        ))}
                                                    </select>
                                                </label>
                                                {field('city', 'City')}
                                                {field('postal_code', 'Pincode')}
                                                {field('address_line_1', 'Address line 1')}
                                                {field('address_line_2', 'Address line 2 (optional)')}
                                            </div>
                                        </div>

                                        <div>
                                            <div className="mb-2 text-xs font-semibold uppercase text-slate-500">Revenue</div>
                                            <div className="grid gap-3 md:grid-cols-3">
                                                {field('revenue_share_percentage', 'Revenue %', { inputMode: 'decimal' })}
                                            </div>
                                            <div className="mt-1 text-xs text-slate-500">
                                                Every label keeps its own % (a label without % stays without %). This is the owner account's default %.
                                            </div>
                                        </div>

                                        <div>
                                            <div className="mb-2 text-xs font-semibold uppercase text-slate-500">Bank details</div>
                                            <div className="grid gap-3 md:grid-cols-3">
                                                {field('account_holder_name', 'Account holder name')}
                                                {field('bank_account_number', 'Bank account number')}
                                                {field('bank_name', 'Bank name')}
                                                {field('ifsc_code', 'IFSC code')}
                                                {field('branch_name', 'Branch name')}
                                            </div>
                                        </div>

                                        <div>
                                            <div className="mb-2 text-xs font-semibold uppercase text-slate-500">Tax</div>
                                            <div className="grid gap-3 md:grid-cols-3">
                                                {field('pan_number', 'PAN')}
                                                {field('gst_number', 'GST (optional)')}
                                            </div>
                                        </div>

                                        <div className="text-xs text-slate-500">
                                            Created exactly like Users → Create (role <b>Label</b>). The account is <b>Pending</b> and gets an
                                            invitation e-mail; it becomes Active after the owner accepts it.
                                        </div>
                                    </div>
                                )}
                            </div>

                            <button
                                type="button"
                                disabled={busy || count === 0}
                                onClick={sendOtp}
                                className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                            >
                                {busy ? 'Sending…' : `3. Send OTP to my e-mail (${count} label${count === 1 ? '' : 's'})`}
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
                                    Back
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
                                    {result.invitation_error} — resend it from Users.
                                </div>
                            )}
                            {result.recalculation_error && (
                                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">
                                    Owner saved, but pending-report earnings could not be recalculated: {result.recalculation_error}
                                </div>
                            )}
                            <button
                                type="button"
                                onClick={() => { reset(); if (onDone) { onDone(); } }}
                                className="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white"
                            >
                                OK — pick next labels
                            </button>
                        </div>
                    )}
                </div>
            )}
        </div>
    );
}
