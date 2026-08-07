import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'label',
    label = {},
    artists = {},
    summary = {},
    filters = {},
}) {
    const [search, setSearch] =
        useState(filters.search ?? '');

    const applyFilters = (
        changes = {}
    ) => {
        router.get(
            '/v2/label/artists',
            {
                search,
                status:
                    filters.status ?? '',
                ...changes,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    return (
        <PanelLayout
            role={role}
            title="Artists"
            subtitle={`Manage artists under ${
                label.name ?? 'your label'
            }`}
        >
            <Head title="Label Artists" />

            <div className="space-y-6">
                <section className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                    <SummaryCard
                        label="Total Artists"
                        value={summary.total ?? 0}
                    />

                    <SummaryCard
                        label="Active"
                        value={summary.active ?? 0}
                    />

                    <SummaryCard
                        label="Pending"
                        value={summary.pending ?? 0}
                    />

                    <SummaryCard
                        label="Suspended"
                        value={summary.suspended ?? 0}
                    />
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="grid flex-1 gap-3 md:grid-cols-[1fr_190px_auto]">
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(
                                        event.target.value
                                    )
                                }
                                onKeyDown={(event) => {
                                    if (
                                        event.key ===
                                        'Enter'
                                    ) {
                                        applyFilters({
                                            search,
                                        });
                                    }
                                }}
                                placeholder="Search name, username or email..."
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-4 focus:ring-violet-500/10"
                            />

                            <select
                                value={
                                    filters.status ??
                                    ''
                                }
                                onChange={(event) =>
                                    applyFilters({
                                        status:
                                            event.target
                                                .value,
                                    })
                                }
                                className="rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            >
                                <option value="">
                                    All Statuses
                                </option>

                                <option value="active">
                                    Active
                                </option>

                                <option value="pending">
                                    Pending
                                </option>

                                <option value="suspended">
                                    Suspended
                                </option>
                            </select>

                            <button
                                type="button"
                                onClick={() =>
                                    applyFilters({
                                        search,
                                    })
                                }
                                className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Search
                            </button>
                        </div>

                        <Link
                            href="/v2/label/artists/create"
                            className="rounded-xl bg-violet-600 px-5 py-3 text-center text-sm font-semibold text-white shadow-sm hover:bg-violet-700"
                        >
                            + Create Artist
                        </Link>
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    {(artists.data ?? []).length ===
                    0 ? (
                        <div className="px-6 py-16 text-center">
                            <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-violet-100 text-2xl">
                                ♫
                            </div>

                            <h2 className="mt-5 text-lg font-semibold text-slate-900">
                                No artists found
                            </h2>

                            <p className="mx-auto mt-2 max-w-md text-sm leading-6 text-slate-500">
                                Create your first artist account.
                                The artist will automatically be
                                linked to this label.
                            </p>

                            <Link
                                href="/v2/label/artists/create"
                                className="mt-6 inline-flex rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                            >
                                Create First Artist
                            </Link>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="min-w-full">
                                <thead className="bg-slate-50">
                                    <tr>
                                        {[
                                            'Artist',
                                            'Username',
                                            'Status',
                                            'KYC',
                                            'Invitation',
                                            'Last Login',
                                            'Actions',
                                        ].map(
                                            (heading) => (
                                                <th
                                                    key={
                                                        heading
                                                    }
                                                    className="whitespace-nowrap px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
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
                                    {(artists.data ?? []).map(
                                        (artist) => (
                                            <tr
                                                key={
                                                    artist.id
                                                }
                                                className="hover:bg-slate-50"
                                            >
                                                <Cell>
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            artist.stage_name
                                                        }
                                                    </div>

                                                    <div className="text-xs text-slate-500">
                                                        {artist.email ??
                                                            'No email'}
                                                    </div>

                                                    {artist.legal_name && (
                                                        <div className="mt-1 text-xs text-slate-400">
                                                            {
                                                                artist.legal_name
                                                            }
                                                        </div>
                                                    )}
                                                </Cell>

                                                <Cell>
                                                    <span className="font-semibold text-violet-600">
                                                        {artist.username
                                                            ? `@${artist.username}`
                                                            : 'Not linked'}
                                                    </span>
                                                </Cell>

                                                <Cell>
                                                    <Badge
                                                        value={
                                                            artist.account_status ??
                                                            'pending'
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <Badge
                                                        value={
                                                            artist.kyc_status ??
                                                            'pending'
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <span className="capitalize text-slate-600">
                                                        {artist.invitation_status ??
                                                            'not invited'}
                                                    </span>
                                                </Cell>

                                                <Cell>
                                                    {artist.last_login_at ??
                                                        'Never'}
                                                </Cell>

                                                <Cell>
                                                    <div className="flex gap-2">
                                                        <Link
                                                            href={`/v2/label/artists/${artist.id}`}
                                                            className="rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                                                        >
                                                            View
                                                        </Link>

                                                        <Link
                                                            href={`/v2/label/artists/${artist.id}/edit`}
                                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white hover:bg-slate-700"
                                                        >
                                                            Edit
                                                        </Link>
                                                    </div>
                                                </Cell>
                                            </tr>
                                        )
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>

                {artists.links?.length > 3 && (
                    <div className="flex flex-wrap gap-2">
                        {artists.links.map(
                            (link, index) => (
                                <Link
                                    key={index}
                                    href={
                                        link.url ??
                                        '#'
                                    }
                                    preserveScroll
                                    className={[
                                        'rounded-lg border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-violet-600 bg-violet-600 text-white'
                                            : 'border-slate-300 bg-white text-slate-600',
                                        !link.url
                                            ? 'pointer-events-none opacity-40'
                                            : '',
                                    ].join(' ')}
                                    dangerouslySetInnerHTML={{
                                        __html:
                                            link.label,
                                    }}
                                />
                            )
                        )}
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function SummaryCard({
    label,
    value,
}) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-sm font-medium text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-black tracking-tight text-slate-950">
                {value}
            </div>
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
        normalized === 'active' ||
        normalized === 'verified'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'suspended' ||
                normalized === 'rejected'
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
