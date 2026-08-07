import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
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

const formatDate = (value) => {
    if (!value) return '—';

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(new Date(value));
};

const statusStyles = {
    draft: 'bg-slate-100 text-slate-700',
    submitted: 'bg-blue-100 text-blue-700',
    approved: 'bg-emerald-100 text-emerald-700',
    processing: 'bg-amber-100 text-amber-700',
    delivered: 'bg-violet-100 text-violet-700',
    live: 'bg-green-100 text-green-700',
    rejected: 'bg-rose-100 text-rose-700',
    failed: 'bg-red-100 text-red-700',
    pending: 'bg-amber-100 text-amber-700',
    paid: 'bg-emerald-100 text-emerald-700',
};

function StatusBadge({ status = 'draft' }) {
    const normalized = String(status).toLowerCase();

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${
                statusStyles[normalized] ??
                'bg-slate-100 text-slate-700'
            }`}
        >
            {normalized.replaceAll('_', ' ')}
        </span>
    );
}

function EmptyState({
    icon: Icon,
    title,
    description,
    action = null,
}) {
    return (
        <div className="flex min-h-56 flex-col items-center justify-center px-6 py-10 text-center">
            <div className="flex h-14 w-14 items-center justify-center rounded-2xl bg-slate-100 text-slate-500">
                <Icon size={26} />
            </div>

            <h4 className="mt-4 font-bold text-slate-900">
                {title}
            </h4>

            <p className="mt-2 max-w-sm text-sm leading-6 text-slate-500">
                {description}
            </p>

            {action}
        </div>
    );
}

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

            <section className="mt-6 grid gap-5 md:grid-cols-3">
                {[
                    {
                        icon: Headphones,
                        title: 'Catalogue',
                        description:
                            'Manage all releases and tracks.',
                        href: '/artist/catalogue',
                    },
                    {
                        icon: TrendingUp,
                        title: 'Reports',
                        description:
                            'Review performance and earnings.',
                        href: '/artist/reports',
                    },
                    {
                        icon: FileAudio,
                        title: 'Statements',
                        description:
                            'Access royalty statements.',
                        href: '/artist/statements',
                    },
                ].map(
                    ({
                        icon: Icon,
                        title,
                        description,
                        href,
                    }) => (
                        <Link
                            key={title}
                            href={href}
                            className="group rounded-3xl border border-slate-200 bg-white p-6 shadow-sm transition hover:-translate-y-1 hover:border-violet-200 hover:shadow-lg"
                        >
                            <div className="flex h-12 w-12 items-center justify-center rounded-2xl bg-violet-100 text-violet-700">
                                <Icon size={22} />
                            </div>

                            <h3 className="mt-5 text-lg font-black text-slate-950">
                                {title}
                            </h3>

                            <p className="mt-2 text-sm leading-6 text-slate-500">
                                {description}
                            </p>

                            <div className="mt-5 inline-flex items-center gap-2 text-sm font-bold text-violet-700">
                                Open
                                <ArrowRight
                                    size={16}
                                    className="transition group-hover:translate-x-1"
                                />
                            </div>
                        </Link>
                    )
                )}
            </section>
        </PanelLayout>
    );
}
