import axios from 'axios';
import { useEffect, useState } from 'react';

/*
 * MIXXTUNE_OWNER_STATEMENT_V1
 * Super admin: e-mail the monthly statement PDF to every label / artist
 * owner. Already-sent statements are skipped; single statements can be re-sent.
 */
const money = (value) =>
    `₹${Number(value ?? 0).toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`;

const when = (value) => {
    if (!value) {
        return '';
    }

    const date = new Date(String(value).replace(' ', 'T') + 'Z');

    return Number.isNaN(date.getTime()) ? value : date.toLocaleString('en-IN', { dateStyle: 'medium', timeStyle: 'short' });
};

/* Preview the statement PDF of one label from a report that is still pending review. */
function PreviewBox({ imports }) {
    const [importId, setImportId] = useState(imports[0]?.id ?? '');
    const [query, setQuery] = useState('');
    const [results, setResults] = useState([]);
    const [label, setLabel] = useState(null);

    useEffect(() => {
        const q = query.trim();

        if (q.length < 2) {
            setResults([]);

            return undefined;
        }

        const timer = setTimeout(async () => {
            try {
                const response = await axios.get('/admin/labels/ownership/labels', { params: { q } });
                setResults(response.data.labels ?? []);
            } catch {
                setResults([]);
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [query]);

    return (
        <div className="rounded-lg border border-amber-200 bg-amber-50/60 p-3">
            <div className="text-sm font-semibold text-slate-900">Preview a statement before approval</div>
            <div className="mt-0.5 text-xs text-slate-500">
                See the PDF an owner will get, from a report that is still pending review. Nothing is saved or sent.
            </div>
            <div className="mt-3 flex flex-wrap items-end gap-3">
                <label className="text-xs font-semibold text-slate-600">
                    Report
                    <select
                        value={importId}
                        onChange={(event) => setImportId(event.target.value)}
                        className="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                    >
                        {imports.map((item) => (
                            <option key={item.id} value={item.id}>#{item.id} · {item.month} · {item.name}</option>
                        ))}
                    </select>
                </label>
                <div className="relative text-xs font-semibold text-slate-600">
                    Label
                    <input
                        value={label ? `#${label.id} ${label.name}` : query}
                        onChange={(event) => {
                            setLabel(null);
                            setQuery(event.target.value);
                        }}
                        placeholder="Type label name..."
                        className="mt-1 block w-64 rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-normal"
                    />
                    {!label && results.length > 0 && (
                        <div className="absolute z-20 mt-1 max-h-56 w-64 overflow-y-auto rounded-lg border border-slate-200 bg-white shadow-lg">
                            {results.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    onClick={() => {
                                        setLabel(item);
                                        setResults([]);
                                    }}
                                    className="block w-full px-3 py-2 text-left text-sm font-normal hover:bg-violet-50"
                                >
                                    #{item.id} {item.name}
                                </button>
                            ))}
                        </div>
                    )}
                </div>
                <a
                    href={label && importId ? `/admin/statement-emails/preview?label_id=${label.id}&import_id=${importId}` : undefined}
                    target="_blank"
                    rel="noreferrer"
                    className={[
                        'rounded-lg px-4 py-2 text-sm font-semibold',
                        label ? 'bg-amber-600 text-white hover:bg-amber-700' : 'pointer-events-none bg-slate-200 text-slate-400',
                    ].join(' ')}
                >
                    Open preview PDF
                </a>
            </div>
        </div>
    );
}

export default function StatementEmailPanel() {
    const [open, setOpen] = useState(false);
    const [month, setMonth] = useState('');
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(false);
    const [sending, setSending] = useState(false);
    const [error, setError] = useState('');
    const [log, setLog] = useState([]);

    const load = async (selected = month) => {
        setLoading(true);
        setError('');

        try {
            const response = await axios.get('/admin/statement-emails', { params: selected ? { month: selected } : {} });
            setData(response.data);
            setMonth(response.data.month || '');
        } catch (exception) {
            setError(exception?.response?.data?.message ?? 'Could not load statements.');
        } finally {
            setLoading(false);
        }
    };

    useEffect(() => {
        if (open && !data) {
            load('');
        }
    }, [open]);

    const addLog = (results) => {
        setLog((previous) => {
            const byId = new Map(previous.map((item) => [item.id, item]));
            results.forEach((item) => byId.set(item.id, item));

            return Array.from(byId.values());
        });
    };

    const sendAll = async () => {
        if (!window.confirm(`E-mail ${data.counts.to_send} statement(s) for ${data.month_label} to their owners?`)) {
            return;
        }

        setSending(true);
        setError('');
        setLog([]);

        try {
            let remaining = 1;
            let guard = 0;

            while (remaining > 0 && guard < 200) {
                guard++;
                const response = await axios.post('/admin/statement-emails/send', { month });
                const results = response.data.results ?? [];
                addLog(results);
                remaining = response.data.remaining ?? 0;

                if (!results.some((item) => item.status === 'sent')) {
                    break;
                }
            }
        } catch (exception) {
            setError(exception?.response?.data?.message ?? 'Sending stopped. Statements already sent stay sent; press Send again to continue.');
        } finally {
            setSending(false);
            load(month);
        }
    };

    const resend = async (row) => {
        if (!window.confirm(`Send the ${data.month_label} statement for ${row.title} again to ${row.email}?`)) {
            return;
        }

        setSending(true);
        setError('');

        try {
            const response = await axios.post('/admin/statement-emails/send', { month, statement_ids: [row.id], force: true });
            addLog(response.data.results ?? []);
        } catch (exception) {
            setError(exception?.response?.data?.message ?? 'Could not send.');
        } finally {
            setSending(false);
            load(month);
        }
    };

    const counts = data?.counts;

    return (
        <div className="rounded-2xl border border-violet-200 bg-violet-50/50 p-4 shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div>
                    <div className="text-sm font-semibold text-slate-900">E-mail statements to owners</div>
                    <div className="mt-1 text-xs text-slate-500">
                        Sends each label / artist owner their monthly statement as a PDF. Each statement is sent only once unless you re-send it.
                    </div>
                </div>
                <button
                    type="button"
                    onClick={() => setOpen((value) => !value)}
                    className="rounded-xl border border-violet-300 bg-white px-4 py-2 text-sm font-semibold text-violet-700 hover:bg-violet-50"
                >
                    {open ? 'Close' : 'Open'}
                </button>
            </div>

            {open && (
                <div className="mt-4 space-y-3 rounded-xl border border-violet-100 bg-white p-4">
                    {loading && !data && <div className="text-sm text-slate-500">Loading...</div>}
                    {error && <div className="rounded-lg bg-red-50 px-3 py-2 text-sm font-medium text-red-700">{error}</div>}

                    {data && (data.pending_imports ?? []).length > 0 && (
                        <PreviewBox imports={data.pending_imports} />
                    )}

                    {data && data.months.length === 0 && (
                        <div className="text-sm text-slate-500">
                            No statements yet. Statements are created when a report is approved and published.
                        </div>
                    )}

                    {data && data.months.length > 0 && (
                        <>
                            <div className="flex flex-wrap items-end gap-3">
                                <label className="text-xs font-semibold text-slate-600">
                                    Month
                                    <select
                                        value={month}
                                        onChange={(event) => load(event.target.value)}
                                        className="mt-1 block rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"
                                    >
                                        {data.months.map((m) => (
                                            <option key={m} value={m}>{m}</option>
                                        ))}
                                    </select>
                                </label>

                                {counts && (
                                    <div className="flex flex-wrap gap-2 text-xs">
                                        <span className="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">{counts.total} statements</span>
                                        <span className="rounded-full bg-emerald-100 px-3 py-1 font-semibold text-emerald-800">{counts.sent} sent</span>
                                        <span className="rounded-full bg-violet-100 px-3 py-1 font-semibold text-violet-800">{counts.to_send} to send</span>
                                        {counts.total - counts.with_email > 0 && (
                                            <span className="rounded-full bg-amber-100 px-3 py-1 font-semibold text-amber-800">{counts.total - counts.with_email} without owner e-mail</span>
                                        )}
                                    </div>
                                )}

                                <button
                                    type="button"
                                    disabled={sending || !counts || counts.to_send === 0}
                                    onClick={sendAll}
                                    className="ml-auto rounded-lg bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                                >
                                    {sending ? 'Sending...' : `Send ${counts?.to_send ?? 0} statement(s)`}
                                </button>
                            </div>

                            <div className="max-h-80 overflow-y-auto rounded-lg border border-slate-200">
                                <table className="min-w-full text-xs">
                                    <thead className="sticky top-0 bg-slate-50 text-left text-slate-500">
                                        <tr>
                                            <th className="px-3 py-2">Owner</th>
                                            <th className="px-3 py-2">E-mail</th>
                                            <th className="px-3 py-2 text-right">Net payable</th>
                                            <th className="px-3 py-2">Sent</th>
                                            <th className="px-3 py-2"></th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-slate-100">
                                        {data.rows.map((row) => (
                                            <tr key={row.id}>
                                                <td className="px-3 py-2">
                                                    <div className="font-semibold text-slate-900">{row.title}</div>
                                                    <div className="text-slate-400">{row.owner_type} · statement #{row.id}</div>
                                                </td>
                                                <td className="px-3 py-2 text-slate-700">
                                                    {row.email ?? <span className="font-semibold text-amber-700">no owner</span>}
                                                    {row.account_status && row.account_status !== 'active' && (
                                                        <span className="ml-1 text-amber-700">({row.account_status})</span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-2 text-right font-semibold text-slate-900">{money(row.net)}</td>
                                                <td className="px-3 py-2 text-slate-600">{row.sent_at ? `✓ ${when(row.sent_at)}` : '—'}</td>
                                                <td className="whitespace-nowrap px-3 py-2 text-right">
                                                    <a
                                                        href={`/statements/${row.id}/download`}
                                                        className="mr-3 font-semibold text-violet-700 hover:underline"
                                                    >
                                                        PDF
                                                    </a>
                                                    {row.email && row.sent_at && (
                                                        <button
                                                            type="button"
                                                            disabled={sending}
                                                            onClick={() => resend(row)}
                                                            className="font-semibold text-slate-500 hover:text-violet-700 disabled:opacity-50"
                                                        >
                                                            Re-send
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>

                            {log.length > 0 && (
                                <ul className="max-h-40 space-y-1 overflow-y-auto text-xs">
                                    {log.map((item) => (
                                        <li key={item.id} className={item.status === 'sent' ? 'text-slate-700' : 'font-medium text-amber-700'}>
                                            {item.status === 'sent' ? '✓' : '•'} {item.title}: {item.status === 'sent' ? `sent to ${item.message}` : item.message}
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </>
                    )}
                </div>
            )}
        </div>
    );
}
