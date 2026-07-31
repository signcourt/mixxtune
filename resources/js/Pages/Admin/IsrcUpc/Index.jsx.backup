import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function IsrcUpc({
    tab = 'isrc',
    tracks,
    releases,
    filters = {},
    counts = {},
}) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [selectedTrackIds, setSelectedTrackIds] = useState([]);
    const [selectedReleaseIds, setSelectedReleaseIds] = useState([]);
    const [editingTrack, setEditingTrack] = useState(null);
    const [editingRelease, setEditingRelease] = useState(null);
    const [processing, setProcessing] = useState(false);

    const rows = tab === 'isrc'
        ? tracks?.data ?? []
        : releases?.data ?? [];

    const applyFilters = (
        nextTab = tab,
        nextStatus = filters.status ?? ''
    ) => {
        router.get(
            '/isrc-upc',
            {
                tab: nextTab,
                status: nextStatus || undefined,
                search: search || undefined,
            },
            {
                preserveState: true,
                preserveScroll: true,
                replace: true,
            }
        );
    };

    const submitSearch = (event) => {
        event.preventDefault();
        applyFilters();
    };

    const copyIdentifier = async (value) => {
        if (!value) {
            return;
        }

        await navigator.clipboard.writeText(value);
    };

    const toggleTrack = (id) => {
        setSelectedTrackIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id]
        );
    };

    const toggleRelease = (id) => {
        setSelectedReleaseIds((current) =>
            current.includes(id)
                ? current.filter((item) => item !== id)
                : [...current, id]
        );
    };

    const toggleAll = () => {
        if (tab === 'isrc') {
            const pendingIds = rows
                .filter((track) => !track.isrc)
                .map((track) => track.id);

            setSelectedTrackIds(
                pendingIds.length > 0 &&
                    pendingIds.every((id) =>
                        selectedTrackIds.includes(id)
                    )
                    ? []
                    : pendingIds
            );

            return;
        }

        const pendingIds = rows
            .filter((release) => !release.upc)
            .map((release) => release.id);

        setSelectedReleaseIds(
            pendingIds.length > 0 &&
                pendingIds.every((id) =>
                    selectedReleaseIds.includes(id)
                )
                ? []
                : pendingIds
        );
    };

    const bulkGenerate = () => {
        const ids =
            tab === 'isrc'
                ? selectedTrackIds
                : selectedReleaseIds;

        if (!ids.length) {
            window.alert('Select at least one pending item.');
            return;
        }

        setProcessing(true);

        router.post(
            tab === 'isrc'
                ? '/isrc-upc/generate-isrc'
                : '/isrc-upc/generate-upc',
            tab === 'isrc'
                ? { track_ids: ids }
                : { release_ids: ids },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSelectedTrackIds([]);
                    setSelectedReleaseIds([]);
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    const selectedCount =
        tab === 'isrc'
            ? selectedTrackIds.length
            : selectedReleaseIds.length;

    return (
        <AdminLayout title="ISRC & UPC">
            <Head title="ISRC & UPC" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        ISRC & UPC
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Assign, generate and manage release identifiers.
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-6">
                    <Stat label="Tracks" value={counts.tracks_total ?? 0} />
                    <Stat label="ISRC Assigned" value={counts.isrc_assigned ?? 0} />
                    <Stat label="ISRC Pending" value={counts.isrc_pending ?? 0} />
                    <Stat label="Releases" value={counts.releases_total ?? 0} />
                    <Stat label="UPC Assigned" value={counts.upc_assigned ?? 0} />
                    <Stat label="UPC Pending" value={counts.upc_pending ?? 0} />
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4">
                        <div className="flex flex-wrap gap-2">
                            <button
                                type="button"
                                onClick={() => applyFilters('isrc')}
                                className={`rounded-xl px-5 py-2.5 text-sm font-semibold ${
                                    tab === 'isrc'
                                        ? 'bg-slate-900 text-white'
                                        : 'border border-slate-300 text-slate-700'
                                }`}
                            >
                                Track ISRC
                            </button>

                            <button
                                type="button"
                                onClick={() => applyFilters('upc')}
                                className={`rounded-xl px-5 py-2.5 text-sm font-semibold ${
                                    tab === 'upc'
                                        ? 'bg-slate-900 text-white'
                                        : 'border border-slate-300 text-slate-700'
                                }`}
                            >
                                Release UPC
                            </button>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {[
                                ['', 'All'],
                                ['pending', 'Pending'],
                                ['assigned', 'Assigned'],
                            ].map(([value, label]) => (
                                <button
                                    key={label}
                                    type="button"
                                    onClick={() =>
                                        applyFilters(tab, value)
                                    }
                                    className={`rounded-lg px-4 py-2 text-sm font-semibold ${
                                        (filters.status ?? '') === value
                                            ? 'bg-violet-600 text-white'
                                            : 'border border-slate-300 text-slate-700'
                                    }`}
                                >
                                    {label}
                                </button>
                            ))}
                        </div>

                        <form
                            onSubmit={submitSearch}
                            className="flex flex-col gap-3 lg:flex-row"
                        >
                            <input
                                type="search"
                                value={search}
                                onChange={(event) =>
                                    setSearch(event.target.value)
                                }
                                placeholder="Search title, artist, ISRC, UPC or catalogue number..."
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm"
                            />

                            <button
                                type="submit"
                                className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white"
                            >
                                Search
                            </button>
                        </form>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                        <label className="flex cursor-pointer items-center gap-3">
                            <input
                                type="checkbox"
                                onChange={toggleAll}
                                className="rounded border-slate-300 text-violet-600"
                            />

                            <span className="text-sm font-semibold text-slate-700">
                                Select pending items on this page
                            </span>
                        </label>

                        <div className="flex items-center gap-3">
                            <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                {selectedCount} selected
                            </span>

                            <button
                                type="button"
                                onClick={bulkGenerate}
                                disabled={processing || selectedCount === 0}
                                className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-40"
                            >
                                {processing
                                    ? 'Generating...'
                                    : tab === 'isrc'
                                      ? 'Generate ISRC'
                                      : 'Generate UPC'}
                            </button>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <h2 className="font-semibold text-slate-900">
                            {tab === 'isrc'
                                ? 'Track ISRC Registry'
                                : 'Release UPC Registry'}
                        </h2>
                    </div>

                    {rows.length === 0 ? (
                        <div className="px-6 py-20 text-center text-slate-500">
                            No records found.
                        </div>
                    ) : tab === 'isrc' ? (
                        <div className="divide-y divide-slate-100">
                            {rows.map((track) => (
                                <div
                                    key={track.id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[40px_minmax(240px,1.5fr)_minmax(180px,1fr)_220px_auto] lg:items-center"
                                >
                                    <input
                                        type="checkbox"
                                        disabled={Boolean(track.isrc)}
                                        checked={selectedTrackIds.includes(track.id)}
                                        onChange={() => toggleTrack(track.id)}
                                        className="rounded border-slate-300 text-violet-600 disabled:opacity-30"
                                    />

                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {track.title}
                                        </div>

                                        <div className="mt-1 text-sm text-slate-500">
                                            {track.primary_artist_name || '—'}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        <div>{track.release?.title || '—'}</div>
                                        <div className="mt-1 text-xs">
                                            Cat#: {track.release?.catalog_number || '—'}
                                        </div>
                                    </div>

                                    <Identifier
                                        value={track.isrc}
                                        pendingText="ISRC Pending"
                                        onCopy={copyIdentifier}
                                    />

                                    <div className="flex gap-2 lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() => setEditingTrack(track)}
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                                        >
                                            Assign
                                        </button>

                                        <Link
                                            href={`/releases/${track.release_id}/edit`}
                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                        >
                                            Release
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    ) : (
                        <div className="divide-y divide-slate-100">
                            {rows.map((release) => (
                                <div
                                    key={release.id}
                                    className="grid gap-4 px-6 py-5 lg:grid-cols-[40px_minmax(240px,1.5fr)_minmax(180px,1fr)_220px_auto] lg:items-center"
                                >
                                    <input
                                        type="checkbox"
                                        disabled={Boolean(release.upc)}
                                        checked={selectedReleaseIds.includes(release.id)}
                                        onChange={() => toggleRelease(release.id)}
                                        className="rounded border-slate-300 text-violet-600 disabled:opacity-30"
                                    />

                                    <div>
                                        <div className="font-semibold text-slate-900">
                                            {release.title}
                                        </div>

                                        <div className="mt-1 text-sm text-slate-500">
                                            {release.primary_artist_name || '—'}
                                        </div>
                                    </div>

                                    <div className="text-sm text-slate-600">
                                        <div>{release.label?.name || 'No label'}</div>
                                        <div className="mt-1 text-xs">
                                            {release.tracks_count ?? 0} tracks
                                        </div>
                                    </div>

                                    <Identifier
                                        value={release.upc}
                                        pendingText="UPC Pending"
                                        onCopy={copyIdentifier}
                                    />

                                    <div className="flex gap-2 lg:justify-end">
                                        <button
                                            type="button"
                                            onClick={() => setEditingRelease(release)}
                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
                                        >
                                            Assign
                                        </button>

                                        <Link
                                            href={`/releases/${release.id}/edit`}
                                            className="rounded-lg bg-slate-900 px-3 py-2 text-xs font-semibold text-white"
                                        >
                                            Edit
                                        </Link>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    <Pagination
                        links={
                            tab === 'isrc'
                                ? tracks?.links ?? []
                                : releases?.links ?? []
                        }
                    />
                </div>
            </div>

            {editingTrack && (
                <AssignModal
                    title="Assign Track ISRC"
                    label="ISRC"
                    initialValue={editingTrack.isrc ?? ''}
                    placeholder="INMTX2600001"
                    onClose={() => setEditingTrack(null)}
                    onSubmit={(value, finish) =>
                        router.patch(
                            `/isrc-upc/tracks/${editingTrack.id}`,
                            { isrc: value },
                            {
                                preserveScroll: true,
                                onSuccess: () => setEditingTrack(null),
                                onFinish: finish,
                            }
                        )
                    }
                />
            )}

            {editingRelease && (
                <AssignModal
                    title="Assign Release UPC"
                    label="UPC"
                    initialValue={editingRelease.upc ?? ''}
                    placeholder="890000000001"
                    onClose={() => setEditingRelease(null)}
                    onSubmit={(value, finish) =>
                        router.patch(
                            `/isrc-upc/releases/${editingRelease.id}`,
                            { upc: value },
                            {
                                preserveScroll: true,
                                onSuccess: () => setEditingRelease(null),
                                onFinish: finish,
                            }
                        )
                    }
                />
            )}
        </AdminLayout>
    );
}

function Identifier({ value, pendingText, onCopy }) {
    if (!value) {
        return (
            <span className="w-fit rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">
                {pendingText}
            </span>
        );
    }

    return (
        <div className="flex items-center gap-2">
            <code className="rounded-lg bg-slate-100 px-3 py-2 text-sm font-semibold text-slate-800">
                {value}
            </code>

            <button
                type="button"
                onClick={() => onCopy(value)}
                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold"
            >
                Copy
            </button>
        </div>
    );
}

function AssignModal({
    title,
    label,
    initialValue,
    placeholder,
    onClose,
    onSubmit,
}) {
    const [value, setValue] = useState(initialValue);
    const [saving, setSaving] = useState(false);

    const submit = (event) => {
        event.preventDefault();
        setSaving(true);
        onSubmit(value, () => setSaving(false));
    };

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/50 p-4">
            <form
                onSubmit={submit}
                className="w-full max-w-lg rounded-2xl bg-white p-6 shadow-xl"
            >
                <h2 className="text-xl font-semibold text-slate-900">
                    {title}
                </h2>

                <label className="mt-6 block text-sm font-medium text-slate-700">
                    {label}
                </label>

                <input
                    value={value}
                    onChange={(event) => setValue(event.target.value)}
                    placeholder={placeholder}
                    className="mt-2 w-full rounded-xl border border-slate-300 px-4 py-3 uppercase"
                    required
                />

                <div className="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        onClick={onClose}
                        className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        disabled={saving}
                        className="rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white disabled:opacity-50"
                    >
                        {saving ? 'Saving...' : 'Save'}
                    </button>
                </div>
            </form>
        </div>
    );
}

function Stat({ label, value }) {
    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 text-3xl font-bold text-slate-900">
                {value}
            </div>
        </div>
    );
}

function Pagination({ links = [] }) {
    if (!Array.isArray(links) || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2 border-t border-slate-200 px-5 py-5">
            {links.map((link, index) => (
                <button
                    key={`${link.label}-${index}`}
                    type="button"
                    disabled={!link.url}
                    onClick={() =>
                        link.url &&
                        router.get(
                            link.url,
                            {},
                            {
                                preserveState: true,
                                preserveScroll: true,
                            }
                        )
                    }
                    className={`rounded-lg border px-3 py-2 text-sm ${
                        link.active
                            ? 'bg-slate-900 text-white'
                            : 'bg-white text-slate-700'
                    } disabled:opacity-40`}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
