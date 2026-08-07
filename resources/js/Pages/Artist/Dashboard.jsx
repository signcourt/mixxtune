import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import { DashboardLinkCards } from '@/V2/Shared/Dashboard/Widgets';
import {
    ArtistActivitySection,
    DashboardAnalyticsSection,
    DashboardHero,
    DashboardKpiGrid,
} from '@/V2/Shared/Dashboard/Sections';
import { Head, Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    BadgeIndianRupee,
    CalendarDays,
    CheckCircle2,
    Clock3,
    Disc3,
    FileAudio,
    Headphones,
    Landmark,
    Music2,
    Plus,
    Radio,
    Sparkles,
    TrendingUp,
    UploadCloud,
    WalletCards,
} from 'lucide-react';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

export default function Dashboard({
    artist = {},
    stats = {},
    analytics = {},
    recentReleases = [],
    recentTransactions = [],
    recentWithdrawals = [],
}) {
    const currency =
        stats.currency ||
        artist.currency ||
        'INR';

    const artistName =
        artist.stage_name ||
        artist.legal_name ||
        'Artist';

    const cards = [
        {
            title: 'Total Releases',
            value: number(stats.total_releases),
            note: 'All submitted music releases',
            icon: Disc3,
            tone: 'violet',
        },
        {
            title: 'Live Releases',
            value: number(stats.live_releases),
            note: 'Approved, delivered or live',
            icon: Radio,
            tone: 'blue',
        },
        {
            title: 'Available Wallet',
            value: money(
                stats.wallet_balance,
                currency
            ),
            note: 'Ready for withdrawal',
            icon: WalletCards,
            tone: 'emerald',
        },
        {
            title: 'Pending Withdrawals',
            value: number(
                stats.pending_withdrawals
            ),
            note: `Pending royalty ${money(
                stats.pending_balance,
                currency
            )}`,
            icon: Clock3,
            tone: 'amber',
        },
    ];

    const quickActions = [
        {
            label: 'Create New Release',
            description: 'Upload and distribute music',
            href: '/artist/releases/create',
            icon: UploadCloud,
        },
        {
            label: 'Open Catalogue',
            description: 'View releases and tracks',
            href: '/artist/catalogue',
            icon: Music2,
        },
        {
            label: 'View Royalties',
            description: 'Review earnings and statements',
            href: '/artist/royalties',
            icon: BadgeIndianRupee,
        },
        {
            label: 'Open Wallet',
            description: 'Balance and transactions',
            href: '/artist/wallet',
            icon: WalletCards,
        },
    ];

    const dashboardLinks = [
        {
            title: 'Catalogue',
            description: 'Browse your releases, tracks and catalogue metadata.',
            href: '/artist/catalogue',
            icon: Music2,
        },
        {
            title: 'Reports',
            description: 'View streaming, platform and territory performance.',
            href: '/artist/reports',
            icon: TrendingUp,
        },
        {
            title: 'Statements',
            description: 'Review royalty statements and payment history.',
            href: '/artist/statements',
            icon: FileAudio,
        },
    ];

    return (
        <PanelLayout
            role="artist"
            title="Artist Dashboard"
            subtitle={`Welcome back, ${artistName}`}
        >
            <Head title="Artist Dashboard" />

            <DashboardHero
                name={artistName}
                accountStatus={
                    artist.account_status ||
                    'active'
                }
                kycStatus={
                    artist.kyc_status ||
                    'pending'
                }
            />

            <DashboardKpiGrid
                cards={cards}
            />

            <DashboardAnalyticsSection
                analytics={analytics}
                currency={currency}
                reportsHref="/artist/reports"
            />

            <ArtistActivitySection
                recentReleases={recentReleases}
                recentTransactions={recentTransactions}
                recentWithdrawals={recentWithdrawals}
                quickActions={quickActions}
                currency={currency}
            />

            <div className="mt-6">
                <DashboardLinkCards
                    items={dashboardLinks}
                />
            </div>
        </PanelLayout>
    );
}
