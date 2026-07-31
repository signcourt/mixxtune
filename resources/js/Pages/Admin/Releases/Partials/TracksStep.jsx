import { router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const emptyTrack = (release, trackNumber) => ({
    title: '',
    version: '',
    subtitle: '',
    primary_artist_name: release.primary_artist_name ?? '',
    featuring_artist_name: '',
    isrc: '',
    language: release.language ?? '',
    genre: release.primary_genre ?? '',
    sub_genre: release.sub_genre ?? '',
    is_explicit: false,
    is_instrumental: false,
    contains_ai_generated_content: false,
    lyrics: '',
    audio: null,
    disc_number: 1,
    track_number: trackNumber,
    status: 'draft',
});

export default function TracksStep({
    release,
    selectedTrackId = null,
    onTrackOpened = () => {},
}) {
    const tracks = release?.tracks ?? [];

    const [editingTrack, setEditingTrack] = useState(null);
    const [showForm, setShowForm] = useState(tracks.length === 0);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(emptyTrack(release, tracks.length + 1));

    const openAddForm = () => {
        clearErrors();
        setEditingTrack(null);
        reset();

        setData(
            emptyTrack(
                release,
                tracks.length > 0
                    ? Math.max(...tracks.map((track) => track.track_number ?? 0)) + 1
                    : 1
            )
        );

        setShowForm(true);
    };

    const openEditForm = (track) => {
        clearErrors();
        setEditingTrack(track);

        setData({
            title: track.title ?? '',
            version: track.version ?? '',
            subtitle: track.subtitle ?? '',
            primary_artist_name:
                track.primary_artist_name ??
                release.primary_artist_name ??
                '',
            featuring_artist_name: track.featuring_artist_name ?? '',
            isrc: track.isrc ?? '',
            language: track.language ?? release.language ?? '',
            genre: track.genre ?? release.primary_genre ?? '',
            sub_genre: track.sub_genre ?? release.sub_genre ?? '',
            is_explicit: Boolean(track.is_explicit),
            is_instrumental: Boolean(track.is_instrumental),
            contains_ai_generated_content: Boolean(
                track.contains_ai_generated_content
            ),
            lyrics: track.lyrics ?? '',
            audio: null,
            disc_number: track.disc_number ?? 1,
            track_number: track.track_number ?? 1,
            status: track.status ?? 'draft',
        });

        setShowForm(true);
    };

    useEffect(() => {
        if (!selectedTrackId) {
            return;
        }

        const track = tracks.find(
            (item) => String(item.id) === String(selectedTrackId)
        );

        if (!track) {
            return;
        }

        openEditForm(track);
        onTrackOpened();

        window.setTimeout(() => {
            document
                .getElementById('track-editor-form')
                ?.scrollIntoView({
                    behavior: 'smooth',
                    block: 'start',
                });
        }, 100);
    }, [selectedTrackId]);

    const closeForm = () => {
        clearErrors();
        setEditingTrack(null);
        setShowForm(false);
    };

    const submit = (event) => {
        event.preventDefault();

        if (editingTrack) {
            router.post(
                `/tracks/${editingTrack.id}`,
                {
                    ...data,
                    _method: 'patch',
                },
                {
                    forceFormData: true,
                    preserveScroll: true,
                    onSuccess: closeForm,
                }
            );

            return;
        }

        post(`/releases/${release.id}/tracks`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: closeForm,
        });
    };

    const removeTrack = (track) => {
        if (!window.confirm(`Delete "${track.title}"?`)) {
            return;
        }

        router.delete(`/tracks/${track.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <div className="space-y-6 p-6">
            <div className="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Tracks
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Add, edit or replace audio for every track.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={openAddForm}
                    className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                >
                    + Add Track
                </button>
            </div>

            {tracks.length > 0 && (
                <div className="space-y-3">
                    {tracks.map((track, index) => (
                        <div
                            key={track.id}
                            className="flex flex-col gap-4 rounded-2xl border border-slate-200 bg-white p-4 lg:flex-row lg:items-center"
                        >
                            <div className="flex h-11 w-11 items-center justify-center rounded-xl bg-slate-100 font-semibold text-slate-700">
                                {index + 1}
                            </div>

                            <div className="min-w-0 flex-1">
                                <div className="truncate font-semibold text-slate-900">
                                    {track.title}
                                    {track.version
                                        ? ` (${track.version})`
                                        : ''}
                                </div>

                                <div className="mt-1 text-sm text-slate-500">
                                    {track.primary_artist_name}
                                    {' · '}
                                    {track.isrc || 'ISRC pending'}
                                </div>
                            </div>

                            <div className="min-w-0 text-sm text-slate-500">
                                <div className="max-w-xs truncate">
                                    {track.audio_original_name ||
                                        'No WAV uploaded'}
                                </div>
                            </div>

                            <div className="flex flex-wrap items-center gap-2">
                                {track.audio_path && (
                                    <a
                                        href={`/tracks/${track.id}/download-audio`}
                                        className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                    >
                                        Download WAV
                                    </a>
                                )}

                                <button
                                    type="button"
                                    onClick={() => openEditForm(track)}
                                    className="rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                                >
                                    Edit
                                </button>

                                <button
                                    type="button"
                                    onClick={() => removeTrack(track)}
                                    className="rounded-lg px-3 py-2 text-sm font-semibold text-red-600 hover:bg-red-50"
                                >
                                    Delete
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showForm && (
                <form
                    id="track-editor-form"
                    onSubmit={submit}
                    className="scroll-mt-6 rounded-2xl border border-slate-200 bg-slate-50 p-5"
                >
                    <div className="mb-5 flex items-center justify-between gap-4">
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                {editingTrack ? 'Edit Track' : 'Add New Track'}
                            </h3>

                            {editingTrack?.audio_original_name && (
                                <p className="mt-1 text-xs text-slate-500">
                                    Current WAV:{' '}
                                    {editingTrack.audio_original_name}
                                </p>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={closeForm}
                            className="rounded-lg px-3 py-2 text-sm text-slate-500 hover:bg-slate-200"
                        >
                            Close
                        </button>
                    </div>

                    <div className="grid gap-5 md:grid-cols-2">
                        <Field
                            label="Track Title"
                            error={errors.title}
                            required
                        >
                            <input
                                className={inputClass}
                                value={data.title}
                                onChange={(event) =>
                                    setData('title', event.target.value)
                                }
                                placeholder="Enter track title"
                            />
                        </Field>

                        <Field label="Version">
                            <input
                                className={inputClass}
                                value={data.version}
                                onChange={(event) =>
                                    setData('version', event.target.value)
                                }
                                placeholder="Original, Remix, Acoustic..."
                            />
                        </Field>

                        <Field label="Subtitle">
                            <input
                                className={inputClass}
                                value={data.subtitle}
                                onChange={(event) =>
                                    setData('subtitle', event.target.value)
                                }
                                placeholder="Optional subtitle"
                            />
                        </Field>

                        <Field label="ISRC" error={errors.isrc}>
                            <input
                                className={inputClass}
                                value={data.isrc}
                                onChange={(event) =>
                                    setData('isrc', event.target.value)
                                }
                                placeholder="Leave blank if pending"
                            />
                        </Field>

                        <Field label="Primary Artist" required>
                            <input
                                className={inputClass}
                                value={data.primary_artist_name}
                                onChange={(event) =>
                                    setData(
                                        'primary_artist_name',
                                        event.target.value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Featuring Artists">
                            <input
                                className={inputClass}
                                value={data.featuring_artist_name}
                                onChange={(event) =>
                                    setData(
                                        'featuring_artist_name',
                                        event.target.value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Language">
                            <input
                                className={inputClass}
                                value={data.language}
                                onChange={(event) =>
                                    setData('language', event.target.value)
                                }
                            />
                        </Field>

                        <Field label="Genre">
                            <input
                                className={inputClass}
                                value={data.genre}
                                onChange={(event) =>
                                    setData('genre', event.target.value)
                                }
                            />
                        </Field>

                        <Field label="Sub Genre">
                            <input
                                className={inputClass}
                                value={data.sub_genre}
                                onChange={(event) =>
                                    setData('sub_genre', event.target.value)
                                }
                            />
                        </Field>

                        <Field
                            label={
                                editingTrack
                                    ? 'Replace WAV File'
                                    : 'WAV File'
                            }
                            error={errors.audio}
                        >
                            <input
                                type="file"
                                accept=".wav,audio/wav,audio/x-wav"
                                className={inputClass}
                                onChange={(event) =>
                                    setData(
                                        'audio',
                                        event.target.files?.[0] ?? null
                                    )
                                }
                            />

                            <p className="mt-1 text-xs text-slate-500">
                                Only WAV files are accepted. Existing audio
                                remains unchanged if no new file is selected.
                            </p>
                        </Field>

                        <Field label="Disc Number">
                            <input
                                type="number"
                                min="1"
                                className={inputClass}
                                value={data.disc_number}
                                onChange={(event) =>
                                    setData(
                                        'disc_number',
                                        Number(event.target.value)
                                    )
                                }
                            />
                        </Field>

                        <Field label="Track Number">
                            <input
                                type="number"
                                min="1"
                                className={inputClass}
                                value={data.track_number}
                                onChange={(event) =>
                                    setData(
                                        'track_number',
                                        Number(event.target.value)
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-3">
                        <Check
                            label="Explicit Content"
                            checked={data.is_explicit}
                            onChange={(value) =>
                                setData('is_explicit', value)
                            }
                        />

                        <Check
                            label="Instrumental"
                            checked={data.is_instrumental}
                            onChange={(value) =>
                                setData('is_instrumental', value)
                            }
                        />

                        <Check
                            label="AI Generated Content"
                            checked={data.contains_ai_generated_content}
                            onChange={(value) =>
                                setData(
                                    'contains_ai_generated_content',
                                    value
                                )
                            }
                        />
                    </div>

                    <div className="mt-5">
                        <Field label="Lyrics">
                            <textarea
                                className={`${inputClass} min-h-36`}
                                value={data.lyrics}
                                onChange={(event) =>
                                    setData('lyrics', event.target.value)
                                }
                                placeholder="Paste lyrics here..."
                            />
                        </Field>
                    </div>

                    <div className="mt-6 flex justify-end">
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : editingTrack
                                  ? 'Update Track'
                                  : 'Save Track'}
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}

function Field({ label, required = false, error, children }) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}
                {required && <span className="ml-1 text-red-500">*</span>}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">{error}</p>
            )}
        </div>
    );
}

function Check({ label, checked, onChange }) {
    return (
        <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-700">
            <input
                type="checkbox"
                checked={checked}
                onChange={(event) => onChange(event.target.checked)}
                className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
            />

            {label}
        </label>
    );
}
