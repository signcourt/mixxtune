import { Head, Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const csrfToken = () =>
    document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content') ?? '';

async function postJson(url, payload = {}) {
    const response = await fetch(url, {
        method: 'POST',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': csrfToken(),
            'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify(payload),
    });

    const data = await response
        .json()
        .catch(() => ({}));

    if (!response.ok) {
        throw new Error(
            data?.message ??
                'Request failed.'
        );
    }

    return data;
}

export default function Index({
    role = 'super_admin',
    pendingIsrc = {},
    pendingUpc = {},
}) {
    const [selectedTracks, setSelectedTracks] =
        useState([]);

    const [selectedReleases, setSelectedReleases] =
        useState([]);

    const [busy, setBusy] = useState(false);
    const [notice, setNotice] = useState('');
    const [error, setError] = useState('');

    const [manualIdentifier, setManualIdentifier] =
        useState(null);

    const [manualValue, setManualValue] =
        useState('');

    const openManualIdentifier = (
        type,
        id,
        title
    ) => {
        setError('');
        setNotice('');
        setManualValue('');
        setManualIdentifier({
            type,
            id,
            title,
        });
    };

    const closeManualIdentifier = () => {
        if (busy) {
            return;
        }

        setManualIdentifier(null);
        setManualValue('');
    };

    const tracks =
        pendingIsrc?.data ?? [];

    const releases =
        pendingUpc?.data ?? [];

    const allTracksSelected = useMemo(
        () =>
            tracks.length > 0 &&
            tracks.every((track) =>
                selectedTracks.includes(track.id)
            ),
        [tracks, selectedTracks]
    );

    const allReleasesSelected = useMemo(
        () =>
            releases.length > 0 &&
            releases.every((release) =>
                selectedReleases.includes(
                    release.id
                )
            ),
        [releases, selectedReleases]
    );

    const runAction = async (
        url,
        payload = {}
    ) => {
        setBusy(true);
        setNotice('');
        setError('');

        try {
            const result =
                await postJson(
                    url,
                    payload
                );

            setNotice(
                result?.message ??
                    'Updated successfully.'
            );

            setSelectedTracks([]);
            setSelectedReleases([]);

            router.reload({
                only: [
                    'pendingIsrc',
                    'pendingUpc',
                ],
            });
        } catch (exception) {
            setError(
                exception?.message ??
                    'Request failed.'
            );
        } finally {
            setBusy(false);
        }
    };

    const saveManualIdentifier = async () => {
        if (!manualIdentifier) {
            return;
        }

        const value = manualValue.trim();

        if (!value) {
            setError(
                manualIdentifier.type === 'isrc'
                    ? 'Please enter an ISRC.'
                    : 'Please enter a UPC/EAN.'
            );
            return;
        }

        const isIsrc =
            manualIdentifier.type === 'isrc';

        const url = isIsrc
            ? `/v2/admin/tracks/${manualIdentifier.id}/isrc/assign`
            : `/v2/admin/releases/${manualIdentifier.id}/upc/assign`;

        const payload = isIsrc
            ? { isrc: value }
            : { upc: value };

        setBusy(true);
        setNotice('');
        setError('');

        try {
            const result =
                await postJson(
                    url,
                    payload
                );

            setNotice(
                result?.message ??
                    'Identifier assigned successfully.'
            );

            setManualIdentifier(null);
            setManualValue('');

            setSelectedTracks([]);
            setSelectedReleases([]);

            router.reload({
                only: [
                    'pendingIsrc',
                    'pendingUpc',
                ],
            });
        } catch (exception) {
            setError(
                exception?.message ??
                    'Unable to assign identifier.'
            );
        } finally {
            setBusy(false);
        }
    };

    return (
        <PanelLayout
            role={role}
            title="ISRC & UPC"
            subtitle="Assign and generate release identifiers"
        >
            <Head title="ISRC & UPC" />

            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-bold text-slate-900">
                        ISRC & UPC Management
                    </h1>

                    <p className="mt-1 text-sm text-slate-500">
                        Generate identifiers for
                        pending tracks and releases.
                    </p>
                </div>

                {notice && (
                    <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-700">
                        {notice}
                    </div>
                )}

                {error && (
                    <div className="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-medium text-red-700">
                        {error}
                    </div>
                )}

                <Section
                    title="Pending ISRC"
                    description={`${pendingIsrc?.total ?? 0} tracks waiting for ISRC`}
                    action={
                        <button
                            type="button"
                            disabled={
                                busy ||
                                selectedTracks.length ===
                                    0
                            }
                            onClick={() =>
                                runAction(
                                    '/v2/admin/identifiers/isrc/bulk-generate',
                                    {
                                        track_ids:
                                            selectedTracks,
                                    }
                                )
                            }
                            className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            Generate Selected ISRC
                        </button>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <HeaderCell>
                                        <input
                                            type="checkbox"
                                            checked={
                                                allTracksSelected
                                            }
                                            onChange={(
                                                event
                                            ) =>
                                                setSelectedTracks(
                                                    event
                                                        .target
                                                        .checked
                                                        ? tracks.map(
                                                              (
                                                                  track
                                                              ) =>
                                                                  track.id
                                                          )
                                                        : []
                                                )
                                            }
                                        />
                                    </HeaderCell>
                                    <HeaderCell>
                                        Track
                                    </HeaderCell>
                                    <HeaderCell>
                                        Release
                                    </HeaderCell>
                                    <HeaderCell>
                                        Audio Status
                                    </HeaderCell>
                                    <HeaderCell>
                                        Action
                                    </HeaderCell>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {tracks.length === 0 ? (
                                    <EmptyRow
                                        text="No pending ISRC tracks."
                                        columns={5}
                                    />
                                ) : (
                                    tracks.map(
                                        (track) => (
                                            <tr
                                                key={
                                                    track.id
                                                }
                                            >
                                                <Cell>
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedTracks.includes(
                                                            track.id
                                                        )}
                                                        onChange={() =>
                                                            setSelectedTracks(
                                                                (
                                                                    current
                                                                ) =>
                                                                    current.includes(
                                                                        track.id
                                                                    )
                                                                        ? current.filter(
                                                                              (
                                                                                  id
                                                                              ) =>
                                                                                  id !==
                                                                                  track.id
                                                                          )
                                                                        : [
                                                                              ...current,
                                                                              track.id,
                                                                          ]
                                                            )
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <div className="font-semibold text-slate-800">
                                                        {track.title ??
                                                            'Untitled Track'}
                                                    </div>

                                                    <div className="text-xs text-slate-500">
                                                        Track #
                                                        {track.track_number ??
                                                            '—'}
                                                    </div>
                                                </Cell>

                                                <Cell>
                                                    {track
                                                        ?.release
                                                        ?.title ??
                                                        '—'}
                                                </Cell>

                                                <Cell>
                                                    <StatusBadge
                                                        value={
                                                            track.audio_validation_status
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <div className="flex flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy
                                                            }
                                                            onClick={() =>
                                                                runAction(
                                                                    `/v2/admin/tracks/${track.id}/isrc/generate`
                                                                )
                                                            }
                                                            className="rounded-lg border border-violet-200 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-50 disabled:opacity-40"
                                                        >
                                                            Generate ISRC
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy
                                                            }
                                                            onClick={() =>
                                                                openManualIdentifier(
                                                                    'isrc',
                                                                    track.id,
                                                                    track.title
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                                                        >
                                                            Edit ISRC
                                                        </button>
                                                    </div>
                                                </Cell>
                                            </tr>
                                        )
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        links={
                            pendingIsrc?.links
                        }
                    />
                </Section>

                <Section
                    title="Pending UPC"
                    description={`${pendingUpc?.total ?? 0} releases waiting for UPC`}
                    action={
                        <button
                            type="button"
                            disabled={
                                busy ||
                                selectedReleases.length ===
                                    0
                            }
                            onClick={() =>
                                runAction(
                                    '/v2/admin/identifiers/upc/bulk-generate',
                                    {
                                        release_ids:
                                            selectedReleases,
                                    }
                                )
                            }
                            className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-40"
                        >
                            Generate Selected UPC
                        </button>
                    }
                >
                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    <HeaderCell>
                                        <input
                                            type="checkbox"
                                            checked={
                                                allReleasesSelected
                                            }
                                            onChange={(
                                                event
                                            ) =>
                                                setSelectedReleases(
                                                    event
                                                        .target
                                                        .checked
                                                        ? releases.map(
                                                              (
                                                                  release
                                                              ) =>
                                                                  release.id
                                                          )
                                                        : []
                                                )
                                            }
                                        />
                                    </HeaderCell>
                                    <HeaderCell>
                                        Release
                                    </HeaderCell>
                                    <HeaderCell>
                                        Artist
                                    </HeaderCell>
                                    <HeaderCell>
                                        Label
                                    </HeaderCell>
                                    <HeaderCell>
                                        Status
                                    </HeaderCell>
                                    <HeaderCell>
                                        Action
                                    </HeaderCell>
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {releases.length ===
                                0 ? (
                                    <EmptyRow
                                        text="No pending UPC releases."
                                        columns={6}
                                    />
                                ) : (
                                    releases.map(
                                        (release) => (
                                            <tr
                                                key={
                                                    release.id
                                                }
                                            >
                                                <Cell>
                                                    <input
                                                        type="checkbox"
                                                        checked={selectedReleases.includes(
                                                            release.id
                                                        )}
                                                        onChange={() =>
                                                            setSelectedReleases(
                                                                (
                                                                    current
                                                                ) =>
                                                                    current.includes(
                                                                        release.id
                                                                    )
                                                                        ? current.filter(
                                                                              (
                                                                                  id
                                                                              ) =>
                                                                                  id !==
                                                                                  release.id
                                                                          )
                                                                        : [
                                                                              ...current,
                                                                              release.id,
                                                                          ]
                                                            )
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <div className="font-semibold text-slate-800">
                                                        {release.title ??
                                                            'Untitled Release'}
                                                    </div>

                                                    <div className="text-xs text-slate-500">
                                                        ID #
                                                        {release.id}
                                                    </div>
                                                </Cell>

                                                <Cell>
                                                    {release
                                                        ?.artist
                                                        ?.stage_name ??
                                                        '—'}
                                                </Cell>

                                                <Cell>
                                                    {release
                                                        ?.label
                                                        ?.name ??
                                                        '—'}
                                                </Cell>

                                                <Cell>
                                                    <StatusBadge
                                                        value={
                                                            release.status
                                                        }
                                                    />
                                                </Cell>

                                                <Cell>
                                                    <div className="flex flex-wrap gap-2">
                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy
                                                            }
                                                            onClick={() =>
                                                                runAction(
                                                                    `/v2/admin/releases/${release.id}/upc/generate`
                                                                )
                                                            }
                                                            className="rounded-lg border border-violet-200 px-3 py-2 text-xs font-semibold text-violet-700 hover:bg-violet-50 disabled:opacity-40"
                                                        >
                                                            Generate UPC
                                                        </button>

                                                        <button
                                                            type="button"
                                                            disabled={
                                                                busy
                                                            }
                                                            onClick={() =>
                                                                openManualIdentifier(
                                                                    'upc',
                                                                    release.id,
                                                                    release.title
                                                                )
                                                            }
                                                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                                                        >
                                                            Edit UPC
                                                        </button>
                                                    </div>
                                                </Cell>
                                            </tr>
                                        )
                                    )
                                )}
                            </tbody>
                        </table>
                    </div>

                    <Pagination
                        links={
                            pendingUpc?.links
                        }
                    />
                </Section>

                {manualIdentifier && (
                    <div className="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/40 p-4">
                        <div className="w-full max-w-md rounded-2xl bg-white p-6 shadow-2xl">
                            <div className="flex items-start justify-between gap-4">
                                <div>
                                    <h2 className="text-lg font-bold text-slate-900">
                                        {manualIdentifier.type ===
                                        'isrc'
                                            ? 'Edit ISRC'
                                            : 'Edit UPC'}
                                    </h2>

                                    <p className="mt-1 text-sm text-slate-500">
                                        {
                                            manualIdentifier.title
                                        }
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={
                                        closeManualIdentifier
                                    }
                                    className="rounded-lg px-2 py-1 text-xl text-slate-400 hover:bg-slate-100 hover:text-slate-700 disabled:opacity-40"
                                >
                                    ×
                                </button>
                            </div>

                            <div className="mt-5">
                                <label className="mb-2 block text-sm font-semibold text-slate-700">
                                    {manualIdentifier.type ===
                                    'isrc'
                                        ? 'ISRC Code'
                                        : 'UPC / EAN Code'}
                                </label>

                                <input
                                    type="text"
                                    autoFocus
                                    value={manualValue}
                                    onChange={(event) =>
                                        setManualValue(
                                            event.target.value
                                        )
                                    }
                                    onKeyDown={(event) => {
                                        if (
                                            event.key ===
                                            'Enter'
                                        ) {
                                            event.preventDefault();
                                            saveManualIdentifier();
                                        }

                                        if (
                                            event.key ===
                                            'Escape'
                                        ) {
                                            closeManualIdentifier();
                                        }
                                    }}
                                    placeholder={
                                        manualIdentifier.type ===
                                        'isrc'
                                            ? 'Example: IN-MXT-26-00001'
                                            : 'Enter 12 or 13 digit UPC/EAN'
                                    }
                                    className="w-full rounded-xl border border-slate-300 px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                                />

                                <p className="mt-2 text-xs text-slate-500">
                                    {manualIdentifier.type ===
                                    'isrc'
                                        ? 'Enter an existing ISRC manually. Duplicate or invalid codes will be rejected.'
                                        : 'Enter an existing 12 or 13 digit UPC/EAN. Spaces or separators are normalized automatically.'}
                                </p>
                            </div>

                            <div className="mt-6 flex justify-end gap-3">
                                <button
                                    type="button"
                                    disabled={busy}
                                    onClick={
                                        closeManualIdentifier
                                    }
                                    className="rounded-xl border border-slate-300 px-4 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50 disabled:opacity-40"
                                >
                                    Cancel
                                </button>

                                <button
                                    type="button"
                                    disabled={
                                        busy ||
                                        !manualValue.trim()
                                    }
                                    onClick={
                                        saveManualIdentifier
                                    }
                                    className="rounded-xl bg-violet-600 px-4 py-2 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-40"
                                >
                                    {busy
                                        ? 'Saving...'
                                        : manualIdentifier.type ===
                                            'isrc'
                                          ? 'Save ISRC'
                                          : 'Save UPC'}
                                </button>
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </PanelLayout>
    );
}

function Section({
    title,
    description,
    action,
    children,
}) {
    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-3 border-b border-slate-200 px-5 py-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="font-bold text-slate-900">
                        {title}
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        {description}
                    </p>
                </div>

                {action}
            </div>

            {children}
        </section>
    );
}

function HeaderCell({ children }) {
    return (
        <th className="px-5 py-3 text-left text-xs font-bold uppercase tracking-wide text-slate-500">
            {children}
        </th>
    );
}

function Cell({ children }) {
    return (
        <td className="px-5 py-4 text-sm text-slate-600">
            {children}
        </td>
    );
}

function EmptyRow({
    text,
    columns,
}) {
    return (
        <tr>
            <td
                colSpan={columns}
                className="px-5 py-14 text-center text-sm text-slate-500"
            >
                {text}
            </td>
        </tr>
    );
}

function StatusBadge({ value }) {
    const status =
        value ?? 'pending';

    return (
        <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
            {String(status).replaceAll(
                '_',
                ' '
            )}
        </span>
    );
}

function Pagination({ links = [] }) {
    if (!Array.isArray(links)) {
        return null;
    }

    return (
        <div className="flex flex-wrap justify-center gap-2 border-t border-slate-200 px-5 py-4">
            {links.map((link, index) => (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url ?? '#'}
                    preserveScroll
                    className={`rounded-lg border px-3 py-2 text-xs font-semibold ${
                        link.active
                            ? 'border-violet-600 bg-violet-600 text-white'
                            : 'border-slate-200 bg-white text-slate-600'
                    } ${
                        !link.url
                            ? 'pointer-events-none opacity-40'
                            : ''
                    }`}
                    dangerouslySetInnerHTML={{
                        __html: link.label,
                    }}
                />
            ))}
        </div>
    );
}
