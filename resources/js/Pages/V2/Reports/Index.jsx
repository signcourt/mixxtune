import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    BarChart3,
    Coins,
    Globe2,
    Music2,
} from 'lucide-react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import AnalyticsCard from '@/V2/Shared/Components/Analytics/AnalyticsCard';
import TrendChart from '@/V2/Shared/Components/Analytics/TrendChart';
import TopListCard from '@/V2/Shared/Components/Analytics/TopListCard';

const numberFormatter = new Intl.NumberFormat('en-IN', {
    maximumFractionDigits: 0,
});

const decimalFormatter = new Intl.NumberFormat('en-IN', {
    maximumFractionDigits: 2,
});

const formatNumber = (value) =>
    numberFormatter.format(Number(value ?? 0));

const formatDecimal = (value) =>
    decimalFormatter.format(Number(value ?? 0));

const formatCurrency = (value, currency = 'INR') => {
    try {
        return new Intl.NumberFormat('en-IN', {
            style: 'currency',
            currency,
            maximumFractionDigits: 2,
        }).format(Number(value ?? 0));
    } catch {
        return `${currency} ${formatDecimal(value)}`;
    }
};

const revenueMoney = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

export default function Index({
    role = 'artist',
    revenueSummary = null,
    filters = {},
    summary = {},
    rows = {},
    platforms = [],
    months = [],
    countries = [],
    monthlyTrend = [],
    topPlatforms = [],
    topCountries = [],
    topTracks = [],
    currencySummary = [],
}) {
    const data = rows.data ?? [];

    const title =
        role === 'artist'
            ? 'My Reports'
            : role === 'label'
              ? 'Label Reports'
              : role === 'admin'
                ? 'Managed Reports'
                : 'Global Reports';

    const update = (changes) => {
        router.get(
            '/v2/reports',
            {
                ...filters,
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Streams, sales, stores and earnings analytics"
        >
            <Head title={title} />

            <div className="space-y-6">
                <header className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-6 p-6 lg:grid-cols-[1fr_auto] lg:items-center">
                        <div>
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="rounded-full bg-violet-50 px-3 py-1 text-xs font-bold uppercase tracking-[0.18em] text-violet-700">
                                    Mixx Tune Analytics
                                </span>

                                <span className="text-xs font-medium text-slate-500">
                                    Reporting Dashboard
                                </span>
                            </div>

                            <h1 className="mt-4 text-2xl font-black tracking-tight text-slate-950 sm:text-3xl">
                                {title}
                            </h1>

                            <p className="mt-2 max-w-2xl text-sm leading-6 text-slate-500">
                                Track revenue, streams, sales,
                                territories and store performance
                                from one reporting workspace.
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Link
                                href="/v2/royalties"
                                className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700"
                            >
                                View Royalties
                            </Link>

                            <Link
                                href="/v2/statements"
                                className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                            >
                                Statements
                            </Link>
                        </div>
                    </div>
                </header>


            {role === 'label' && revenueSummary && (
                <section className="mb-6 space-y-5">
                    <div>
                        <h2 className="text-lg font-bold text-slate-950">
                            Revenue Allocation
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {revenueSummary.is_master
                                ? 'Managed revenue, direct child allocations and your retained share for the selected reporting period.'
                                : 'Your allocated and payable revenue for the selected reporting period.'}
                        </p>
                    </div>

                    {revenueSummary.is_master ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Managed Revenue
                                    </p>

                                    <p className="mt-2 text-2xl font-black text-slate-950">
                                        {revenueMoney(
                                            revenueSummary.managed_revenue
                                        )}
                                    </p>
                                </article>

                                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Child Allocation
                                    </p>

                                    <p className="mt-2 text-2xl font-black text-slate-950">
                                        {revenueMoney(
                                            revenueSummary.allocated_revenue
                                        )}
                                    </p>
                                </article>

                                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Retained Revenue
                                    </p>

                                    <p className="mt-2 text-2xl font-black text-slate-950">
                                        {revenueMoney(
                                            revenueSummary.retained_revenue
                                        )}
                                    </p>
                                </article>

                                <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Master Payable
                                    </p>

                                    <p className="mt-2 text-2xl font-black text-slate-950">
                                        {revenueMoney(
                                            revenueSummary.payable_revenue
                                        )}
                                    </p>
                                </article>
                            </div>

                            {revenueSummary.children?.length > 0 && (
                                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                                    <div className="border-b border-slate-200 px-5 py-4">
                                        <h3 className="font-bold text-slate-950">
                                            Sub-Label Allocation
                                        </h3>

                                        <p className="mt-1 text-xs text-slate-500">
                                            Allocation is a revenue split, not additional source revenue.
                                        </p>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-slate-200">
                                            <thead className="bg-slate-50">
                                                <tr>
                                                    {[
                                                        'Sub-Label',
                                                        'Managed Revenue',
                                                        'Share',
                                                        'Child Payable',
                                                        'Master Retained',
                                                    ].map(
                                                        (heading) => (
                                                            <th
                                                                key={heading}
                                                                className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                                            >
                                                                {heading}
                                                            </th>
                                                        )
                                                    )}
                                                </tr>
                                            </thead>

                                            <tbody className="divide-y divide-slate-100">
                                                {revenueSummary.children.map(
                                                    (child) => (
                                                        <tr
                                                            key={child.id}
                                                            className="hover:bg-slate-50"
                                                        >
                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {child.name}
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                                                                {revenueMoney(
                                                                    child.managed_revenue
                                                                )}
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-700">
                                                                {Number(
                                                                    child.share_percent ?? 0
                                                                ).toLocaleString(
                                                                    'en-IN'
                                                                )}
                                                                %
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {revenueMoney(
                                                                    child.allocated_revenue
                                                                )}
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {revenueMoney(
                                                                    child.master_retained
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
                            <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Your Revenue
                                </p>

                                <p className="mt-2 text-2xl font-black text-slate-950">
                                    {revenueMoney(
                                        revenueSummary.allocated_revenue
                                    )}
                                </p>
                            </article>

                            <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Payable Revenue
                                </p>

                                <p className="mt-2 text-2xl font-black text-slate-950">
                                    {revenueMoney(
                                        revenueSummary.payable_revenue
                                    )}
                                </p>
                            </article>

                            {revenueSummary.share_visible &&
                                revenueSummary.share_percent !== null && (
                                    <article className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                                        <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Revenue Share
                                        </p>

                                        <p className="mt-2 text-2xl font-black text-slate-950">
                                            {Number(
                                                revenueSummary.share_percent
                                            ).toLocaleString('en-IN')}
                                            %
                                        </p>
                                    </article>
                                )}
                        </div>
                    )}
                </section>
            )}

                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <AnalyticsCard
                        label="Total Earnings"
                        value={formatCurrency(
                            summary.earnings ?? 0
                        )}
                        description="Revenue in selected filters"
                        icon={<Coins size={20} />}
                        tone="violet"
                    />

                    <AnalyticsCard
                        label="Streams"
                        value={formatNumber(
                            summary.streams ?? 0
                        )}
                        description="Total reported streams"
                        icon={<BarChart3 size={20} />}
                        tone="blue"
                    />

                    <AnalyticsCard
                        label="Sale Units"
                        value={formatNumber(
                            summary.sale_units ?? 0
                        )}
                        description="Downloads and sale units"
                        icon={<Music2 size={20} />}
                        tone="emerald"
                    />

                    <AnalyticsCard
                        label="Report Rows"
                        value={formatNumber(
                            summary.rows ?? 0
                        )}
                        description="Imported reporting records"
                        icon={<Globe2 size={20} />}
                        tone="amber"
                    />
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="grid gap-3 md:grid-cols-2 xl:grid-cols-6">
                        <input
                            type="search"
                            defaultValue={filters.search ?? ''}
                            placeholder="Track, artist, UPC, ISRC..."
                            onKeyDown={(event) => {
                                if (event.key === 'Enter') {
                                    update({
                                        search:
                                            event.currentTarget
                                                .value,
                                    });
                                }
                            }}
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm xl:col-span-2"
                        />

                        <select
                            value={filters.month ?? ''}
                            onChange={(event) =>
                                update({
                                    month:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Sale Months
                            </option>

                            {months.map((month) => (
                                <option
                                    key={month}
                                    value={month}
                                >
                                    {month}
                                </option>
                            ))}
                        </select>

                        <select
                            value={filters.platform ?? ''}
                            onChange={(event) =>
                                update({
                                    platform:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Platforms
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

                        <select
                            value={filters.country ?? ''}
                            onChange={(event) =>
                                update({
                                    country:
                                        event.target.value,
                                })
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                        >
                            <option value="">
                                All Countries
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

                        <button
                            type="button"
                            onClick={() =>
                                router.get('/v2/reports')
                            }
                            className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-bold text-slate-700 transition hover:bg-slate-50"
                        >
                            Clear Filters
                        </button>
                    </div>
                </section>

                <section className="grid gap-6 xl:grid-cols-[minmax(0,1.6fr)_minmax(320px,0.7fr)]">
                    <TrendChart
                        rows={monthlyTrend}
                        metric="earnings"
                        title="Monthly Revenue Trend"
                    />

                    <TopListCard
                        title="Top Platforms"
                        subtitle="Stores ranked by earnings"
                        rows={topPlatforms}
                        valueKey="earnings"
                        formatter={(value) =>
                            formatCurrency(value)
                        }
                    />
                </section>

                <section className="grid gap-6 lg:grid-cols-2">
                    <TopListCard
                        title="Top Countries"
                        subtitle="Territories ranked by earnings"
                        rows={topCountries}
                        valueKey="earnings"
                        formatter={(value) =>
                            formatCurrency(value)
                        }
                    />

                    <TopListCard
                        title="Currencies"
                        subtitle="Reported earnings by currency"
                        rows={currencySummary.map(
                            (item) => ({
                                name: item.currency,
                                earnings: item.earnings,
                            })
                        )}
                        valueKey="earnings"
                        formatter={(value) =>
                            formatDecimal(value)
                        }
                    />
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold text-slate-950">
                                Top Tracks
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Best-performing catalogue by earnings
                            </p>
                        </div>
                    </div>

                    <div className="mt-5 divide-y divide-slate-100">
                        {topTracks.length > 0 ? (
                            topTracks.map((track, index) => (
                                <div
                                    key={`${track.track_id}-${track.isrc}-${index}`}
                                    className="grid gap-3 py-4 sm:grid-cols-[auto_1fr_auto] sm:items-center"
                                >
                                    <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-violet-50 text-sm font-black text-violet-700">
                                        {index + 1}
                                    </div>

                                    <div className="min-w-0">
                                        <p className="truncate font-bold text-slate-900">
                                            {track.title}
                                        </p>

                                        <p className="mt-1 truncate text-sm text-slate-500">
                                            {track.artist}
                                            {track.isrc
                                                ? ` · ${track.isrc}`
                                                : ''}
                                        </p>
                                    </div>

                                    <div className="text-left sm:text-right">
                                        <p className="font-black text-slate-950">
                                            {formatCurrency(
                                                track.earnings
                                            )}
                                        </p>

                                        <p className="mt-1 text-xs text-slate-500">
                                            {formatNumber(
                                                track.streams
                                            )}{' '}
                                            streams
                                        </p>
                                    </div>
                                </div>
                            ))
                        ) : (
                            <div className="rounded-xl bg-slate-50 px-4 py-12 text-center text-sm text-slate-500">
                                No top-track data available.
                            </div>
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-5 py-4">
                        <h2 className="text-lg font-bold text-slate-950">
                            Detailed Report Rows
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Individual imported royalty records
                        </p>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        'Month',
                                        'Track',
                                        'Artist',
                                        'ISRC',
                                        'UPC',
                                        'Platform',
                                        'Country',
                                        'Streams',
                                        'Units',
                                        'Currency',
                                        'Earnings',
                                    ].map((heading) => (
                                        <th
                                            key={heading}
                                            className="whitespace-nowrap px-5 py-4 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {data.length > 0 ? (
                                    data.map((row) => (
                                        <tr
                                            key={row.id}
                                            className="transition hover:bg-slate-50"
                                        >
                                            <Cell>
                                                {row.sale_month || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.track_title ||
                                                    row.album_title ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.track_artist ||
                                                    row.album_artist ||
                                                    '—'}
                                            </Cell>

                                            <Cell>
                                                {row.isrc || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.upc || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.platform || '—'}
                                            </Cell>

                                            <Cell>
                                                {row.country_code || '—'}
                                            </Cell>

                                            <Cell>
                                                {formatNumber(
                                                    row.streams
                                                )}
                                            </Cell>

                                            <Cell>
                                                {formatNumber(
                                                    row.sale_units
                                                )}
                                            </Cell>

                                            <Cell>
                                                {row.currency || '—'}
                                            </Cell>

                                            <Cell>
                                                {formatDecimal(
                                                    row.earnings
                                                )}
                                            </Cell>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="11"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No report data found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                {rows.links && (
                    <div className="flex flex-wrap justify-center gap-2">
                        {rows.links.map((link, index) => (
                            <Link
                                key={index}
                                href={link.url ?? '#'}
                                preserveScroll
                                preserveState
                                className={[
                                    'rounded-xl border px-3 py-2 text-sm font-semibold',
                                    link.active
                                        ? 'border-violet-600 bg-violet-600 text-white'
                                        : 'border-slate-300 bg-white text-slate-700',
                                    !link.url
                                        ? 'pointer-events-none opacity-40'
                                        : '',
                                ].join(' ')}
                                dangerouslySetInnerHTML={{
                                    __html: link.label,
                                }}
                            />
                        ))}
                    </div>
                )}
            </div>
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
