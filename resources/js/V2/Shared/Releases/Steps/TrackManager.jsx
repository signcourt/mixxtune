import {
    router,
    useForm,
    usePage,
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
    /* MIXXTUNE_SETTINGS_WIRED_V1: Master Settings > Audio Maximum MB */
    const audioMaxMb = Number(usePage().props?.platformRules?.audio_max_mb) || 300;

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
        transform,
    } = useForm(
        makeEmptyTrack(
            release,
            nextTrackNumber
        )
    );

    /* W65: local checks before saving, credits copy, same-language switch */
    const [localErrors, setLocalErrors] = useState({});
    /* W66: title and lyrics language always follow the track language (the extra switch was removed) */
    const sameLanguage = true;
    const setSameLanguage = () => {};

    const err = (key) => errors[key] || localErrors[key] || null;

    const setField = (key, value) => {
        setData(key, value);
        if (localErrors[key]) {
            setLocalErrors((current) => {
                const next = { ...current };
                delete next[key];
                return next;
            });
        }
    };

    const previousTrack = useMemo(() => {
        const others = tracks.filter((t) => !editingTrack || t.id !== editingTrack.id);
        return others.length ? others[others.length - 1] : null;
    }, [tracks, editingTrack]);

    const CREDIT_KEYS = ['author_name', 'composer_name', 'arranger_name', 'producer_name', 'music_director_name', 'publisher_name', 'p_line'];

    const copyCredits = () => {
        if (!previousTrack) return;
        setData((d) => {
            const next = { ...d };
            CREDIT_KEYS.forEach((k) => {
                next[k] = previousTrack[k] ?? '';
            });
            return next;
        });
        setLocalErrors({});
    };

    const moreCreditsFilled = ['arranger_name', 'producer_name', 'music_director_name', 'publisher_name', 'p_line']
        .filter((k) => String(data[k] ?? '').trim() !== '').length;

    const LABELS = {
        title: 'Track title',
        primary_artist_name: 'Primary artist',
        author_name: 'Lyricist',
        composer_name: 'Composer',
        genre: 'Genre',
        sub_genre: 'Sub genre',
        language: 'Language',
        title_language: 'Title language',
        lyrics_language: 'Lyrics language',
        track_number: 'Track number',
        release_year: 'Recording year',
        isrc: 'ISRC',
    };

    const missingList = Object.keys({ ...localErrors, ...errors })
        .map((k) => LABELS[k] || k.replaceAll('_', ' '))
        .filter((v, i, a) => a.indexOf(v) === i);

    const checkForm = () => {
        const out = {};
        const need = (k, msg) => {
            if (String(data[k] ?? '').trim() === '') out[k] = msg;
        };
        need('title', 'Enter the track title.');
        need('primary_artist_name', 'Enter the primary artist.');
        if (!data.is_instrumental) need('author_name', 'Enter the lyricist (or mark the track as instrumental).');
        need('composer_name', 'Enter the composer.');
        need('genre', 'Pick a genre.');
        need('sub_genre', 'Pick a sub genre.');
        need('language', 'Pick a language.');
        if (!sameLanguage) {
            need('title_language', 'Pick the title language.');
            need('lyrics_language', 'Pick the lyrics language.');
        }
        if (!(Number(data.track_number) >= 1)) out.track_number = 'Track number must be 1 or more.';
        const year = Number(data.release_year);
        if (!(year >= 1900 && year <= new Date().getFullYear() + 1)) out.release_year = 'Enter a year between 1900 and next year.';
        if (!data.isrc_is_auto_generated) {
            const code = String(data.isrc ?? '').replace(/-/g, '').toUpperCase();
            if (!/^[A-Z]{2}[A-Z0-9]{3}\d{7}$/.test(code)) out.isrc = 'ISRC must be 12 characters: 2 letters, 3 letters/digits, 7 digits.';
        }
        setLocalErrors(out);
        return Object.keys(out).length === 0;
    };

    const addTrack = () => {
        clearErrors();
        setLocalErrors({});
        setSameLanguage(true);

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

            /* W65: "already has an ISRC" only when a code was typed by hand */
            isrc_is_auto_generated:
                !track.isrc
                || Boolean(
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

        setLocalErrors({});
        {
            const lang = track.language ?? release?.language ?? '';
            const tl = track.title_language ?? lang;
            const ll = track.lyrics_language ?? lang;
            setSameLanguage(tl === lang && ll === lang);
        }

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

        const maxMb = audioMaxMb;
        const maxSize =
            maxMb * 1024 * 1024;

        if (file.size > maxSize) {
            window.alert(
                `WAV file must be ${maxMb} MB or smaller.`
            );

            return;
        }

        setData((d) => ({
            ...d,
            audio: file,
            /* W65: title from the file name when it is still empty */
            title: d.title
                ? d.title
                : file.name.replace(/\.wav$/i, '').replace(/[_]+/g, ' ').replace(/\s+/g, ' ').trim(),
        }));
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

        if (!checkForm()) {
            const box = event.currentTarget?.closest?.('.mixx-v6-track-modal');
            if (box) box.scrollTo({ top: 0, behavior: 'smooth' });
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

            title_language: sameLanguage ? data.language : data.title_language,
            lyrics_language: sameLanguage ? data.language : data.lyrics_language,
            author_name: data.is_instrumental ? '' : data.author_name,
            isrc: data.isrc_is_auto_generated ? (editingTrack?.isrc_is_auto_generated ? editingTrack.isrc ?? '' : '') : String(data.isrc ?? '').replace(/-/g, '').toUpperCase(),
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

        /*
         * W65: both new and edited tracks go through the form, so the
         * server's field errors show under the fields (edits showed none)
         * and the cleaned values below are what is sent.
         */
        transform(() => (
            editingTrack
                ? { ...payload, _method: 'patch' }
                : payload
        ));

        post(
            editingTrack
                ? `${TRACKS_BASE_PATH}/${editingTrack.id}`
                : `${RELEASES_BASE_PATH}/${release.id}/tracks`,
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
                                        WAV only · Maximum {audioMaxMb} MB
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
                                noValidate
                            >
                                {/* W65 MIXXTUNE_TRACK_FORM_V2: song first, then credits, genre, codes, lyrics */}
                                <SectionTitle
                                    number="01"
                                    title="Song"
                                    subtitle="Name and artists exactly as they should appear in stores."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field label="Track title" required error={err('title')}>
                                        <input className={inputClass} value={data.title} onChange={(e) => setField('title', e.target.value)} placeholder="Song name, no “feat.” or “Official”" />
                                    </Field>

                                    <Field label="Version" hint="Only if it is not the original">
                                        <input className={inputClass} value={data.version} onChange={(e) => setField('version', e.target.value)} placeholder="Remix, Lofi, Acoustic…" />
                                    </Field>

                                    <Field label="Primary artist / singer" required error={err('primary_artist_name')}>
                                        <input className={inputClass} value={data.primary_artist_name} onChange={(e) => setField('primary_artist_name', e.target.value)} />
                                    </Field>

                                    <Field label="Featuring artist" error={err('featuring_artist_name')}>
                                        <input className={inputClass} value={data.featuring_artist_name} onChange={(e) => setField('featuring_artist_name', e.target.value)} placeholder="Leave empty if none" />
                                    </Field>
                                </div>

                                <div className="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
                                    <Field label="Track type">
                                        <select className={inputClass} value={data.track_type} onChange={(e) => setField('track_type', e.target.value)}>
                                            {trackTypes.map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Explicit lyrics">
                                        <select className={inputClass} value={data.parental_advisory} onChange={(e) => setField('parental_advisory', e.target.value)}>
                                            {parentalOptions.map(([value, label]) => (
                                                <option key={value} value={value}>{label}</option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Toggle label="Instrumental" hint="No vocals" checked={Boolean(data.is_instrumental)} onChange={(v) => setField('is_instrumental', v)} />
                                    <Toggle label="Made with AI" hint="Any AI voice or music" checked={Boolean(data.contains_ai_generated_content)} onChange={(v) => setField('contains_ai_generated_content', v)} />
                                </div>

                                <div className="mixx-v6-section-title">
                                    <span>02</span>
                                    <div className="flex-1">
                                        <h3>Credits</h3>
                                        <p>Writers and makers of the song. Stores in India require both.</p>
                                    </div>
                                    {previousTrack && (
                                        <button type="button" onClick={copyCredits} className="rounded-lg border border-violet-200 bg-violet-50 px-3 py-1.5 text-xs font-semibold text-violet-700 hover:bg-violet-100">
                                            Copy from track {previousTrack.track_number || ''}
                                        </button>
                                    )}
                                </div>

                                <div className="mixx-v6-form-grid">
                                    <Field label="Lyricist / writer" required={!data.is_instrumental} error={err('author_name')} hint={data.is_instrumental ? 'Not needed for an instrumental' : null}>
                                        <input className={inputClass} value={data.author_name} onChange={(e) => setField('author_name', e.target.value)} disabled={Boolean(data.is_instrumental)} />
                                    </Field>

                                    <Field label="Composer" required error={err('composer_name')}>
                                        <input className={inputClass} value={data.composer_name} onChange={(e) => setField('composer_name', e.target.value)} />
                                    </Field>
                                </div>

                                <details className="mb-6 rounded-xl border border-slate-200 bg-slate-50/60 px-4 py-3" open={moreCreditsFilled > 0}>
                                    <summary className="cursor-pointer text-sm font-semibold text-slate-700">
                                        More credits (optional){moreCreditsFilled > 0 ? ` · ${moreCreditsFilled} filled` : ''}
                                    </summary>
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        {[
                                            ['producer_name', 'Producer'],
                                            ['music_director_name', 'Music director'],
                                            ['arranger_name', 'Arranger'],
                                            ['publisher_name', 'Publisher'],
                                        ].map(([key, label]) => (
                                            <Field key={key} label={label} error={err(key)}>
                                                <input className={inputClass} value={data[key] ?? ''} onChange={(e) => setField(key, e.target.value)} />
                                            </Field>
                                        ))}
                                        <Field label="℗ line" hint="e.g. 2026 Mixx Tune Entertainment" error={err('p_line')}>
                                            <input className={inputClass} value={data.p_line ?? ''} onChange={(e) => setField('p_line', e.target.value)} />
                                        </Field>
                                    </div>
                                </details>

                                <SectionTitle
                                    number="03"
                                    title="Genre & language"
                                    subtitle="Filled from the release - change only if this track is different. Title and lyrics use the same language."
                                />

                                <div className="mixx-v6-form-grid">
                                    <Field label="Genre" required error={err('genre')}>
                                        <SearchableTrackSelect value={data.genre} options={TRACK_GENRES} placeholder="Search genre..." onChange={(value) => setField('genre', value)} />
                                    </Field>

                                    <Field label="Sub genre" required error={err('sub_genre')}>
                                        <SearchableTrackSelect value={data.sub_genre} options={TRACK_SUB_GENRES} placeholder="Search sub genre..." onChange={(value) => setField('sub_genre', value)} />
                                    </Field>

                                    <Field label="Language" required error={err('language')}>
                                        <SearchableTrackSelect
                                            value={data.language}
                                            options={TRACK_LANGUAGES}
                                            placeholder="Search language..."
                                            onChange={(value) => {
                                                setData((d) => ({ ...d, language: value, title_language: value, lyrics_language: value }));
                                            }}
                                        />
                                    </Field>

                                </div>

                                <SectionTitle
                                    number="04"
                                    title="Numbering & ISRC"
                                    subtitle="Position on the release and the recording code."
                                />

                                <div className="mb-6 grid gap-4 sm:grid-cols-3">
                                    <Field label="Track number" required error={err('track_number')}>
                                        <input type="number" min="1" className={inputClass} value={data.track_number ?? ''} onChange={(e) => setField('track_number', e.target.value === '' ? '' : Number(e.target.value))} />
                                    </Field>

                                    <Field label="Recording year" required error={err('release_year')}>
                                        <input type="number" min="1900" max={new Date().getFullYear() + 1} className={inputClass} value={data.release_year ?? ''} onChange={(e) => setField('release_year', e.target.value === '' ? '' : Number(e.target.value))} />
                                    </Field>

                                    <Field label="Disc" hint="For multi-disc albums">
                                        <input type="number" min="1" className={inputClass} value={data.disc_number ?? 1} onChange={(e) => setField('disc_number', e.target.value === '' ? 1 : Number(e.target.value))} />
                                    </Field>
                                </div>

                                <fieldset className="mb-6">
                                    <legend className="mb-2 text-sm font-semibold text-slate-700">ISRC</legend>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <label className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 ${data.isrc_is_auto_generated ? 'border-violet-400 bg-violet-50' : 'border-slate-200'}`}>
                                            <input type="radio" name="isrc_mode" checked={Boolean(data.isrc_is_auto_generated)} onChange={() => setData((d) => ({ ...d, isrc_is_auto_generated: true, isrc: editingTrack?.isrc && !editingTrack?.isrc_is_auto_generated ? '' : d.isrc }))} className="mt-0.5" />
                                            <span>
                                                <span className="block text-sm font-semibold text-slate-900">New song - give an ISRC</span>
                                                <span className="block text-xs text-slate-500">Mixx Tune assigns it when the release is approved</span>
                                            </span>
                                        </label>
                                        <label className={`flex cursor-pointer items-start gap-3 rounded-xl border p-4 ${!data.isrc_is_auto_generated ? 'border-violet-400 bg-violet-50' : 'border-slate-200'}`}>
                                            <input type="radio" name="isrc_mode" checked={!data.isrc_is_auto_generated} onChange={() => setField('isrc_is_auto_generated', false)} className="mt-0.5" />
                                            <span>
                                                <span className="block text-sm font-semibold text-slate-900">It already has an ISRC</span>
                                                <span className="block text-xs text-slate-500">Old song released before - type its code</span>
                                            </span>
                                        </label>
                                    </div>
                                    {!data.isrc_is_auto_generated && (
                                        <div className="mt-3 max-w-sm">
                                            <Field label="Existing ISRC" required error={err('isrc')} hint="12 characters, e.g. INA1C2600001">
                                                <input className={`${inputClass} font-mono uppercase`} value={data.isrc ?? ''} onChange={(e) => setField('isrc', e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ''))} placeholder="INXXX2600001" />
                                            </Field>
                                        </div>
                                    )}
                                </fieldset>

                                {!data.is_instrumental && (
                                    <details className="mb-6 rounded-xl border border-slate-200 px-4 py-3" open={Boolean(data.lyrics)}>
                                        <summary className="cursor-pointer text-sm font-semibold text-slate-700">
                                            Lyrics (optional){data.lyrics ? ` · ${String(data.lyrics).split('\n').filter(Boolean).length} lines` : ''}
                                        </summary>
                                        <textarea
                                            className={`${inputClass} mt-3 min-h-52 resize-y`}
                                            value={data.lyrics}
                                            onChange={(e) => setField('lyrics', e.target.value)}
                                            placeholder="Full lyrics, one line per line. Used by Apple Music, Spotify and Instagram."
                                        />
                                    </details>
                                )}

                                {missingList.length > 0 && (
                                    <div className="mixx-v6-error-summary">
                                        Please fill: {missingList.join(', ')}.
                                    </div>
                                )}

                                <div className="mixx-v6-modal-footer">
                                    {!editingTrack && (
                                        <button type="button" onClick={() => setModalStage('upload')} className="mixx-v6-secondary-button">
                                            ← Back to WAV
                                        </button>
                                    )}

                                    <button type="button" onClick={closeForm} className="mixx-v6-secondary-button">
                                        Cancel
                                    </button>

                                    <button type="submit" disabled={processing} className="mixx-v6-primary-button">
                                        {processing ? 'Saving Track...' : editingTrack ? 'Update Track' : 'Save Track'}
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
    hint = null,
    children,
}) {
    return (
        <div className="min-w-0">
            <label className="mb-2 block text-sm font-semibold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {error ? (
                <p className="mt-1 text-xs font-medium text-red-600">
                    {error}
                </p>
            ) : hint ? (
                <p className="mt-1 text-xs text-slate-500">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}

function Toggle({ label, hint, checked, onChange }) {
    return (
        <div className="min-w-0">
            <span className="mb-2 block text-sm font-semibold text-slate-700">{label}</span>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={`flex min-h-[46px] w-full items-center justify-between gap-3 rounded-xl border px-4 text-sm transition ${checked ? 'border-violet-400 bg-violet-50 text-violet-800' : 'border-slate-300 bg-white text-slate-600'}`}
            >
                <span className="truncate">{checked ? 'Yes' : 'No'} <span className="text-xs text-slate-400">· {hint}</span></span>
                <span className={`relative inline-flex h-5 w-9 shrink-0 rounded-full transition ${checked ? 'bg-violet-600' : 'bg-slate-300'}`}>
                    <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all ${checked ? 'left-[18px]' : 'left-0.5'}`} />
                </span>
            </button>
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
