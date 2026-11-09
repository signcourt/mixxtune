import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function RevenueImportShow({ revenueImport, columnSettings = null }) {
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

                {columnSettings && (
                    <ColumnSharing settings={columnSettings} />
                )}
            </div>
        </AdminLayout>
    );
}

/* W137 MIXXTUNE_REPORT_COLUMN_PREFS: pick which columns labels/artists may see */
function ColumnSharing({ settings }) {
    const all = settings.all_columns || [];
    const [cols, setCols] = useState(() => new Set(settings.default.columns || all));
    const [rate, setRate] = useState(Boolean(settings.default.show_label_rate));
    const [earn, setEarn] = useState(Boolean(settings.default.show_earning));
    const [saving, setSaving] = useState(false);

    const toggle = (c) => {
        const n = new Set(cols);
        n.has(c) ? n.delete(c) : n.add(c);
        setCols(n);
    };

    const saveDefault = () => {
        setSaving(true);
        router.post(
            '/revenue-reports/columns/default',
            {
                columns: cols.size === all.length ? [] : Array.from(cols),
                show_label_rate: rate,
                show_earning: earn,
            },
            { preserveScroll: true, onFinish: () => setSaving(false) },
        );
    };

    if (all.length === 0) {
        return (
            <div className="rounded-2xl border border-slate-200 bg-white p-6 text-sm text-slate-500 shadow-sm">
                Process the report first — then you can choose which columns labels and artists see.
            </div>
        );
    }

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-lg font-semibold text-slate-900">Columns shared with labels / artists</h2>
            <p className="mt-1 text-sm text-slate-500">
                Tick the columns a label or artist may see when they open or download their report. Unticked columns
                stay with you only. Label Rate and Earning are added automatically.
            </p>

            <div className="mt-4 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                {all.map((c) => (
                    <label key={c} className="flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm">
                        <input type="checkbox" checked={cols.has(c)} onChange={() => toggle(c)} />
                        <span className="truncate text-slate-700">{c}</span>
                    </label>
                ))}
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-5">
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={rate} onChange={(e) => setRate(e.target.checked)} />
                    Show Label Rate column
                </label>
                <label className="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" checked={earn} onChange={(e) => setEarn(e.target.checked)} />
                    Show Earning column
                </label>
                <button
                    type="button"
                    onClick={saveDefault}
                    disabled={saving}
                    className="ml-auto rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-60"
                >
                    {saving ? 'Saving…' : 'Save default'}
                </button>
            </div>

            {settings.labels && settings.labels.length > 0 && (
                <p className="mt-4 text-xs text-slate-500">
                    {settings.labels.length} label(s) in this report. Per-label overrides can be set from the label's row —
                    by default everyone uses the template above.
                </p>
            )}
        </div>
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
