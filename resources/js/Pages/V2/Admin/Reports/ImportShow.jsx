import {
    Head,
    router,
} from '@inertiajs/react';

import {
    ArrowLeft,
    Download,
    Search,
} from 'lucide-react';

import {
    useEffect,
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const numberFormatter = new Intl.NumberFormat(
    'en-IN',
    {
        maximumFractionDigits: 4,
    }
);

function formatNumber(value) {
    const number = Number(value ?? 0);

    return Number.isFinite(number)
        ? numberFormatter.format(number)
        : '0';
}

function formatRate(value) {
    const number = Number(value);

    if (!Number.isFinite(number)) {
        return '—';
    }

    return `${formatNumber(number * 100)}%`;
}

function statusClass(status) {
    const value = String(status || '').toLowerCase();

    if (value === 'mapped') {
        return 'bg-emerald-50 text-emerald-700 ring-emerald-200';
    }

    if (
        value === 'unmapped' ||
        value === 'missing'
    ) {
        return 'bg-amber-50 text-amber-700 ring-amber-200';
    }

    if (
        value === 'conflict' ||
        value === 'failed'
    ) {
        return 'bg-red-50 text-red-700 ring-red-200';
    }

    return 'bg-slate-100 text-slate-600 ring-slate-200';
}

export default function ImportShow({
    role = 'admin',
    reportImport = {},
    rows = {},
    summary = {},
    mappingCounts = [],
    financialReview = {},
    missingRateLabels = [],
    filters = {},
}) {
    const [search, setSearch] = useState(
        filters.search || ''
    );

    const [mappingStatus, setMappingStatus] =
        useState(filters.mapping_status || '');

    const [publishAt, setPublishAt] = useState(
        financialReview.publish_at
            ? String(financialReview.publish_at).slice(0, 10)
            : ''
    );

    const [reviewNote, setReviewNote] = useState(
        financialReview.review_note || ''
    );

    const [approvalProcessing, setApprovalProcessing] =
        useState(false);

    const [approvalError, setApprovalError] =
        useState('');

    useEffect(() => {
        setSearch(filters.search || '');
        setMappingStatus(
            filters.mapping_status || ''
        );
    }, [
        filters.search,
        filters.mapping_status,
    ]);

    const applyFilters = (event) => {
        event.preventDefault();

        router.get(
            `/v2/admin/reports/imports/${reportImport.id}/view`,
            {
                search:
                    search.trim() || undefined,
                mapping_status:
                    mappingStatus || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const clearFilters = () => {
        setSearch('');
        setMappingStatus('');

        router.get(
            `/v2/admin/reports/imports/${reportImport.id}/view`,
            {},
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const financialStatus = String(
        financialReview.financial_status ||
        'review_pending'
    );

    const alreadyApproved = [
        'approved_scheduled',
        'published',
    ].includes(financialStatus);

    const canApprove =
        role === 'super_admin' &&
        Boolean(financialReview.ready) &&
        !alreadyApproved;

    const [revenueRates, setRevenueRates] = useState({});
    const [rateSaving, setRateSaving] = useState(false);
    const [rateError, setRateError] = useState('');

    const saveRevenueRates = (event) => {
        event.preventDefault();

        const rates = Object.fromEntries(
            Object.entries(revenueRates).filter(
                ([, value]) =>
                    value !== ''
                    && value !== null
                    && value !== undefined
            )
        );

        if (Object.keys(rates).length === 0) {
            setRateError(
                'Enter at least one Revenue Rate.'
            );
            return;
        }

        setRateSaving(true);
        setRateError('');

        router.post(
            `/v2/admin/reports/imports/${reportImport.id}/missing-revenue-rates`,
            { rates },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setRevenueRates({});
                    setRateError('');
                },

                onError: (errors) => {
                    setRateError(
                        errors.rates ||
                        'Unable to save Revenue Rates.'
                    );
                },

                onFinish: () => {
                    setRateSaving(false);
                },
            }
        );
    };

    const submitApproval = (event) => {
        event.preventDefault();

        if (!canApprove || approvalProcessing) {
            return;
        }

        if (!publishAt) {
            setApprovalError(
                'Select a publish date before approval.'
            );
            return;
        }

        setApprovalError('');
        setApprovalProcessing(true);

        router.post(
            `/v2/admin/reports/imports/${reportImport.id}/approve-and-schedule`,
            {
                publish_at: publishAt,
                review_note:
                    reviewNote.trim() || null,
            },
            {
                preserveScroll: true,

                onError: (errors) => {
                    setApprovalError(
                        errors.approval ||
                        errors.publish_at ||
                        errors.review_note ||
                        'Approval could not be completed.'
                    );
                },

                onFinish: () => {
                    setApprovalProcessing(false);
                },
            }
        );
    };

    const mappingOptions = [
        ...new Set(
            (mappingCounts ?? [])
                .map((item) => item.mapping_status)
                .filter(Boolean)
        ),
    ];

    return (
        <PanelLayout
            role={role}
            title="Report Data"
            subtitle={
                reportImport.original_filename ||
                'Revenue report'
            }
        >
            <Head title="Report Data" />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <a
                        href="/v2/admin/reports/imports"
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        <ArrowLeft size={16} />
                        Back to Imports
                    </a>

                    <a
                        href={`/v2/admin/reports/imports/${reportImport.id}/download`}
                        className="inline-flex items-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"
                    >
                        <Download size={16} />
                        Download Original
                    </a>
                </div>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="text-lg font-semibold text-slate-900">
                                {reportImport.original_filename}
                            </div>

                            <div className="mt-1 text-sm text-slate-500">
                                Reporting Month:{' '}
                                {reportImport.reporting_month || '—'}
                                {' · '}
                                Status:{' '}
                                {reportImport.status || '—'}
                            </div>
                        </div>

                        <div className="text-sm text-slate-500">
                            Import #{reportImport.id}
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
                        <SummaryCard
                            label="Stored Rows"
                            value={formatNumber(
                                summary.row_count
                            )}
                        />

                        <SummaryCard
                            label="Collected Revenue"
                            value={formatNumber(
                                summary.collected_revenue
                            )}
                        />

                        <SummaryCard
                            label="Earnings"
                            value={formatNumber(
                                summary.earnings
                            )}
                        />

                        <SummaryCard
                            label="Streams"
                            value={formatNumber(
                                summary.streams
                            )}
                        />

                        <SummaryCard
                            label="Sale Units"
                            value={formatNumber(
                                summary.sale_units
                            )}
                        />
                    </div>

                    <div className="mt-5 flex flex-wrap gap-2">
                        {(mappingCounts ?? []).map(
                            (item, index) => (
                                <span
                                    key={
                                        item.mapping_status ??
                                        `blank-${index}`
                                    }
                                    className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold ring-1 ring-inset ${statusClass(
                                        item.mapping_status
                                    )}`}
                                >
                                    {item.mapping_status ||
                                        'No Status'}
                                    :{' '}
                                    {formatNumber(
                                        item.total
                                    )}
                                </span>
                            )
                        )}

                        {(mappingCounts ?? []).length === 0 && (
                            <span className="text-sm text-slate-500">
                                No stored report rows for this import.
                            </span>
                        )}
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <div className="text-lg font-semibold text-slate-900">
                                Final Financial Review
                            </div>

                            <div className="mt-1 max-w-3xl text-sm text-slate-500">
                                Verify mapping, commercial rates and calculated
                                earnings before scheduling this revenue batch.
                                Approval does not publish revenue or credit
                                wallets immediately.
                            </div>
                        </div>

                        <span
                            className={[
                                'inline-flex rounded-full px-3 py-1.5 text-xs font-semibold ring-1 ring-inset',
                                financialStatus === 'published'
                                    ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                    : alreadyApproved
                                      ? 'bg-blue-50 text-blue-700 ring-blue-200'
                                      : financialReview.ready
                                        ? 'bg-emerald-50 text-emerald-700 ring-emerald-200'
                                        : 'bg-amber-50 text-amber-700 ring-amber-200',
                            ].join(' ')}
                        >
                            {financialStatus === 'published'
                                ? 'Published'
                                : alreadyApproved
                                  ? 'Approved & Scheduled'
                                  : financialReview.ready
                                    ? 'Ready for Approval'
                                    : 'Review Blocked'}
                        </span>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <SummaryCard
                            label="Collected Revenue"
                            value={formatNumber(
                                financialReview.collected_revenue
                            )}
                        />

                        <SummaryCard
                            label="Calculated Earnings"
                            value={formatNumber(
                                financialReview.earnings
                            )}
                        />

                        <SummaryCard
                            label="Mapped Rows"
                            value={formatNumber(
                                financialReview.mapped_rows
                            )}
                        />

                        <SummaryCard
                            label="Unmapped Rows"
                            value={formatNumber(
                                financialReview.unmapped_rows
                            )}
                        />
                    </div>

                    <div className="mt-4 grid gap-3 lg:grid-cols-3">
                        <ReviewCheck
                            title="ISRC / Ownership Mapping"
                            ok={
                                Number(
                                    financialReview.unmapped_rows || 0
                                ) === 0
                            }
                            detail={
                                Number(
                                    financialReview.unmapped_rows || 0
                                ) === 0
                                    ? 'All stored rows are mapped.'
                                    : `${formatNumber(
                                          financialReview.unmapped_rows
                                      )} rows unresolved · ${formatNumber(
                                          financialReview.unmapped_revenue
                                      )} Collected Revenue`
                            }
                        />

                        <ReviewCheck
                            title="Commercial Revenue Rate"
                            ok={
                                Number(
                                    financialReview.missing_rate_rows || 0
                                ) === 0
                            }
                            detail={
                                Number(
                                    financialReview.missing_rate_rows || 0
                                ) === 0
                                    ? 'No positive-revenue mapped rows have a missing rate.'
                                    : `${formatNumber(
                                          financialReview.missing_rate_rows
                                      )} rows blocked · ${formatNumber(
                                          financialReview.missing_rate_revenue
                                      )} Collected Revenue`
                            }
                        />

                        <ReviewCheck
                            title="Earnings Calculation"
                            ok={
                                Number(
                                    financialReview.invalid_earnings_rows || 0
                                ) === 0
                            }
                            detail={
                                Number(
                                    financialReview.invalid_earnings_rows || 0
                                ) === 0
                                    ? 'Mapped positive-revenue rows have calculated earnings.'
                                    : `${formatNumber(
                                          financialReview.invalid_earnings_rows
                                      )} rows are missing calculated earnings.`
                            }
                        />
                    </div>

                    {role === 'super_admin' &&
                        !alreadyApproved &&
                        (missingRateLabels ?? []).length > 0 && (
                            <div className="mt-6 overflow-hidden rounded-xl border border-amber-200">
                                <div className="flex flex-wrap items-start justify-between gap-3 bg-amber-50 px-5 py-4">
                                    <div>
                                        <div className="font-semibold text-amber-950">
                                            Missing Revenue Rates
                                        </div>

                                        <div className="mt-1 text-sm text-amber-800">
                                            These labels have mapped positive
                                            Collected Revenue but no valid
                                            commercial revenue rate. Set the
                                            label rate before approving this
                                            revenue batch.
                                        </div>
                                    </div>

                                    <div className="rounded-full bg-white px-3 py-1 text-xs font-semibold text-amber-800 ring-1 ring-amber-200">
                                        {formatNumber(
                                            missingRateLabels.length
                                        )}{' '}
                                        labels blocked
                                    </div>
                                </div>

                                <form onSubmit={saveRevenueRates}>
                                    <div className="overflow-x-auto">
                                        <table className="min-w-[1050px] w-full">
                                        <thead className="bg-slate-50">
                                            <tr>
                                                {[
                                                    'Label',
                                                    'Account',
                                                    'Current Rate',
                                                    'Affected Rows',
                                                    'Collected Revenue',
                                                    'Revenue Rate (%)',
                                                    'Action',
                                                ].map(
                                                    (heading) => (
                                                        <th
                                                            key={
                                                                heading
                                                            }
                                                            className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500"
                                                        >
                                                            {
                                                                heading
                                                            }
                                                        </th>
                                                    )
                                                )}
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-slate-100 bg-white">
                                            {missingRateLabels.map(
                                                (item) => (
                                                    <tr
                                                        key={
                                                            item.label_id
                                                        }
                                                    >
                                                        <td className="px-4 py-3">
                                                            <div className="font-semibold text-slate-900">
                                                                {item.label_name ||
                                                                    '—'}
                                                            </div>

                                                            <div className="mt-0.5 text-xs text-slate-500">
                                                                Label #
                                                                {
                                                                    item.label_id
                                                                }
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <div className="text-sm text-slate-700">
                                                                {item.user_name ||
                                                                    '—'}
                                                            </div>

                                                            <div className="mt-0.5 text-xs text-slate-500">
                                                                {item.email ||
                                                                    '—'}
                                                            </div>
                                                        </td>

                                                        <td className="px-4 py-3">
                                                            <span className="inline-flex rounded-full bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 ring-1 ring-red-200">
                                                                {item.current_rate ===
                                                                null
                                                                    ? 'Missing'
                                                                    : `${formatNumber(
                                                                          item.current_rate
                                                                      )}%`}
                                                            </span>
                                                        </td>

                                                        <td className="whitespace-nowrap px-4 py-3 text-sm text-slate-700">
                                                            {formatNumber(
                                                                item.affected_rows
                                                            )}
                                                        </td>

                                                        <td className="whitespace-nowrap px-4 py-3 text-sm font-semibold text-slate-900">
                                                            {formatNumber(
                                                                item.collected_revenue
                                                            )}
                                                        </td>

                                                        <td className="whitespace-nowrap px-4 py-3">
                                                            <input
                                                                type="number"
                                                                min="0.0001"
                                                                max="100"
                                                                step="0.0001"
                                                                value={
                                                                    revenueRates[
                                                                        item.label_id
                                                                    ] ?? ''
                                                                }
                                                                onChange={(event) =>
                                                                    setRevenueRates(
                                                                        (current) => ({
                                                                            ...current,
                                                                            [item.label_id]:
                                                                                event.target.value,
                                                                        })
                                                                    )
                                                                }
                                                                placeholder="e.g. 80"
                                                                className="w-32 rounded-lg border border-slate-300 px-3 py-2 text-sm"
                                                            />
                                                        </td>

                                                        <td className="whitespace-nowrap px-4 py-3">
                                                            {item.user_id ? (
                                                                <a
                                                                    href={`/v2/admin/users/${item.user_id}/edit`}
                                                                    target="_blank"
                                                                    rel="noreferrer"
                                                                    className="inline-flex rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white hover:bg-violet-700"
                                                                >
                                                                    Edit Rate
                                                                </a>
                                                            ) : (
                                                                <span className="text-xs font-semibold text-red-600">
                                                                    No account
                                                                </span>
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            )}
                                        </tbody>
                                    </table>
                                </div>


                                    <div className="flex flex-wrap items-center justify-between gap-3 border-t border-amber-200 bg-amber-50/60 px-5 py-4">
                                        <div>
                                            <div className="text-xs text-amber-800">
                                                Enter only confirmed commercial rates.
                                                Blank labels remain blocked.
                                                Saving recalculates review-pending rows automatically.
                                            </div>

                                            {rateError && (
                                                <div className="mt-2 text-xs font-semibold text-red-600">
                                                    {rateError}
                                                </div>
                                            )}
                                        </div>

                                        <button
                                            type="submit"
                                            disabled={rateSaving}
                                            className={[
                                                'rounded-lg px-4 py-2 text-sm font-semibold text-white',
                                                rateSaving
                                                    ? 'cursor-not-allowed bg-slate-300'
                                                    : 'bg-emerald-600 hover:bg-emerald-700',
                                            ].join(' ')}
                                        >
                                            {rateSaving
                                                ? 'Saving...'
                                                : 'Save Entered Rates'}
                                        </button>
                                    </div>
                                </form>
                            </div>
                        )}

                    {!alreadyApproved &&
                        role === 'super_admin' && (
                            <form
                                onSubmit={submitApproval}
                                className="mt-6 border-t border-slate-200 pt-6"
                            >
                                <div className="grid gap-5 lg:grid-cols-[280px_minmax(0,1fr)]">
                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700">
                                            Publish Date
                                        </label>

                                        <input
                                            type="date"
                                            value={publishAt}
                                            onChange={(event) =>
                                                setPublishAt(
                                                    event.target.value
                                                )
                                            }
                                            min={
                                                new Date()
                                                    .toISOString()
                                                    .slice(0, 10)
                                            }
                                            className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                        />

                                        <div className="mt-2 text-xs text-slate-500">
                                            Users must not receive this batch
                                            before this date.
                                        </div>
                                    </div>

                                    <div>
                                        <label className="block text-sm font-semibold text-slate-700">
                                            Review Note
                                        </label>

                                        <textarea
                                            value={reviewNote}
                                            onChange={(event) =>
                                                setReviewNote(
                                                    event.target.value
                                                )
                                            }
                                            rows={3}
                                            maxLength={2000}
                                            placeholder="Optional internal review note..."
                                            className="mt-2 w-full rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                                        />
                                    </div>
                                </div>

                                {!financialReview.ready && (
                                    <div className="mt-5 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                                        Approval is blocked until all revenue
                                        rows are mapped, all positive mapped
                                        revenue has a valid commercial rate,
                                        and earnings calculation is complete.
                                    </div>
                                )}

                                {approvalError && (
                                    <div className="mt-4 rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                                        {approvalError}
                                    </div>
                                )}

                                <div className="mt-5 flex flex-wrap items-center justify-between gap-3">
                                    <div className="text-xs text-slate-500">
                                        Approving freezes the current financial
                                        review totals and schedules the batch.
                                        It does not credit wallets now.
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={
                                            !canApprove ||
                                            approvalProcessing
                                        }
                                        className={[
                                            'rounded-xl px-5 py-2.5 text-sm font-semibold text-white',
                                            canApprove &&
                                            !approvalProcessing
                                                ? 'bg-emerald-600 hover:bg-emerald-700'
                                                : 'cursor-not-allowed bg-slate-300',
                                        ].join(' ')}
                                    >
                                        {approvalProcessing
                                            ? 'Approving...'
                                            : 'Approve & Schedule'}
                                    </button>
                                </div>
                            </form>
                        )}

                    {alreadyApproved && (
                        <div className="mt-6 rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <div className="text-sm font-semibold text-blue-900">
                                Financial review approved
                            </div>

                            <div className="mt-1 text-sm text-blue-700">
                                Scheduled publish:{' '}
                                {financialReview.publish_at || '—'}
                            </div>

                            {financialReview.review_note && (
                                <div className="mt-2 text-sm text-blue-700">
                                    Review note:{' '}
                                    {financialReview.review_note}
                                </div>
                            )}
                        </div>
                    )}
                </section>

                <form
                    onSubmit={applyFilters}
                    className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm"
                >
                    <div className="grid gap-3 lg:grid-cols-[minmax(260px,1fr)_220px_auto]">
                        <div className="relative">
                            <Search
                                size={17}
                                className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400"
                            />

                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(
                                        event.target.value
                                    )
                                }
                                placeholder="Search track, artist, label, ISRC, UPC, platform..."
                                className="w-full rounded-xl border border-slate-300 py-2.5 pl-10 pr-3 text-sm"
                            />
                        </div>

                        <select
                            value={mappingStatus}
                            onChange={(event) =>
                                setMappingStatus(
                                    event.target.value
                                )
                            }
                            className="rounded-xl border border-slate-300 px-3 py-2.5 text-sm"
                        >
                            <option value="">
                                All Mapping Status
                            </option>

                            {mappingOptions.map(
                                (status) => (
                                    <option
                                        key={status}
                                        value={status}
                                    >
                                        {status}
                                    </option>
                                )
                            )}
                        </select>

                        <div className="flex gap-2">
                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-5 py-2.5 text-sm font-semibold text-white"
                            >
                                Apply
                            </button>

                            <button
                                type="button"
                                onClick={clearFilters}
                                className="rounded-xl border border-slate-300 px-4 py-2.5 text-sm font-semibold text-slate-700"
                            >
                                Clear
                            </button>
                        </div>
                    </div>
                </form>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <div className="font-semibold text-slate-900">
                            Report Rows
                        </div>

                        <div className="mt-1 text-xs text-slate-500">
                            Showing server-side paginated data.
                            50 rows per page.
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-[1900px] w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Track',
                                        'Artist',
                                        'Label',
                                        'ISRC',
                                        'UPC',
                                        'Platform',
                                        'Country',
                                        'Sales Month',
                                        'Streams',
                                        'Units',
                                        'Currency',
                                        'Collected Revenue',
                                        'Applied Rate',
                                        'Earnings',
                                        'Mapping',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="whitespace-nowrap px-4 py-3 text-left text-xs font-semibold uppercase text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {(rows.data ?? []).map(
                                    (row) => (
                                        <tr
                                            key={row.id}
                                            className="hover:bg-slate-50/70"
                                        >
                                            <Cell>
                                                {row.track_title ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.track_artist ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.label_name ||
                                                    '—'}
                                            </Cell>

                                            <Cell mono>
                                                {row.isrc || '—'}
                                            </Cell>

                                            <Cell mono>
                                                {row.upc || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.platform ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.country_code ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.sale_month ||
                                                    '—'}
                                            </Cell>

                                            <Cell numeric>
                                                {formatNumber(
                                                    row.streams
                                                )}
                                            </Cell>

                                            <Cell numeric>
                                                {formatNumber(
                                                    row.sale_units
                                                )}
                                            </Cell>

                                            <Cell>
                                                {row.currency ||
                                                    '—'}
                                            </Cell>

                                            <Cell numeric>
                                                {formatNumber(
                                                    row.collected_revenue
                                                )}
                                            </Cell>

                                            <Cell numeric>
                                                {formatRate(
                                                    row.label_rate
                                                )}
                                            </Cell>

                                            <Cell numeric>
                                                {formatNumber(
                                                    row.earnings
                                                )}
                                            </Cell>

                                            <Cell>
                                                <span
                                                    className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold ring-1 ring-inset ${statusClass(
                                                        row.mapping_status
                                                    )}`}
                                                >
                                                    {row.mapping_status ||
                                                        'No Status'}
                                                </span>
                                            </Cell>
                                        </tr>
                                    )
                                )}

                                {(rows.data ?? []).length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="15"
                                            className="px-6 py-14 text-center text-sm text-slate-500"
                                        >
                                            No report rows found for this import.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        links={rows.links ?? []}
                        from={rows.from}
                        to={rows.to}
                        total={rows.total}
                    />
                </section>
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
}) {
    return (
        <div className="rounded-xl border border-slate-200 bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function ReviewCheck({
    title,
    ok,
    detail,
}) {
    return (
        <div
            className={[
                'rounded-xl border p-4',
                ok
                    ? 'border-emerald-200 bg-emerald-50'
                    : 'border-amber-200 bg-amber-50',
            ].join(' ')}
        >
            <div
                className={[
                    'text-sm font-semibold',
                    ok
                        ? 'text-emerald-800'
                        : 'text-amber-800',
                ].join(' ')}
            >
                {ok ? '✓ ' : '⚠ '}
                {title}
            </div>

            <div
                className={[
                    'mt-1 text-xs leading-5',
                    ok
                        ? 'text-emerald-700'
                        : 'text-amber-700',
                ].join(' ')}
            >
                {detail}
            </div>
        </div>
    );
}

function Cell({
    children,
    mono = false,
    numeric = false,
}) {
    return (
        <td
            className={[
                'whitespace-nowrap px-4 py-3 text-sm text-slate-600',
                mono
                    ? 'font-mono text-xs'
                    : '',
                numeric
                    ? 'text-right tabular-nums'
                    : '',
            ].join(' ')}
        >
            {children}
        </td>
    );
}

function Pagination({
    links = [],
    from,
    to,
    total,
}) {
    if (!links.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 border-t border-slate-200 px-5 py-4">
            <div className="text-sm text-slate-500">
                {from ?? 0}–{to ?? 0} of{' '}
                {formatNumber(total ?? 0)}
            </div>

            <div className="flex flex-wrap gap-1">
                {links.map((link, index) => (
                    <button
                        key={`${link.label}-${index}`}
                        type="button"
                        disabled={!link.url}
                        onClick={() => {
                            if (!link.url) {
                                return;
                            }

                            router.visit(
                                link.url,
                                {
                                    preserveState: true,
                                    preserveScroll: true,
                                }
                            );
                        }}
                        className={[
                            'min-w-9 rounded-lg border px-3 py-2 text-sm',
                            link.active
                                ? 'border-violet-600 bg-violet-600 text-white'
                                : 'border-slate-200 bg-white text-slate-600 hover:bg-slate-50',
                            !link.url
                                ? 'cursor-not-allowed opacity-40'
                                : '',
                        ].join(' ')}
                        dangerouslySetInnerHTML={{
                            __html: link.label,
                        }}
                    />
                ))}
            </div>
        </div>
    );
}
