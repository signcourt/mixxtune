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
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

const trackTypes = [
    ['original', 'Original'],
    ['karaoke', 'Karaoke'],
    ['medley', 'Medley'],
    ['cover', 'Cover'],
    ['cover_by_cover_band', 'Cover by Cover Band'],
];

const parentalOptions = [
    ['no', 'No'],
    ['yes', 'Yes'],
    ['cleaned', 'Cleaned'],
];

const TRACK_GENRES = [
    'Alternative',
    'Anime',
    'Blues',
    'Bollywood',
    'Children',
    'Classical',
    'Comedy',
    'Country',
    'Dance',
    'Devotional',
    'Electronic',
    'Folk',
    'Ghazal',
    'Hip-Hop/Rap',
    'Indian',
    'Instrumental',
    'Jazz',
    'K-Pop',
    'Latin',
    'Metal',
    'New Age',
    'Pop',
    'Punjabi',
    'R&B/Soul',
    'Reggae',
    'Regional Indian',
    'Rock',
    'Singer/Songwriter',
    'Soundtrack',
    'Spiritual',
    'World',
];

const TRACK_SUB_GENRES = [
    'Acoustic',
    'Ambient',
    'Bhajan',
    'Bhojpuri',
    'Christian & Gospel',
    'Dance Pop',
    'Desi Hip-Hop',
    'Devotional & Spiritual',
    'EDM',
    'Folk Pop',
    'Haryanvi',
    'Hindi Pop',
    'House',
    'Indie Pop',
    'Indian Classical',
    'Indian Folk',
    'Karaoke',
    'Lo-Fi',
    'Meditation',
    'Punjabi Pop',
    'Qawwali',
    'Rajasthani',
    'Remix',
    'Sufi',
    'Tamil',
    'Telugu',
    'Trap',
    'World Pop',
];

const TRACK_LANGUAGES = [
    'Assamese',
    'Awadhi',
    'Bengali',
    'Bhojpuri',
    'Bodo',
    'Dogri',
    'English',
    'Garhwali',
    'Gujarati',
    'Haryanvi',
    'Hindi',
    'Kannada',
    'Kashmiri',
    'Khasi',
    'Konkani',
    'Kumaoni',
    'Maithili',
    'Malayalam',
    'Manipuri',
    'Marathi',
    'Marwari',
    'Mizo',
    'Nagpuri',
    'Nepali',
    'Odia',
    'Punjabi',
    'Rajasthani',
    'Sanskrit',
    'Santali',
    'Sindhi',
    'Tamil',
    'Telugu',
    'Tulu',
    'Urdu',
    'Afrikaans',
    'Albanian',
    'Amharic',
    'Arabic',
    'Armenian',
    'Azerbaijani',
    'Basque',
    'Belarusian',
    'Bosnian',
    'Bulgarian',
    'Burmese',
    'Catalan',
    'Chinese (Cantonese)',
    'Chinese (Mandarin)',
    'Croatian',
    'Czech',
    'Danish',
    'Dutch',
    'Estonian',
    'Filipino',
    'Finnish',
    'French',
    'Georgian',
    'German',
    'Greek',
    'Hausa',
    'Hebrew',
    'Hungarian',
    'Icelandic',
    'Indonesian',
    'Irish',
    'Italian',
    'Japanese',
    'Javanese',
    'Kazakh',
    'Khmer',
    'Korean',
    'Kurdish',
    'Lao',
    'Latin',
    'Latvian',
    'Lithuanian',
    'Macedonian',
    'Malay',
    'Maltese',
    'Mongolian',
    'Norwegian',
    'Pashto',
    'Persian',
    'Polish',
    'Portuguese',
    'Romanian',
    'Russian',
    'Serbian',
    'Sinhala',
    'Slovak',
    'Slovenian',
    'Somali',
    'Spanish',
    'Swahili',
    'Swedish',
    'Thai',
    'Turkish',
    'Ukrainian',
    'Uzbek',
    'Vietnamese',
    'Welsh',
    'Yoruba',
    'Zulu',
];

