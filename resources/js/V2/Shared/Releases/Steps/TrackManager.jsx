import {
    router,
    useForm,
} from '@inertiajs/react';

import {
    useEffect,
    useMemo,
    useState,
} from 'react';

import TrackUploadOverlay from '@/V2/Shared/Releases/V5/Tracks/TrackUploadOverlay';

const ARTIST_CONTEXT =
    typeof window !== 'undefined'
    && window.location.pathname.startsWith('/artist');

const RELEASES_BASE_PATH = ARTIST_CONTEXT
    ? '/artist/releases'
    : '/v2/releases';

const TRACKS_BASE_PATH = ARTIST_CONTEXT
    ? '/artist/tracks'
    : '/v2/tracks';

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const makeEmptyTrack = (
    release,
    number
) => ({
    title: '',
    version: '',
    subtitle: '',

    primary_artist_name:
        release?.primary_artist_name ?? '',

    featuring_artist_name: '',

    isrc: '',

    language:
        release?.language ?? '',

    genre:
        release?.primary_genre ?? '',

    sub_genre:
        release?.sub_genre ?? '',

    is_explicit: false,
    is_instrumental: false,
    contains_ai_generated_content: false,

    lyrics: '',
    audio: null,

    disc_number: 1,
    track_number: number,

    status: 'draft',
});

