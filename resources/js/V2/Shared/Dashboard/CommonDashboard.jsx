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
    DashboardHero,
    DashboardKpiGrid,
} from '@/V2/Shared/Dashboard/Sections';

const number = (value) =>
    Number(value ?? 0).toLocaleString('en-IN');

export default function CommonDashboard({
    role = 'artist',
    panelName = 'Artist',
    accountName = null,
    stats = {},
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

    return (
        <PanelLayout
            role={role}
            title={`${panelName} Dashboard`}
            subtitle={accountName ? `Welcome back, ${accountName}` : `Welcome to ${panelName}`}
        >
            <Head
                title={`${panelName} Dashboard`}
            />

            <DashboardHero
                name={accountName || panelName}
                eyebrow={`${String(
                    panelName
                ).toUpperCase()} OVERVIEW`}
                /* W88: the line under "Welcome, ..." is removed */
                description=""
                /* W89: one click opens the create-release form; the second button is removed */
                primaryAction={{
                    label: 'Create Release',
                    href: '/releases/create',
                }}
                secondaryAction={null}
            />

            <DashboardKpiGrid
                cards={cards}
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