const makeEmptyTrack = (
    release,
    number
) => ({
    title: '',
    version: '',
    subtitle: '',

    track_type: 'original',

    primary_artist_name:
        release?.primary_artist_name ?? '',

    featuring_artist_name: '',

    author_name: '',
    composer_name: '',
    arranger_name: '',
    producer_name: '',
    music_director_name: '',
    publisher_name: '',

    p_line: '',
    release_year: new Date().getFullYear(),

    isrc: '',
    isrc_is_auto_generated: true,

    language:
        release?.language ?? '',

    title_language:
        release?.language ?? '',

    lyrics_language:
        release?.language ?? '',

    genre:
        release?.primary_genre ?? '',

    sub_genre:
        release?.sub_genre ?? '',

    parental_advisory: 'no',
    price_tier: 'premium',

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
    const tracks = Array.isArray(release?.tracks)
        ? release.tracks
        : [];

    const nextTrackNumber = useMemo(
        () =>
            tracks.length > 0
                ? Math.max(
                      ...tracks.map(
                          (track) =>
                              Number(
                                  track.track_number ?? 0
                              )
                      )
                  ) + 1
                : 1,
        [tracks]
    );

    const [editingTrack, setEditingTrack] =
        useState(null);

    const [formVisible, setFormVisible] =
        useState(false);

    const [modalStage, setModalStage] =
        useState('upload');

    const [uploading, setUploading] =
        useState(false);

    const [uploadProgress, setUploadProgress] =
        useState(0);

    const [uploadFileName, setUploadFileName] =
        useState('');

    const [playingTrackId, setPlayingTrackId] =
        useState(null);

    const [audioPlayer, setAudioPlayer] =
        useState(null);

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

    useEffect(() => {
        document.body.style.overflow =
            formVisible ? 'hidden' : '';

        return () => {
            document.body.style.overflow = '';
        };
    }, [formVisible]);

    const {
        data,
        setData,
        post,
        processing,
        errors,
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

        setUploadFileName('');
        setModalStage('upload');
        setFormVisible(true);
    };

    const editTrack = (track) => {
        clearErrors();
        setEditingTrack(track);

        setData({
            title: track.title ?? '',
            version: track.version ?? '',
            subtitle: track.subtitle ?? '',

            track_type:
                track.track_type ?? 'original',

            primary_artist_name:
                track.primary_artist_name
                ?? release?.primary_artist_name
                ?? '',

            featuring_artist_name:
                track.featuring_artist_name ?? '',

            author_name:
                track.author_name ?? '',

            composer_name:
                track.composer_name ?? '',

            arranger_name:
                track.arranger_name ?? '',

            producer_name:
                track.producer_name ?? '',

            music_director_name:
                track.music_director_name ?? '',

            publisher_name:
                track.publisher_name ?? '',

            p_line:
                track.p_line ?? '',

            release_year:
                track.release_year
                ?? new Date().getFullYear(),

            isrc:
                track.isrc ?? '',

            isrc_is_auto_generated:
                Boolean(
                    track.isrc_is_auto_generated
                ),

            language:
                track.language
                ?? release?.language
                ?? '',

            title_language:
                track.title_language
                ?? track.language
                ?? release?.language
                ?? '',

            lyrics_language:
                track.lyrics_language
                ?? track.language
                ?? release?.language
                ?? '',

            genre:
                track.genre
                ?? release?.primary_genre
                ?? '',

            sub_genre:
                track.sub_genre
                ?? release?.sub_genre
                ?? '',

            parental_advisory:
                track.parental_advisory
                ?? 'no',

            price_tier: 'premium',

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

            lyrics:
                track.lyrics ?? '',

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

        setUploadFileName(
            track.audio_original_name ?? ''
        );

        setModalStage('details');
        setFormVisible(true);
    };

    const closeForm = () => {
        if (processing || uploading) {
            return;
        }

        clearErrors();
        setEditingTrack(null);

        setData(
            makeEmptyTrack(
                release,
                nextTrackNumber
            )
        );

        setUploadFileName('');
        setModalStage('upload');
        setFormVisible(false);
    };

    const chooseAudio = (file) => {
        if (!file) {
            return;
        }

        const lowerName =
            file.name.toLowerCase();

        if (!lowerName.endsWith('.wav')) {
            window.alert(
                'Only WAV audio files are allowed.'
            );

            return;
        }

        const maxSize =
            300 * 1024 * 1024;

        if (file.size > maxSize) {
            window.alert(
                'WAV file must be 300 MB or smaller.'
            );

            return;
        }

        setData('audio', file);
        setUploadFileName(file.name);
    };

    const continueToDetails = () => {
        if (
            !editingTrack
            && !(data.audio instanceof File)
        ) {
            window.alert(
                'Please select a WAV master first.'
            );

            return;
        }

        setModalStage('details');
    };

    const submitTrack = (event) => {
        event.preventDefault();

        const hasAudio =
            data.audio instanceof File;

        if (!editingTrack && !hasAudio) {
            setModalStage('upload');

            window.alert(
                'Please select a WAV master.'
            );

            return;
        }

        setData(
            'is_explicit',
            data.parental_advisory === 'yes'
        );

        if (hasAudio) {
            setUploadFileName(
                data.audio.name
            );

            setUploadProgress(0);
            setUploading(true);
        }

        const payload = {
            ...data,

            is_explicit:
                data.parental_advisory === 'yes',
        };

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
                    hasAudio
                    && progress?.percentage
                        !== undefined
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
                    setFormVisible(false);
                    setEditingTrack(null);
                    setModalStage('upload');
                    onSaved();
                }, hasAudio ? 700 : 0);
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
                    ...payload,
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

    const toggleTrackPlayback = (track) => {
        if (!track?.audio_path) {
            return;
        }

        if (
            playingTrackId === track.id
            && audioPlayer
        ) {
            audioPlayer.pause();
            setPlayingTrackId(null);
            return;
        }

        if (audioPlayer) {
            audioPlayer.pause();
            audioPlayer.src = '';
        }

        const player = new Audio(
            `${TRACKS_BASE_PATH}/${track.id}/stream`
        );

        player.preload = 'metadata';

        player.onended = () => {
            setPlayingTrackId(null);
            setAudioPlayer(null);
        };

        player.onerror = () => {
            setPlayingTrackId(null);
            setAudioPlayer(null);

            window.alert(
                'Unable to play this WAV master.'
            );
        };

        setAudioPlayer(player);
        setPlayingTrackId(track.id);

        player.play().catch(() => {
            setPlayingTrackId(null);
            setAudioPlayer(null);

            window.alert(
                'Audio playback could not be started.'
            );
        });
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
        <div className="mixx-v6-track-manager">
            <TrackUploadOverlay
                visible={uploading}
                fileName={uploadFileName}
                progress={uploadProgress}
            />

            <div className="mixx-v6-track-toolbar">
                <h2>Tracks</h2>

                <button
                    type="button"
                    onClick={addTrack}
                    className="mixx-v6-add-track"
                >
                    <span>＋</span>
                    Add Track
                </button>
            </div>

            {tracks.length === 0 && (
                <div className="mixx-v6-track-empty">
                    <div className="mixx-v6-empty-icon">
                        ♪
                    </div>

                    <h3>No tracks added yet</h3>

                    <p>
                        Add your first master track to this release.
                    </p>
                </div>
            )}

            {tracks.length > 0 && (
                <div className="mixx-v6-track-list">
                    {tracks.map(
                        (track, index) => (
                            <div
                                key={track.id}
                                className="mixx-v6-track-row"
                            >
                                <div className="mixx-v6-track-index">
                                    {index + 1}
                                </div>

                                <button
                                    type="button"
                                    onClick={() =>
                                        toggleTrackPlayback(track)
                                    }
                                    disabled={!track.audio_path}
                                    title={
                                        playingTrackId === track.id
                                            ? 'Pause track'
                                            : 'Play track'
                                    }
                                    className={`flex h-11 w-11 shrink-0 items-center justify-center rounded-full border text-sm font-black transition ${
                                        playingTrackId === track.id
                                            ? 'border-violet-600 bg-violet-600 text-white'
                                            : 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100'
                                    } ${
                                        !track.audio_path
                                            ? 'cursor-not-allowed opacity-30'
                                            : ''
                                    }`}
                                >
                                    {playingTrackId === track.id
                                        ? '❚❚'
                                        : '▶'}
                                </button>

                                <div className="mixx-v6-track-main">
                                    <strong>
                                        {track.title}
                                    </strong>

                                    <span>
                                        {
                                            track.primary_artist_name
                                        }
                                        {' · '}
                                        {track.isrc
                                            || 'ISRC Pending'}
                                    </span>
                                </div>

                                <div className="mixx-v6-track-file">
                                    {track.audio_original_name
                                        || 'No WAV'}
                                </div>

                                <div className="mixx-v6-track-actions">
                                    <button
                                        type="button"
                                        onClick={() =>
                                            editTrack(track)
                                        }
                                    >
                                        Edit
                                    </button>

                                    <button
                                        type="button"
                                        className="danger"
                                        onClick={() =>
                                            deleteTrack(track)
                                        }
                                    >
                                        Delete
                                    </button>
                                </div>
                            </div>
                        )
                    )}
                </div>
            )}

            {formVisible && (
                <div
                    className="mixx-v6-modal-shell"
                    role="dialog"
                    aria-modal="true"
                >
                    <div
                        className="mixx-v6-modal-backdrop"
                        onClick={closeForm}
                    />

                    <div className="mixx-v6-track-modal">
                        <div className="mixx-v6-modal-header">
                            <div>
                                <span className="mixx-v6-modal-eyebrow">
                                    {editingTrack
                                        ? 'EDIT TRACK'
                                        : 'NEW TRACK'}
                                </span>

                                <h2>
                                    {modalStage === 'upload'
                                        ? 'Upload Track'
                                        : 'Track Details'}
                                </h2>

                                <p>
                                    {modalStage === 'upload'
                                        ? 'Upload your WAV master before entering metadata.'
                                        : 'Complete the metadata for this recording.'}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={closeForm}
                                className="mixx-v6-modal-close"
                            >
                                ×
                            </button>
                        </div>

                        <div className="mixx-v6-modal-steps">
                            <div
                                className={
                                    modalStage === 'upload'
                                        ? 'active'
                                        : 'complete'
                                }
                            >
                                <span>1</span>
                                Upload WAV
                            </div>

                            <div
                                className={
                                    modalStage === 'details'
                                        ? 'active'
                                        : ''
                                }
                            >
                                <span>2</span>
                                Track Details
                            </div>
                        </div>

                        {modalStage === 'upload' && (
                            <div className="mixx-v6-upload-stage">
                                <label className="mixx-v6-upload-box">
                                    <input
                                        type="file"
                                        accept=".wav,audio/wav,audio/x-wav"
                                        className="hidden"
                                        onChange={(event) =>
                                            chooseAudio(
                                                event.target
                                                    .files?.[0]
                                                ?? null
                                            )
                                        }
                                    />

                                    <div className="mixx-v6-upload-symbol">
                                        ⇧
                                    </div>

                                    <h3>
                                        {data.audio?.name
                                            ?? editingTrack
                                                ?.audio_original_name
                                            ?? 'Upload WAV Master'}
                                    </h3>

                                    <p>
                                        Drag & drop or click to browse
                                    </p>

                                    <span>
                                        WAV only · Maximum 300 MB
                                    </span>
                                </label>

                                {data.audio && (
                                    <div className="mixx-v6-selected-file">
                                        <div>
                                            <strong>
                                                {data.audio.name}
                                            </strong>

                                            <span>
                                                {(
                                                    data.audio.size
                                                    / 1024
                                                    / 1024
                                                ).toFixed(2)}
                                                {' MB'}
                                            </span>
                                        </div>

                                        <span>✓ Ready</span>
                                    </div>
                                )}

                                <div className="mixx-v6-upload-footer">
                                    <button
                                        type="button"
                                        onClick={closeForm}
                                        className="mixx-v6-secondary-button"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="button"
                                        onClick={continueToDetails}
                                        className="mixx-v6-primary-button"
                                    >
                                        Continue to Track Details
                                        <span>→</span>
                                    </button>
                                </div>
                            </div>
                        )}

                        {modalStage === 'details' && (
                            <form
                                onSubmit={submitTrack}
                                className="mixx-v6-details-form"
                            >
                                <SectionTitle
                                    number="01"
                                    title="Track Information"
                                    subtitle="Basic information about this recording."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field
                                        label="Track Type"
                                        required
                                    >
                                        <select
                                            className={inputClass}
                                            value={data.track_type}
                                            onChange={(event) =>
                                                setData(
                                                    'track_type',
                                                    event.target.value
                                                )
                                            }
                                        >
                                            {trackTypes.map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                )
                                            )}
                                        </select>
                                    </Field>

                                    <Field
                                        label="Instrumental"
                                        required
                                    >
                                        <select
                                            className={inputClass}
                                            value={
                                                data.is_instrumental
                                                    ? 'yes'
                                                    : 'no'
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'is_instrumental',
                                                    event.target.value
                                                        === 'yes'
                                                )
                                            }
                                        >
                                            <option value="no">
                                                No
                                            </option>

                                            <option value="yes">
                                                Yes
                                            </option>
                                        </select>
                                    </Field>

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
                                                    event.target.value
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
                                                    event.target.value
                                                )
                                            }
                                            placeholder="Original, Remix, Acoustic..."
                                        />
                                    </Field>
                                </div>

                                <SectionTitle
                                    number="02"
                                    title="Artists & Credits"
                                    subtitle="Enter the performers and creative contributors."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field
                                        label="Primary Artist / Singer"
                                        required
                                    >
                                        <input
                                            className={inputClass}
                                            value={
                                                data.primary_artist_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'primary_artist_name',
                                                    event.target.value
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
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Author / Lyricist / Writer"
                                        required={
                                            !data.is_instrumental
                                        }
                                    >
                                        <input
                                            className={inputClass}
                                            value={data.author_name}
                                            onChange={(event) =>
                                                setData(
                                                    'author_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Composer"
                                        required
                                    >
                                        <input
                                            className={inputClass}
                                            value={
                                                data.composer_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'composer_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="Arranger">
                                        <input
                                            className={inputClass}
                                            value={
                                                data.arranger_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'arranger_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="Producer">
                                        <input
                                            className={inputClass}
                                            value={
                                                data.producer_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'producer_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="Music Director">
                                        <input
                                            className={inputClass}
                                            value={
                                                data.music_director_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'music_director_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="Publisher">
                                        <input
                                            className={inputClass}
                                            value={
                                                data.publisher_name
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'publisher_name',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>
                                </div>

                                <SectionTitle
                                    number="03"
                                    title="Rights & Identification"
                                    subtitle="Copyright, ISRC and release information."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field
                                        label="Releasing Year"
                                        required
                                        error={errors.release_year}
                                    >
                                        <input
                                            type="number"
                                            min="1900"
                                            max={
                                                new Date()
                                                    .getFullYear()
                                                + 1
                                            }
                                            className={inputClass}
                                            value={
                                                data.release_year
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'release_year',
                                                    event.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Generate ISRC"
                                        required
                                        error={errors.isrc_is_auto_generated}
                                    >
                                        <select
                                            className={inputClass}
                                            value={
                                                data
                                                    .isrc_is_auto_generated
                                                    ? 'yes'
                                                    : 'no'
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'isrc_is_auto_generated',
                                                    event.target.value
                                                        === 'yes'
                                                )
                                            }
                                        >
                                            <option value="yes">
                                                Yes
                                            </option>

                                            <option value="no">
                                                No
                                            </option>
                                        </select>
                                    </Field>

                                    {!data.isrc_is_auto_generated && (
                                        <Field
                                            label="ISRC"
                                            required
                                            error={errors.isrc}
                                        >
                                            <input
                                                className={inputClass}
                                                value={data.isrc}
                                                onChange={(event) =>
                                                    setData(
                                                        'isrc',
                                                        event.target.value
                                                    )
                                                }
                                                placeholder="Enter existing ISRC"
                                            />
                                        </Field>
                                    )}

                                    <Field
                                        label="Track Number"
                                        required
                                        error={errors.track_number}
                                    >
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
                                                        event.target.value
                                                    )
                                                )
                                            }
                                        />
                                    </Field>
                                </div>

                                <SectionTitle
                                    number="04"
                                    title="Genre & Language"
                                    subtitle="Classification and language metadata."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field
                                        label="Genre"
                                        required
                                        error={errors.genre}
                                    >
                                        <SearchableTrackSelect
                                            value={data.genre}
                                            options={TRACK_GENRES}
                                            placeholder="Search genre..."
                                            onChange={(value) =>
                                                setData(
                                                    'genre',
                                                    value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Sub Genre"
                                        required
                                        error={errors.sub_genre}
                                    >
                                        <SearchableTrackSelect
                                            value={data.sub_genre}
                                            options={TRACK_SUB_GENRES}
                                            placeholder="Search sub genre..."
                                            onChange={(value) =>
                                                setData(
                                                    'sub_genre',
                                                    value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Language"
                                        required
                                        error={errors.language}
                                    >
                                        <SearchableTrackSelect
                                            value={data.language}
                                            options={TRACK_LANGUAGES}
                                            placeholder="Search language..."
                                            onChange={(value) =>
                                                setData(
                                                    'language',
                                                    value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Track Title Language"
                                        required
                                        error={errors.title_language}
                                    >
                                        <SearchableTrackSelect
                                            value={data.title_language}
                                            options={TRACK_LANGUAGES}
                                            placeholder="Search title language..."
                                            onChange={(value) =>
                                                setData(
                                                    'title_language',
                                                    value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Lyrics Language"
                                        required
                                        error={errors.lyrics_language}
                                    >
                                        <SearchableTrackSelect
                                            value={data.lyrics_language}
                                            options={TRACK_LANGUAGES}
                                            placeholder="Search lyrics language..."
                                            onChange={(value) =>
                                                setData(
                                                    'lyrics_language',
                                                    value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field
                                        label="Parental Advisory"
                                        required
                                        error={errors.parental_advisory}
                                    >
                                        <select
                                            className={inputClass}
                                            value={
                                                data.parental_advisory
                                            }
                                            onChange={(event) =>
                                                setData(
                                                    'parental_advisory',
                                                    event.target.value
                                                )
                                            }
                                        >
                                            {parentalOptions.map(
                                                ([value, label]) => (
                                                    <option
                                                        key={value}
                                                        value={value}
                                                    >
                                                        {label}
                                                    </option>
                                                )
                                            )}
                                        </select>
                                    </Field>

                                </div>

                                {!data.is_instrumental && (
                                    <>
                                        <SectionTitle
                                            number="05"
                                            title="Lyrics"
                                            subtitle="Enter the complete lyrics for this track."
                                        />

                                        <Field label="Lyrics">
                                            <textarea
                                                className={`${inputClass} min-h-52 resize-y`}
                                                value={data.lyrics}
                                                onChange={(event) =>
                                                    setData(
                                                        'lyrics',
                                                        event.target.value
                                                    )
                                                }
                                                placeholder="Enter full song lyrics..."
                                            />
                                        </Field>
                                    </>
                                )}

                                <div className="mixx-v6-ai-option">
                                    <CheckBox
                                        label="Contains AI Generated Content"
                                        checked={
                                            data
                                                .contains_ai_generated_content
                                        }
                                        onChange={(value) =>
                                            setData(
                                                'contains_ai_generated_content',
                                                value
                                            )
                                        }
                                    />
                                </div>

                                {Object.keys(errors).length > 0 && (
                                    <div className="mixx-v6-error-summary">
                                        Please review the highlighted fields before saving.
                                    </div>
                                )}

                                <div className="mixx-v6-modal-footer">
                                    {!editingTrack && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setModalStage(
                                                    'upload'
                                                )
                                            }
                                            className="mixx-v6-secondary-button"
                                        >
                                            ← Back to WAV
                                        </button>
                                    )}

                                    <button
                                        type="button"
                                        onClick={closeForm}
                                        className="mixx-v6-secondary-button"
                                    >
                                        Cancel
                                    </button>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="mixx-v6-primary-button"
                                    >
                                        {processing
                                            ? 'Saving Track...'
                                            : editingTrack
                                                ? 'Update Track'
                                                : 'Save Track'}
                                    </button>
                                </div>
                            </form>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}

function SectionTitle({
    number,
    title,
    subtitle,
}) {
    return (
        <div className="mixx-v6-section-title">
            <span>{number}</span>

            <div>
                <h3>{title}</h3>
                <p>{subtitle}</p>
            </div>
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
            <label className="mb-2 block text-sm font-semibold text-slate-700">
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

function SearchableTrackSelect({
    value = '',
    options = [],
    placeholder = 'Search...',
    onChange,
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const filteredOptions = useMemo(() => {
        const search = query
            .trim()
            .toLowerCase();

        if (!search) {
            return options;
        }

        return options.filter((option) =>
            option
                .toLowerCase()
                .includes(search)
        );
    }, [options, query]);

    return (
        <div className="relative">
            <input
                type="text"
                autoComplete="off"
                className={inputClass}
                value={open ? query : (value ?? '')}
                placeholder={placeholder}
                onFocus={() => {
                    setQuery(value ?? '');
                    setOpen(true);
                }}
                onChange={(event) => {
                    setQuery(event.target.value);
                    setOpen(true);
                }}
                onBlur={() => {
                    window.setTimeout(
                        () => setOpen(false),
                        120
                    );
                }}
            />

            {open && (
                <div className="absolute left-0 right-0 top-[calc(100%+6px)] z-[100] max-h-60 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                    {filteredOptions.length > 0 ? (
                        filteredOptions.map(
                            (option) => (
                                <button
                                    key={option}
                                    type="button"
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => {
                                        onChange(option);
                                        setQuery(option);
                                        setOpen(false);
                                    }}
                                    className={`block w-full rounded-lg px-3 py-2.5 text-left text-xs font-semibold transition ${
                                        value === option
                                            ? 'bg-violet-50 text-violet-700'
                                            : 'text-slate-700 hover:bg-slate-50'
                                    }`}
                                >
                                    {option}
                                </button>
                            )
                        )
                    ) : (
                        <div className="px-3 py-3 text-xs text-slate-400">
                            No matching option
                        </div>
                    )}
                </div>
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
