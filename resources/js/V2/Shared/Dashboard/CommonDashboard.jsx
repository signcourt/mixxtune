import { Head } from '@inertiajs/react';
import {
    Disc3,
    Radio,
    Users,
    WalletCards,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import {
    DashboardAnalyticsSection,
    DashboardHero,
    DashboardKpiGrid,
} from '@/V2/Shared/Dashboard/Sections';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

export default function CommonDashboard({
    role = 'artist',
    panelName = 'Artist',
    stats = {},
    analytics = {},
    recentReleases = [],
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
                : 'Wallet Balance',
            value: isLabel
                ? number(
                      stats.activeArtists
                  )
                : stats.walletBalance ??
                  '₹0.00',
            note: isLabel
                ? 'Artists under this label'
                : 'Current available balance',
            icon: isLabel
                ? Users
                : WalletCards,
            tone: 'amber',
        },
    ];

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
                            : '/v2/releases/create',
                }}
                secondaryAction={{
                    label: isLabel
                        ? 'View Artists'
                        : 'View Releases',
                    href: isLabel
                        ? '/label/artists'
                        : '/v2/releases',
                }}
            />

            <DashboardKpiGrid
                cards={cards}
            />

            <DashboardAnalyticsSection
                analytics={
                    normalizedAnalytics
                }
                currency="INR"
                reportsHref={
                    role === 'label'
                        ? '/label/reports'
                        : '/v2/reports'
                }
            />

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
        </PanelLayout>
    );
}
