import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    BadgeIndianRupee,
    BarChart3,
    Disc3,
    Globe2,
    Layers3,
    Music2,
    Store,
    Users,
} from 'lucide-react';

import {
    Area,
    AreaChart,
    CartesianGrid,
    Cell,
    Line,
    Pie,
    PieChart,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const numberFormat = (value) =>
    Number(value || 0).toLocaleString('en-IN', {
        maximumFractionDigits: 0,
    });

const moneyFormat = (value, currency = 'INR') => {
    try {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(Number(value || 0));
    } catch {
        return `${currency} ${Number(value || 0).toLocaleString('en-IN')}`;
    }
};

const monthFormat = (month) => {
    if (!month) return '—';

    const [year, value] = String(month).split('-');

    return new Date(
        Number(year),
        Number(value) - 1,
        1
    ).toLocaleDateString('en-IN', {
        month: 'short',
        year: 'numeric',
    });
};

const percentFormat = (value) => {
    if (value === null || value === undefined) {
        return 'N/A';
    }

    const numeric = Number(value);

    return `${numeric > 0 ? '+' : ''}${numeric.toFixed(2)}%`;
};

function Card({
    title,
    value,
    subtitle,
    icon: Icon,
    growth = null,
}) {
    const numeric =
        growth === null || growth === undefined
            ? null
            : Number(growth);

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-start justify-between gap-4">
                <div>
                    <div className="text-sm font-medium text-slate-500">
                        {title}
                    </div>

                    <div className="mt-2 text-2xl font-bold tracking-tight text-slate-950">
                        {value}
                    </div>

                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                        {numeric !== null && (
                            <span
                                className={
                                    numeric > 0
                                        ? 'font-bold text-emerald-600'
                                        : numeric < 0
                                          ? 'font-bold text-red-600'
                                          : 'font-bold text-slate-500'
                                }
                            >
                                {percentFormat(numeric)}
                            </span>
                        )}

                        <span>{subtitle}</span>
                    </div>
                </div>

                <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-violet-700">
                    <Icon className="h-5 w-5" />
                </div>
            </div>
        </div>
    );
}

function SectionHeader({
    icon: Icon,
    title,
    subtitle,
}) {
    return (
        <div className="flex items-start gap-3">
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700">
                <Icon className="h-4 w-4" />
            </div>

            <div>
                <h2 className="font-semibold text-slate-950">
                    {title}
                </h2>

                <p className="mt-0.5 text-sm text-slate-500">
                    {subtitle}
                </p>
            </div>
        </div>
    );
}

function EmptyState({ text = 'No data available.' }) {
    return (
        <div className="flex min-h-[220px] items-center justify-center text-center text-sm text-slate-400">
            {text}
        </div>
    );
}

