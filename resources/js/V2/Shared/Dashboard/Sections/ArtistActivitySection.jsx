import { Link } from '@inertiajs/react';
import {
    ArrowDownRight,
    ArrowRight,
    ArrowUpRight,
    CalendarDays,
    Clock3,
    Disc3,
    Landmark,
    Music2,
    Plus,
    WalletCards,
} from 'lucide-react';
import QuickActionsCard from '@/V2/Shared/Dashboard/Widgets/QuickActionsCard';

const money = (value, currency = 'INR') =>
    new Intl.NumberFormat('en-IN', {
        style: 'currency',
        currency,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0));

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

function StatusBadge({
    status = 'draft',
}) {
    const normalized =
        String(status).toLowerCase();

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

export default function ArtistActivitySection({
    recentReleases = [],
    recentTransactions = [],
    recentWithdrawals = [],
    quickActions = [],
    currency = 'INR',
}) {
    return (
        <>
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
                            {recentReleases.map((release) => (
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
                                            <Music2 size={24} />
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
                                                <CalendarDays size={12} />

                                                {formatDate(
                                                    release.digital_release_date ||
                                                        release.created_at
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <StatusBadge
                                        status={release.status}
                                    />
                                </div>
                            ))}
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

                <QuickActionsCard
                    actions={quickActions}
                    title="Quick Actions"
                    subtitle="Frequently used artist tools"
                />
            </section>

            <section className="mt-6 grid gap-6 xl:grid-cols-2">
                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-lg font-black text-slate-950">
                            Wallet Activity
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Latest wallet transactions
                        </p>
                    </div>

                    {recentTransactions.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentTransactions.map(
                                (transaction, index) => {
                                    const amount =
                                        Number(
                                            transaction.amount ||
                                                0
                                        );

                                    const positive =
                                        amount >= 0;

                                    return (
                                        <div
                                            key={
                                                transaction.id ||
                                                index
                                            }
                                            className="flex items-center gap-4 px-6 py-4"
                                        >
                                            <div
                                                className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-xl ${
                                                    positive
                                                        ? 'bg-emerald-100 text-emerald-700'
                                                        : 'bg-rose-100 text-rose-700'
                                                }`}
                                            >
                                                {positive ? (
                                                    <ArrowUpRight size={19} />
                                                ) : (
                                                    <ArrowDownRight size={19} />
                                                )}
                                            </div>

                                            <div className="min-w-0 flex-1">
                                                <p className="truncate font-bold text-slate-900">
                                                    {transaction.description ||
                                                        transaction.type ||
                                                        'Wallet transaction'}
                                                </p>

                                                <p className="mt-1 text-xs text-slate-500">
                                                    {formatDate(
                                                        transaction.created_at
                                                    )}
                                                </p>
                                            </div>

                                            <div
                                                className={`font-black ${
                                                    positive
                                                        ? 'text-emerald-600'
                                                        : 'text-rose-600'
                                                }`}
                                            >
                                                {money(
                                                    amount,
                                                    transaction.currency ||
                                                        currency
                                                )}
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
                            description="Your latest royalty credits and withdrawals will appear here."
                        />
                    )}
                </div>

                <div className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h3 className="text-lg font-black text-slate-950">
                            Recent Withdrawals
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Latest payout requests
                        </p>
                    </div>

                    {recentWithdrawals.length > 0 ? (
                        <div className="divide-y divide-slate-100">
                            {recentWithdrawals.map(
                                (withdrawal, index) => (
                                    <div
                                        key={
                                            withdrawal.id ||
                                            index
                                        }
                                        className="flex items-center gap-4 px-6 py-4"
                                    >
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-amber-100 text-amber-700">
                                            {withdrawal.status ===
                                            'paid' ? (
                                                <Landmark size={19} />
                                            ) : (
                                                <Clock3 size={19} />
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <p className="font-bold text-slate-900">
                                                {money(
                                                    withdrawal.amount,
                                                    withdrawal.currency ||
                                                        currency
                                                )}
                                            </p>

                                            <p className="mt-1 text-xs text-slate-500">
                                                {formatDate(
                                                    withdrawal.created_at
                                                )}
                                            </p>
                                        </div>

                                        <StatusBadge
                                            status={
                                                withdrawal.status ||
                                                'pending'
                                            }
                                        />
                                    </div>
                                )
                            )}
                        </div>
                    ) : (
                        <EmptyState
                            icon={Clock3}
                            title="No withdrawals"
                            description="Your recent withdrawal requests will appear here."
                        />
                    )}
                </div>
            </section>
        </>
    );
}
