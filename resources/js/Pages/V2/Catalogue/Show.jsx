import {
    Head,
    Link,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'artist',
    catalogueItem,
}) {
    const release =
        catalogueItem.release ?? {};

    const tracks =
        release.tracks ?? [];

    const delivery =
        catalogueItem.delivery_summary ??
        {};

    return (
        <PanelLayout
            role={role}
            title="Catalogue Release"
            subtitle={catalogueItem.title}
        >
            <Head
                title={
                    catalogueItem.title
                }
            />

            <div className="space-y-6">
                <Link
                    href="/v2/catalogue"
                    className="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                >
                    ← Back to Catalogue
                </Link>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-6 md:grid-cols-[220px_1fr]">
                        <div className="aspect-square overflow-hidden rounded-2xl bg-slate-100">
                            {catalogueItem.artwork_path ? (
                                <img
                                    src={
                                        catalogueItem.artwork_path.startsWith(
                                            'http'
                                        )
                                            ? catalogueItem.artwork_path
                                            : `/storage/${catalogueItem.artwork_path}`
                                    }
                                    alt={
                                        catalogueItem.title
                                    }
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center text-6xl text-slate-300">
                                    ♪
                                </div>
                            )}
                        </div>

                        <div>
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <h1 className="text-3xl font-bold text-slate-900">
                                        {
                                            catalogueItem.title
                                        }
                                    </h1>

                                    <p className="mt-2 text-lg text-slate-500">
                                        {catalogueItem.primary_artist_name ||
                                            'Unknown Artist'}
                                    </p>
                                </div>

                                <span className="rounded-full bg-emerald-100 px-4 py-2 text-sm font-semibold capitalize text-emerald-700">
                                    {catalogueItem.release_status.replaceAll(
                                        '_',
                                        ' '
                                    )}
                                </span>
                            </div>

                            <div className="mt-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <Info
                                    label="UPC"
                                    value={
                                        catalogueItem.upc ||
                                        'Pending'
                                    }
                                />


                                <Info
                                    label="Release Date"
                                    value={
                                        catalogueItem.digital_release_date ||
                                        catalogueItem.original_release_date ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Label"
                                    value={
                                        catalogueItem.label_name ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Language"
                                    value={
                                        catalogueItem.language ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Genre"
                                    value={
                                        catalogueItem.primary_genre ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Release Type"
                                    value={
                                        catalogueItem.release_type ||
                                        '—'
                                    }
                                />

                                <Info
                                    label="Tracks"
                                    value={
                                        catalogueItem.track_count ??
                                        tracks.length
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <h2 className="text-lg font-semibold text-slate-900">
                        DSP Delivery Summary
                    </h2>

                    <div className="mt-4 grid gap-4 sm:grid-cols-3 xl:grid-cols-6">
                        {[
                            'total',
                            'pending',
                            'processing',
                            'delivered',
                            'live',
                            'failed',
                        ].map(
                            (status) => (
                                <div
                                    key={
                                        status
                                    }
                                    className="rounded-xl bg-slate-50 p-4"
                                >
                                    <div className="text-xs font-semibold uppercase text-slate-500">
                                        {
                                            status
                                        }
                                    </div>

                                    <div className="mt-2 text-2xl font-bold text-slate-900">
                                        {delivery[
                                            status
                                        ] ??
                                            0}
                                    </div>
                                </div>
                            )
                        )}
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="text-lg font-semibold text-slate-900">
                            Tracks
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        '#',
                                        'Track',
                                        'Artist',
                                        'ISRC',
                                        'Language',
                                        'Genre',
                                        'Audio',
                                    ].map(
                                        (
                                            heading
                                        ) => (
                                            <th
                                                key={
                                                    heading
                                                }
                                                className="px-5 py-4 text-left text-xs font-semibold uppercase text-slate-500"
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
                                {tracks.length >
                                0 ? (
                                    tracks.map(
                                        (
                                            track,
                                            index
                                        ) => (
                                            <tr
                                                key={
                                                    track.id
                                                }
                                            >
                                                <td className="px-5 py-4 text-sm text-slate-500">
                                                    {index +
                                                        1}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {
                                                            track.title
                                                        }
                                                    </div>

                                                    {track.version && (
                                                        <div className="mt-1 text-xs text-slate-500">
                                                            {
                                                                track.version
                                                            }
                                                        </div>
                                                    )}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.primary_artist_name ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 font-mono text-sm text-slate-700">
                                                    {track.isrc ||
                                                        'Pending'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.language ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.genre ||
                                                        '—'}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span
                                                        className={[
                                                            'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                            track.audio_validation_status ===
                                                            'passed'
                                                                ? 'bg-emerald-100 text-emerald-700'
                                                                : track.audio_validation_status ===
                                                                    'failed'
                                                                  ? 'bg-red-100 text-red-700'
                                                                  : 'bg-amber-100 text-amber-700',
                                                        ].join(
                                                            ' '
                                                        )}
                                                    >
                                                        {track.audio_validation_status ||
                                                            'pending'}
                                                    </span>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="7"
                                            className="px-5 py-14 text-center text-sm text-slate-500"
                                        >
                                            No tracks
                                            found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 truncate text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}
