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
        : 'Admin';

    const cards = [
        {
            title: 'Revenue',
            value: money(
                stats.earnings,
                'INR'
            ),
            note: `${number(
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
        {
            label: 'Review Queue',
            description: 'Approve or reject releases',
            href: '/admin/release-reviews',
            icon: ShieldCheck,
        },
        {
            label: 'Import Revenue',
            description: 'Upload DSP reports',
            href: '/admin/reports/imports',
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
        {
            title: 'Release Reviews',
            description:
                'Review submitted releases and approval workflow.',
            href: '/admin/release-reviews',
            icon: ShieldCheck,
        },
        {
            title: 'Reports',
            description:
                'Inspect revenue, streams and reporting performance.',
            href: '/admin/reports/imports',
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
                    href: '/admin/release-reviews',
                }}
                secondaryAction={{
                    label: 'Import Revenue',
                    href: '/admin/reports/imports',
                }}
                accountStatus="active"
                kycStatus="verified"
            />

            <DashboardKpiGrid
                cards={cards}
            />

            <section className="mt-6">
                <QuickActionsCard
                    actions={quickActions}
                    title="Admin Quick Actions"
                    subtitle="Most-used operational tools"
                />
            </section>

            <DashboardAnalyticsSection
                analytics={
                    normalizedAnalytics
                }
                currency="INR"
                reportsHref="/admin/reports/imports"
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
