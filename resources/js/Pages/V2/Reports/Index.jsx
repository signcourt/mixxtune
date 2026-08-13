import { Head } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import {
    Check,
    ChevronRight,
    Download,
    FileSpreadsheet,
    FileText,
    Trash2,
} from 'lucide-react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const automaticReports = [
    {
        id: 1,
        period: 'June 2026',
        type: 'Full catalogue single report',
        amount: '₹0.00',
        generatedAt: '12 Aug 2026',
        status: 'ready',
    },
    {
        id: 2,
        period: 'May 2026',
        type: 'Full catalogue single report',
        amount: '₹0.00',
        generatedAt: '12 Aug 2026',
        status: 'ready',
    },
    {
        id: 3,
        period: 'April 2026',
        type: 'Full catalogue single report',
        amount: '₹0.00',
        generatedAt: '12 Aug 2026',
        status: 'ready',
    },
];

const defaultColumns = [
    'Reporting Month',
    'Sales Month',
    'Track Artist',
    'Track Title',
    'Album Title',
    'Album Artist',
    'Label',
    'ISRC',
    'UPC',
    'Platform',
    'Country / Region',
    'CMS',
    'Sale Type',
    'Quantity / Streams',
    'Currency',
    'Collected Revenue',
    'Assigned Rate',
    'User Revenue',
];

