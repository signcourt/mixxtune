import React, { useMemo, useState } from 'react';
import { Head, router } from '@inertiajs/react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const Field = ({ label, children }) => (
    <label className="block space-y-1">
        <span className="text-sm font-semibold text-slate-700">
            {label}
        </span>
        {children}
    </label>
);

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-indigo-500';

export default function OwnershipIndex({
    releases = [],
    tracks = [],
    artists = [],
    labels = [],
    users = [],
    history = [],
}) {
    const [search, setSearch] = useState('');

    const filteredReleases = useMemo(() => {
        const q = search.trim().toLowerCase();

        if (!q) return releases;

        return releases.filter((release) =>
            [
                release.title,
                release.upc,
                release.primary_artist_name,
            ]
                .filter(Boolean)
                .some((value) =>
                    String(value)
                        .toLowerCase()
                        .includes(q)
                )
        );
    }, [releases, search]);

    const post = (url, data) => {
        router.post(url, data, {
            preserveScroll: true,
        });
    };

    return (
        <PanelLayout
            role="super_admin"
            title="Ownership Transfer"
            subtitle="V2 Catalogue Ownership & User Transfer Centre"
        >
            <Head title="Ownership Transfer" />

            <div className="space-y-6">
                <div className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h1 className="text-2xl font-bold text-slate-900">
                        Ownership Transfer Centre
                    </h1>

                    <p className="mt-2 text-sm text-slate-600">
                        Revenue follows catalogue ownership resolved from
                        ISRC/UPC. Artist names remain metadata credits.
                    </p>
                </div>

                <div className="grid gap-6 xl:grid-cols-2">
                    <ReleaseTransfer
                        releases={filteredReleases}
                        artists={artists}
                        labels={labels}
                        search={search}
                        setSearch={setSearch}
                        post={post}
                    />

                    <TrackTransfer
                        tracks={tracks}
                        releases={releases}
                        post={post}
                    />

                    <UserTransfer
                        title="Transfer Label User"
                        entities={labels}
                        users={users}
                        entityKey="label_id"
                        entityLabel="Label"
                        endpoint="/v2/admin/ownership/label-user"
                        post={post}
                    />

                    <UserTransfer
                        title="Transfer Artist User"
                        entities={artists}
                        users={users}
                        entityKey="artist_id"
                        entityLabel="Artist"
                        endpoint="/v2/admin/ownership/artist-user"
                        post={post}
                    />
                </div>

                <div className="overflow-hidden rounded-2xl border border-slate-200 bg-white">
                    <div className="border-b border-slate-200 p-4">
                        <h2 className="font-bold text-slate-900">
                            Transfer History
                        </h2>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full text-sm">
                            <thead className="bg-slate-50 text-left text-slate-600">
                                <tr>
                                    <th className="px-4 py-3">Type</th>
                                    <th className="px-4 py-3">Entity</th>
                                    <th className="px-4 py-3">From</th>
                                    <th className="px-4 py-3">To</th>
                                    <th className="px-4 py-3">Scope</th>
                                    <th className="px-4 py-3">Date</th>
                                </tr>
                            </thead>

                            <tbody>
                                {history.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="border-t border-slate-100"
                                    >
                                        <td className="px-4 py-3">
                                            {item.transfer_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.entity_type} #{item.entity_id}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.from_owner_type || 'User'}{' '}
                                            #{item.from_owner_id || item.from_user_id || '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.to_owner_type || 'User'}{' '}
                                            #{item.to_owner_id || item.to_user_id || '-'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.revenue_scope}
                                        </td>
                                        <td className="px-4 py-3">
                                            {item.transferred_at}
                                        </td>
                                    </tr>
                                ))}

                                {history.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-4 py-10 text-center text-slate-500"
                                        >
                                            No transfer history yet.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </PanelLayout>
    );
}