function Ranking({
    rows = [],
    currency = 'INR',
}) {
    if (!rows.length) {
        return <EmptyState />;
    }

    const maximum = Math.max(
        ...rows.map((row) => Number(row.earnings || 0)),
        0
    );

    return (
        <div className="space-y-5">
            {rows.map((row, index) => {
                const width = maximum
                    ? Math.max(
                          4,
                          (Number(row.earnings || 0) / maximum) * 100
                      )
                    : 0;

                return (
                    <div key={`${row.name}-${index}`}>
                        <div className="mb-2 flex items-center justify-between gap-4">
                            <div className="min-w-0">
                                <div className="truncate text-sm font-semibold text-slate-800">
                                    <span className="mr-2 text-slate-400">
                                        {index + 1}.
                                    </span>
                                    {row.name}
                                </div>

                                {row.artist && (
                                    <div className="ml-6 mt-0.5 truncate text-xs text-slate-400">
                                        {row.artist}
                                    </div>
                                )}
                            </div>

                            <div className="shrink-0 text-sm font-bold text-slate-900">
                                {moneyFormat(row.earnings, currency)}
                            </div>
                        </div>

                        <div className="h-1.5 overflow-hidden rounded-full bg-slate-100">
                            <div
                                className="h-full rounded-full bg-violet-600"
                                style={{
                                    width: `${width}%`,
                                }}
                            />
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

export default function Index({
    role = 'artist',
    filters = {},
    filterOptions = {},
    summary = {},
    growth = {},
    topPlatforms = [],
    topTracks = [],
    topArtists = [],
    topAlbums = [],
    topLabels = [],
    currencySummary = [],
    financialAnalytics = {},
}) {
    const primaryCurrency =
        currencySummary?.[0]?.currency || 'INR';

    const [trackSort, setTrackSort] = useState({
        key: 'earnings',
        direction: 'desc',
    });

    const sortedTracks = [...topTracks].sort((a, b) => {
        const left = Number(a?.[trackSort.key] || 0);
        const right = Number(b?.[trackSort.key] || 0);

        return trackSort.direction === 'asc'
            ? left - right
            : right - left;
    });

    const toggleTrackSort = (key) => {
        setTrackSort((current) => ({
            key,
            direction:
                current.key === key && current.direction === 'desc'
                    ? 'asc'
                    : 'desc',
        }));
    };

    const trackSortIndicator = (key) => {
        if (trackSort.key !== key) {
            return null;
        }

        return trackSort.direction === 'desc' ? '↓' : '↑';
    };

    const financialSummary =
        financialAnalytics?.summary || {};

    const financialMonthly =
        financialAnalytics?.monthly || [];

    const financialPlatforms =
        financialAnalytics?.platforms || [];

    const financialCountries =
        financialAnalytics?.countries || [];

    const financialCurrencies =
        financialAnalytics?.currencies || [];

    const financialCms =
        financialAnalytics?.cms || [];

    const financialCurrency =
        financialSummary?.currency ||
        primaryCurrency;

    const months =
        filterOptions?.months || [];

    const platforms =
        filterOptions?.platforms || [];

    const countries =
        filterOptions?.countries || [];

    const financialPlatformTotal =
        financialPlatforms.reduce(
            (total, item) =>
                total + Number(item.net_payable || 0),
            0
        );

    const financialPlatformPie =
        financialPlatforms.length <= 10
            ? financialPlatforms
            : [
                  ...financialPlatforms.slice(0, 9),
                  {
                      platform: 'Others',
                      net_payable: financialPlatforms
                          .slice(9)
                          .reduce(
                              (total, item) =>
                                  total +
                                  Number(
                                      item.net_payable || 0
                                  ),
                              0
                          ),
                  },
              ];

    const financialCountryTotal =
        financialCountries.reduce(
            (total, item) =>
                total + Number(item.net_payable || 0),
            0
        );

    const financialCountryPie =
        financialCountries.length <= 10
            ? financialCountries
            : [
                  ...financialCountries.slice(0, 9),
                  {
                      country: 'Others',
                      net_payable: financialCountries
                          .slice(9)
                          .reduce(
                              (total, item) =>
                                  total +
                                  Number(
                                      item.net_payable || 0
                                  ),
                              0
                          ),
                  },
              ];

    return (
        <>
            <Head title="Financial Analytics" />

            <PanelLayout
                role={role}
                title="Financial Analytics"
                subtitle="Performance intelligence from imported royalty reports"
            >
                <div className="space-y-6 p-5 lg:p-7">

                    {/* RAW REPORT KPI CARDS */}
                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Card
                            title="Reported Earnings"
                            value={moneyFormat(
                                summary.earnings,
                                primaryCurrency
                            )}
                            subtitle={`${numberFormat(summary.rows)} report rows`}
                            growth={
                                growth.has_previous
                                    ? growth.earnings_percent
                                    : null
                            }
                            icon={BadgeIndianRupee}
                        />

                        <Card
                            title="Streams"
                            value={numberFormat(summary.streams)}
                            subtitle="Reported DSP streams"
                            growth={
                                growth.has_previous
                                    ? growth.streams_percent
                                    : null
                            }
                            icon={Activity}
                        />

                        <Card
                            title="Consumption Units"
                            value={numberFormat(summary.sale_units)}
                            subtitle="Imported sale / stream units"
                            icon={BarChart3}
                        />

                        <Card
                            title="Active Stores"
                            value={numberFormat(topPlatforms.length)}
                            subtitle="DSPs in selected period"
                            icon={Store}
                        />
                    </div>

                    {/* REPORTING RANGE */}
                    <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-end">
                            <div className="w-full sm:max-w-xs">
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    From Month
                                </label>

                                <select
                                    value={filters.from_month || filters.month || ''}
                                    onChange={(event) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                from_month:
                                                    event.target.value,
                                                to_month:
                                                    filters.to_month ||
                                                    filters.month ||
                                                    event.target.value,
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                >
                                    {months.map((month) => (
                                        <option key={month} value={month}>
                                            {monthFormat(month)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="hidden pb-2 text-slate-400 sm:block">
                                →
                            </div>

                            <div className="w-full sm:max-w-xs">
                                <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    To Month
                                </label>

                                <select
                                    value={filters.to_month || filters.month || ''}
                                    onChange={(event) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                from_month:
                                                    filters.from_month ||
                                                    filters.month ||
                                                    event.target.value,
                                                to_month:
                                                    event.target.value,
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm font-semibold text-slate-700 shadow-sm outline-none transition focus:border-violet-400 focus:ring-2 focus:ring-violet-100"
                                >
                                    {months.map((month) => (
                                        <option key={month} value={month}>
                                            {monthFormat(month)}
                                        </option>
                                    ))}
                                </select>
                            </div>

                            <div className="pb-2 text-xs font-medium text-slate-400">
                                Reporting period
                            </div>
                        </div>
                    </div>

                    {/* FINANCIAL STORE + COUNTRY */}
                    <div className="grid gap-4 xl:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                <SectionHeader
                                    icon={Store}
                                    title="Financial Revenue by Store"
                                    subtitle="Net payable allocation by DSP / platform"
                                />

                                {financialPlatforms.length ? (
                                    <div className="mt-5 grid min-h-[320px] items-center gap-5 lg:grid-cols-[minmax(0,1fr)_220px]">
                                        <div className="relative h-[320px]">
                                            <ResponsiveContainer
                                                width="100%"
                                                height="100%"
                                            >
                                                <PieChart>
                                                    <Pie
                                                        data={
                                                            financialPlatformPie
                                                        }
                                                        dataKey="net_payable"
                                                        nameKey="platform"
                                                        cx="50%"
                                                        cy="50%"
                                                        innerRadius={82}
                                                        outerRadius={125}
                                                        paddingAngle={2}
                                                        stroke="#ffffff"
                                                        strokeWidth={3}
                                                    >
                                                        {financialPlatformPie.map(
                                                            (
                                                                item,
                                                                index
                                                            ) => (
                                                                <Cell
                                                                    key={`${item.platform}-${index}`}
                                                                    fill={
                                                                        [
                                                                            '#7c3aed',
                                                                            '#2563eb',
                                                                            '#059669',
                                                                            '#ea580c',
                                                                            '#db2777',
                                                                            '#0891b2',
                                                                            '#4f46e5',
                                                                            '#65a30d',
                                                                            '#d97706',
                                                                            '#475569',
                                                                        ][
                                                                            index %
                                                                                10
                                                                        ]
                                                                    }
                                                                />
                                                            )
                                                        )}
                                                    </Pie>

                                                    <Tooltip
                                                        formatter={(
                                                            value,
                                                            name
                                                        ) => [
                                                            moneyFormat(
                                                                value,
                                                                financialCurrency
                                                            ),
                                                            name ||
                                                                'Unknown Store',
                                                        ]}
                                                    />
                                                </PieChart>
                                            </ResponsiveContainer>

                                            <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                                <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                    Total
                                                </div>

                                                <div className="mt-1 text-xl font-black text-slate-950">
                                                    {moneyFormat(
                                                        financialPlatformTotal,
                                                        financialCurrency
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        <div className="space-y-2.5">
                                            {financialPlatformPie.map(
                                                (item, index) => {
                                                    const value =
                                                        Number(
                                                            item.net_payable ||
                                                                0
                                                        );

                                                    const share =
                                                        financialPlatformTotal >
                                                        0
                                                            ? (value /
                                                                  financialPlatformTotal) *
                                                              100
                                                            : 0;

                                                    return (
                                                        <div
                                                            key={`${item.platform}-legend-${index}`}
                                                            className="flex items-center justify-between gap-3 text-sm"
                                                        >
                                                            <div className="flex min-w-0 items-center gap-2">
                                                                <span
                                                                    className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                                    style={{
                                                                        backgroundColor:
                                                                            [
                                                                                '#7c3aed',
                                                                                '#2563eb',
                                                                                '#059669',
                                                                                '#ea580c',
                                                                                '#db2777',
                                                                                '#0891b2',
                                                                                '#4f46e5',
                                                                                '#65a30d',
                                                                                '#d97706',
                                                                                '#475569',
                                                                            ][
                                                                                index %
                                                                                    10
                                                                            ],
                                                                    }}
                                                                />

                                                                <span className="truncate font-medium text-slate-700">
                                                                    {
                                                                        item.platform
                                                                    }
                                                                </span>
                                                            </div>

                                                            <span className="shrink-0 font-bold text-slate-900">
                                                                {share.toFixed(
                                                                    1
                                                                )}
                                                                %
                                                            </span>
                                                        </div>
                                                    );
                                                }
                                            )}
                                        </div>
                                    </div>
                                ) : (
                                    <EmptyState text="No financial platform allocation data available." />
                                )}
                            </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5">
                            <SectionHeader
                                icon={Globe2}
                                title="Financial Revenue by Country"
                                subtitle="Net payable royalty allocation by territory"
                            />

                            {financialCountries.length ? (
                                <div className="mt-5 grid min-h-[320px] items-center gap-5 lg:grid-cols-[minmax(0,1fr)_220px]">
                                    <div className="relative h-[320px]">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <PieChart>
                                                <Pie
                                                    data={
                                                        financialCountryPie
                                                    }
                                                    dataKey="net_payable"
                                                    nameKey="country"
                                                    cx="50%"
                                                    cy="50%"
                                                    innerRadius={82}
                                                    outerRadius={125}
                                                    paddingAngle={2}
                                                    stroke="#ffffff"
                                                    strokeWidth={3}
                                                >
                                                    {financialCountryPie.map(
                                                        (
                                                            item,
                                                            index
                                                        ) => (
                                                            <Cell
                                                                key={`${item.country || 'Unknown'}-${index}`}
                                                                fill={
                                                                    [
                                                                        '#7c3aed',
                                                                        '#2563eb',
                                                                        '#059669',
                                                                        '#ea580c',
                                                                        '#db2777',
                                                                        '#0891b2',
                                                                        '#4f46e5',
                                                                        '#65a30d',
                                                                        '#d97706',
                                                                        '#475569',
                                                                    ][
                                                                        index %
                                                                            10
                                                                    ]
                                                                }
                                                            />
                                                        )
                                                    )}
                                                </Pie>

                                                <Tooltip
                                                    formatter={(
                                                        value,
                                                        name
                                                    ) => [
                                                        moneyFormat(
                                                            value,
                                                            financialCurrency
                                                        ),
                                                        name ||
                                                            'Unknown Country',
                                                    ]}
                                                />
                                            </PieChart>
                                        </ResponsiveContainer>

                                        <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                                            <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                                Total
                                            </div>

                                            <div className="mt-1 text-xl font-black text-slate-950">
                                                {moneyFormat(
                                                    financialCountryTotal,
                                                    financialCurrency
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    <div className="space-y-2.5">
                                        {financialCountryPie.map(
                                            (item, index) => {
                                                const value =
                                                    Number(
                                                        item.net_payable ||
                                                            0
                                                    );

                                                const share =
                                                    financialCountryTotal >
                                                    0
                                                        ? (value /
                                                              financialCountryTotal) *
                                                          100
                                                        : 0;

                                                return (
                                                    <div
                                                        key={`${item.country || 'Unknown'}-legend-${index}`}
                                                        className="flex items-center justify-between gap-3 text-sm"
                                                    >
                                                        <div className="flex min-w-0 items-center gap-2">
                                                            <span
                                                                className="h-2.5 w-2.5 shrink-0 rounded-full"
                                                                style={{
                                                                    backgroundColor:
                                                                        [
                                                                            '#7c3aed',
                                                                            '#2563eb',
                                                                            '#059669',
                                                                            '#ea580c',
                                                                            '#db2777',
                                                                            '#0891b2',
                                                                            '#4f46e5',
                                                                            '#65a30d',
                                                                            '#d97706',
                                                                            '#475569',
                                                                        ][
                                                                            index %
                                                                                10
                                                                        ],
                                                                }}
                                                            />

                                                            <span className="truncate font-medium text-slate-700">
                                                                {item.country ||
                                                                    'Unknown'}
                                                            </span>
                                                        </div>

                                                        <span className="shrink-0 font-bold text-slate-900">
                                                            {share.toFixed(
                                                                1
                                                            )}
                                                            %
                                                        </span>
                                                    </div>
                                                );
                                            }
                                        )}
                                    </div>
                                </div>
                            ) : (
                                <EmptyState text="No financial country allocation data available." />
                            )}
                        </div>
                    </div>

                    {/* FINANCIAL MONTHLY */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <SectionHeader
                            icon={BadgeIndianRupee}
                            title="Monthly Financial Revenue"
                            subtitle="Gross, commission and net payable by statement month"
                        />

                                {financialMonthly.length ? (
                                    <div className="mt-5 h-[320px]">
                                        <ResponsiveContainer
                                            width="100%"
                                            height="100%"
                                        >
                                            <AreaChart
                                                data={financialMonthly}
                                                margin={{
                                                    top: 10,
                                                    right: 20,
                                                    left: 24,
                                                    bottom: 0,
                                                }}
                                            >
                                                <CartesianGrid
                                                    vertical={false}
                                                    strokeDasharray="3 3"
                                                />

                                                <XAxis
                                                    dataKey="month"
                                                    tickFormatter={
                                                        monthFormat
                                                    }
                                                />

                                                <YAxis
                                                    width={88}
                                                    tickMargin={8}
                                                    tickFormatter={
                                                        numberFormat
                                                    }
                                                />

                                                <Tooltip
                                                    formatter={(
                                                        value,
                                                        name
                                                    ) => [
                                                        moneyFormat(
                                                            value,
                                                            financialCurrency
                                                        ),
                                                        String(
                                                            name || ''
                                                        )
                                                            .replaceAll(
                                                                '_',
                                                                ' '
                                                            )
                                                            .replace(
                                                                /\b\w/g,
                                                                (c) =>
                                                                    c.toUpperCase()
                                                            ),
                                                    ]}
                                                    labelFormatter={
                                                        monthFormat
                                                    }
                                                />

                                                <Area
                                                    type="monotone"
                                                    dataKey="gross_earnings"
                                                    stroke="#7c3aed"
                                                    fill="#ede9fe"
                                                    strokeWidth={2}
                                                />

                                                <Line
                                                    type="monotone"
                                                    dataKey="net_payable"
                                                    stroke="#0f172a"
                                                    strokeWidth={2}
                                                    dot={false}
                                                />
                                            </AreaChart>
                                        </ResponsiveContainer>
                                    </div>
                                ) : (
                                    <EmptyState text="No financial statement data available for the selected period." />
                                )}
                            </div>

                        {/* CANONICAL FINANCIAL DIMENSIONS */}
                        <div className="mt-4 grid gap-4 xl:grid-cols-2">
                            {role === 'super_admin' && (
                            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                <div className="border-b border-slate-100 p-5">
                                    <SectionHeader
                                        icon={BadgeIndianRupee}
                                        title="Financial Revenue by Currency"
                                        subtitle="Allocation-safe gross and net payable by report currency"
                                    />
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="min-w-full">
                                        <thead className="sticky top-0 z-10 bg-slate-50 shadow-sm">
                                            <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                <th className="px-5 py-3">
                                                    Currency
                                                </th>
                                                <th className="px-5 py-3 text-right">
                                                    Gross
                                                </th>
                                                <th className="px-5 py-3 text-right">
                                                    Net Payable
                                                </th>
                                            </tr>
                                        </thead>

                                        <tbody className="divide-y divide-slate-100">
                                            {financialCurrencies.length ? (
                                                financialCurrencies.map(
                                                    (row, index) => (
                                                        <tr
                                                            key={`${row.currency || 'unknown'}-${index}`}
                                                            className="hover:bg-slate-50"
                                                        >
                                                            <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                                                {row.currency ||
                                                                    'Unknown'}
                                                            </td>
                                                            <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                                {moneyFormat(
                                                                    row.gross_earnings,
                                                                    row.currency ||
                                                                        financialCurrency
                                                                )}
                                                            </td>
                                                            <td className="px-5 py-4 text-right text-sm font-bold text-violet-700">
                                                                {moneyFormat(
                                                                    row.net_payable,
                                                                    row.currency ||
                                                                        financialCurrency
                                                                )}
                                                            </td>
                                                        </tr>
                                                    )
                                                )
                                            ) : (
                                                <tr>
                                                    <td
                                                        colSpan="3"
                                                        className="px-5 py-14 text-center text-sm text-slate-400"
                                                    >
                                                        No financial currency allocation data available.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                            )}
                        </div>

                        {role === 'super_admin' && (
                        <div className="mt-4 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <SectionHeader
                                    icon={BarChart3}
                                    title="Financial Revenue by CMS"
                                    subtitle="Allocation-safe gross and net payable by CMS"
                                />
                            </div>

                            <div className="max-h-[620px] overflow-auto">
                                <table className="min-w-full">

                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-5 py-3">
                                                CMS
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Gross
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Net Payable
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {financialCms.length ? (
                                            financialCms.map(
                                                (row, index) => (
                                                    <tr
                                                        key={`${row.cms || 'unknown'}-${index}`}
                                                        className="hover:bg-slate-50"
                                                    >
                                                        <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                                            {row.cms ||
                                                                'Unknown'}
                                                        </td>
                                                        <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                            {moneyFormat(
                                                                row.gross_earnings,
                                                                financialCurrency
                                                            )}
                                                        </td>
                                                        <td className="px-5 py-4 text-right text-sm font-bold text-violet-700">
                                                            {moneyFormat(
                                                                row.net_payable,
                                                                financialCurrency
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            )
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan="3"
                                                    className="px-5 py-14 text-center text-sm text-slate-400"
                                                >
                                                    No financial CMS allocation data available.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        )}
                    {/* RANKINGS */}
                    <div
                        className={`grid gap-4 ${
                            role === 'super_admin'
                                ? 'xl:grid-cols-3'
                                : 'xl:grid-cols-2'
                        }`}
                    >
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={Users}
                                title="Top Artists"
                                subtitle="Highest reported earnings contributors"
                            />

                            <div className="mt-6">
                                <Ranking
                                    rows={topArtists}
                                    currency={primaryCurrency}
                                />
                            </div>
                        </div>

                        {role === 'super_admin' && (
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={Disc3}
                                title="Top Albums"
                                subtitle="Best performing albums"
                            />

                            <div className="mt-6">
                                <Ranking
                                    rows={topAlbums}
                                    currency={primaryCurrency}
                                />
                            </div>
                        </div>
                        )}

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={Layers3}
                                title="Top Labels"
                                subtitle="Reported earnings performance by label"
                            />

                            <div className="mt-6">
                                <Ranking
                                    rows={topLabels}
                                    currency={primaryCurrency}
                                />
                            </div>
                        </div>
                    </div>

                    {/* TRACK TABLE */}
                    <div className="grid gap-4">
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm xl:col-span-2">
                            <div className="border-b border-slate-100 p-5">
                                <SectionHeader
                                    icon={Music2}
                                    title="Top Tracks"
                                    subtitle="Track-level performance"
                                />
                            </div>

                            <div className="max-h-[620px] overflow-auto">
                                <table className="min-w-full">
                                    <thead className="sticky top-0 z-10 bg-slate-50 shadow-sm">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-5 py-3">#</th>
                                            <th className="px-5 py-3">Track</th>
                                            <th className="px-5 py-3">ISRC</th>
                                            <th className="px-5 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleTrackSort('streams')}
                                                    className="ml-auto inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-slate-500 transition hover:text-violet-700"
                                                    title="Sort by streams"
                                                >
                                                    Streams
                                                    <span aria-hidden="true">
                                                        {trackSortIndicator('streams')}
                                                    </span>
                                                </button>
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                <button
                                                    type="button"
                                                    onClick={() => toggleTrackSort('earnings')}
                                                    className="ml-auto inline-flex items-center gap-1 font-semibold uppercase tracking-wide text-slate-500 transition hover:text-violet-700"
                                                    title="Sort by earnings"
                                                >
                                                    Earnings
                                                    <span aria-hidden="true">
                                                        {trackSortIndicator('earnings')}
                                                    </span>
                                                </button>
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {sortedTracks.length ? (
                                            sortedTracks.map(
                                                (track, index) => (
                                                    <tr
                                                        key={`${track.track_id ?? index}-${track.isrc ?? index}`}
                                                        className="hover:bg-slate-50"
                                                    >
                                                        <td className="px-5 py-4 text-sm text-slate-400">
                                                            {index + 1}
                                                        </td>

                                                        <td className="px-5 py-4">
                                                            <div className="font-semibold text-slate-900">
                                                                {track.title}
                                                            </div>

                                                            <div className="mt-0.5 text-xs text-slate-500">
                                                                {track.artist}
                                                            </div>
                                                        </td>

                                                        <td className="px-5 py-4 text-sm text-slate-600">
                                                            {track.isrc || '—'}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm font-medium text-slate-700">
                                                            {numberFormat(
                                                                track.streams
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm font-bold text-slate-950">
                                                            {moneyFormat(
                                                                track.earnings,
                                                                primaryCurrency
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            )
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan="5"
                                                    className="px-5 py-14 text-center text-sm text-slate-400"
                                                >
                                                    No track data available.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                    </div>

                </div>
            </PanelLayout>
        </>
    );
}