function ReportTable({
    reports,
    requested = false,
    onDelete,
    deletingId = null,
}) {
    if (reports.length === 0) {
        return (
            <div className="rounded-2xl border border-dashed border-slate-300 bg-white px-6 py-12 text-center">
                <FileSpreadsheet
                    size={34}
                    className="mx-auto text-slate-300"
                />

                <div className="mt-3 text-sm font-bold text-slate-800">
                    {requested
                        ? 'No requested reports yet'
                        : 'No automatic reports available'}
                </div>

                <div className="mt-1 text-sm text-slate-500">
                    {requested
                        ? 'Generate a report below and it will appear here.'
                        : 'Automatic reports will appear here when available.'}
                </div>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
            <div className="overflow-x-auto">
                <table className="min-w-full">
                    <thead className="bg-slate-50">
                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                            <th className="px-5 py-4">
                                Period
                            </th>

                            <th className="px-5 py-4">
                                Report Type
                            </th>

                            <th className="px-5 py-4">
                                Royalty Amount
                            </th>

                            <th className="px-5 py-4">
                                Generation Date
                            </th>

                            <th className="px-5 py-4">
                                Status
                            </th>

                            <th className="px-5 py-4 text-right">
                                Actions
                            </th>
                        </tr>
                    </thead>

                    <tbody className="divide-y divide-slate-100">
                        {reports.map((report) => (
                            <tr key={report.id}>
                                <td className="px-5 py-4 text-sm font-medium text-slate-900">
                                    {report.period}
                                </td>

                                <td className="px-5 py-4 text-sm text-slate-600">
                                    {report.type}
                                </td>

                                <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                    {report.amount}
                                </td>

                                <td className="px-5 py-4 text-sm text-slate-600">
                                    {report.generatedAt}
                                </td>

                                <td className="px-5 py-4">
                                    {report.status === 'completed' ||
                                    report.status === 'ready' ? (
                                        <span
                                            title="Ready"
                                            className="inline-flex h-8 w-8 items-center justify-center rounded-full bg-emerald-50 text-emerald-600"
                                        >
                                            <Check size={16} />
                                        </span>
                                    ) : report.status === 'failed' ? (
                                        <span className="inline-flex rounded-full bg-rose-50 px-3 py-1 text-xs font-bold text-rose-600">
                                            Failed
                                        </span>
                                    ) : (
                                        <span className="inline-flex rounded-full bg-amber-50 px-3 py-1 text-xs font-bold text-amber-700">
                                            Processing
                                        </span>
                                    )}
                                </td>

                                <td className="px-5 py-4">
                                    <div className="flex justify-end gap-2">
                                        {requested ? (
                                            <>
                                                <a
                                                    href={
                                                        report.status ===
                                                        'completed'
                                                            ? `/v2/generated-reports/${report.id}/download`
                                                            : undefined
                                                    }
                                                    title="Download Excel"
                                                    aria-disabled={
                                                        report.status !==
                                                        'completed'
                                                    }
                                                    onClick={(event) => {
                                                        if (
                                                            report.status !==
                                                            'completed'
                                                        ) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                    className={[
                                                        'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200',
                                                        report.status ===
                                                        'completed'
                                                            ? 'text-slate-600 hover:bg-slate-50'
                                                            : 'cursor-not-allowed text-slate-300',
                                                    ].join(' ')}
                                                >
                                                    <Download
                                                        size={16}
                                                    />
                                                </a>

                                                <a
                                                    href={
                                                        report.status ===
                                                        'completed'
                                                            ? `/v2/generated-reports/${report.id}/pdf`
                                                            : undefined
                                                    }
                                                    title="Download PDF"
                                                    aria-disabled={
                                                        report.status !==
                                                        'completed'
                                                    }
                                                    onClick={(event) => {
                                                        if (
                                                            report.status !==
                                                            'completed'
                                                        ) {
                                                            event.preventDefault();
                                                        }
                                                    }}
                                                    className={[
                                                        'inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200',
                                                        report.status ===
                                                        'completed'
                                                            ? 'text-slate-600 hover:bg-slate-50'
                                                            : 'cursor-not-allowed text-slate-300',
                                                    ].join(' ')}
                                                >
                                                    <FileText
                                                        size={16}
                                                    />
                                                </a>

                                                <button
                                                    type="button"
                                                    title="Delete report"
                                                    disabled={
                                                        deletingId ===
                                                        report.id
                                                    }
                                                    onClick={() =>
                                                        onDelete?.(
                                                            report
                                                        )
                                                    }
                                                    className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600 disabled:cursor-not-allowed disabled:opacity-40"
                                                >
                                                    <Trash2
                                                        size={16}
                                                    />
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                {periodToMonth(
                                                    report.period
                                                ) ? (
                                                    <>
                                                        <a
                                                            href={`/v2/reports/automatic/${periodToMonth(
                                                                report.period
                                                            )}/download`}
                                                            title="Download Excel"
                                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                                                        >
                                                            <Download
                                                                size={16}
                                                            />
                                                        </a>

                                                        <a
                                                            href={`/v2/reports/automatic/${periodToMonth(
                                                                report.period
                                                            )}/pdf`}
                                                            title="Download PDF"
                                                            className="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-slate-200 text-slate-600 hover:bg-slate-50"
                                                        >
                                                            <FileText
                                                                size={16}
                                                            />
                                                        </a>
                                                    </>
                                                ) : (
                                                    <>
                                                        <button
                                                            type="button"
                                                            disabled
                                                            title="Invalid reporting period"
                                                            className="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 text-slate-300"
                                                        >
                                                            <Download
                                                                size={16}
                                                            />
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled
                                                            title="Invalid reporting period"
                                                            className="inline-flex h-9 w-9 cursor-not-allowed items-center justify-center rounded-lg border border-slate-200 text-slate-300"
                                                        >
                                                            <FileText
                                                                size={16}
                                                            />
                                                        </button>
                                                    </>
                                                )}
                                            </>
                                        )}
                                    </div>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function periodToMonth(period) {
    if (!period) {
        return null;
    }

    const match = String(period)
        .trim()
        .match(
            /^([A-Za-z]+)\s+(\d{4})$/
        );

    if (!match) {
        return null;
    }

    const months = {
        january: '01',
        february: '02',
        march: '03',
        april: '04',
        may: '05',
        june: '06',
        july: '07',
        august: '08',
        september: '09',
        october: '10',
        november: '11',
        december: '12',
    };

    const month =
        months[
            match[1].toLowerCase()
        ];

    if (!month) {
        return null;
    }

    return `${match[2]}-${month}`;
}

function StepHeader({ step, currentStep, title }) {
    const complete = currentStep > step;
    const active = currentStep === step;

    return (
        <div
            className={[
                'flex min-h-14 items-center gap-3 rounded-xl border px-4',
                active
                    ? 'border-violet-300 bg-violet-50'
                    : 'border-slate-200 bg-white',
            ].join(' ')}
        >
            <span
                className={[
                    'inline-flex h-8 w-8 items-center justify-center rounded-full text-sm font-bold',
                    complete
                        ? 'bg-slate-700 text-white'
                        : active
                          ? 'bg-violet-600 text-white'
                          : 'bg-slate-100 text-slate-500',
                ].join(' ')}
            >
                {complete ? <Check size={16} /> : step}
            </span>

            <span className="text-sm font-semibold text-slate-800">
                {title}
            </span>
        </div>
    );
}

export default function ReportsIndex({ months = [] }) {
    const [tab, setTab] = useState('automatic');
    const [step, setStep] = useState(1);

    const reportingPeriods = useMemo(() => {
        return [...months]
            .filter((value) =>
                /^\d{4}-\d{2}$/.test(String(value))
            )
            .sort()
            .map((value) => {
                const [year, month] = String(value)
                    .split('-')
                    .map(Number);

                const label = new Intl.DateTimeFormat(
                    'en-US',
                    {
                        month: 'long',
                        year: 'numeric',
                        timeZone: 'UTC',
                    }
                ).format(
                    new Date(
                        Date.UTC(
                            year,
                            month - 1,
                            1
                        )
                    )
                );

                return {
                    value: String(value),
                    label,
                };
            });
    }, [months]);

    const latestPeriod =
        reportingPeriods.length > 0
            ? reportingPeriods[
                  reportingPeriods.length - 1
              ].value
            : '';

    const [fromPeriod, setFromPeriod] =
        useState(latestPeriod);

    const [toPeriod, setToPeriod] =
        useState(latestPeriod);

    useEffect(() => {
        if (reportingPeriods.length === 0) {
            setFromPeriod('');
            setToPeriod('');
            return;
        }

        const values = reportingPeriods.map(
            (item) => item.value
        );

        const latest =
            values[values.length - 1];

        if (!values.includes(fromPeriod)) {
            setFromPeriod(latest);
            setToPeriod(latest);
            return;
        }

        if (!values.includes(toPeriod)) {
            setToPeriod(fromPeriod);
        }
    }, [reportingPeriods, fromPeriod, toPeriod]);

    const monthSerial = (value) => {
        const [year, month] = String(value)
            .split('-')
            .map(Number);

        return year * 12 + (month - 1);
    };

    const availableToPeriods = useMemo(() => {
        if (!fromPeriod) {
            return [];
        }

        const fromSerial =
            monthSerial(fromPeriod);

        return reportingPeriods.filter(
            (item) => {
                const difference =
                    monthSerial(item.value)
                    - fromSerial;

                return (
                    difference >= 0
                    && difference <= 2
                );
            }
        );
    }, [reportingPeriods, fromPeriod]);

    const period = useMemo(() => {
        const fromLabel = reportingPeriods.find(
            (item) => item.value === fromPeriod
        )?.label;

        const toLabel = reportingPeriods.find(
            (item) => item.value === toPeriod
        )?.label;

        if (!fromLabel || !toLabel) {
            return 'No reporting data available';
        }

        return fromPeriod === toPeriod
            ? fromLabel
            : `${fromLabel} to ${toLabel}`;
    }, [
        reportingPeriods,
        fromPeriod,
        toPeriod,
    ]);
    const [reportMode, setReportMode] = useState('single');
    const [scope, setScope] = useState('full_catalogue');

    const [selectedColumns, setSelectedColumns] =
        useState(defaultColumns);

    const [requestedReports, setRequestedReports] =
        useState([]);

    const [reportsLoading, setReportsLoading] =
        useState(true);

    const [generating, setGenerating] =
        useState(false);

    const [deletingId, setDeletingId] =
        useState(null);

    const [reportMessage, setReportMessage] =
        useState(null);

    const reports = useMemo(
        () =>
            tab === 'automatic'
                ? automaticReports
                : requestedReports,
        [tab, requestedReports]
    );

    const toggleColumn = (column) => {
        setSelectedColumns((current) =>
            current.includes(column)
                ? current.filter((item) => item !== column)
                : [...current, column]
        );
    };

    const monthLabel = (value) => {
        if (!value || !/^\d{4}-\d{2}$/.test(value)) {
            return value || '—';
        }

        const [year, month] = value
            .split('-')
            .map(Number);

        return new Intl.DateTimeFormat(
            'en-US',
            {
                month: 'long',
                year: 'numeric',
                timeZone: 'UTC',
            }
        ).format(
            new Date(
                Date.UTC(
                    year,
                    month - 1,
                    1
                )
            )
        );
    };

    const formatAmount = (
        amount,
        currency
    ) => {
        const numeric =
            Number(amount || 0);

        if (
            currency &&
            currency !== 'MULTI'
        ) {
            try {
                return new Intl.NumberFormat(
                    'en-IN',
                    {
                        style: 'currency',
                        currency,
                        maximumFractionDigits: 2,
                    }
                ).format(numeric);
            } catch {
                // Fall through to generic formatting.
            }
        }

        const formatted =
            new Intl.NumberFormat(
                'en-IN',
                {
                    maximumFractionDigits: 2,
                }
            ).format(numeric);

        return currency === 'MULTI'
            ? `${formatted} (Multiple currencies)`
            : formatted;
    };

    const mapRequestedReport = (
        report
    ) => {
        const from =
            monthLabel(
                report.from_month
            );

        const to =
            monthLabel(
                report.to_month
            );

        const mode =
            report.report_mode === 'multiple'
                ? 'Multiple reports'
                : 'Single report';

        const scopeLabel =
            report.scope === 'full_catalogue'
                ? 'Full catalogue'
                : report.scope === 'labels'
                  ? 'Selected labels'
                  : report.scope === 'platforms'
                    ? 'Selected platforms'
                    : report.scope;

        const dateValue =
            report.generated_at ||
            report.created_at;

        return {
            ...report,

            period:
                report.from_month ===
                report.to_month
                    ? from
                    : `${from} to ${to}`,

            type:
                `${scopeLabel} ${mode}`,

            amount:
                formatAmount(
                    report.net_amount,
                    report.currency
                ),

            generatedAt:
                dateValue
                    ? new Intl.DateTimeFormat(
                          'en-GB',
                          {
                              day: '2-digit',
                              month: 'short',
                              year: 'numeric',
                          }
                      ).format(
                          new Date(
                              dateValue
                          )
                      )
                    : '—',
        };
    };

    const loadRequestedReports =
        async () => {
            try {
                setReportsLoading(true);

                const response =
                    await window.axios.get(
                        '/v2/generated-reports'
                    );

                setRequestedReports(
                    (
                        response.data.reports ||
                        []
                    ).map(
                        mapRequestedReport
                    )
                );
            } catch (error) {
                console.error(
                    'Unable to load requested reports.',
                    error
                );
            } finally {
                setReportsLoading(false);
            }
        };

    useEffect(() => {
        loadRequestedReports();
    }, []);

    const generateReport =
        async () => {
            if (
                generating ||
                !fromPeriod ||
                !toPeriod ||
                selectedColumns.length === 0
            ) {
                return;
            }

            setGenerating(true);
            setReportMessage(null);

            try {
                const response =
                    await window.axios.post(
                        '/v2/generated-reports',
                        {
                            from_month:
                                fromPeriod,

                            to_month:
                                toPeriod,

                            scope,

                            report_mode:
                                reportMode,

                            selected_columns:
                                selectedColumns,
                        }
                    );

                const generated =
                    mapRequestedReport(
                        response.data.report
                    );

                setRequestedReports(
                    (current) => [
                        generated,
                        ...current.filter(
                            (item) =>
                                item.id !==
                                generated.id
                        ),
                    ]
                );

                setReportMessage({
                    type: 'success',
                    text:
                        'Report generated successfully. It is ready in Requested Reports.',
                });

                setTab('requested');
            } catch (error) {
                const validation =
                    error?.response?.data
                        ?.errors;

                const firstValidation =
                    validation
                        ? Object.values(
                              validation
                          )
                              .flat()
                              .find(Boolean)
                        : null;

                setReportMessage({
                    type: 'error',
                    text:
                        firstValidation ||
                        error?.response?.data
                            ?.message ||
                        'Unable to generate report.',
                });
            } finally {
                setGenerating(false);
            }
        };

    const deleteRequestedReport =
        async (report) => {
            if (
                !window.confirm(
                    'Delete this requested report?'
                )
            ) {
                return;
            }

            setDeletingId(report.id);
            setReportMessage(null);

            try {
                await window.axios.delete(
                    `/v2/generated-reports/${report.id}`
                );

                setRequestedReports(
                    (current) =>
                        current.filter(
                            (item) =>
                                item.id !==
                                report.id
                        )
                );

                setReportMessage({
                    type: 'success',
                    text:
                        'Report deleted successfully.',
                });
            } catch (error) {
                setReportMessage({
                    type: 'error',
                    text:
                        error?.response?.data
                            ?.message ||
                        'Unable to delete report.',
                });
            } finally {
                setDeletingId(null);
            }
        };

    return (
        <PanelLayout>
            <Head title="Financial Reports" />

            <div className="space-y-6">
                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <p className="text-xs font-bold uppercase tracking-[0.18em] text-violet-600">
                            Financial
                        </p>

                        <h1 className="mt-2 text-2xl font-black text-slate-950">
                            Financial Reports
                        </h1>

                        <p className="mt-2 text-sm text-slate-500">
                            View automatic royalty reports or generate custom reports.
                        </p>
                    </div>
                </section>

                <section>
                    <div className="mb-3">
                        <h2 className="text-sm font-black uppercase tracking-wide text-slate-900">
                            Available Reports
                        </h2>
                    </div>

                    <div className="mb-4 flex gap-2 border-b border-slate-200">
                        <button
                            type="button"
                            onClick={() => setTab('automatic')}
                            className={[
                                'border-b-2 px-4 py-3 text-sm font-semibold',
                                tab === 'automatic'
                                    ? 'border-violet-600 text-violet-600'
                                    : 'border-transparent text-slate-500',
                            ].join(' ')}
                        >
                            Automatic Reports
                        </button>

                        <button
                            type="button"
                            onClick={() => setTab('requested')}
                            className={[
                                'border-b-2 px-4 py-3 text-sm font-semibold',
                                tab === 'requested'
                                    ? 'border-violet-600 text-violet-600'
                                    : 'border-transparent text-slate-500',
                            ].join(' ')}
                        >
                            Requested Reports
                        </button>
                    </div>

                    {tab === 'requested' &&
                    reportsLoading ? (
                        <div className="rounded-2xl border border-slate-200 bg-white px-6 py-12 text-center text-sm font-semibold text-slate-500">
                            Loading requested reports...
                        </div>
                    ) : (
                        <ReportTable
                            reports={reports}
                            requested={
                                tab ===
                                'requested'
                            }
                            onDelete={
                                deleteRequestedReport
                            }
                            deletingId={
                                deletingId
                            }
                        />
                    )}

                    {reportMessage && (
                        <div
                            className={[
                                'mt-4 rounded-xl border px-4 py-3 text-sm font-semibold',
                                reportMessage.type ===
                                'success'
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-rose-200 bg-rose-50 text-rose-700',
                            ].join(' ')}
                        >
                            {reportMessage.text}
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="mb-6">
                        <h2 className="text-lg font-black text-slate-950">
                            Generate Your Report
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Create a custom royalty report in four steps.
                        </p>
                    </div>

                    <div className="grid gap-3 lg:grid-cols-4">
                        <StepHeader
                            step={1}
                            currentStep={step}
                            title="Period"
                        />
                        <StepHeader
                            step={2}
                            currentStep={step}
                            title="Report Type"
                        />
                        <StepHeader
                            step={3}
                            currentStep={step}
                            title="Columns"
                        />
                        <StepHeader
                            step={4}
                            currentStep={step}
                            title="Generate"
                        />
                    </div>

                    <div className="mt-8 min-h-[320px]">
                        {step === 1 && (
                            <div className="max-w-4xl">
                                <h3 className="text-base font-bold text-slate-900">
                                    Select reporting period
                                </h3>

                                <p className="mt-1 text-sm text-slate-500">
                                    Select a reporting range of up to 3 months.
                                </p>

                                <div className="mt-5 grid gap-5 md:grid-cols-2">
                                    <div>
                                        <label className="mb-2 block text-sm font-semibold text-slate-700">
                                            From Month
                                        </label>

                                        <select
                                            value={fromPeriod}
                                            onChange={(e) => {
                                                const nextFrom =
                                                    e.target.value;

                                                setFromPeriod(nextFrom);

                                                const difference =
                                                    toPeriod
                                                        ? monthSerial(
                                                              toPeriod
                                                          )
                                                          - monthSerial(
                                                              nextFrom
                                                          )
                                                        : -1;

                                                if (
                                                    difference < 0 ||
                                                    difference > 2
                                                ) {
                                                    setToPeriod(
                                                        nextFrom
                                                    );
                                                }
                                            }}
                                            className="w-full rounded-xl border-slate-300 bg-white"
                                        >
                                            {reportingPeriods.map(
                                                (item) => (
                                                    <option
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </option>
                                                )
                                            )}
                                        </select>
                                    </div>

                                    <div>
                                        <label className="mb-2 block text-sm font-semibold text-slate-700">
                                            To Month
                                        </label>

                                        <select
                                            value={toPeriod}
                                            onChange={(e) =>
                                                setToPeriod(
                                                    e.target.value
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300 bg-white"
                                        >
                                            {availableToPeriods.map(
                                                (item) => (
                                                    <option
                                                        key={item.value}
                                                        value={item.value}
                                                    >
                                                        {item.label}
                                                    </option>
                                                )
                                            )}
                                        </select>
                                    </div>
                                </div>

                                <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                                    <span className="text-sm text-slate-500">
                                        Selected Period:
                                    </span>

                                    <span className="ml-2 text-sm font-bold text-slate-900">
                                        {period}
                                    </span>
                                </div>
                            </div>
                        )}

                        {step === 2 && (
                            <div>
                                <h3 className="text-base font-bold text-slate-900">
                                    Select the report type
                                </h3>

                                <p className="mt-1 text-sm text-slate-500">
                                    Choose the report format and report scope.
                                </p>

                                <div className="mt-6 grid gap-5 lg:grid-cols-2">
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <label className="mb-2 block text-sm font-bold text-slate-800">
                                            Report Type
                                        </label>

                                        <select
                                            value={reportMode}
                                            onChange={(e) =>
                                                setReportMode(
                                                    e.target.value
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300 bg-white"
                                        >
                                            <option value="single">
                                                Single Report
                                            </option>

                                            <option value="multiple">
                                                Multiple Reports
                                            </option>
                                        </select>

                                        <p className="mt-3 text-sm text-slate-500">
                                            {reportMode === 'single'
                                                ? 'Generate one combined report.'
                                                : 'Generate separate reports based on the selected scope.'}
                                        </p>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <label className="mb-2 block text-sm font-bold text-slate-800">
                                            Report Scope
                                        </label>

                                        <select
                                            value={scope}
                                            onChange={(e) =>
                                                setScope(
                                                    e.target.value
                                                )
                                            }
                                            className="w-full rounded-xl border-slate-300 bg-white"
                                        >
                                            <option value="full_catalogue">
                                                Full Catalogue
                                            </option>

                                            <option value="labels">
                                                Selected Labels
                                            </option>

                                            <option value="artists">
                                                Selected Artists
                                            </option>

                                            <option value="releases">
                                                Selected Releases
                                            </option>

                                            <option value="tracks">
                                                Selected Tracks
                                            </option>

                                            <option value="platforms">
                                                Selected Platforms
                                            </option>

                                            <option value="countries">
                                                Selected Countries
                                            </option>
                                        </select>

                                        <p className="mt-3 text-sm text-slate-500">
                                            Select which catalogue data should be included in the report.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        )}

                        {step === 3 && (
                            <div>
                                <div className="flex items-center justify-between gap-4">
                                    <div>
                                        <h3 className="text-base font-bold text-slate-900">
                                            Select report columns
                                        </h3>

                                        <p className="mt-1 text-sm text-slate-500">
                                            Choose which fields should appear in the generated report.
                                        </p>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            setSelectedColumns(
                                                defaultColumns
                                            )
                                        }
                                        className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold"
                                    >
                                        Default Columns
                                    </button>
                                </div>

                                <div className="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                                    {defaultColumns.map((column) => (
                                        <label
                                            key={column}
                                            className="flex items-center gap-3 rounded-xl border border-slate-200 p-4"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={selectedColumns.includes(
                                                    column
                                                )}
                                                onChange={() =>
                                                    toggleColumn(column)
                                                }
                                            />

                                            <span className="text-sm font-medium text-slate-700">
                                                {column}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        )}

                        {step === 4 && (
                            <div>
                                <div>
                                    <h3 className="text-lg font-black text-slate-950">
                                        Review & Generate
                                    </h3>

                                    <p className="mt-1 text-sm text-slate-500">
                                        Review your report settings before generating the report.
                                    </p>
                                </div>

                                <div className="mt-6 grid gap-4 md:grid-cols-2">
                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                            Reporting Period
                                        </div>

                                        <div className="mt-2 text-base font-bold text-slate-900">
                                            {period}
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                            Report Type
                                        </div>

                                        <div className="mt-2 text-base font-bold text-slate-900">
                                            {reportMode === 'single'
                                                ? 'Single Report'
                                                : 'Multiple Reports'}
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                            Report Scope
                                        </div>

                                        <div className="mt-2 text-base font-bold text-slate-900">
                                            {scope === 'full_catalogue'
                                                ? 'Full Catalogue'
                                                : scope === 'labels'
                                                  ? 'Selected Labels'
                                                  : scope === 'platforms'
                                                    ? 'Selected Platforms'
                                                    : scope}
                                        </div>
                                    </div>

                                    <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                        <div className="text-xs font-bold uppercase tracking-wide text-slate-400">
                                            Selected Columns
                                        </div>

                                        <div className="mt-2 text-base font-bold text-slate-900">
                                            {selectedColumns.length} fields
                                        </div>
                                    </div>
                                </div>

                                <div className="mt-4 rounded-2xl border border-slate-200 bg-slate-50 p-5">
                                    <div className="flex items-center justify-between gap-4">
                                        <div>
                                            <div className="text-sm font-bold text-slate-900">
                                                Report Columns
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                These fields will be included in the generated report.
                                            </div>
                                        </div>

                                        <div className="rounded-full bg-white px-3 py-1 text-xs font-bold text-slate-600 shadow-sm ring-1 ring-slate-200">
                                            {selectedColumns.length} selected
                                        </div>
                                    </div>

                                    <div className="mt-4 flex flex-wrap gap-2">
                                        {selectedColumns.map((column) => (
                                            <span
                                                key={column}
                                                className="inline-flex rounded-lg border border-slate-200 bg-white px-3 py-2 text-xs font-semibold text-slate-700"
                                            >
                                                {column}
                                            </span>
                                        ))}
                                    </div>

                                    {selectedColumns.length === 0 && (
                                        <div className="mt-4 rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-700">
                                            Select at least one report column before generating.
                                        </div>
                                    )}
                                </div>

                                <div className="mt-6 flex items-center justify-between gap-4 rounded-2xl border border-violet-100 bg-violet-50 p-5">
                                    <div>
                                        <div className="text-sm font-bold text-slate-900">
                                            Ready to generate
                                        </div>

                                        <div className="mt-1 text-sm text-slate-600">
                                            Your report will use the selected period, scope and columns above.
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        onClick={
                                            generateReport
                                        }
                                        disabled={
                                            generating ||
                                            !fromPeriod ||
                                            !toPeriod ||
                                            selectedColumns.length ===
                                                0
                                        }
                                        className="inline-flex shrink-0 items-center gap-2 rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        {generating
                                            ? 'Generating...'
                                            : 'Generate Report'}

                                        {!generating && (
                                            <ChevronRight
                                                size={16}
                                            />
                                        )}
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>

                    <div className="mt-6 flex justify-between border-t border-slate-200 pt-5">
                        <button
                            type="button"
                            disabled={step === 1}
                            onClick={() =>
                                setStep((current) =>
                                    Math.max(1, current - 1)
                                )
                            }
                            className="rounded-xl border border-slate-200 px-5 py-2.5 text-sm font-semibold disabled:opacity-40"
                        >
                            Back
                        </button>

                        {step < 4 && (
                            <button
                                type="button"
                                onClick={() =>
                                    setStep((current) =>
                                        Math.min(4, current + 1)
                                    )
                                }
                                className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-bold text-white"
                            >
                                Next
                                <ChevronRight size={16} />
                            </button>
                        )}
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
