import axios from 'axios';
import { useState } from 'react';

/*
 * MIXXTUNE_LABEL_RATE_HISTORY_V1
 * Read-only popup: who changed a label's revenue % and when, and the rate
 * actually used in every report (approved reports never change).
 */
const pct = (value) =>
    value === null || value === undefined ? 'Not set' : `${Number(value).toFixed(2).replace(/\.00$/, '')}%`;

const money = (value) =>
    `₹${Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const when = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(String(value).replace(' ', 'T') + 'Z');

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
};

const SOURCES = {
    'v2.admin.labels.revenue-rate': 'Labels page',
    'v2.admin.labels.bulk-action': 'Labels page (bulk)',
    label_ownership: 'Owner assignment',
    'v2.admin.users.store': 'Create User',
    'v2.admin.users.update': 'Edit User',
};

const sourceText = (source) => {
    if (!source) {
        return '';
    }

    if (SOURCES[source]) {
        return SOURCES[source];
    }

    if (String(source).includes('report-import') || String(source).includes('reports')) {
        return 'Report import page';
    }

    return source;
};

const STATUS = {
    review_pending: ['Pending review', 'bg-amber-100 text-amber-800'],
    approved: ['Approved', 'bg-emerald-100 text-emerald-800'],
    scheduled: ['Scheduled', 'bg-sky-100 text-sky-800'],
    approved_scheduled: ['Approved (scheduled)', 'bg-sky-100 text-sky-800'],
    published: ['Published', 'bg-emerald-100 text-emerald-800'],
};

export default function LabelRateHistory({ labelId = null, labelName = '', buttonText = null }) {
    const [open, setOpen] = useState(false);
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [data, setData] = useState(null);

    const load = async () => {
        setOpen(true);
        setLoading(true);
        setError('');

        try {
            const response = await axios.get('/v2/admin/labels/rate-history', {
                params: labelId ? { label_id: labelId } : {},
            });
            setData(response.data);
        } catch (exception) {
            setError(exception?.response?.data?.message ?? 'Could not load the history.');
        } finally {
            setLoading(false);
        }
    };

    const changes = data?.changes ?? [];
    const used = data?.rates_used ?? [];

    return (
        <>
            {labelId ? (
                <button
                    type="button"
                    onClick={load}
                    className="mt-1 block text-[11px] font-semibold text-slate-400 hover:text-violet-700"
                    title={`Rate history for ${labelName}`}
                >
                    History
                </button>
            ) : (
                <button
                    type="button"
                    onClick={load}
                    className="rounded-xl border border-slate-300 bg-white px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                >
                    {buttonText ?? 'All rate changes'}
                </button>
            )}

            {open && (
                <div
                    className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/40 p-4"
                    onMouseDown={(event) => {
                        if (event.target === event.currentTarget) {
                            setOpen(false);
                        }
                    }}
                >
                    <div className="mt-10 w-full max-w-3xl rounded-2xl bg-white p-5 text-left shadow-2xl">
                        <div className="flex items-start justify-between gap-4">
                            <div>
                                <div className="text-lg font-semibold text-slate-900">
                                    {labelId ? `Rate history: ${data?.label?.name ?? labelName}` : 'All label rate changes'}
                                </div>
                                {data?.label && (
                                    <div className="mt-1 text-sm text-slate-500">
                                        Current rate <b className="text-slate-900">{pct(data.label.current)}</b>
                                        {data.label.pending_import_ids?.length > 0 && (
                                            <> · changing it now recalculates pending report(s) #{data.label.pending_import_ids.join(', #')} only</>
                                        )}
                                    </div>
                                )}
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                className="rounded-lg px-2 py-1 text-xl leading-none text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            >
                                ×
                            </button>
                        </div>

                        {loading && <div className="mt-6 text-sm text-slate-500">Loading...</div>}
                        {error && <div className="mt-4 rounded-lg bg-red-50 px-3 py-2 text-sm text-red-700">{error}</div>}

                        {!loading && data && (
                            <div className="mt-5 space-y-6">
                                <section>
                                    <div className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                                        Changes
                                    </div>
                                    {changes.length === 0 ? (
                                        <p className="mt-2 text-sm text-slate-500">
                                            No changes recorded yet. Recording started with this update; earlier changes were not logged (the rate used in each report is shown below).
                                        </p>
                                    ) : (
                                        <div className="mt-2 max-h-80 overflow-y-auto rounded-lg border border-slate-200">
                                            <table className="min-w-full text-xs">
                                                <thead className="sticky top-0 bg-slate-50 text-left text-slate-500">
                                                    <tr>
                                                        <th className="px-3 py-2">When</th>
                                                        {!labelId && <th className="px-3 py-2">Label</th>}
                                                        <th className="px-3 py-2">Change</th>
                                                        <th className="px-3 py-2">By / where</th>
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-100">
                                                    {changes.map((change) => (
                                                        <tr key={change.id} className="align-top">
                                                            <td className="whitespace-nowrap px-3 py-2 text-slate-600">{when(change.at)}</td>
                                                            {!labelId && (
                                                                <td className="px-3 py-2 text-slate-800">
                                                                    #{change.label_id} {change.label_name}
                                                                </td>
                                                            )}
                                                            <td className="whitespace-nowrap px-3 py-2">
                                                                <span className="text-slate-500">{pct(change.old)}</span>
                                                                <span className="mx-1 text-slate-400">→</span>
                                                                <b className="text-slate-900">{pct(change.new)}</b>
                                                            </td>
                                                            <td className="px-3 py-2 text-slate-600">
                                                                <div>{change.by}</div>
                                                                <div className="text-slate-400">
                                                                    {sourceText(change.source)}
                                                                    {change.pending_import_ids?.length > 0 && ` · recalculated #${change.pending_import_ids.join(', #')}`}
                                                                </div>
                                                                {change.note && <div className="text-slate-400">{change.note}</div>}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    )}
                                </section>

                                {labelId && (
                                    <section>
                                        <div className="text-xs font-bold uppercase tracking-[0.14em] text-slate-500">
                                            Rate used in each report
                                        </div>
                                        <p className="mt-1 text-xs text-slate-500">
                                            Approved reports are locked: changing the rate never changes them.
                                        </p>
                                        {used.length === 0 ? (
                                            <p className="mt-2 text-sm text-slate-500">This label has no report rows yet.</p>
                                        ) : (
                                            <div className="mt-2 max-h-72 overflow-y-auto rounded-lg border border-slate-200">
                                                <table className="min-w-full text-xs">
                                                    <thead className="sticky top-0 bg-slate-50 text-left text-slate-500">
                                                        <tr>
                                                            <th className="px-3 py-2">Report</th>
                                                            <th className="px-3 py-2">Month</th>
                                                            <th className="px-3 py-2">Status</th>
                                                            <th className="px-3 py-2 text-right">Rate used</th>
                                                            <th className="px-3 py-2 text-right">Collected</th>
                                                            <th className="px-3 py-2 text-right">Earnings</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody className="divide-y divide-slate-100">
                                                        {used.map((row) => {
                                                            const [statusText, statusClass] = STATUS[row.status] ?? [row.status ?? '—', 'bg-slate-100 text-slate-700'];

                                                            return (
                                                                <tr key={`${row.import_id}-${row.rate}`}>
                                                                    <td className="px-3 py-2 text-slate-700" title={row.file}>#{row.import_id}</td>
                                                                    <td className="px-3 py-2 text-slate-700">{row.month ?? '—'}</td>
                                                                    <td className="px-3 py-2">
                                                                        <span className={`rounded-full px-2 py-0.5 font-semibold ${statusClass}`}>{statusText}</span>
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right font-semibold text-slate-900">{pct(row.rate)}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-700">{money(row.collected)}</td>
                                                                    <td className="px-3 py-2 text-right text-slate-700">{money(row.earnings)}</td>
                                                                </tr>
                                                            );
                                                        })}
                                                    </tbody>
                                                </table>
                                            </div>
                                        )}
                                    </section>
                                )}
                            </div>
                        )}
                    </div>
                </div>
            )}
        </>
    );
}
