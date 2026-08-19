import { Link } from '@inertiajs/react';
import { ArrowRight, TrendingUp } from 'lucide-react';
import TrendChart from '@/V2/Shared/Components/Analytics/TrendChart';
import PerformanceList from '@/V2/Shared/Dashboard/Widgets/PerformanceList';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

export default function DashboardAnalyticsSection({
    analytics = {},
    currency = 'INR',
    reportsHref = '/artist/reports',
}) {
    const summary = analytics?.summary || {};
    const reportCurrency =
        summary.currency ||
        currency ||
        'INR';

    const summaryCards = [
        {
            title: 'Total Streams',
            value: number(summary.total_streams),
            note: `${Number(
                summary.stream_growth_percent || 0
            ).toFixed(1)}% monthly growth`,
        },
        {
            title: 'Reported Earnings',
            value: money(
                summary.total_earnings,
                reportCurrency
            ),
            note: `${Number(
                summary.earning_growth_percent || 0
            ).toFixed(1)}% monthly growth`,
        },
        {
            title: 'Reported Tracks',
            value: number(summary.unique_tracks),
            note: 'Tracks with mapped reports',
        },
        {
            title: 'Active Platforms',
            value: number(summary.active_platforms),
            note: 'DSPs with reporting activity',
        },
    ];

    return (
        <>
            <section className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p className="text-xs font-black uppercase tracking-[0.18em] text-violet-600">
                            Performance Analytics
                        </p>

                        <h3 className="mt-2 text-xl font-black text-slate-950">
                            Streams & Earnings
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Real reporting data mapped to your account.
                        </p>
                    </div>

                    <Link
                        href={reportsHref}
                        className="inline-flex items-center gap-2 text-sm font-bold text-violet-700"
                    >
                        Full Reports
                        <ArrowRight size={16} />
                    </Link>
                </div>

                <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    {summaryCards.map((item) => (
                        <div
                            key={item.title}
                            className="rounded-2xl bg-slate-50 p-5"
                        >
                            <p className="text-xs font-bold uppercase tracking-wider text-slate-400">
                                {item.title}
                            </p>

                            <p className="mt-2 text-2xl font-black text-slate-950">
                                {item.value}
                            </p>

                            <p className="mt-2 text-xs font-semibold text-slate-500">
                                {item.note}
                            </p>
                        </div>
                    ))}
                </div>

                {analytics?.has_data ? (
                    <div className="mt-6 grid gap-6 xl:grid-cols-[1.5fr_1fr]">
                        <TrendChart
                            rows={analytics?.monthly || []}
                            metric="streams"
                            title="Monthly Streams"
                        />

                        <PerformanceList
                            rows={analytics?.top_platforms || []}
                            title="Top Platforms"
                            emptyText="No platform analytics available."
                            labelKey="name"
                            valueKey="streams"
                            secondaryKey="earnings"
                            formatValue={number}
                            formatSecondary={(value) =>
                                money(
                                    value,
                                    reportCurrency
                                )
                            }
                        />
                    </div>
                ) : (
                    <div className="mt-6 rounded-2xl border border-dashed border-slate-300 bg-slate-50 p-8 text-center">
                        <TrendingUp
                            size={26}
                            className="mx-auto text-slate-400"
                        />

                        <p className="mt-3 font-black text-slate-900">
                            No reporting analytics yet
                        </p>

                        <p className="mt-1 text-sm text-slate-500">
                            Charts will populate automatically after mapped reports are imported.
                        </p>
                    </div>
                )}
            </section>

            {analytics?.has_data && (
                <section className="mt-6 grid gap-6 xl:grid-cols-2">
                    <PerformanceList
                        rows={analytics?.top_tracks || []}
                        title="Top Performing Tracks"
                        emptyText="No track analytics available."
                        labelKey="title"
                        valueKey="streams"
                        secondaryKey="earnings"
                        formatValue={number}
                        formatSecondary={(value) =>
                            money(
                                value,
                                reportCurrency
                            )
                        }
                    />

                    <PerformanceList
                        rows={analytics?.top_countries || []}
                        title="Top Countries"
                        emptyText="No country analytics available."
                        labelKey="country_code"
                        valueKey="streams"
                        secondaryKey="earnings"
                        formatValue={number}
                        formatSecondary={(value) =>
                            money(
                                value,
                                reportCurrency
                            )
                        }
                    />
                </section>
            )}
        </>
    );
}
