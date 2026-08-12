import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'label',
    label = {},
    artist = {},
    releaseSummary = {},
    catalogueSummary = {},
    walletSummary = {},
    recentReleases = [],
}) {
    const currency =
        walletSummary.currency ?? 'INR';

    const money = (value) =>
        new Intl.NumberFormat(
            'en-IN',
            {
                style: 'currency',
                currency,
                maximumFractionDigits: 2,
            }
        ).format(Number(value ?? 0));

    return (
        <PanelLayout
            role={role}
            title={artist.stage_name}
            subtitle={`Artist profile under ${
                label.name ?? 'Label'
            }`}
        >
            <Head
                title={artist.stage_name}
            />

            <div className="space-y-6">
                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="flex flex-col gap-6 xl:flex-row xl:items-start xl:justify-between">
                        <div className="flex items-start gap-5">
                            <div className="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-3xl bg-gradient-to-br from-violet-500 to-indigo-600 text-2xl font-black text-white">
                                {artist.profile_image_path ? (
                                    <img
                                        src={`/storage/${artist.profile_image_path}`}
                                        alt={artist.stage_name}
                                        className="h-full w-full object-cover"
                                    />
                                ) : (
                                    artist.stage_name
                                        ?.slice(0, 2)
                                        ?.toUpperCase()
                                )}
                            </div>

                            <div>
                                <div className="flex flex-wrap items-center gap-2">
                                    <h1 className="text-2xl font-black tracking-tight text-slate-950">
                                        {artist.stage_name}
                                    </h1>

                                    <Badge
                                        value={
                                            artist.account_status
                                        }
                                    />

                                    <Badge
                                        value={
                                            artist.kyc_status
                                        }
                                    />
                                </div>

                                <p className="mt-1 font-semibold text-violet-600">
                                    @{artist.username}
                                </p>

                                <p className="mt-2 text-sm text-slate-500">
                                    {artist.email}
                                    {artist.phone
                                        ? ` · ${artist.phone}`
                                        : ''}
                                </p>

                                <p className="mt-1 text-sm text-slate-400">
                                    {artist.country}
                                    {' · '}
                                    {artist.timezone}
                                    {' · '}
                                    {artist.currency}
                                </p>
                            </div>
                        </div>

                        <div className="flex flex-wrap gap-3">
                            <Link
                                href="/v2/label/artists"
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Back
                            </Link>

                            <Link
                                href={`/v2/label/artists/${artist.id}/edit`}
                                className="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800"
                            >
                                Edit Artist
                            </Link>
                        </div>
                    </div>

                    <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <Info
                            label="Public ID"
                            value={artist.public_id}
                        />

                        <Info
                            label="Legal Name"
                            value={
                                artist.legal_name
                                ?? 'Not provided'
                            }
                        />

                        <Info
                            label="Invitation"
                            value={
                                artist.invitation_status
                                ?? 'Not sent'
                            }
                        />

                        <Info
                            label="Last Login"
                            value={
                                artist.last_login_at
                                ?? 'Never'
                            }
                        />
                    </div>
                </section>

                <section>
                    <SectionTitle
                        title="Release Performance"
                        subtitle="Release status summary for this artist."
                    />

                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
                        <Stat
                            label="Total"
                            value={
                                releaseSummary.total
                                ?? 0
                            }
                        />

                        <Stat
                            label="Draft"
                            value={
                                releaseSummary.draft
                                ?? 0
                            }
                        />

                        <Stat
                            label="Submitted"
                            value={
                                releaseSummary.submitted
                                ?? 0
                            }
                        />

                        <Stat
                            label="Approved"
                            value={
                                releaseSummary.approved
                                ?? 0
                            }
                        />

                        <Stat
                            label="Live"
                            value={
                                releaseSummary.live
                                ?? 0
                            }
                        />

                        <Stat
                            label="Rejected"
                            value={
                                releaseSummary.rejected
                                ?? 0
                            }
                        />
                    </div>
                </section>

                <section>
                    <SectionTitle
                        title="Catalogue & Wallet"
                        subtitle="Current catalogue and financial summary."
                    />

                    <div className="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        <Stat
                            label="Catalogue Items"
                            value={
                                catalogueSummary.items
                                ?? 0
                            }
                        />

                        <Stat
                            label="Tracks"
                            value={
                                catalogueSummary.tracks
                                ?? 0
                            }
                        />

                        <MoneyCard
                            label="Available Balance"
                            value={money(
                                walletSummary.available_balance
                            )}
                        />

                        <MoneyCard
                            label="Pending Balance"
                            value={money(
                                walletSummary.pending_balance
                            )}
                        />

                        <MoneyCard
                            label="Lifetime Earnings"
                            value={money(
                                walletSummary.lifetime_earnings
                            )}
                        />

                        <MoneyCard
                            label="Withdrawn"
                            value={money(
                                walletSummary.withdrawn_balance
                            )}
                        />

                        <Stat
                            label="Transactions"
                            value={
                                walletSummary.transactions
                                ?? 0
                            }
                        />

                        <Stat
                            label="Withdrawals"
                            value={
                                walletSummary.withdrawals
                                ?? 0
                            }
                        />
                    </div>
                </section>

                <section className="rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <SectionTitle
                            title="Recent Releases"
                            subtitle="Latest releases created for this artist."
                        />
                    </div>

                    {recentReleases.length === 0 ? (
                        <div className="px-6 py-14 text-center">
                            <p className="font-semibold text-slate-800">
                                No releases yet
                            </p>

                            <p className="mt-1 text-sm text-slate-500">
                                Releases created for this artist will appear here.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[
                                            'Title',
                                            'Type',
                                            'Status',
                                            'UPC',
                                            'Release Date',
                                        ].map(
                                            (heading) => (
                                                <th
                                                    key={heading}
                                                    className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                                >
                                                    {heading}
                                                </th>
                                            )
                                        )}
                                    </tr>
                                </thead>

                                <tbody className="divide-y divide-slate-100">
                                    {recentReleases.map(
                                        (release) => (
                                            <tr
                                                key={release.id}
                                                className="hover:bg-slate-50"
                                            >
                                                <Cell>
                                                    <div className="font-semibold text-slate-900">
                                                        {release.title}
                                                    </div>

                                                    <div className="text-xs text-slate-400">
                                                        {release.public_id}
                                                    </div>
                                                </Cell>

                                                <Cell>
                                                    {release.release_type}
                                                </Cell>

                                                <Cell>
                                                    <Badge
                                                        value={release.status}
                                                    />
                                                </Cell>

                                                <Cell>
                                                    {release.upc ?? 'Pending'}
                                                </Cell>

                                                <Cell>
                                                    {release.digital_release_date
                                                        ?? release.original_release_date
                                                        ?? 'Not set'}
                                                </Cell>
                                            </tr>
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}

function SectionTitle({
    title,
    subtitle,
}) {
    return (
        <div>
            <h2 className="text-lg font-bold text-slate-950">
                {title}
            </h2>

            <p className="mt-1 text-sm text-slate-500">
                {subtitle}
            </p>
        </div>
    );
}

function Stat({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-3xl font-black text-slate-950">
                {value}
            </p>
        </div>
    );
}

function MoneyCard({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <p className="text-sm font-medium text-slate-500">
                {label}
            </p>

            <p className="mt-2 text-xl font-black text-slate-950">
                {value}
            </p>
        </div>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl bg-slate-50 p-4">
            <p className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </p>

            <p className="mt-1 break-words text-sm font-semibold text-slate-800">
                {String(value)}
            </p>
        </div>
    );
}

function Cell({
    children,
}) {
    return (
        <td className="whitespace-nowrap px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}

function Badge({
    value,
}) {
    const normalized =
        String(value ?? 'pending')
            .toLowerCase();

    const className =
        normalized === 'active'
        || normalized === 'approved'
        || normalized === 'live'
        || normalized === 'verified'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'suspended'
                || normalized === 'rejected'
              ? 'bg-red-100 text-red-700'
              : 'bg-amber-100 text-amber-700';

    return (
        <span
            className={`inline-flex rounded-full px-2.5 py-1 text-xs font-semibold capitalize ${className}`}
        >
            {normalized.replaceAll('_', ' ')}
        </span>
    );
}
