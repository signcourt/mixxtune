import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
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

function StatCard({
    title,
    value,
    note,
    icon: Icon,
    tone = 'violet',
}) {
    const tones = {
        violet: {
            icon: 'bg-violet-100 text-violet-700',
            glow: 'from-violet-500/10',
        },
        blue: {
            icon: 'bg-blue-100 text-blue-700',
            glow: 'from-blue-500/10',
        },
        emerald: {
            icon: 'bg-emerald-100 text-emerald-700',
            glow: 'from-emerald-500/10',
        },
        amber: {
            icon: 'bg-amber-100 text-amber-700',
            glow: 'from-amber-500/10',
        },
    };

    const style = tones[tone] ?? tones.violet;

    return (
        <article className="relative overflow-hidden rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <div
                className={`absolute inset-x-0 top-0 h-24 bg-gradient-to-b ${style.glow} to-transparent`}
            />

            <div className="relative flex items-start justify-between gap-4">
                <div>
                    <p className="text-sm font-semibold text-slate-500">
                        {title}
                    </p>

                    <p className="mt-3 text-3xl font-black tracking-tight text-slate-950">
                        {value}
                    </p>

                    <p className="mt-3 text-xs leading-5 text-slate-400">
                        {note}
                    </p>
                </div>

                <div
                    className={`flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl ${style.icon}`}
                >
                    <Icon size={22} strokeWidth={2.2} />
                </div>
            </div>
        </article>
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

            <section className="relative overflow-hidden rounded-[32px] bg-gradient-to-br from-[#090f1d] via-[#111a2e] to-[#22164b] px-6 py-8 text-white shadow-2xl sm:px-8 lg:px-10 lg:py-10">
                <div className="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl" />
                <div className="absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl" />

                <div className="relative flex flex-col justify-between gap-8 xl:flex-row xl:items-center">
                    <div className="max-w-2xl">
                        <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-semibold text-violet-100">
                            <Sparkles size={14} />
                            ARTIST OVERVIEW
                        </div>

                        <h2 className="mt-5 text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
                            Welcome, {artistName}
                        </h2>

                        <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                            Manage releases, monitor royalties,
                            track wallet activity and distribute
                            your music from one professional
                            workspace.
                        </p>

                        <div className="mt-6 flex flex-wrap gap-3">
                            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs font-semibold capitalize">
                                <CheckCircle2
                                    size={14}
                                    className="text-emerald-300"
                                />
                                Account:{' '}
                                {artist.account_status ||
                                    'active'}
                            </span>

                            <span className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-4 py-2 text-xs font-semibold capitalize">
                                <Landmark
                                    size={14}
                                    className="text-blue-300"
                                />
                                KYC:{' '}
                                {artist.kyc_status ||
                                    'pending'}
                            </span>
                        </div>
                    </div>

                    <div className="flex flex-col gap-3 sm:flex-row xl:flex-col">
                        <Link
                            href="/artist/releases/create"
                            className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg transition hover:-translate-y-0.5 hover:bg-violet-50"
                        >
                            <Plus size={18} />
                            Create Release
                        </Link>

                        <Link
                            href="/artist/catalogue"
                            className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/15"
                        >
                            View Catalogue
                            <ArrowRight size={17} />
                        </Link>
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
                {cards.map((card) => (
                    <StatCard
                        key={card.title}
                        {...card}
                    />
                ))}
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-[1.55fr_1fr]">
                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-col gap-4 border-b border-slate-200 px-6 py-5 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="text-lg font-black text-slate-950">
                                Recent Releases
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Latest music added to your catalogue
                            </p>
                        </div>

                        <Link
                            href="/artist/releases"
                            className="inline-flex items-center gap-2 text-sm font-bold text-violet-700"
                        >
                            View All
                            <ArrowRight size={16} />
                        </Link>
                    </div>

                    {recentReleases.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentReleases.map(
                                (release) => (
                                    <div
                                        key={release.id}
                                        className="flex items-center gap-4 px-6 py-4 transition hover:bg-slate-50"
                                    >
                                        <div className="flex h-14 w-14 shrink-0 items-center justify-center overflow-hidden rounded-2xl bg-gradient-to-br from-violet-100 to-blue-100 text-violet-700">
                                            {release.artwork_path ? (
                                                <img
                                                    src={`/${release.artwork_path}`}
                                                    alt={
                                                        release.title ||
                                                        'Release artwork'
                                                    }
                                                    className="h-full w-full object-cover"
                                                />
                                            ) : (
                                                <Music2
                                                    size={24}
                                                />
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-bold text-slate-900">
                                                {release.title ||
                                                    'Untitled Release'}
                                            </p>

                                            <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-500">
                                                <span className="capitalize">
                                                    {release.release_type ||
                                                        'Release'}
                                                </span>

                                                <span>•</span>

                                                <span className="inline-flex items-center gap-1">
                                                    <CalendarDays
                                                        size={
                                                            12
                                                        }
                                                    />
                                                    {formatDate(
                                                        release.digital_release_date ||
                                                            release.created_at
                                                    )}
                                                </span>
                                            </div>
                                        </div>

                                        <StatusBadge
                                            status={
                                                release.status
                                            }
                                        />
                                    </div>
                                )
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            icon={Disc3}
                            title="No releases yet"
                            description="Create your first release and begin distributing your music."
                            action={
                                <Link
                                    href="/artist/releases/create"
                                    className="mt-5 inline-flex items-center gap-2 rounded-2xl bg-slate-950 px-5 py-3 text-sm font-bold text-white"
                                >
                                    <Plus size={17} />
                                    Create First Release
                                </Link>
                            }
                        />
                    )}
                </div>

                <div className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div>
                        <h3 className="text-lg font-black text-slate-950">
                            Quick Actions
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Frequently used artist tools
                        </p>
                    </div>

                    <div className="mt-5 space-y-3">
                        {quickActions.map(
                            ({
                                label,
                                description,
                                href,
                                icon: Icon,
                            }) => (
                                <Link
                                    key={label}
                                    href={href}
                                    className="group flex items-center gap-4 rounded-2xl border border-slate-200 px-4 py-4 transition hover:border-violet-200 hover:bg-violet-50/50"
                                >
                                    <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 text-slate-700 transition group-hover:bg-violet-100 group-hover:text-violet-700">
                                        <Icon size={20} />
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <p className="font-bold text-slate-900">
                                            {label}
                                        </p>

                                        <p className="mt-0.5 truncate text-xs text-slate-500">
                                            {description}
                                        </p>
                                    </div>

                                    <ArrowRight
                                        size={17}
                                        className="text-slate-400 transition group-hover:translate-x-1 group-hover:text-violet-700"
                                    />
                                </Link>
                            )
                        )}
                    </div>
                </div>
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-2">
                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 className="text-lg font-black text-slate-950">
                                Wallet Activity
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Recent credits and debits
                            </p>
                        </div>

                        <Link
                            href="/artist/wallet"
                            className="text-sm font-bold text-violet-700"
                        >
                            Open Wallet
                        </Link>
                    </div>

                    {recentTransactions.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentTransactions.map(
                                (transaction) => {
                                    const credit =
                                        transaction.direction ===
                                        'credit';

                                    return (
                                        <div
                                            key={
                                                transaction.id
                                            }
                                            className="flex items-center gap-4 px-6 py-4"
                                        >
                                            <div
                                                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${
                                                    credit
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-rose-100 text-rose-700'
                                                }`}
                                            >
                                                {credit ? (
                                                    <ArrowDownRight
                                                        size={
                                                            20
                                                        }
                                                    />
                                                ) : (
                                                    <ArrowUpRight
                                                        size={
                                                            20
                                                        }
                                                    />
                                                )}
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-bold text-slate-900">
                                                    {transaction.description ||
                                                        transaction.transaction_type ||
                                                        'Wallet transaction'}
                                                </p>

                                                <p className="mt-1 text-xs text-slate-500">
                                                    {formatDate(
                                                        transaction.posted_at ||
                                                            transaction.created_at
                                                    )}
                                                </p>
                                            </div>

                                            <div className="text-right">
                                                <p
                                                    className={`font-black ${
                                                        credit
                                                            ? 'text-emerald-600'
                                                            : 'text-rose-600'
                                                    }`}
                                                >
                                                    {credit
                                                        ? '+'
                                                        : '-'}
                                                    {money(
                                                        transaction.amount,
                                                        transaction.currency ||
                                                            currency
                                                    )}
                                                </p>

                                                <p className="mt-1 text-xs capitalize text-slate-500">
                                                    {transaction.status ||
                                                        'posted'}
                                                </p>
                                            </div>
                                        </div>
                                    );
                                }
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            icon={WalletCards}
                            title="No wallet activity"
                            description="Royalty credits and withdrawal transactions will appear here."
                        />
                    )}
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex items-center justify-between border-b border-slate-200 px-6 py-5">
                        <div>
                            <h3 className="text-lg font-black text-slate-950">
                                Recent Withdrawals
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Latest payout requests
                            </p>
                        </div>

                        <Link
                            href="/artist/withdrawals"
                            className="text-sm font-bold text-violet-700"
                        >
                            View All
                        </Link>
                    </div>

                    {recentWithdrawals.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentWithdrawals.map(
                                (withdrawal) => (
                                    <div
                                        key={withdrawal.id}
                                        className="flex items-center gap-4 px-6 py-4"
                                    >
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-blue-100 text-blue-700">
                                            <Landmark
                                                size={20}
                                            />
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="truncate font-bold text-slate-900">
                                                {withdrawal.withdrawal_number ||
                                                    'Withdrawal'}
                                            </p>

                                            <p className="mt-1 text-xs text-slate-500">
                                                {formatDate(
                                                    withdrawal.requested_at
                                                )}
                                            </p>
                                        </div>

                                        <div className="text-right">
                                            <p className="font-black text-slate-900">
                                                {money(
                                                    withdrawal.amount,
                                                    withdrawal.currency ||
                                                        currency
                                                )}
                                            </p>

                                            <div className="mt-1">
                                                <StatusBadge
                                                    status={
                                                        withdrawal.status ||
                                                        'pending'
                                                    }
                                                />
                                            </div>
                                        </div>
                                    </div>
                                )
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            icon={Landmark}
                            title="No withdrawals yet"
                            description="Your payout requests and their current status will appear here."
                            action={
                                <Link
                                    href="/artist/withdrawals"
                                    className="mt-5 inline-flex items-center gap-2 rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-bold text-slate-800"
                                >
                                    Request Withdrawal
                                    <ArrowRight
                                        size={16}
                                    />
                                </Link>
                            }
                        />
                    )}
                </div>
            </section>

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
