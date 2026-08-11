import {
    Head,
    router,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Index({
    role = 'admin',
    artists = {},
    search = '',
}) {
    return (
        <PanelLayout
            role={role}
            title="Artists"
            subtitle="Assigned artist accounts"
        >
            <Head title="Artists" />

            <div className="mb-5 flex justify-end">
                <a
                    href="/v2/admin/artists/create"
                    className="rounded-xl bg-slate-950 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-800"
                >
                    + Add Artist
                </a>
            </div>


            <div className="space-y-5">
                <input
                    type="search"
                    defaultValue={search}
                    placeholder="Search artist..."
                    onKeyDown={(event) => {
                        if (
                            event.key === 'Enter'
                        ) {
                            router.get(
                                '/v2/admin/artists',
                                {
                                    search:
                                        event
                                            .currentTarget
                                            .value,
                                }
                            );
                        }
                    }}
                    className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 md:max-w-md"
                />

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <table className="min-w-full">
                        <thead className="bg-slate-50">
                            <tr>
                                {[
                                    'Artist',
                                    'Label',
                                    'Status',
                                    'KYC',
                                    'Releases',
                                    'Assigned Admins',
                                    'Actions',
                                ].map((heading) => (
                                    <th
                                        key={heading}
                                        className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
                                    >
                                        {heading}
                                    </th>
                                ))}
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {(artists.data ?? []).map(
                                (artist) => (
                                    <tr key={artist.id}>
                                        <Cell>
                                            <div className="font-semibold text-slate-900">
                                                {
                                                    artist.stage_name
                                                }
                                            </div>

                                            <div className="text-xs text-slate-500">
                                                {artist.email}
                                            </div>
                                        </Cell>

                                        <Cell>
                                            {artist.label
                                                ?.name ||
                                                'Independent'}
                                        </Cell>

                                        <Cell>
                                            {
                                                artist.account_status
                                            }
                                        </Cell>

                                        <Cell>
                                            {artist.kyc_status}
                                        </Cell>

                                        <Cell>
                                            {
                                                artist.releases_count
                                            }
                                        </Cell>

                                        <Cell>
                                            {(
                                                artist.assigned_admins ??
                                                []
                                            )
                                                .map(
                                                    (
                                                        admin
                                                    ) =>
                                                        admin.name
                                                )
                                                .join(', ') ||
                                                'Unassigned'}
                                        </Cell>

                                        <Cell>
                                            <div className="flex items-center gap-2">
                                                <a
                                                    href={`/v2/admin/artists/${artist.id}`}
                                                    title="View"
                                                    className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                                >
                                                    View
                                                </a>

                                                <a
                                                    href={`/v2/admin/artists/${artist.id}/edit`}
                                                    title="Edit"
                                                    className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold hover:bg-slate-50"
                                                >
                                                    Edit
                                                </a>

                                                <button
                                                    type="button"
                                                    title="Delete"
                                                    onClick={() => {
                                                        if (
                                                            window.confirm(
                                                                `Delete ${artist.stage_name}?`
                                                            )
                                                        ) {
                                                            router.delete(
                                                                `/v2/admin/artists/${artist.id}`,
                                                                {
                                                                    preserveScroll: true,
                                                                }
                                                            );
                                                        }
                                                    }}
                                                    className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600 hover:bg-red-50"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </Cell>
                                    </tr>
                                )
                            )}
                        </tbody>
                    </table>
                </section>
            </div>
        </PanelLayout>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}