export default function TrackManager({
    release,
    onSaved = () => {},
    onMascotEvent = () => {},
}) {
    const tracks = Array.isArray(
        release?.tracks
    )
        ? release.tracks
        : [];

    const nextTrackNumber = useMemo(
        () =>
            tracks.length > 0
                ? Math.max(
                      ...tracks.map(
                          (track) =>
                              Number(
                                  track.track_number
                                      ?? 0
                              )
                      )
                  ) + 1
                : 1,
        [tracks]
    );

    const [editingTrack, setEditingTrack] =
        useState(null);

    const [formVisible, setFormVisible] =
        useState(true);

    const [uploading, setUploading] =
        useState(false);

    const [uploadProgress, setUploadProgress] =
        useState(0);

    const [uploadFileName, setUploadFileName] =
        useState('');

    useEffect(() => {
        document.documentElement.classList.toggle(
            'v2-audio-uploading',
            uploading
        );

        return () => {
            document.documentElement.classList.remove(
                'v2-audio-uploading'
            );
        };
    }, [uploading]);

    const {
        data,
        setData,
        post,
        processing,
        errors,
        reset,
        clearErrors,
    } = useForm(
        makeEmptyTrack(
            release,
            nextTrackNumber
        )
    );

    const addTrack = () => {
        clearErrors();
        setEditingTrack(null);

        setData(
            makeEmptyTrack(
                release,
                nextTrackNumber
            )
        );

        setFormVisible(true);
    };

    const editTrack = (track) => {
        clearErrors();
        setEditingTrack(track);

        setData({
            title: track.title ?? '',
            version: track.version ?? '',
            subtitle: track.subtitle ?? '',

            primary_artist_name:
                track.primary_artist_name
                ?? release.primary_artist_name
                ?? '',

            featuring_artist_name:
                track.featuring_artist_name
                ?? '',

            isrc: track.isrc ?? '',

            language:
                track.language
                ?? release.language
                ?? '',

            genre:
                track.genre
                ?? release.primary_genre
                ?? '',

            sub_genre:
                track.sub_genre
                ?? release.sub_genre
                ?? '',

            is_explicit:
                Boolean(track.is_explicit),

            is_instrumental:
                Boolean(
                    track.is_instrumental
                ),

            contains_ai_generated_content:
                Boolean(
                    track
                        .contains_ai_generated_content
                ),

            lyrics: track.lyrics ?? '',
            audio: null,

            disc_number:
                Number(
                    track.disc_number ?? 1
                ),

            track_number:
                Number(
                    track.track_number ?? 1
                ),

            status:
                track.status ?? 'draft',
        });

        setFormVisible(true);
    };

    const closeForm = () => {
        clearErrors();
        setEditingTrack(null);

        setData(
            makeEmptyTrack(
                release,
                nextTrackNumber
            )
        );

        setFormVisible(true);
    };

    const submitTrack = (event) => {
        event.preventDefault();

        const hasAudio =
            data.audio instanceof File;

        if (hasAudio) {
            setUploadFileName(
                data.audio.name
            );

            setUploadProgress(0);
            setUploading(true);
        }

        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,

            onStart: () => {
                if (hasAudio) {
                    setUploading(true);
                    onMascotEvent(
                        'track-uploading'
                    );
                }
            },

            onProgress: (progress) => {
                if (
                    hasAudio &&
                    progress?.percentage !==
                        undefined
                ) {
                    setUploadProgress(
                        Math.round(
                            progress.percentage
                        )
                    );
                }
            },

            onSuccess: () => {
                setUploadProgress(100);

                if (hasAudio) {
                    onMascotEvent(
                        'track-uploaded'
                    );
                }

                window.setTimeout(() => {
                    setUploading(false);
                    closeForm();
                    onSaved();
                }, hasAudio ? 900 : 0);
            },

            onError: () => {
                setUploading(false);
                setUploadProgress(0);
                onMascotEvent(
                    'track-error'
                );
            },
        };

        if (editingTrack) {
            router.post(
                `${TRACKS_BASE_PATH}/${editingTrack.id}`,
                {
                    ...data,
                    _method: 'patch',
                },
                requestOptions
            );

            return;
        }

        post(
            `${RELEASES_BASE_PATH}/${release.id}/tracks`,
            requestOptions
        );
    };

    const deleteTrack = (track) => {
        if (
            !window.confirm(
                `Delete "${track.title}"?`
            )
        ) {
            return;
        }

        router.delete(
            `${TRACKS_BASE_PATH}/${track.id}`,
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <div className="mixx-v4-track-manager">
            <TrackUploadOverlay
                visible={uploading}
                fileName={uploadFileName}
                progress={uploadProgress}
            />

            <div className="mixx-v4-track-toolbar">
                <div>
                    <h2 className="mixx-v4-track-heading">
                        Track Upload
                    </h2>

                    <p className="mixx-v4-track-subheading">
                        Upload WAV masters and complete
                        all track information.
                    </p>
                </div>

                <button
                    type="button"
                    onClick={addTrack}
                    className="mixx-v4-add-track-button"
                >
                    + Add Track
                </button>
            </div>

            {tracks.length > 0 && (
                <section className="mixx-v4-track-list-card">
                    <div className="mixx-v4-track-list-heading">
                        <div>
                            <h3>
                                Tracks ({tracks.length})
                            </h3>

                            <p>
                                Uploaded masters added to this release.
                            </p>
                        </div>
                    </div>

                    <div className="mixx-v4-track-list">
                    {tracks.map(
                        (track, index) => (
                            <div
                                key={track.id}
                                className="mixx-v4-track-row"
                            >
                                <div className="mixx-v4-track-number">
                                    <span>♪</span>
                                    <small>{index + 1}</small>
                                </div>

                                <div className="min-w-0 flex-1">
                                    <div className="truncate font-semibold text-slate-900">
                                        {track.title}

                                        {track.version
                                            ? ` (${track.version})`
                                            : ''}
                                    </div>

                                    <div className="mt-1 text-sm text-slate-500">
                                        {
                                            track.primary_artist_name
                                        }
                                        {' · '}
                                        {track.isrc ||
                                            'ISRC Pending'}
                                    </div>
                                </div>

                                <div className="max-w-xs truncate text-sm text-slate-500">
                                    {track.audio_original_name ||
                                        'No WAV uploaded'}
                                </div>

                                <div className="mixx-v4-track-actions">
                                    {track.audio_path && (
                                        <a
                                            href={`${TRACKS_BASE_PATH}/${track.id}/${ARTIST_CONTEXT ? 'download-audio' : 'download'}`}
                                            className="mixx-v4-track-action-button"
                                        >
                                            Download WAV
                                        </a>
                                    )}

                                    <button
                                        type="button"
                                        onClick={() =>
                                            editTrack(
                                                track
                                            )
                                        }
                                        onMouseDown={(e)=>e.preventDefault()}
                                        className="mixx-v4-track-action-button"
                                    type="button"
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            deleteTrack(
                                                track
                                            )
                                        }
                                        className="mixx-v4-track-delete-button"
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        )
                    )}
                    </div>
                </section>
            )}

            {formVisible && (
                <form
                    onSubmit={submitTrack}
                    className="mixx-v4-track-form-card"
                >
                    <div className="mixx-v4-track-form-heading">
                        <div>
                            <h3>
                                {editingTrack
                                    ? 'Edit Track'
                                    : 'Add New Track'}
                            </h3>

                            {editingTrack
                                ?.audio_original_name && (
                                <p className="mt-1 text-xs text-slate-500">
                                    Current WAV:{' '}
                                    {
                                        editingTrack.audio_original_name
                                    }
                                </p>
                            )}
                        </div>

                        <button
                            type="button"
                            onClick={closeForm}
                            className="mixx-v4-track-close-button"
                        >
                            Close
                        </button>
                    </div>

                    <div className="mixx-v4-track-form-grid">
                        <Field
                            label="Track Title"
                            required
                            error={errors.title}
                        >
                            <input
                                className={inputClass}
                                value={data.title}
                                onChange={(event) =>
                                    setData(
                                        'title',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Version">
                            <input
                                className={inputClass}
                                value={data.version}
                                onChange={(event) =>
                                    setData(
                                        'version',
                                        event.target
                                            .value
                                    )
                                }
                                placeholder="Original, Remix..."
                            />
                        </Field>

                        <Field label="Primary Artist" required>
                            <input
                                className={inputClass}
                                value={
                                    data.primary_artist_name
                                }
                                onChange={(event) =>
                                    setData(
                                        'primary_artist_name',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Featuring Artist">
                            <input
                                className={inputClass}
                                value={
                                    data.featuring_artist_name
                                }
                                onChange={(event) =>
                                    setData(
                                        'featuring_artist_name',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <Field
                            label="ISRC"
                            error={errors.isrc}
                        >
                            <input
                                className={inputClass}
                                value={data.isrc}
                                onChange={(event) =>
                                    setData(
                                        'isrc',
                                        event.target
                                            .value
                                    )
                                }
                                placeholder="Leave blank if pending"
                            />
                        </Field>

                        <Field label="Language">
                            <input
                                className={inputClass}
                                value={data.language}
                                onChange={(event) =>
                                    setData(
                                        'language',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Genre">
                            <input
                                className={inputClass}
                                value={data.genre}
                                onChange={(event) =>
                                    setData(
                                        'genre',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Sub Genre">
                            <input
                                className={inputClass}
                                value={data.sub_genre}
                                onChange={(event) =>
                                    setData(
                                        'sub_genre',
                                        event.target
                                            .value
                                    )
                                }
                            />
                        </Field>

                        <div className="mixx-v4-track-audio-field">
                            <Field
                                label={
                                    editingTrack
                                        ? 'Replace WAV'
                                        : 'Upload Audio File (WAV)'
                                }
                                required={!editingTrack}
                                error={errors.audio}
                            >
                                <label className="mixx-v4-track-upload-zone">
                                    <input
                                        type="file"
                                        accept=".wav,audio/wav,audio/x-wav"
                                        className="hidden"
                                        onChange={(event) => {
                                            const file =
                                                event.target.files?.[0]
                                                ?? null;

                                            setData(
                                                'audio',
                                                file
                                            );

                                            setUploadFileName(
                                                file?.name ?? ''
                                            );
                                        }}
                                    />

                                    <span className="mixx-v4-track-upload-icon">
                                        ⇧
                                    </span>

                                    <strong>
                                        {data.audio?.name
                                            ?? editingTrack
                                                ?.audio_original_name
                                            ?? 'Drag & drop your WAV file here'}
                                    </strong>

                                    <span>
                                        or click to choose a file
                                    </span>

                                    <small>
                                        WAV only · Maximum 300 MB
                                    </small>
                                </label>
                            </Field>
                        </div>

                        <Field label="Disc Number">
                            <input
                                type="number"
                                min="1"
                                className={inputClass}
                                value={
                                    data.disc_number
                                }
                                onChange={(event) =>
                                    setData(
                                        'disc_number',
                                        Number(
                                            event.target
                                                .value
                                        )
                                    )
                                }
                            />
                        </Field>

                        <Field label="Track Number">
                            <input
                                type="number"
                                min="1"
                                className={inputClass}
                                value={
                                    data.track_number
                                }
                                onChange={(event) =>
                                    setData(
                                        'track_number',
                                        Number(
                                            event.target
                                                .value
                                        )
                                    )
                                }
                            />
                        </Field>
                    </div>

                    <div className="mixx-v4-track-options-grid">
                        <CheckBox
                            label="Explicit Content"
                            checked={
                                data.is_explicit
                            }
                            onChange={(value) =>
                                setData(
                                    'is_explicit',
                                    value
                                )
                            }
                        />

                        <CheckBox
                            label="Instrumental"
                            checked={
                                data.is_instrumental
                            }
                            onChange={(value) =>
                                setData(
                                    'is_instrumental',
                                    value
                                )
                            }
                        />

                        <CheckBox
                            label="AI Generated Content"
                            checked={
                                data.contains_ai_generated_content
                            }
                            onChange={(value) =>
                                setData(
                                    'contains_ai_generated_content',
                                    value
                                )
                            }
                        />
                    </div>

                    <Field label="Lyrics">
                        <textarea
                            className={`${inputClass} mt-5 min-h-36 resize-y`}
                            value={data.lyrics}
                            onChange={(event) =>
                                setData(
                                    'lyrics',
                                    event.target.value
                                )
                            }
                            placeholder="Optional lyrics"
                        />
                    </Field>

                    <div className="mixx-v4-track-submit-row">
                        <button
                            type="submit"
                            disabled={processing}
                            className="mixx-v4-track-submit-button"
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

function Field({
    label,
    required = false,
    error = null,
    children,
}) {
    return (
        <div>
            <label className="mb-2 block text-sm font-medium text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {error && (
                <p className="mt-1 text-sm text-red-600">
                    {error}
                </p>
            )}
        </div>
    );
}

function CheckBox({
    label,
    checked,
    onChange,
}) {
    return (
        <label className="flex cursor-pointer items-center justify-between rounded-xl border border-slate-200 bg-white p-4">
            <span className="text-sm font-medium text-slate-700">
                {label}
            </span>

            <input
                type="checkbox"
                checked={checked}
                onChange={(event) =>
                    onChange(
                        event.target.checked
                    )
                }
                className="h-5 w-5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
            />
        </label>
    );
}
