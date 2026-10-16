import {
    Head,
    router,
    useForm,
} from '@inertiajs/react';

import {
    Download,
    Eye,
    Pencil,
    RefreshCw,
    Trash2,
    X,
} from 'lucide-react';

import {
    useEffect,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const formatReportingMonth = (value) => {
    if (!value) return '—';

    const [year, month] = value.split('-');

    if (!year || !month) {
        return value;
    }

    const date = new Date(
        Number(year),
        Number(month) - 1,
        1
    );

    return new Intl.DateTimeFormat('en-IN', {
        month: 'short',
        year: 'numeric',
    }).format(date);
};

const formatNumber = (value) =>
    new Intl.NumberFormat('en-IN').format(
        Number(value ?? 0)
    );

const formatUploadedAt = (value) => {
    if (!value) {
        return {
            date: '—',
            time: '',
        };
    }

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return {
            date: value,
            time: '',
        };
    }

    return {
        date: new Intl.DateTimeFormat('en-IN', {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
            timeZone: 'Asia/Kolkata',
        }).format(date),

        time: new Intl.DateTimeFormat('en-IN', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: true,
            timeZone: 'Asia/Kolkata',
        }).format(date),
    };
};

export default function Imports({
    role = 'admin',
    imports = {},
    errors: pageErrors = {},
}) {
    const [editing, setEditing] = useState(null);
    const [editMonth, setEditMonth] = useState('');
    const [actionBusy, setActionBusy] = useState(false);

    /*
     * Revenue reports are processed by a background worker.
     * Poll only while this page contains active imports.
     */
    useEffect(() => {
        const hasActiveImport =
            (imports.data ?? []).some(
                (item) =>
                    item.status === 'queued' ||
                    item.status === 'processing'
            );

        if (!hasActiveImport) {
            return undefined;
        }

        const timer = window.setInterval(
            () => {
                router.reload({
                    only: ['imports'],
                    preserveScroll: true,
                    preserveState: true,
                });
            },
            5000
        );

        return () =>
            window.clearInterval(timer);
    }, [imports.data]);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
    } = useForm({
        reporting_month: '',
        report_file: null,
    });

    const submit = (event) => {
        event.preventDefault();

        post('/v2/admin/reports/imports', {
            forceFormData: true,

            onSuccess: () => {
                reset();
            },
        });
    };

    const openEdit = (item) => {
        setEditing(item);
        setEditMonth(item.reporting_month || '');
    };

    const closeEdit = () => {
        if (actionBusy) {
            return;
        }

        setEditing(null);
        setEditMonth('');
    };

    const saveEdit = (event) => {
        event.preventDefault();

        if (!editing || !editMonth || actionBusy) {
            return;
        }

        setActionBusy(true);

        router.patch(
            `/v2/admin/reports/imports/${editing.id}`,
            {
                reporting_month: editMonth,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setEditing(null);
                    setEditMonth('');
                },

                onFinish: () => {
                    setActionBusy(false);
                },
            }
        );
    };

    const deleteImport = (item) => {
        if (actionBusy) {
            return;
        }

        const confirmed = window.confirm(
            `Delete "${item.original_filename}"?\n\n` +
            `This action is allowed only when the report has no royalty allocations.`
        );

        if (!confirmed) {
            return;
        }

        setActionBusy(true);

        router.delete(
            `/v2/admin/reports/imports/${item.id}`,
            {
                preserveScroll: true,

                onFinish: () => {
                    setActionBusy(false);
                },
            }
        );
    };

    const reDetectUnmapped = (item) => {
        const count =
            Number(item.unmapped_rows_count || 0);

        if (actionBusy) {
            return;
        }

        const confirmed = window.confirm(
            `Re-detect ${count.toLocaleString()} currently unmapped row(s) in ${item.original_filename}?\n\n` +
            'Latest catalogue ISRC ownership will be checked again.\n' +
            'Already mapped rows will NOT be changed.\n' +
            'Wallets will NOT be credited and the report will NOT be published.'
        );

        if (!confirmed) {
            return;
        }

        setActionBusy(true);

        router.post(
            `/v2/admin/reports/imports/${item.id}/re-detect-unmapped`,
            {},
            {
                preserveScroll: true,
                preserveState: true,

                onFinish: () => {
                    setActionBusy(false);
                },
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Report Imports"
            subtitle="Upload DSP CSV reports"
        >
            <Head title="Report Imports" />

            <div className="space-y-6">
                {pageErrors?.delete && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {pageErrors.delete}
                    </div>
                )}

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <h2 className="text-lg font-semibold text-slate-900">
                        Upload Report CSV
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Maximum file size: 500 MB.
                    </p>

                    <div className="mt-5">
                        <label className="mb-2 block text-sm font-semibold text-slate-700">
                            Reporting Month
                        </label>

                        <input
                            type="month"
                            value={data.reporting_month}
                            onChange={(event) =>
                                setData(
                                    'reporting_month',
                                    event.target.value
                                )
                            }
                            required
                            className="block w-full rounded-xl border border-slate-300 p-3 text-sm"
                        />

                        <p className="mt-2 text-xs text-slate-500">
                            Revenue from this CSV will appear in dashboards,
                            analytics and royalties under this reporting month.
                            Sales Month inside the CSV remains unchanged.
                        </p>

                        {errors.reporting_month && (
                            <div className="mt-2 text-sm text-red-600">
                                {errors.reporting_month}
                            </div>
                        )}
                    </div>

                    <input
                        type="file"
                        accept=".csv,.txt"
                        onChange={(event) =>
                            setData(
                                'report_file',
                                event.target.files?.[0] ?? null
                            )
                        }
                        className="mt-5 block w-full rounded-xl border border-slate-300 p-3 text-sm"
                    />

                    {errors.report_file && (
                        <div className="mt-2 text-sm text-red-600">
                            {errors.report_file}
                        </div>
                    )}

                    <button
                        type="submit"
                        disabled={
                            processing ||
                            !data.reporting_month ||
                            !data.report_file
                        }
                        className="mt-5 rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing
                            ? 'Uploading...'
                            : 'Upload & Import'}
                    </button>
                </form>

                <section className="overflow-x-auto rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'File',
                                    'Reporting Month',
                                    'Status',
                                    'Total',
                                    'Imported',
                                    'Duplicate',
                                    'Failed',
                                    'Uploaded',
                                    'Actions',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="whitespace-nowrap px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(imports.data ?? []).map((item) => (
                                <tr
                                    key={item.id}
                                    className="hover:bg-slate-50/70"
                                >
                                    <Cell>
                                        {item.original_filename}
                                    </Cell>

                                    <Cell>
                                        {formatReportingMonth(item.reporting_month)}
                                    </Cell>

                                    <Cell>
                                        {item.status}
                                    </Cell>

                                    <Cell>
                                        {formatNumber(item.total_rows)}
                                    </Cell>

                                    <Cell>
                                        {formatNumber(item.imported_rows)}
                                    </Cell>

                                    <Cell>
                                        {formatNumber(item.duplicate_rows)}
                                    </Cell>

                                    <Cell>
                                        {formatNumber(item.failed_rows)}
                                    </Cell>

                                    <Cell>
                                        {(() => {
                                            const uploaded =
                                                formatUploadedAt(
                                                    item.created_at
                                                );

                                            return (
                                                <div className="leading-tight">
                                                    <div className="font-medium text-slate-700">
                                                        {uploaded.date}
                                                    </div>

                                                    {uploaded.time && (
                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {uploaded.time} IST
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })()}
                                    </Cell>

                                    <Cell>
                                        <div className="flex items-center gap-2">
                                            <a
                                                href={`/v2/admin/reports/imports/${item.id}/view`}
                                                title="View report data"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                                            >
                                                <Eye size={16} />
                                            </a>

                                            {Number(item.unmapped_rows_count || 0) > 0 && (
                                                <a
                                                    href={`/super-admin/unmapped-revenue?report_import_id=${item.id}`}
                                                    title={`${Number(item.unmapped_rows_count || 0).toLocaleString()} unmapped ISRC rows`}
                                                    className="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-amber-200 bg-amber-50 px-3 text-xs font-semibold text-amber-700 transition hover:border-amber-300 hover:bg-amber-100"
                                                >
                                                    <span>Unmapped</span>
                                                    <span className="rounded-full bg-amber-200/70 px-1.5 py-0.5 text-[10px] font-bold text-amber-900">
                                                        {Number(item.unmapped_rows_count || 0).toLocaleString()}
                                                    </span>
                                                </a>
                                            )}

                                            {Number(item.unmapped_rows_count || 0) > 0 &&
                                                item.financial_status === 'review_pending' && (
                                                    <button
                                                        type="button"
                                                        onClick={() =>
                                                            reDetectUnmapped(item)
                                                        }
                                                        disabled={actionBusy}
                                                        title="Refresh / Re-detect unmapped ISRCs"
                                                        className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-cyan-200 bg-cyan-50 text-cyan-700 transition hover:border-cyan-300 hover:bg-cyan-100 disabled:cursor-not-allowed disabled:opacity-50"
                                                    >
                                                        <RefreshCw size={16} />
                                                    </button>
                                                )}

                                            <a
                                                href={`/v2/admin/reports/imports/${item.id}/download`}
                                                title="Download original report"
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-emerald-300 hover:bg-emerald-50 hover:text-emerald-700"
                                            >
                                                <Download size={16} />
                                            </a>

                                            <button
                                                type="button"
                                                title="Edit reporting month"
                                                onClick={() => openEdit(item)}
                                                disabled={actionBusy}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 bg-white text-slate-600 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700 disabled:opacity-50"
                                            >
                                                <Pencil size={16} />
                                            </button>

                                            <button
                                                type="button"
                                                title="Delete report"
                                                onClick={() =>
                                                    deleteImport(item)
                                                }
                                                disabled={actionBusy}
                                                className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-red-200 bg-white text-red-600 transition hover:bg-red-50 disabled:opacity-50"
                                            >
                                                <Trash2 size={16} />
                                            </button>
                                        </div>
                                    </Cell>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </section>
            </div>

            {editing && (
                <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                    <div className="w-full max-w-md rounded-2xl bg-white shadow-2xl">
                        <div className="flex items-center justify-between border-b border-slate-100 px-6 py-4">
                            <div>
                                <h3 className="font-semibold text-slate-900">
                                    Edit Report
                                </h3>

                                <p className="mt-1 text-xs text-slate-500">
                                    {editing.original_filename}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeEdit}
                                className="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-700"
                            >
                                <X size={18} />
                            </button>
                        </div>

                        <form
                            onSubmit={saveEdit}
                            className="p-6"
                        >
                            <label className="mb-2 block text-sm font-semibold text-slate-700">
                                Reporting Month
                            </label>

                            <input
                                type="month"
                                value={editMonth}
                                onChange={(event) =>
                                    setEditMonth(event.target.value)
                                }
                                required
                                className="block w-full rounded-xl border border-slate-300 p-3 text-sm"
                            />

                            <p className="mt-2 text-xs text-slate-500">
                                This updates the reporting month for this
                                import and its report rows.
                            </p>

                            <div className="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    onClick={closeEdit}
                                    disabled={actionBusy}
                                    className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="submit"
                                    disabled={
                                        actionBusy ||
                                        !editMonth
                                    }
                                    className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white disabled:opacity-50"
                                >
                                    {actionBusy
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
