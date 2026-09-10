import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Activity,
    BadgeIndianRupee,
    BarChart3,
    Disc3,
    Download,
    Filter,
    Globe2,
    Layers3,
    MapPinned,
    Music2,
    RefreshCcw,
    Store,
    TrendingDown,
    TrendingUp,
    Users,
} from 'lucide-react';

import {
    Area,
    AreaChart,
    Bar,
    BarChart,
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
    hierarchyOptions = {},
    summary = {},
    growth = {},
    monthlyTrend = [],
    topPlatforms = [],
    topCountries = [],
    topTracks = [],
    topArtists = [],
    topAlbums = [],
    topLabels = [],
    saleTypes = [],
    currencySummary = [],
    cmsSummary = [],
    revenueVisibility = {},
    financialAnalytics = {},
}) {
    const primaryCurrency =
        currencySummary?.[0]?.currency || 'INR';

    const financialSummary =
        financialAnalytics?.summary || {};

    const financialMonthly =
        financialAnalytics?.monthly || [];

    const financialPlatforms =
        financialAnalytics?.platforms || [];

    const financialCountries =
        financialAnalytics?.countries || [];

    const financialSaleTypes =
        financialAnalytics?.saleTypes || [];

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

    const saleTypeOptions =
        filterOptions?.saleTypes || [];

    const cmsOptions =
        filterOptions?.cms || [];

    const hierarchyMasters =
        hierarchyOptions?.masters || [];

    const hierarchyLevels =
        hierarchyOptions?.levels || [];

    const hierarchyArtists =
        hierarchyOptions?.artists || [];

    const selectedMasterId =
        filters.master_label_id || '';

    const selectedLevelId =
        filters.level_id || '';

    const selectedArtistId =
        filters.artist_id || '';

    const filteredLevels =
        selectedMasterId
            ? hierarchyLevels.filter(
                  (level) =>
                      String(level.root_id) ===
                      String(selectedMasterId)
              )
            : hierarchyLevels;

    const selectedLevel =
        hierarchyLevels.find(
            (level) =>
                String(level.id) ===
                String(selectedLevelId)
        ) || null;

    const descendantLevelIds =
        selectedLevel
            ? new Set(
                  hierarchyLevels
                      .filter((level) => {
                          const selectedPath =
                              String(
                                  selectedLevel.path ||
                                  ''
                              );

                          const path =
                              String(
                                  level.path ||
                                  ''
                              );

                          return (
                              String(level.id) ===
                                  String(
                                      selectedLevel.id
                                  )
                              ||
                              path.startsWith(
                                  `${selectedPath} › `
                              )
                          );
                      })
                      .map(
                          (level) =>
                              String(level.id)
                      )
              )
            : null;

    const filteredHierarchyArtists =
        hierarchyArtists.filter(
            (artist) => {
                if (
                    selectedMasterId
                    && artist.label_id
                ) {
                    const artistLevel =
                        hierarchyLevels.find(
                            (level) =>
                                String(
                                    level.id
                                ) ===
                                String(
                                    artist.label_id
                                )
                        );

                    if (
                        !artistLevel
                        ||
                        String(
                            artistLevel.root_id
                        ) !==
                            String(
                                selectedMasterId
                            )
                    ) {
                        return false;
                    }
                }

                if (
                    descendantLevelIds
                    && artist.label_id
                    && !descendantLevelIds.has(
                        String(
                            artist.label_id
                        )
                    )
                ) {
                    return false;
                }

                return true;
            }
        );

    const changeFilter = (key, value) => {
        router.get(
            window.location.pathname,
            {
                ...filters,
                [key]: value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            }
        );
    };

    const resetFilters = () => {
        router.get(
            window.location.pathname,
            {},
            {
                preserveScroll: true,
                preserveState: true,
                replace: true,
            }
        );
    };

    const exportCsv = () => {
        const params = new URLSearchParams();

        Object.entries(filters || {}).forEach(
            ([key, value]) => {
                if (value !== null && value !== undefined && value !== '') {
                    params.set(key, value);
                }
            }
        );

        const query = params.toString();

        window.location.href =
            `/v2/analytics/export${query ? `?${query}` : ''}`;
    };

    const scope =
        role === 'super_admin'
            ? 'All platform data'
            : role === 'admin'
              ? 'Assigned labels and artists'
              : role === 'label'
                ? 'Your label catalogue'
                : 'Your artist catalogue';

    const direction =
        growth.direction || 'neutral';

    const GrowthIcon =
        direction === 'down'
            ? TrendingDown
            : TrendingUp;

    const platformPie =
        topPlatforms.slice(0, 6);

    const financialPlatformPie =
        financialPlatforms.slice(0, 10);

    const financialPlatformTotal =
        financialPlatformPie.reduce(
            (total, item) =>
                total + Number(item.net_payable || 0),
            0
        );

    const financialCountryPie =
        financialCountries.slice(0, 10);

    const financialCountryTotal =
        financialCountryPie.reduce(
            (total, item) =>
                total + Number(item.net_payable || 0),
            0
        );

    const [filtersOpen, setFiltersOpen] = useState(false);
    const [hierarchyOpen, setHierarchyOpen] = useState(false);

    return (
        <>
            <Head title="Financial Analytics" />

            <PanelLayout
                role={role}
                title="Financial Analytics"
                subtitle="Performance intelligence from imported royalty reports"
            >
                <div className="space-y-6 p-5 lg:p-7">

                    {/* ANALYTICS CONTROLS */}
                    <div className="flex items-center justify-end gap-2">
                        <button
                            type="button"
                            onClick={() => setFiltersOpen((open) => !open)}
                            title="Filters"
                            aria-label="Filters"
                            aria-expanded={filtersOpen}
                            className={`flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition ${
                                filtersOpen
                                    ? 'border-violet-300 bg-violet-600 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700'
                            }`}
                        >
                            <Filter className="h-4 w-4" />
                        </button>

                        <button
                            type="button"
                            onClick={() => setHierarchyOpen((open) => !open)}
                            title="Revenue Hierarchy Scope"
                            aria-label="Revenue Hierarchy Scope"
                            aria-expanded={hierarchyOpen}
                            className={`flex h-10 w-10 items-center justify-center rounded-xl border shadow-sm transition ${
                                hierarchyOpen
                                    ? 'border-violet-300 bg-violet-600 text-white'
                                    : 'border-slate-200 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700'
                            }`}
                        >
                            <Layers3 className="h-4 w-4" />
                        </button>
                    </div>

                    {/* FILTER BAR */}
                    {filtersOpen && (
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <div className="flex flex-col gap-5 2xl:flex-row 2xl:items-end 2xl:justify-between">
                            <div>
                                <h1 className="text-xl font-bold text-slate-950">
                                    Financial Analytics
                                </h1>

                                <p className="mt-1 text-sm text-slate-500">
                                    {scope}. Data isolation is applied automatically.
                                </p>
                            </div>

                            <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-7">
                                <div>
                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Reporting Month
                                    </label>

                                    <select
                                    value={filters.month || ''}
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                month: e.target.value,
                                                sale_month: '',
                                                from_month: '',
                                                to_month: '',
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        Select Reporting Month
                                    </option>

                                    {months.map((month) => (
                                        <option
                                            key={month}
                                            value={month}
                                        >
                                            {monthFormat(month)}
                                        </option>
                                    ))}
                                </select>
                                </div>

                                <div>
                                    <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        Sale Month
                                    </label>

                                    <select
                                        value={filters.sale_month || ''}
                                        onChange={(event) =>
                                            changeFilter(
                                                'sale_month',
                                                event.target.value
                                            )
                                        }
                                        className="w-full rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm text-slate-700 shadow-sm outline-none transition focus:border-indigo-400 focus:ring-2 focus:ring-indigo-100"
                                    >
                                        <option value="">
                                            All
                                        </option>

                                        {(filterOptions.saleMonths || []).map(
                                            (month) => (
                                                <option
                                                    key={month}
                                                    value={month}
                                                >
                                                    {monthFormat(month)}
                                                </option>
                                            )
                                        )}
                                    </select>
                                </div>

                                <select
                                    value={filters.from_month || ''}
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                month: '',
                                                from_month: e.target.value,
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        From Month
                                    </option>

                                    {[...months].reverse().map((month) => (
                                        <option
                                            key={month}
                                            value={month}
                                        >
                                            {monthFormat(month)}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={filters.to_month || ''}
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                month: '',
                                                to_month: e.target.value,
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        To Month
                                    </option>

                                    {[...months].reverse().map((month) => (
                                        <option
                                            key={month}
                                            value={month}
                                        >
                                            {monthFormat(month)}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={filters.platform || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'platform',
                                            e.target.value
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Stores
                                    </option>

                                    {platforms.map((platform) => (
                                        <option
                                            key={platform}
                                            value={platform}
                                        >
                                            {platform}
                                        </option>
                                    ))}
                                </select>

                                <input
                                    type="search"
                                    value={filters.isrc || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'isrc',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Search ISRC"
                                    autoComplete="off"
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                />

                                <input
                                    type="search"
                                    value={filters.upc || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'upc',
                                            e.target.value
                                        )
                                    }
                                    placeholder="Search UPC"
                                    autoComplete="off"
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                />

                                <select
                                    value={filters.sale_type || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'sale_type',
                                            e.target.value
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Sale Types
                                    </option>

                                    {saleTypeOptions.map((saleType) => (
                                        <option
                                            key={saleType}
                                            value={saleType}
                                        >
                                            {saleType}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={filters.country || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'country',
                                            e.target.value
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Regions
                                    </option>

                                    {countries.map((country) => (
                                        <option
                                            key={country}
                                            value={country}
                                        >
                                            {country}
                                        </option>
                                    ))}
                                </select>

                                <select
                                    value={filters.cms || ''}
                                    onChange={(e) =>
                                        changeFilter(
                                            'cms',
                                            e.target.value
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All CMS
                                    </option>

                                    {cmsOptions.map((cmsValue) => (
                                        <option
                                            key={cmsValue}
                                            value={cmsValue}
                                        >
                                            {cmsValue}
                                        </option>
                                    ))}
                                </select>

                                <button
                                    type="button"
                                    onClick={exportCsv}
                                    className="flex items-center justify-center gap-2 rounded-xl bg-violet-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-violet-700"
                                >
                                    <Download className="h-4 w-4" />
                                    Export CSV
                                </button>

                                <button
                                    type="button"
                                    onClick={resetFilters}
                                    className="flex items-center justify-center gap-2 rounded-xl border border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100"
                                >
                                    <RefreshCcw className="h-4 w-4" />
                                    Reset
                                </button>
                            </div>
                        </div>
                    </div>
                    )}

                    {hierarchyOpen && (
                    <div className="rounded-2xl border border-violet-200 bg-violet-50/40 p-5 shadow-sm">
                        <div className="flex flex-col gap-5 xl:flex-row xl:items-end xl:justify-between">
                            <div>
                                <div className="text-sm font-black text-slate-950">
                                    Revenue Hierarchy Scope
                                </div>

                                <p className="mt-1 text-sm text-slate-500">
                                    Select a master account, recursive catalogue level, or exact artist.
                                </p>
                            </div>

                            <div className="grid flex-1 gap-3 md:grid-cols-3 xl:max-w-4xl">
                                <select
                                    value={
                                        selectedMasterId
                                    }
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                master_label_id:
                                                    e.target.value,
                                                level_id: '',
                                                artist_id: '',
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Master Accounts
                                    </option>

                                    {hierarchyMasters.map(
                                        (master) => (
                                            <option
                                                key={
                                                    master.id
                                                }
                                                value={
                                                    master.id
                                                }
                                            >
                                                {
                                                    master.name
                                                }
                                            </option>
                                        )
                                    )}
                                </select>

                                <select
                                    value={
                                        selectedLevelId
                                    }
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                level_id:
                                                    e.target.value,
                                                artist_id: '',
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Levels
                                    </option>

                                    {filteredLevels.map(
                                        (level) => (
                                            <option
                                                key={
                                                    level.id
                                                }
                                                value={
                                                    level.id
                                                }
                                            >
                                                {
                                                    level.path ||
                                                    level.name
                                                }
                                            </option>
                                        )
                                    )}
                                </select>

                                <select
                                    value={
                                        selectedArtistId
                                    }
                                    onChange={(e) =>
                                        router.get(
                                            window.location.pathname,
                                            {
                                                ...filters,
                                                artist_id:
                                                    e.target.value,
                                            },
                                            {
                                                preserveScroll: true,
                                                preserveState: true,
                                                replace: true,
                                            }
                                        )
                                    }
                                    className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 text-sm"
                                >
                                    <option value="">
                                        All Artists
                                    </option>

                                    {filteredHierarchyArtists.map(
                                        (artist) => (
                                            <option
                                                key={
                                                    artist.id
                                                }
                                                value={
                                                    artist.id
                                                }
                                            >
                                                {
                                                    artist.name
                                                }
                                            </option>
                                        )
                                    )}
                                </select>
                            </div>
                        </div>

                        <div className="mt-4 text-xs font-semibold text-slate-500">
                            Selecting a level includes that level and its complete descendant subtree.
                        </div>
                    </div>
                    )}

                    {revenueVisibility?.available &&
                        !revenueVisibility?.is_master && (
                        <div className="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 to-white p-5 shadow-sm">
                            <div className="flex flex-col gap-5">
                                <SectionHeader
                                    icon={BadgeIndianRupee}
                                    title={
                                        revenueVisibility.is_master
                                            ? 'Master Label Revenue'
                                            : 'Your Revenue Share'
                                    }
                                    subtitle={
                                        revenueVisibility.is_master
                                            ? 'Canonical royalty allocation across your complete managed catalogue hierarchy'
                                            : 'Your payable revenue after the configured revenue-share allocation'
                                    }
                                />

                                {revenueVisibility.is_master ? (
                                    <>
                                        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                            <Card
                                                title="Managed Gross"
                                                value={moneyFormat(
                                                    revenueVisibility.managed_revenue,
                                                    primaryCurrency
                                                )}
                                                subtitle="Revenue managed across the complete hierarchy"
                                                icon={Layers3}
                                            />

                                            <Card
                                                title="Child Allocated"
                                                value={moneyFormat(
                                                    revenueVisibility.allocated_revenue,
                                                    primaryCurrency
                                                )}
                                                subtitle="Payable to child labels and artists"
                                                icon={Users}
                                            />

                                            <Card
                                                title="Master Retained"
                                                value={moneyFormat(
                                                    revenueVisibility.retained_revenue,
                                                    primaryCurrency
                                                )}
                                                subtitle="Master label payable amount"
                                                icon={BadgeIndianRupee}
                                            />

                                            <Card
                                                title="Master Statement Gross"
                                                value={moneyFormat(
                                                    revenueVisibility.gross_statement_revenue,
                                                    primaryCurrency
                                                )}
                                                subtitle="Gross amount on master statements"
                                                icon={BarChart3}
                                            />
                                        </div>

                                        {!!revenueVisibility.children?.length && (
                                            <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                                                <div className="border-b border-slate-200 px-5 py-4">
                                                    <div className="font-semibold text-slate-950">
                                                        Revenue Beneficiaries
                                                    </div>

                                                    <div className="mt-1 text-sm text-slate-500">
                                                        Catalogue levels and artists within the managed hierarchy.
                                                    </div>
                                                </div>

                                                <div className="overflow-x-auto">
                                                    <table className="min-w-full divide-y divide-slate-200 text-sm">
                                                        <thead className="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                                            <tr>
                                                                <th className="px-5 py-3">
                                                                    Beneficiary
                                                                </th>

                                                                <th className="px-5 py-3">
                                                                    Type
                                                                </th>

                                                                <th className="px-5 py-3 text-right">
                                                                    Share
                                                                </th>

                                                                <th className="px-5 py-3 text-right">
                                                                    Managed Gross
                                                                </th>

                                                                <th className="px-5 py-3 text-right">
                                                                    Allocated
                                                                </th>

                                                                <th className="px-5 py-3 text-right">
                                                                    Master Retained
                                                                </th>
                                                            </tr>
                                                        </thead>

                                                        <tbody className="divide-y divide-slate-100">
                                                            {revenueVisibility.children.map(
                                                                (child) => (
                                                                    <tr
                                                                        key={`${child.type}-${child.id}`}
                                                                        className="text-slate-700"
                                                                    >
                                                                        <td className="px-5 py-4 font-semibold text-slate-900">
                                                                            {child.name}
                                                                        </td>

                                                                        <td className="px-5 py-4 capitalize">
                                                                            {String(
                                                                                child.type || ''
                                                                            ).replace(
                                                                                '_',
                                                                                ' '
                                                                            )}
                                                                        </td>

                                                                        <td className="px-5 py-4 text-right">
                                                                            {child.share_percent !== null &&
                                                                            child.share_percent !== undefined
                                                                                ? `${Number(
                                                                                      child.share_percent
                                                                                  ).toFixed(
                                                                                      2
                                                                                  )}%`
                                                                                : '—'}
                                                                        </td>

                                                                        <td className="px-5 py-4 text-right font-medium">
                                                                            {moneyFormat(
                                                                                child.managed_revenue,
                                                                                primaryCurrency
                                                                            )}
                                                                        </td>

                                                                        <td className="px-5 py-4 text-right font-medium">
                                                                            {moneyFormat(
                                                                                child.allocated_revenue,
                                                                                primaryCurrency
                                                                            )}
                                                                        </td>

                                                                        <td className="px-5 py-4 text-right font-bold text-violet-700">
                                                                            {moneyFormat(
                                                                                child.master_retained,
                                                                                primaryCurrency
                                                                            )}
                                                                        </td>
                                                                    </tr>
                                                                )
                                                            )}
                                                        </tbody>
                                                    </table>
                                                </div>
                                            </div>
                                        )}
                                    </>
                                ) : (
                                    <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
                                        <Card
                                            title="Payable Revenue"
                                            value={moneyFormat(
                                                revenueVisibility.payable_revenue,
                                                primaryCurrency
                                            )}
                                            subtitle="Your allocated payable amount"
                                            icon={BadgeIndianRupee}
                                        />

                                        <Card
                                            title="Statement Gross"
                                            value={moneyFormat(
                                                revenueVisibility.gross_statement_revenue,
                                                primaryCurrency
                                            )}
                                            subtitle="Gross value recorded on your statements"
                                            icon={BarChart3}
                                        />

                                        {revenueVisibility.share_visible && (
                                            <Card
                                                title="Revenue Share"
                                                value={`${Number(
                                                    revenueVisibility.share_percent || 0
                                                ).toFixed(2)}%`}
                                                subtitle="Configured share visible to your account"
                                                icon={Activity}
                                            />
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    )}

                    {/* CANONICAL FINANCIAL ANALYTICS */}
                    <div className="rounded-2xl border border-violet-200 bg-gradient-to-br from-violet-50 via-white to-white p-5 shadow-sm">
                        <SectionHeader
                            icon={BadgeIndianRupee}
                            title="Financial Analytics"
                            subtitle="Canonical payable analytics generated from royalty statements and allocations"
                        />

                        <div className="mt-5 grid gap-4 xl:grid-cols-2">
                            <div className="rounded-2xl border border-slate-200 bg-white p-5">
                                <SectionHeader
                                    icon={TrendingUp}
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
                        </div>

                        <div className="mt-4 rounded-2xl border border-slate-200 bg-white p-5">
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
                                        <thead className="bg-slate-50">
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

                            <div className="overflow-x-auto">
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
                    </div>

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

                    {/* BUSINESS GROWTH */}
                    <div className="grid gap-4 xl:grid-cols-3">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm xl:col-span-2">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <SectionHeader
                                    icon={TrendingUp}
                                    title="Month-wise Business Growth"
                                    subtitle="Reported earnings and streams performance over time"
                                />

                                <div
                                    className={`flex items-center gap-2 rounded-xl px-3 py-2 text-sm font-bold ${
                                        direction === 'up'
                                            ? 'bg-emerald-50 text-emerald-700'
                                            : direction === 'down'
                                              ? 'bg-red-50 text-red-700'
                                              : 'bg-slate-100 text-slate-600'
                                    }`}
                                >
                                    <GrowthIcon className="h-4 w-4" />

                                    {growth.has_previous
                                        ? percentFormat(
                                              growth.earnings_percent
                                          )
                                        : 'No previous month'}
                                </div>
                            </div>

                            {monthlyTrend.length ? (
                                <div className="mt-6 h-[360px]">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <AreaChart
                                            data={monthlyTrend}
                                            margin={{
                                                top: 10,
                                                right: 20,
                                                left: 24,
                                                bottom: 0,
                                            }}
                                        >
                                            <defs>
                                                <linearGradient
                                                    id="analyticsRevenue"
                                                    x1="0"
                                                    y1="0"
                                                    x2="0"
                                                    y2="1"
                                                >
                                                    <stop
                                                        offset="5%"
                                                        stopColor="#7c3aed"
                                                        stopOpacity={0.28}
                                                    />
                                                    <stop
                                                        offset="95%"
                                                        stopColor="#7c3aed"
                                                        stopOpacity={0}
                                                    />
                                                </linearGradient>
                                            </defs>

                                            <CartesianGrid
                                                vertical={false}
                                                strokeDasharray="3 3"
                                            />

                                            <XAxis
                                                dataKey="month"
                                                tickFormatter={monthFormat}
                                            />

                                            <YAxis
                                                width={88}
                                                tickMargin={8}
                                                tickFormatter={
                                                    numberFormat
                                                }
                                            />

                                            <Tooltip
                                                labelFormatter={monthFormat}
                                                formatter={(value, name) => [
                                                    name === 'Reported Earnings'
                                                        ? moneyFormat(
                                                              value,
                                                              primaryCurrency
                                                          )
                                                        : numberFormat(value),
                                                    name,
                                                ]}
                                            />

                                            <Area
                                                type="monotone"
                                                dataKey="earnings"
                                                name="Reported Earnings"
                                                stroke="#7c3aed"
                                                strokeWidth={3}
                                                fill="url(#analyticsRevenue)"
                                            />

                                            <Line
                                                type="monotone"
                                                dataKey="streams"
                                                name="Streams"
                                                stroke="#0f172a"
                                                strokeWidth={2}
                                                dot={false}
                                            />
                                        </AreaChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <EmptyState />
                            )}
                        </div>

                        {/* MOM */}
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={Activity}
                                title="Growth Overview"
                                subtitle="Current vs previous month"
                            />

                            <div className="mt-6 space-y-4">
                                <div className="rounded-xl bg-slate-50 p-4">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Current Month
                                    </div>

                                    <div className="mt-1 font-semibold text-slate-800">
                                        {monthFormat(
                                            growth.current_month
                                        )}
                                    </div>

                                    <div className="mt-2 text-2xl font-bold text-slate-950">
                                        {moneyFormat(
                                            growth.current_earnings,
                                            primaryCurrency
                                        )}
                                    </div>
                                </div>

                                <div className="rounded-xl bg-slate-50 p-4">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Previous Month
                                    </div>

                                    <div className="mt-1 font-semibold text-slate-800">
                                        {growth.has_previous
                                            ? monthFormat(
                                                  growth.previous_month
                                              )
                                            : 'Not available'}
                                    </div>

                                    <div className="mt-2 text-2xl font-bold text-slate-950">
                                        {growth.has_previous
                                            ? moneyFormat(
                                                  growth.previous_earnings,
                                                  primaryCurrency
                                              )
                                            : '—'}
                                    </div>
                                </div>

                                <div className="grid grid-cols-2 gap-3">
                                    <div className="rounded-xl border border-slate-200 p-4">
                                        <div className="text-xs text-slate-500">
                                            Reported Earnings Growth
                                        </div>

                                        <div
                                            className={`mt-1 text-lg font-bold ${
                                                Number(
                                                    growth.earnings_percent
                                                ) > 0
                                                    ? 'text-emerald-600'
                                                    : Number(
                                                          growth.earnings_percent
                                                      ) < 0
                                                      ? 'text-red-600'
                                                      : 'text-slate-700'
                                            }`}
                                        >
                                            {growth.has_previous
                                                ? percentFormat(
                                                      growth.earnings_percent
                                                  )
                                                : 'N/A'}
                                        </div>
                                    </div>

                                    <div className="rounded-xl border border-slate-200 p-4">
                                        <div className="text-xs text-slate-500">
                                            Stream Growth
                                        </div>

                                        <div
                                            className={`mt-1 text-lg font-bold ${
                                                Number(
                                                    growth.streams_percent
                                                ) > 0
                                                    ? 'text-emerald-600'
                                                    : Number(
                                                          growth.streams_percent
                                                      ) < 0
                                                      ? 'text-red-600'
                                                      : 'text-slate-700'
                                            }`}
                                        >
                                            {growth.has_previous
                                                ? percentFormat(
                                                      growth.streams_percent
                                                  )
                                                : 'N/A'}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* STORES + REGIONS */}
                    <div className="grid gap-4 xl:grid-cols-2">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={Store}
                                title="Stores"
                                subtitle="DSP reported earnings performance"
                            />

                            {topPlatforms.length ? (
                                <div className="mt-5 h-[320px]">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart data={topPlatforms}>
                                            <CartesianGrid
                                                vertical={false}
                                                strokeDasharray="3 3"
                                            />

                                            <XAxis dataKey="name" />
                                            <YAxis />

                                            <Tooltip
                                                formatter={(value) => [
                                                    moneyFormat(
                                                        value,
                                                        primaryCurrency
                                                    ),
                                                    'Reported Earnings',
                                                ]}
                                            />

                                            <Bar
                                                dataKey="earnings"
                                                fill="#7c3aed"
                                                radius={[7, 7, 0, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <EmptyState />
                            )}
                        </div>

                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <SectionHeader
                                icon={MapPinned}
                                title="Regions"
                                subtitle="Country-wise reported earnings performance"
                            />

                            {topCountries.length ? (
                                <div className="mt-5 h-[320px]">
                                    <ResponsiveContainer
                                        width="100%"
                                        height="100%"
                                    >
                                        <BarChart
                                            data={topCountries}
                                            layout="vertical"
                                        >
                                            <CartesianGrid
                                                horizontal={false}
                                                strokeDasharray="3 3"
                                            />

                                            <XAxis type="number" />

                                            <YAxis
                                                type="category"
                                                dataKey="name"
                                                width={60}
                                            />

                                            <Tooltip
                                                formatter={(value) => [
                                                    moneyFormat(
                                                        value,
                                                        primaryCurrency
                                                    ),
                                                    'Reported Earnings',
                                                ]}
                                            />

                                            <Bar
                                                dataKey="earnings"
                                                fill="#0f172a"
                                                radius={[0, 7, 7, 0]}
                                            />
                                        </BarChart>
                                    </ResponsiveContainer>
                                </div>
                            ) : (
                                <EmptyState />
                            )}
                        </div>
                    </div>

                    {/* RANKINGS */}
                    <div className="grid gap-4 xl:grid-cols-3">
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

                    {/* SALE TYPE + CURRENCY */}
                    <div className="grid gap-4 xl:grid-cols-2">
                        {role === 'super_admin' && (
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <SectionHeader
                                    icon={BarChart3}
                                    title="Revenue by Sale Type"
                                    subtitle="Reported earnings grouped by transaction / usage type"
                                />
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-5 py-3">
                                                Sale Type
                                            </th>

                                            <th className="px-5 py-3 text-right">
                                                Streams
                                            </th>

                                            <th className="px-5 py-3 text-right">
                                                Units
                                            </th>

                                            <th className="px-5 py-3 text-right">
                                                Reported Earnings
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {saleTypes.length ? (
                                            saleTypes.map(
                                                (row, index) => (
                                                    <tr
                                                        key={`${row.name || 'unknown'}-${index}`}
                                                        className="hover:bg-slate-50"
                                                    >
                                                        <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                                            {row.name ||
                                                                'Unknown'}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                            {numberFormat(
                                                                row.streams
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                            {numberFormat(
                                                                row.sale_units
                                                            )}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm font-bold text-slate-950">
                                                            {moneyFormat(
                                                                row.earnings,
                                                                primaryCurrency
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            )
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan="4"
                                                    className="px-5 py-14 text-center text-sm text-slate-400"
                                                >
                                                    No sale type analytics available.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        )}

                        {role === 'super_admin' && (
                        <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                            <div className="border-b border-slate-100 p-5">
                                <SectionHeader
                                    icon={BadgeIndianRupee}
                                    title="Revenue by Currency"
                                    subtitle="Reported earnings grouped by statement currency"
                                />
                            </div>

                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-5 py-3">
                                                Currency
                                            </th>

                                            <th className="px-5 py-3 text-right">
                                                Reported Earnings
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {currencySummary.length ? (
                                            currencySummary.map(
                                                (row, index) => (
                                                    <tr
                                                        key={`${row.currency || 'unknown'}-${index}`}
                                                        className="hover:bg-slate-50"
                                                    >
                                                        <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                                            {row.currency ||
                                                                'Unknown'}
                                                        </td>

                                                        <td className="px-5 py-4 text-right text-sm font-bold text-slate-950">
                                                            {moneyFormat(
                                                                row.earnings,
                                                                row.currency ||
                                                                    primaryCurrency
                                                            )}
                                                        </td>
                                                    </tr>
                                                )
                                            )
                                        ) : (
                                            <tr>
                                                <td
                                                    colSpan="2"
                                                    className="px-5 py-14 text-center text-sm text-slate-400"
                                                >
                                                    No currency analytics available.
                                                </td>
                                            </tr>
                                        )}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        )}
                    </div>

                    {/* CMS BREAKDOWN — SUPER ADMIN ONLY */}
                    {role === 'super_admin' && (
                    <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div className="border-b border-slate-100 p-5">
                            <SectionHeader
                                icon={BarChart3}
                                title="Revenue by CMS"
                                subtitle="Reported earnings grouped by CMS"
                            />
                        </div>

                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                        <th className="px-5 py-3">
                                            CMS
                                        </th>

                                        <th className="px-5 py-3 text-right">
                                            Streams
                                        </th>

                                        <th className="px-5 py-3 text-right">
                                            Units
                                        </th>

                                        <th className="px-5 py-3 text-right">
                                            Reported Earnings
                                        </th>
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {cmsSummary.length ? (
                                        cmsSummary.map(
                                            (row, index) => (
                                                <tr
                                                    key={`${row.name || 'unknown'}-${index}`}
                                                    className="hover:bg-slate-50"
                                                >
                                                    <td className="px-5 py-4 text-sm font-semibold text-slate-900">
                                                        {row.name ||
                                                            'Unknown'}
                                                    </td>

                                                    <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                        {numberFormat(
                                                            row.streams
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4 text-right text-sm text-slate-700">
                                                        {numberFormat(
                                                            row.sale_units
                                                        )}
                                                    </td>

                                                    <td className="px-5 py-4 text-right text-sm font-bold text-slate-950">
                                                        {moneyFormat(
                                                            row.earnings,
                                                            primaryCurrency
                                                        )}
                                                    </td>
                                                </tr>
                                            )
                                        )
                                    ) : (
                                        <tr>
                                            <td
                                                colSpan="4"
                                                className="px-5 py-14 text-center text-sm text-slate-400"
                                            >
                                                No CMS analytics available.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    )}

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

                            <div className="overflow-x-auto">
                                <table className="min-w-full">
                                    <thead className="bg-slate-50">
                                        <tr className="text-left text-xs font-semibold uppercase tracking-wide text-slate-500">
                                            <th className="px-5 py-3">#</th>
                                            <th className="px-5 py-3">Track</th>
                                            <th className="px-5 py-3">ISRC</th>
                                            <th className="px-5 py-3 text-right">
                                                Streams
                                            </th>
                                            <th className="px-5 py-3 text-right">
                                                Reported Earnings
                                            </th>
                                        </tr>
                                    </thead>

                                    <tbody className="divide-y divide-slate-100">
                                        {topTracks.length ? (
                                            topTracks.map(
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
