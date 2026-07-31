import { Head, router } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RevenueImportShow({ revenueImport }) {
    const processReport = () => {
        if (!window.confirm('Start processing this revenue report?')) {
            return;
        }

        router.post(
            `/revenue-imports/${revenueImport.public_id}/process`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <AdminLayout title="Revenue Import">
            <Head title={`Revenue Import - ${revenueImport.dsp_name}`} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            {revenueImport.dsp_name}
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            {revenueImport.original_filename}
                        </p>
                    </div>

                    <button
                        type="button"
                        onClick={() => router.get('/revenue-imports')}
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                    >
                        Back to Imports
                    </button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                    <Card label="Status" value={revenueImport.status} />
                    <Card label="Total Rows" value={revenueImport.total_rows} />
                    <Card label="Matched" value={revenueImport.matched_rows} />
                    <Card label="Unmatched" value={revenueImport.unmatched_rows} />
                    <Card label="Errors" value={revenueImport.error_rows} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        Import Details
                    </h2>

                    <div className="mt-6 grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                        <Detail label="DSP" value={revenueImport.dsp_name} />
                        <Detail
                            label="Statement Month"
                            value={formatMonth(revenueImport.statement_month)}
                        />
                        <Detail label="Currency" value={revenueImport.currency} />
                        <Detail
                            label="Gross Revenue"
                            value={`${revenueImport.currency} ${Number(
                                revenueImport.gross_revenue ?? 0
                            ).toLocaleString()}`}
                        />
                        <Detail
                            label="Net Revenue"
                            value={`${revenueImport.currency} ${Number(
                                revenueImport.net_revenue ?? 0
                            ).toLocaleString()}`}
                        />
                        <Detail
                            label="Imported By"
                            value={revenueImport.importer?.name ?? 'Unknown'}
                        />
                    </div>
                </div>

                <div className="rounded-2xl border border-amber-200 bg-amber-50 p-6">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="font-semibold text-amber-900">
                                {revenueImport.status === 'uploaded'
                                    ? 'Ready to Process'
                                    : 'Processing Status'}
                            </h2>

                            <p className="mt-1 text-sm text-amber-800">
                                {revenueImport.status === 'uploaded'
                                    ? 'The report is uploaded and ready for row parsing and matching.'
                                    : `Current status: ${revenueImport.status}`}
                            </p>
                        </div>

                        {revenueImport.status === 'uploaded' && (
                            <button
                                type="button"
                                onClick={processReport}
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                            >
                                Process Report
                            </button>
                        )}
                    </div>
                </div>
            </div>
        </AdminLayout>
    );
}

function Card({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>
            <div className="mt-2 text-2xl font-bold text-slate-900">
                {value ?? 0}
            </div>
        </div>
    );
}

function Detail({ label, value }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>
            <div className="mt-2 text-sm font-semibold text-slate-900">
                {value ?? '—'}
            </div>
        </div>
    );
}

function formatMonth(value) {
    if (!value) {
        return '—';
    }

    return new Date(value).toLocaleDateString('en-GB', {
        month: 'long',
        year: 'numeric',
    });
}
