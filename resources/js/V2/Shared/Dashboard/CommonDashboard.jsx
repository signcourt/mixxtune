import { Head } from '@inertiajs/react';
import {
    Clock3,
    Disc3,
    Landmark,
    Radio,
    Users,
    WalletCards,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import {
    ArtistActivitySection,
    DashboardAnalyticsSection,
    DashboardHero,
    DashboardKpiGrid,
} from '@/V2/Shared/Dashboard/Sections';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

const money = (value) =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency: 'INR',
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

export default function CommonDashboard({
    role = 'artist',
    panelName = 'Artist',
    stats = {},
    analytics = {},
    revenueSummary = null,
    recentReleases = [],
    recentTransactions = [],
    recentWithdrawals = [],
    quickActions = [],
}) {
    const isLabel = role === 'label';

    const cards = [
        {
            title: 'Total Releases',
            value: number(
                stats.totalReleases
            ),
            note: 'All releases visible to this account',
            icon: Disc3,
            tone: 'violet',
        },
        {
            title: 'Submitted',
            value: number(
                stats.submittedReleases
            ),
            note: 'Waiting for review',
            icon: Radio,
            tone: 'blue',
        },
        {
            title: 'Approved',
            value: number(
                stats.approvedReleases
            ),
            note: 'Approved catalogue',
            icon: Disc3,
            tone: 'emerald',
        },
        {
            title: isLabel
                ? 'Active Artists'
                : role === 'artist'
                  ? 'Available Balance'
                  : 'Wallet Balance',
            value: isLabel
                ? number(
                      stats.activeArtists
                  )
                : stats.walletBalance ??
                  '₹0.00',
            note: isLabel
                ? 'Artists under this label'
                : role === 'artist'
                  ? 'Available for withdrawal'
                  : 'Current available balance',
            icon: isLabel
                ? Users
                : WalletCards,
            tone: 'amber',
        },
    ];

    if (role === 'artist') {
        const currency =
            stats.walletCurrency || 'INR';

        const financeMoney = (value) =>
            new Intl.NumberFormat('en-IN', {
                style: 'currency',
                currency,
                maximumFractionDigits: 2,
            }).format(Number(value ?? 0));

        cards.push(
            {
                title: 'Pending Balance',
                value: financeMoney(
                    stats.walletPendingBalance
                ),
                note:
                    'Royalty balance pending availability',
                icon: Clock3,
                tone: 'amber',
            },
            {
                title: 'Pending Withdrawals',
                value: number(
                    stats.pendingWithdrawals
                ),
                note:
                    'Withdrawal requests currently in process',
                icon: Landmark,
                tone: 'blue',
            }
        );
    }

    const normalizedAnalytics = {
        has_data:
            analytics?.hasData ??
            false,

        summary: {
            total_streams:
                analytics?.summary
                    ?.streams ?? 0,

            total_earnings:
                analytics?.summary
                    ?.earnings ?? 0,

            unique_tracks:
                analytics?.topTracks
                    ?.length ?? 0,

            active_platforms:
                analytics?.topPlatforms
                    ?.length ?? 0,

            stream_growth_percent:
                0,

            earning_growth_percent:
                0,

            currency: 'INR',
        },

        monthly:
            analytics?.monthly ?? [],

        top_tracks: (
            analytics?.topTracks ?? []
        ).map((item) => ({
            ...item,
            title:
                item.title ||
                item.name ||
                'Untitled Track',
        })),

        top_platforms: (
            analytics?.topPlatforms ?? []
        ).map((item) => ({
            ...item,
            name:
                item.name ||
                'Unknown DSP',
        })),

        top_countries: (
            analytics?.topCountries ?? []
        ).map((item) => ({
            ...item,
            country_code:
                item.country_code ||
                item.code ||
                item.name ||
                'Unknown',
        })),
    };

    return (
        <PanelLayout
            role={role}
            title={`${panelName} Dashboard`}
            subtitle={`Welcome to ${panelName}`}
        >
            <Head
                title={`${panelName} Dashboard`}
            />

            <DashboardHero
                name={panelName}
                eyebrow={`${String(
                    panelName
                ).toUpperCase()} OVERVIEW`}
                description={
                    isLabel
                        ? 'Manage artists, releases, royalties and performance from one label workspace.'
                        : 'Manage releases, analytics and account activity from one professional workspace.'
                }
                primaryAction={{
                    label: 'Create Release',
                    href:
                        role === 'label'
                            ? '/label/releases'
                            : role === 'artist'
                              ? '/artist/releases/create'
                              : '/v2/releases/create',
                }}
                secondaryAction={{
                    label: isLabel
                        ? 'View Artists'
                        : 'View Releases',
                    href: isLabel
                        ? '/label/artists'
                        : role === 'artist'
                          ? '/artist/releases'
                          : '/v2/releases',
                }}
            />

            <DashboardKpiGrid
                cards={cards}
            />

            {(isLabel || role === 'artist') && revenueSummary && (
                <section className="mt-6 space-y-5">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
                        <div>
                            <h2 className="text-xl font-black text-slate-950">
                                Revenue Overview
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                {revenueSummary.is_master
                                    ? 'Master revenue, direct beneficiary allocations and retained earnings.'
                                    : 'Your allocated royalty revenue and payable earnings.'}
                            </p>
                        </div>

                        {revenueSummary.is_master && (
                            <span className="w-fit rounded-full bg-violet-50 px-3 py-1 text-xs font-bold text-violet-700">
                                Master Account
                            </span>
                        )}
                    </div>

                    {revenueSummary.is_master ? (
                        <>
                            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Managed Revenue
                                    </p>

                                    <p className="mt-3 text-2xl font-black text-slate-950">
                                        {money(
                                            revenueSummary.managed_revenue
                                        )}
                                    </p>

                                    <p className="mt-2 text-xs leading-5 text-slate-500">
                                        Gross revenue managed across direct sub-labels and artists.
                                    </p>
                                </article>

                                <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Allocated Revenue
                                    </p>

                                    <p className="mt-3 text-2xl font-black text-slate-950">
                                        {money(
                                            revenueSummary.allocated_revenue
                                        )}
                                    </p>

                                    <p className="mt-2 text-xs leading-5 text-slate-500">
                                        Revenue allocated to direct sub-label and artist accounts.
                                    </p>
                                </article>

                                <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Retained Revenue
                                    </p>

                                    <p className="mt-3 text-2xl font-black text-slate-950">
                                        {money(
                                            revenueSummary.retained_revenue
                                        )}
                                    </p>

                                    <p className="mt-2 text-xs leading-5 text-slate-500">
                                        Master share retained from direct revenue-share allocations.
                                    </p>
                                </article>

                                <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                    <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                        Master Payable
                                    </p>

                                    <p className="mt-3 text-2xl font-black text-slate-950">
                                        {money(
                                            revenueSummary.payable_revenue
                                        )}
                                    </p>

                                    <p className="mt-2 text-xs leading-5 text-slate-500">
                                        Actual royalty payable to this master account.
                                    </p>
                                </article>
                            </div>

                            {false && revenueSummary.children?.length > 0 && (
                                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                                    <div className="border-b border-slate-200 px-6 py-5">
                                        <h3 className="text-lg font-black text-slate-950">
                                            Revenue Beneficiary Breakdown
                                        </h3>

                                        <p className="mt-1 text-sm text-slate-500">
                                            Direct sub-label and artist allocation with master retention.
                                        </p>
                                    </div>

                                    <div className="overflow-x-auto">
                                        <table className="min-w-full divide-y divide-slate-200">
                                            <thead className="bg-slate-50">
                                                <tr>
                                                    {[
                                                        'Type',
                                                        'Beneficiary',
                                                        'Managed',
                                                        'Share',
                                                        'Beneficiary Payable',
                                                        'Master Retained',
                                                    ].map(
                                                        (
                                                            heading
                                                        ) => (
                                                            <th
                                                                key={
                                                                    heading
                                                                }
                                                                className="whitespace-nowrap px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500"
                                                            >
                                                                {
                                                                    heading
                                                                }
                                                            </th>
                                                        )
                                                    )}
                                                </tr>
                                            </thead>

                                            <tbody className="divide-y divide-slate-100">
                                                {revenueSummary.children.map(
                                                    (
                                                        child
                                                    ) => (
                                                        <tr
                                                            key={`${child.type}-${child.id}`}
                                                            className="hover:bg-slate-50"
                                                        >
                                                            <td className="whitespace-nowrap px-5 py-4">
                                                                <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-bold ${
                                                                    child.type === 'artist'
                                                                        ? 'bg-blue-50 text-blue-700'
                                                                        : 'bg-violet-50 text-violet-700'
                                                                }`}>
                                                                    {child.type === 'artist'
                                                                        ? 'Artist'
                                                                        : 'Sub-Label'}
                                                                </span>
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {
                                                                    child.name
                                                                }
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-700">
                                                                {money(
                                                                    child.managed_revenue
                                                                )}
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-semibold text-slate-700">
                                                                {number(
                                                                    child.share_percent
                                                                )}
                                                                %
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {money(
                                                                    child.allocated_revenue
                                                                )}
                                                            </td>

                                                            <td className="whitespace-nowrap px-5 py-4 text-sm font-bold text-slate-900">
                                                                {money(
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
                            <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Your Revenue
                                </p>

                                <p className="mt-3 text-2xl font-black text-slate-950">
                                    {money(
                                        revenueSummary.allocated_revenue
                                    )}
                                </p>

                                <p className="mt-2 text-xs leading-5 text-slate-500">
                                    Revenue allocated to your account.
                                </p>
                            </article>

                            <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                    Payable Revenue
                                </p>

                                <p className="mt-3 text-2xl font-black text-slate-950">
                                    {money(
                                        revenueSummary.payable_revenue
                                    )}
                                </p>

                                <p className="mt-2 text-xs leading-5 text-slate-500">
                                    Your current royalty entitlement.
                                </p>
                            </article>

                            {revenueSummary.share_visible &&
                                revenueSummary.share_percent !== null && (
                                    <article className="rounded-3xl border border-slate-200 bg-white p-5 shadow-sm">
                                        <p className="text-xs font-bold uppercase tracking-wide text-slate-500">
                                            Revenue Share
                                        </p>

                                        <p className="mt-3 text-2xl font-black text-slate-950">
                                            {number(
                                                revenueSummary.share_percent
                                            )}
                                            %
                                        </p>

                                        <p className="mt-2 text-xs leading-5 text-slate-500">
                                            Your configured revenue-share percentage.
                                        </p>
                                    </article>
                                )}
                        </div>
                    )}
                </section>
            )}

            <DashboardAnalyticsSection
                analytics={
                    normalizedAnalytics
                }
                currency="INR"
                reportsHref={
                    role === 'label'
                        ? '/label/reports'
                        : role === 'artist'
                          ? '/artist/reports'
                          : '/v2/reports'
                }
            />

            {role === 'artist' && (
                <ArtistActivitySection
                    recentReleases={recentReleases}
                    recentTransactions={recentTransactions}
                    recentWithdrawals={recentWithdrawals}
                    quickActions={quickActions}
                    currency={
                        stats.walletCurrency || 'INR'
                    }
                />
            )}

            {role !== 'artist' && (
                <section className="mt-6 rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                <h3 className="text-lg font-black text-slate-950">
                    Recent Releases
                </h3>

                <p className="mt-1 text-sm text-slate-500">
                    Latest release activity for this panel.
                </p>

                <div className="mt-5">
                    {recentReleases.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentReleases.map(
                                (release) => (
                                    <div
                                        key={
                                            release.id
                                        }
                                        className="flex items-center justify-between gap-4 py-4"
                                    >
                                        <div className="min-w-0">
                                            <p className="truncate font-bold text-slate-900">
                                                {release.title ||
                                                    'Untitled Release'}
                                            </p>

                                            <p className="mt-1 truncate text-xs text-slate-500">
                                                {release.primary_artist_name ||
                                                    'Artist not available'}
                                            </p>
                                        </div>

                                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                                            {String(
                                                release.status ||
                                                    'draft'
                                            ).replaceAll(
                                                '_',
                                                ' '
                                            )}
                                        </span>
                                    </div>
                                )
                            )}
                        </div>
                    ) : (
                        <div className="rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-10 text-center text-sm text-slate-500">
                            No releases found.
                        </div>
                    )}
                </div>
            </section>
            )}
        </PanelLayout>
    );
}
