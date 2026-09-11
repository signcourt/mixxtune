import { Head } from '@inertiajs/react';
import {
    BadgeIndianRupee,
    Disc3,
    FileUp,
    Headphones,
    Landmark,
    Library,
    Music2,
    Radio,
    ShieldCheck,
    TrendingUp,
    Users,
    WalletCards,
} from 'lucide-react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import {
    AdminOperationsSection,
    DashboardAnalyticsSection,
    DashboardHero,
    DashboardKpiGrid,
    SuperAdminExecutiveSection,
} from '@/V2/Shared/Dashboard/Sections';
import {
    DashboardLinkCards,
    QuickActionsCard,
} from '@/V2/Shared/Dashboard/Widgets';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

export default function Dashboard({
    role = 'admin',
    stats = {},
    analytics = {},
    recentReleases = [],
    recentImports = [],
    recentWithdrawals = [],
}) {
    const isSuperAdmin =
        role === 'super_admin';

    const panelName = isSuperAdmin
        ? 'Super Admin'
        : 'Manager';

    const cards = [
        {
            /*
             * Super Admin sees retained Mixx Tune profit:
             *
             * collected revenue - user earning.
             *
             * Normal Admin keeps the existing scoped
             * reported earnings card.
             */
            title: isSuperAdmin
                ? 'Profit Earnings'
                : 'Reported Earnings',
            value: money(
                isSuperAdmin
                    ? stats.profit_earnings
                    : stats.earnings,
                'INR'
            ),
            note: isSuperAdmin
                ? 'Retained Mixx Tune revenue'
                : `${number(
                      stats.streams
                  )} reported streams`,
            icon: BadgeIndianRupee,
            tone: 'emerald',
        },
        {
            title: 'Artists',
            value: number(
                stats.artists
            ),
            note: 'Artists in your scope',
            icon: Users,
            tone: 'violet',
        },
        {
            title: 'Labels',
            value: number(
                stats.labels
            ),
            note: 'Labels in your scope',
            icon: Library,
            tone: 'blue',
        },
        {
            title: 'Releases',
            value: number(
                stats.total_releases
            ),
            note: `${number(
                stats.live
            )} live releases`,
            icon: Disc3,
            tone: 'amber',
        },
    ];

    const normalizedAnalytics = {
        has_data:
            Number(stats.streams || 0) > 0 ||
            Number(stats.earnings || 0) !== 0,

        summary: {
            total_streams:
                stats.streams || 0,
            total_earnings:
                stats.earnings || 0,
            unique_tracks:
                stats.tracks || 0,
            active_platforms:
                analytics?.topPlatforms
                    ?.length || 0,
            stream_growth_percent: 0,
            earning_growth_percent: 0,
            currency: 'INR',
        },

        monthly:
            analytics?.monthly || [],

        top_tracks: (
            analytics?.topTracks || []
        ).map((item) => ({
            ...item,
            title:
                item.title ||
                item.name ||
                'Untitled Track',
        })),

        top_platforms: (
            analytics?.topPlatforms || []
        ).map((item) => ({
            ...item,
            name:
                item.name ||
                'Unknown DSP',
        })),

        top_countries: (
            analytics?.topCountries || []
        ).map((item) => ({
            ...item,
            country_code:
                item.country_code ||
                item.code ||
                item.name ||
                'Unknown',
        })),
    };

    const quickActions = [
        ...(isSuperAdmin
            ? [
                  {
                      label: 'Business Profit',
                      description:
                          'View retained revenue and account earnings',
                      href: '/v2/admin/profit',
                      icon: Landmark,
                  },
              ]
            : []),
        {
            label: 'Review Queue',
            description: 'Approve or reject releases',
            href: '/v2/admin/release-reviews',
            icon: ShieldCheck,
        },
        {
            label: 'Import Revenue',
            description: 'Upload DSP reports',
            href: '/v2/admin/reports/imports',
            icon: FileUp,
        },
        {
            label: 'Withdrawals',
            description: 'Manage payout requests',
            href: '/admin/withdrawals',
            icon: WalletCards,
        },
        {
            label: 'Distribution',
            description: 'Review delivery operations',
            href: '/v2/admin/distribution',
            icon: Radio,
        },
    ];

    const dashboardLinks = [
        ...(isSuperAdmin
            ? [
                  {
                      title: 'Business Profit',
                      description:
                          'Collected revenue, account earnings and retained Mixx Tune profit.',
                      href: '/v2/admin/profit',
                      icon: Landmark,
                  },
              ]
            : []),
        {
            title: 'Release Reviews',
            description:
                'Review submitted releases and approval workflow.',
            href: '/v2/admin/release-reviews',
            icon: ShieldCheck,
        },
        {
            title: 'Reports',
            description:
                'Inspect revenue, streams and reporting performance.',
            href: '/v2/admin/reports/imports',
            icon: TrendingUp,
        },
        {
            title: 'Distribution',
            description:
                'Manage DSP deliveries and release processing.',
            href: '/v2/admin/distribution',
            icon: Music2,
        },
    ];

    return (
        <PanelLayout
            role={role}
            title={`${panelName} Dashboard`}
            subtitle="Operations, analytics and platform control"
        >
            <Head
                title={`${panelName} Dashboard`}
            />

            <DashboardHero
                name={panelName}
                eyebrow={`${panelName.toUpperCase()} CONTROL CENTER`}
                description={
                    isSuperAdmin
                        ? 'Monitor platform-wide revenue, releases, distribution, payouts and operational health.'
                        : 'Manage assigned artists, labels, releases, revenue reports and daily operations.'
                }
                primaryAction={{
                    label: 'Review Queue',
                    href: '/v2/admin/release-reviews',
                }}
                secondaryAction={{
                    label: 'Import Revenue',
                    href: '/v2/admin/reports/imports',
                }}
                accountStatus="active"
                kycStatus="verified"
            />

            <DashboardKpiGrid
                cards={cards}
            />

            {isSuperAdmin && (
                <SuperAdminExecutiveSection
                    stats={stats}
                />
            )}

            <section className="mt-6">
                <QuickActionsCard
                    actions={quickActions}
                    title="Manager Quick Actions"
                    subtitle="Most-used operational tools"
                />
            </section>

            <DashboardAnalyticsSection
                analytics={
                    normalizedAnalytics
                }
                currency="INR"
                reportsHref="/v2/admin/reports/imports"
            />

            <AdminOperationsSection
                stats={stats}
                recentReleases={recentReleases}
                recentImports={recentImports}
                recentWithdrawals={recentWithdrawals}
            />

            <div className="mt-6">
                <DashboardLinkCards
                    items={dashboardLinks}
                />
            </div>
        </PanelLayout>
    );
}