function ReleaseTransfer({
    releases,
    artists,
    labels,
    search,
    setSearch,
    post,
}) {
    const [form, setForm] = useState({
        release_id: '',
        owner_type: 'label',
        owner_id: '',
        scope: 'future_only',
        reason: '',
    });

    const owners =
        form.owner_type === 'label'
            ? labels
            : artists;

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post('/v2/admin/ownership/release', form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                Song / Release Transfer
            </h2>

            <Field label="Search ISRC / UPC / Release">
                <input
                    className={inputClass}
                    value={search}
                    onChange={(event) =>
                        setSearch(event.target.value)
                    }
                    placeholder="Search catalogue..."
                />
            </Field>

            <Field label="Release">
                <select
                    className={inputClass}
                    value={form.release_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            release_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select release</option>
                    {releases.map((release) => (
                        <option key={release.id} value={release.id}>
                            {release.title} — {release.upc || 'No UPC'}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New Revenue Owner">
                <select
                    className={inputClass}
                    value={form.owner_type}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            owner_type: event.target.value,
                            owner_id: '',
                        })
                    }
                >
                    <option value="label">Label</option>
                    <option value="artist">Artist</option>
                </select>
            </Field>

            <Field label="Owner">
                <select
                    className={inputClass}
                    value={form.owner_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            owner_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select owner</option>
                    {owners.map((owner) => (
                        <option key={owner.id} value={owner.id}>
                            {owner.name || owner.stage_name}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Revenue Scope">
                <select
                    className={inputClass}
                    value={form.scope}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            scope: event.target.value,
                        })
                    }
                >
                    <option value="future_only">
                        Future reports only
                    </option>
                    <option value="pending_and_future">
                        Unpaid historical + future
                    </option>
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Transfer Release
            </button>
        </form>
    );
}

function TrackTransfer({ tracks, releases, post }) {
    const [form, setForm] = useState({
        track_id: '',
        release_id: '',
        scope: 'future_only',
        reason: '',
    });

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post('/v2/admin/ownership/track', form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                Track Transfer
            </h2>

            <Field label="Track / ISRC">
                <select
                    className={inputClass}
                    value={form.track_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            track_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select track</option>
                    {tracks.map((track) => (
                        <option key={track.id} value={track.id}>
                            {track.title} — {track.isrc || 'No ISRC'}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New Release">
                <select
                    className={inputClass}
                    value={form.release_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            release_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select release</option>
                    {releases.map((release) => (
                        <option key={release.id} value={release.id}>
                            {release.title}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Revenue Scope">
                <select
                    className={inputClass}
                    value={form.scope}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            scope: event.target.value,
                        })
                    }
                >
                    <option value="future_only">
                        Future reports only
                    </option>
                    <option value="pending_and_future">
                        Unpaid historical + future
                    </option>
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Transfer Track
            </button>
        </form>
    );
}

function UserTransfer({
    title,
    entities,
    users,
    entityKey,
    entityLabel,
    endpoint,
    post,
}) {
    const [form, setForm] = useState({
        [entityKey]: '',
        user_id: '',
        reason: '',
    });

    return (
        <form
            className="space-y-4 rounded-2xl border border-slate-200 bg-white p-5"
            onSubmit={(event) => {
                event.preventDefault();
                post(endpoint, form);
            }}
        >
            <h2 className="text-lg font-bold text-slate-900">
                {title}
            </h2>

            <Field label={entityLabel}>
                <select
                    className={inputClass}
                    value={form[entityKey]}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            [entityKey]: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">
                        Select {entityLabel.toLowerCase()}
                    </option>
                    {entities.map((entity) => (
                        <option key={entity.id} value={entity.id}>
                            {entity.name || entity.stage_name}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="New User">
                <select
                    className={inputClass}
                    value={form.user_id}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            user_id: event.target.value,
                        })
                    }
                    required
                >
                    <option value="">Select user</option>
                    {users.map((user) => (
                        <option key={user.id} value={user.id}>
                            {user.name} — {user.email}
                        </option>
                    ))}
                </select>
            </Field>

            <Field label="Reason">
                <textarea
                    className={inputClass}
                    value={form.reason}
                    onChange={(event) =>
                        setForm({
                            ...form,
                            reason: event.target.value,
                        })
                    }
                />
            </Field>

            <button className="rounded-xl bg-indigo-600 px-4 py-2 font-semibold text-white">
                Change User
            </button>
        </form>
    );
}
