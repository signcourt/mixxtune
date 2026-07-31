import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RevenueImports({
    imports,
    summary = {},
    filters = {},
}) {
    const [showUpload, setShowUpload] = useState(false);
    const [search, setSearch] = useState(filters.search ?? '');
    const [status, setStatus] = useState(filters.status ?? '');

    const submitSearch = (event) => {
        event.preventDefault();

        router.get(
            '/revenue-imports',
            {
                search: search || undefined,
                status: status || undefined,
            },
            {
                preserveState: true,
                replace: true,
            }
        );
    };

    const deleteImport = (item) => {
        if (!window.confirm(`Delete ${item.original_filename}?`)) {
            return;
        }

        router.delete(`/revenue-imports/${item.public_id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Revenue Imports">
            <Head title="Revenue Imports" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Revenue Imports
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Upload and manage DSP revenue reports.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => setShowUpload(true)}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white"
                    >
                        + Upload Report
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-6">
                    <SummaryCard
                        label="Total Imports"
                        value={summary.total_imports ?? 0}
                    />
                    <SummaryCard
                        label="Processing"
                        value={summary.processing ?? 0}
                    />
                    <SummaryCard
                        label="Completed"
                        value={summary.completed ?? 0}
                    />
                    <SummaryCard
                        label="Failed"
                        value={summary.failed ?? 0}
                    />
                    <SummaryCard
                        label="Total Rows"
                        value={summary.total_rows ?? 0}
                    />
                    <SummaryCard
                        label="Net Revenue"
                        value={summary.net_revenue ?? 0}
                    />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form
                        onSubmit={submitSearch}
                        className="grid gap-3 lg:grid-cols-[1fr_220px_auto]"
                    >
                        <input
                            type="search"
                            value={search}
                            onChange={(event) =>
                                setSearch(event.target.value)
                            }
                            placeholder="Search DSP or filename..."
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        />

                        <select
                            value={status}
                            onChange={(event) =>
                                setStatus(event.target.value)
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">All Statuses</option>
                            <option value="uploaded">Uploaded</option>
                            <option value="processing">Processing</option>
                            <option value="completed">Completed</option>
                            <option value="failed">Failed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>

                        <button
                            type="submit"
                            className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                        >
                            Search
                        </button>
                    </form>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="hidden border-b border-slate-200 bg-slate-50 px-6 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500 lg:grid lg:grid-cols-[minmax(180px,1fr)_minmax(220px,1.4fr)_150px_110px_130px_130px_180px]">
                        <div>DSP</div>
                        <div>File</div>
                        <div>Month</div>
                        <div>Rows</div>
                        <div>Status</div>
                        <div>Revenue</div>
                        <div className="text-right">Actions</div>
                    </div>

                    {(imports?.data ?? []).length === 0 ? (
                        <div className="px-6 py-20 text-center text-slate-500">
                            No revenue imports found.
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {imports.data.map((item) => (
                                <div
                                    key={item.public_id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[minmax(180px,1fr)_minmax(220px,1.4fr)_150px_110px_130px_130px_180px] lg:items-center"
                                >
                                    <div className="font-semibold text-slate-900">
                                        {item.dsp_name}
                                    </div>

                                    <div>
                                        <div className="text-sm font-medium text-slate-800">
                                            {item.original_filename}
                                        </div>
                                        <div className="mt-1 text-xs text-slate-500">
                                            {item.importer?.name ?? 'Unknown'}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        {formatMonth(item.statement_month)}
                                    </div>

                                    <div className="text-sm text-slate-700">
                                        {Number(item.total_rows ?? 0).toLocaleString()}
                                    </div>

                                    <div>
                                        <StatusBadge status={item.status} />
                                    </div>

                                    <div className="text-sm font-semibold text-slate-900">
                                        {item.currency} {Number(item.net_revenue ?? 0).toLocaleString()}
                                    </div>

                                    <div className="flex gap-2 lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() =>
                                                router.get(
                                                    `/revenue-imports/${item.public_id}`
                                                )
                                            }
                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                        >
                                            View
                                        </button>

                                        <button
                                            type="button"
                                            onClick={() => deleteImport(item)}
                                            className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600"
                                        >
                                            Delete
                                        </button>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}
                </div>
            </div>

            {showUpload && (
                <UploadReportModal
                    onClose={() => setShowUpload(false)}
                />
            )}
        </AdminLayout>
    );
}

function SummaryCard({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-2xl font-bold text-slate-900">
                {Number(value ?? 0).toLocaleString()}
            </div>
        </div>
    );
}

function StatusBadge({ status }) {
    const classes = {
        uploaded: 'bg-blue-50 text-blue-700',
        processing: 'bg-amber-50 text-amber-700',
        completed: 'bg-emerald-50 text-emerald-700',
        failed: 'bg-red-50 text-red-700',
        cancelled: 'bg-slate-100 text-slate-700',
    };

    return (
        <span
            className={`rounded-full px-3 py-1 text-xs font-semibold ${
                classes[status] ?? classes.cancelled
            }`}
        >
            {status ?? 'unknown'}
        </span>
    );
}

function UploadReportModal({ onClose }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        dsp_name: '',
        statement_month: '',
        currency: 'INR',
        report_file: null,
    });

    const submit = (event) => {
        event.preventDefault();

        post('/revenue-imports', {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                reset();
                onClose();
            },
        });
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <form
                onSubmit={submit}
                className="w-full max-w-2xl rounded-2xl bg-white p-6 shadow-xl"
            >
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-900">
                            Upload Revenue Report
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            CSV, XLSX and XLS files are supported.
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={onClose}
                        className="text-sm font-semibold text-slate-500"
                    >
                        Close
                    </button>
                </div>

                <div className="mt-6 grid gap-5 md:grid-cols-2">
                    <Field label="DSP Name" error={errors.dsp_name}>
                        <input
                            value={data.dsp_name}
                            onChange={(event) =>
                                setData('dsp_name', event.target.value)
                            }
                            placeholder="Spotify, YouTube, Apple Music..."
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            required
                        />
                    </Field>

                    <Field label="Statement Month" error={errors.statement_month}>
                        <input
                            type="month"
                            value={data.statement_month}
                            onChange={(event) =>
                                setData('statement_month', event.target.value)
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            required
                        />
                    </Field>

                    <Field label="Currency" error={errors.currency}>
                        <select
                            value={data.currency}
                            onChange={(event) =>
                                setData('currency', event.target.value)
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                        >
                            <option value="INR">INR</option>
                            <option value="USD">USD</option>
                            <option value="EUR">EUR</option>
                            <option value="GBP">GBP</option>
                        </select>
                    </Field>

                    <Field label="Report File" error={errors.report_file}>
                        <input
                            type="file"
                            accept=".csv,.txt,.xlsx,.xls"
                            onChange={(event) =>
                                setData(
                                    'report_file',
                                    event.target.files?.[0] ?? null
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 px-4 py-3"
                            required
                        />
                    </Field>
                </div>

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {processing ? 'Uploading...' : 'Upload Report'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function Field({ label, error, children }) {
    return (
        <label className="block">
            <span className="mb-2 block text-sm font-semibold text-slate-700">
                {label}
            </span>

            {children}

            {error && (
                <span className="mt-1 block text-xs text-red-600">
                    {error}
                </span>
            )}
        </label>
    );
}

function formatMonth(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        month: 'short',
        year: 'numeric',
    });
}
