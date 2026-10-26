import {
    router,
    useForm,
    usePage,
} from '@inertiajs/react';

import {
    useEffect,
    useMemo,
    useRef,
    useState,
} from 'react';

import TrackUploadOverlay from '@/V2/Shared/Releases/V5/Tracks/TrackUploadOverlay';
import NameSuggestInput from '@/V2/Shared/Releases/NameSuggestInput';

const ARTIST_CONTEXT =
    typeof window !== 'undefined'
    && /^\/artist(\/|$)/.test(window.location.pathname);

const RELEASES_BASE_PATH = ARTIST_CONTEXT
    ? '/artist/releases'
    : '/releases';

const TRACKS_BASE_PATH = ARTIST_CONTEXT
    ? '/artist/tracks'
    : '/tracks';

/* W67: compact fields (were 46px tall with large text) */
const inputClass =
    'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-[13px] leading-5 text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100 disabled:bg-slate-50 disabled:text-slate-400';

/* W67: several names in one credit, saved as "A, B" in the same field */
const splitNames = (value) =>
    String(value ?? '')
        .split(',')
        .map((v) => v.trim());

const joinNames = (list) =>
    list
        .map((v) => String(v ?? '').trim())
        .filter(Boolean)
        .join(', ');

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
    /* W91: names already used on this account (from the release form data) */
    const nameSuggestions = usePage().props?.artistNameSuggestions ?? [];

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

    /* W87 MIXXTUNE_BACKGROUND_WAV_UPLOAD: the WAV uploads while the details are filled */
    const [bgUpload, setBgUpload] =
        useState({ status: 'idle', progress: 0, token: null, error: '' });
    const bgAbort = useRef(null);
    const backgroundUploadOn = !ARTIST_CONTEXT && Boolean(release?.id);

    const stopBackgroundUpload = () => {
        if (bgAbort.current) {
            bgAbort.current.abort();
            bgAbort.current = null;
        }
        setBgUpload({ status: 'idle', progress: 0, token: null, error: '' });
    };

    const startBackgroundUpload = (file) => {
        if (bgAbort.current) {
            bgAbort.current.abort();
        }
        const controller = new AbortController();
        bgAbort.current = controller;
        setBgUpload({ status: 'uploading', progress: 0, token: null, error: '' });
        onMascotEvent('track-uploading');

        const form = new FormData();
        form.append('audio', file);

        window.axios
            .post(`${RELEASES_BASE_PATH}/${release.id}/audio-uploads`, form, {
                signal: controller.signal,
                onUploadProgress: (event) => {
                    if (event.total) {
                        setBgUpload((u) => ({ ...u, progress: Math.min(99, Math.round((event.loaded * 100) / event.total)) }));
                    }
                },
            })
            .then((response) => {
                if (bgAbort.current !== controller) {
                    return;
                }
                bgAbort.current = null;
                setBgUpload({ status: 'done', progress: 100, token: response.data?.token ?? null, error: '' });
                onMascotEvent('track-uploaded');
            })
            .catch((error) => {
                if (controller.signal.aborted) {
                    return;
                }
                bgAbort.current = null;
                const message =
                    error?.response?.data?.errors?.audio?.[0]
                    ?? error?.response?.data?.message
                    ?? 'Upload stopped - check the internet and choose the file again.';
                setBgUpload({ status: 'error', progress: 0, token: null, error: message });
                onMascotEvent('track-error');
            });
    };

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
            if (String(data[k] ?? '').replace(/[,\s]/g, '') === '') out[k] = msg;
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

        stopBackgroundUpload();

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
                : file.name
                      .replace(/\.wav$/i, '')
                      /* W68: drop converter tags like "(online-audio-converter.com)" */
                      .replace(/[([][^)\]]*(\.com|\.net|\.org|converter|download)[^)\]]*[)\]]/gi, '')
                      .replace(/[_]+/g, ' ')
                      .replace(/\s+/g, ' ')
                      .trim(),
        }));
        setUploadFileName(file.name);

        if (backgroundUploadOn) {
            startBackgroundUpload(file);
            setModalStage('details');
        }
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
            const box = event.currentTarget?.closest?.('[data-track-modal]');
            if (box) box.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        /* W87: the WAV went up in the background - send its token, not the file again */
        const useToken = hasAudio && backgroundUploadOn;
        if (useToken && bgUpload.status !== 'done') {
            window.alert(
                bgUpload.status === 'error'
                    ? bgUpload.error
                    : 'The WAV is still uploading. Save again when it shows "WAV uploaded".'
            );
            return;
        }

        setData(
            'is_explicit',
            data.parental_advisory === 'yes'
        );

        const sendsFile = hasAudio && !useToken;

        if (sendsFile) {
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
            author_name: data.is_instrumental ? '' : joinNames(splitNames(data.author_name)),
            composer_name: joinNames(splitNames(data.composer_name)),
            primary_artist_name: joinNames(splitNames(data.primary_artist_name)),
            featuring_artist_name: joinNames(splitNames(data.featuring_artist_name)),
            ...(useToken ? { audio: undefined, audio_upload_token: bgUpload.token } : {}),
            isrc: data.isrc_is_auto_generated ? (editingTrack?.isrc_is_auto_generated ? editingTrack.isrc ?? '' : '') : String(data.isrc ?? '').replace(/-/g, '').toUpperCase(),
        };

        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,

            onStart: () => {
                if (sendsFile) {
                    setUploading(true);

                    onMascotEvent(
                        'track-uploading'
                    );
                }
            },

            onProgress: (progress) => {
                if (
                    sendsFile
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

                if (sendsFile) {
                    onMascotEvent(
                        'track-uploaded'
                    );
                }

                window.setTimeout(() => {
                    setBgUpload({ status: 'idle', progress: 0, token: null, error: '' });
                    setUploading(false);
                    setFormVisible(false);
                    setEditingTrack(null);
                    setModalStage('upload');
                    onSaved();
                }, sendsFile ? 700 : 0);
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

    /* W119 MIXXTUNE_NEW_RELEASE_UI_N2_N3: track list (N2) and track window (N3) - same fields and saving */
    const fmtLen = (sec) => {
        const n = Math.round(Number(sec) || 0);
        return n > 0 ? `${Math.floor(n / 60)}:${String(n % 60).padStart(2, '0')}` : '';
    };
    const trackLen = (t) => t?.audio_duration_seconds ?? t?.duration_seconds ?? null;
    const totalSec = tracks.reduce((a, t) => a + (Number(trackLen(t)) || 0), 0);
    const editLen = fmtLen(trackLen(editingTrack));
    const editFile = uploadFileName || data.audio?.name || editingTrack?.audio_original_name || '';
    const wavOk = bgUpload.status === 'done' || Boolean(editingTrack?.audio_path) || data.audio instanceof File;
    const dropFile = (file) => {
        if (!file) return;
        addTrack();
        chooseAudio(file);
    };
    const card = 'rounded-2xl border border-slate-200 bg-white shadow-sm';
    const btn2 = 'h-9 rounded-lg border border-slate-300 bg-white px-3 text-xs font-semibold text-slate-700 hover:bg-slate-50';

    return (
        <div className="space-y-4">
            <TrackUploadOverlay
                visible={uploading}
                fileName={uploadFileName}
                progress={uploadProgress}
            />

            <label
                className="flex cursor-pointer flex-wrap items-center gap-4 rounded-2xl border-2 border-dashed border-violet-300 bg-violet-50/50 p-5"
                onDragOver={(e) => e.preventDefault()}
                onDrop={(e) => {
                    e.preventDefault();
                    dropFile(e.dataTransfer?.files?.[0] ?? null);
                }}
            >
                <input type="file" accept=".wav,audio/wav,audio/x-wav" className="hidden" onChange={(e) => { dropFile(e.target.files?.[0] ?? null); e.target.value = ''; }} />
                <div className="min-w-0 flex-[1_1_240px]">
                    <div className="text-sm font-bold text-slate-900">Add a track</div>
                    <div className="mt-0.5 text-xs text-slate-600">Drop the WAV master here (max {audioMaxMb} MB). The details window opens next.</div>
                </div>
                <button type="button" onClick={(e) => { e.preventDefault(); addTrack(); }} className="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                    + Add track
                </button>
            </label>

            <section className={`${card} overflow-hidden`}>
                <div className="flex items-center justify-between px-4 py-3.5">
                    <h2 className="text-sm font-bold text-slate-900">Tracks ({tracks.length})</h2>
                    {totalSec > 0 && <span className="text-xs text-slate-500">Total {fmtLen(totalSec)}</span>}
                </div>

                {tracks.length === 0 ? (
                    <div className="border-t border-slate-100 px-5 py-10 text-center">
                        <div className="text-3xl text-violet-400" aria-hidden="true">♪</div>
                        <h3 className="mt-2 text-sm font-bold text-slate-800">No tracks added yet</h3>
                        <p className="mt-1 text-xs text-slate-500">Add your first master track to this release.</p>
                    </div>
                ) : (
                    tracks.map((track, index) => {
                        const missing = trackMissingLabels(track);
                        const line = [track.primary_artist_name, track.author_name ? `Lyrics ${track.author_name}` : null, track.composer_name ? `Music ${track.composer_name}` : null].filter(Boolean).join(' · ');

                        return (
                            <div key={track.id} className="flex flex-wrap items-center gap-3 border-t border-slate-100 px-4 py-3 text-[13px]">
                                <span className="w-[18px] text-slate-500">{index + 1}</span>
                                <button
                                    type="button"
                                    onClick={() => toggleTrackPlayback(track)}
                                    disabled={!track.audio_path}
                                    title={playingTrackId === track.id ? 'Pause track' : 'Play track'}
                                    aria-label={playingTrackId === track.id ? 'Pause track' : 'Play track'}
                                    className={`flex h-9 w-9 shrink-0 items-center justify-center rounded-full border text-xs font-black transition ${playingTrackId === track.id ? 'border-violet-600 bg-violet-600 text-white' : 'border-violet-200 bg-violet-50 text-violet-700 hover:bg-violet-100'} ${!track.audio_path ? 'cursor-not-allowed opacity-30' : ''}`}
                                >
                                    {playingTrackId === track.id ? '❚❚' : '▶'}
                                </button>
                                <div className="min-w-0 flex-[1_1_200px]">
                                    <div className="truncate font-semibold text-slate-900">
                                        {track.title}
                                        {track.version ? <span className="font-normal text-slate-500"> ({track.version})</span> : null}
                                        {trackLen(track) ? <span className="ml-1.5 text-xs font-normal text-slate-400">{fmtLen(trackLen(track))}</span> : null}
                                    </div>
                                    {line && <div className="truncate text-xs text-slate-500">{line}</div>}
                                </div>
                                <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${track.audio_path ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-700'}`} title={track.audio_original_name || ''}>
                                    {track.audio_path ? 'WAV ✓' : 'No WAV'}
                                </span>
                                <span className={`rounded-full px-2.5 py-1 text-[11px] font-semibold ${missing.length ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800'}`}>
                                    {missing.length ? `${missing.join(', ')} missing` : 'Details ✓'}
                                </span>
                                <span className="text-xs text-slate-500">{track.isrc || 'ISRC at approval'}</span>
                                <div className="flex gap-2">
                                    <button type="button" onClick={() => editTrack(track)} className={btn2}>Edit</button>
                                    <button type="button" onClick={() => deleteTrack(track)} className={`${btn2} text-rose-700`}>Delete</button>
                                </div>
                            </div>
                        );
                    })
                )}
            </section>

            {formVisible && (
                <div className="fixed inset-0 z-50 flex items-start justify-center overflow-y-auto bg-slate-900/55 p-4 sm:p-8" role="dialog" aria-modal="true" aria-label="Track details">
                    <div className="fixed inset-0" onClick={closeForm} aria-hidden="true" />

                    <div data-track-modal className="relative flex w-full max-w-[880px] flex-col overflow-hidden rounded-2xl bg-white shadow-2xl">
                        <div className="flex items-center gap-3 border-b border-slate-200 px-5 py-3.5">
                            <button
                                type="button"
                                onClick={() => editingTrack && toggleTrackPlayback(editingTrack)}
                                disabled={!editingTrack?.audio_path}
                                aria-label="Play track"
                                className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-violet-600 text-xs font-black text-white disabled:opacity-30"
                            >
                                {editingTrack && playingTrackId === editingTrack.id ? '❚❚' : '▶'}
                            </button>
                            <div className="min-w-0 flex-1">
                                <h2 className="text-[15px] font-bold text-slate-900">{modalStage === 'upload' ? (editingTrack ? 'Track file' : 'Add a track') : 'Track details'}</h2>
                                <p className="truncate text-xs text-slate-500">
                                    {modalStage === 'upload'
                                        ? `WAV master · 16 or 24-bit · 44.1 or 48 kHz · max ${audioMaxMb} MB`
                                        : [editFile || null, editLen || null, wavOk ? 'WAV ✓' : null].filter(Boolean).join(' · ')}
                                    {modalStage === 'details' && !editingTrack && (
                                        <>
                                            {' · '}
                                            <button type="button" onClick={() => setModalStage('upload')} className="font-semibold text-violet-700 underline">Replace file</button>
                                        </>
                                    )}
                                </p>
                            </div>
                            <button type="button" onClick={closeForm} aria-label="Close" className="flex h-9 w-9 items-center justify-center rounded-full text-xl text-slate-500 hover:bg-slate-100">×</button>
                        </div>

                        {modalStage === 'upload' && (
                            <div className="space-y-4 px-5 py-5">
                                <label
                                    className="flex cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-violet-300 bg-violet-50/50 px-6 py-10 text-center"
                                    onDragOver={(e) => e.preventDefault()}
                                    onDrop={(e) => { e.preventDefault(); chooseAudio(e.dataTransfer?.files?.[0] ?? null); }}
                                >
                                    <input type="file" accept=".wav,audio/wav,audio/x-wav" className="hidden" onChange={(event) => chooseAudio(event.target.files?.[0] ?? null)} />
                                    <span className="text-3xl text-violet-500" aria-hidden="true">⇧</span>
                                    <span className="mt-2 text-sm font-bold text-slate-900">{data.audio?.name ?? editingTrack?.audio_original_name ?? 'Drop the WAV master here'}</span>
                                    <span className="mt-1 text-xs text-slate-500">or click to choose · WAV only · max {audioMaxMb} MB</span>
                                </label>
                                {data.audio && (
                                    <div className="flex items-center justify-between rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-xs text-emerald-800">
                                        <span className="truncate font-semibold">{data.audio.name} · {(data.audio.size / 1024 / 1024).toFixed(2)} MB</span>
                                        <span>✓ Ready</span>
                                    </div>
                                )}
                                <div className="flex justify-end gap-2 border-t border-slate-100 pt-4">
                                    <button type="button" onClick={closeForm} className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">Cancel</button>
                                    <button type="button" onClick={continueToDetails} className="h-10 rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white">Continue to track details →</button>
                                </div>
                            </div>
                        )}

                        {modalStage === 'details' && (
                            <form onSubmit={submitTrack} noValidate className="flex flex-col">
                                <div className="px-5 py-4">
                                    {/* W87: WAV upload status while the details are filled */}
                                    {bgUpload.status !== 'idle' && (
                                        <div className={`mb-4 rounded-xl border px-3.5 py-2.5 text-xs ${bgUpload.status === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : bgUpload.status === 'done' ? 'border-emerald-200 bg-emerald-50 text-emerald-800' : 'border-violet-200 bg-violet-50 text-violet-800'}`}>
                                            <div className="flex flex-wrap items-center justify-between gap-2">
                                                <span className="min-w-0 truncate font-semibold">
                                                    {bgUpload.status === 'uploading' && `Uploading ${uploadFileName} · ${bgUpload.progress}% - fill in the details meanwhile`}
                                                    {bgUpload.status === 'done' && `WAV uploaded ✓ ${uploadFileName}`}
                                                    {bgUpload.status === 'error' && bgUpload.error}
                                                </span>
                                                {bgUpload.status === 'error' && (
                                                    <button type="button" onClick={() => data.audio instanceof File ? startBackgroundUpload(data.audio) : setModalStage('upload')} className="rounded-lg bg-white px-2.5 py-1 font-bold text-rose-700 ring-1 ring-rose-200">
                                                        Try again
                                                    </button>
                                                )}
                                            </div>
                                            {bgUpload.status === 'uploading' && (
                                                <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-violet-100">
                                                    <div className="h-full rounded-full bg-violet-600 transition-all" style={{ width: `${bgUpload.progress}%` }} />
                                                </div>
                                            )}
                                        </div>
                                    )}

                                    <div className="grid grid-cols-1 gap-x-3.5 gap-y-2.5 sm:grid-cols-2">
                                        <Field label="Track title" required error={err('title')}>
                                            <input className={inputClass} value={data.title} onChange={(e) => setField('title', e.target.value)} placeholder="Song name, no “feat.” or “Official”" />
                                        </Field>
                                        <Field label="Version" hint="Only if it is not the original">
                                            <input className={inputClass} value={data.version} onChange={(e) => setField('version', e.target.value)} placeholder="Remix, Lofi, Acoustic…" />
                                        </Field>
                                        <Field label="Primary artist / singer" required error={err('primary_artist_name')}>
                                            <MultiNameInput suggestions={nameSuggestions} value={data.primary_artist_name} onChange={(v) => setField('primary_artist_name', v)} max={3} noun="artist" />
                                        </Field>
                                        <Field label="Featuring artist" error={err('featuring_artist_name')}>
                                            <MultiNameInput suggestions={nameSuggestions} value={data.featuring_artist_name} onChange={(v) => setField('featuring_artist_name', v)} max={5} noun="artist" placeholder="None" />
                                        </Field>
                                        <Field label="Lyricist / writer" required={!data.is_instrumental} error={err('author_name')} hint={data.is_instrumental ? 'Not needed for an instrumental' : null}>
                                            <MultiNameInput suggestions={nameSuggestions} value={data.author_name} onChange={(v) => setField('author_name', v)} max={5} noun="lyricist" disabled={Boolean(data.is_instrumental)} />
                                        </Field>
                                        <Field label="Composer" required error={err('composer_name')}>
                                            <MultiNameInput suggestions={nameSuggestions} value={data.composer_name} onChange={(v) => setField('composer_name', v)} max={5} noun="composer" />
                                        </Field>
                                    </div>

                                    <div className="mt-3 flex flex-wrap items-start gap-x-5 gap-y-2 text-xs font-semibold text-violet-700">
                                        {previousTrack && (
                                            <button type="button" onClick={copyCredits} className="hover:underline">
                                                Copy credits from track {previousTrack.track_number || ''}
                                            </button>
                                        )}
                                        <details className="min-w-0 flex-1 basis-full sm:basis-auto" open={moreCreditsFilled > 0}>
                                            <summary className="cursor-pointer list-none hover:underline">
                                                + Producer, music director, arranger, publisher, ℗ line{moreCreditsFilled > 0 ? ` · ${moreCreditsFilled} filled` : ''}
                                            </summary>
                                            <div className="mt-3 grid gap-2.5 font-normal text-slate-800 sm:grid-cols-2">
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
                                    </div>

                                    <div className="mt-4 grid grid-cols-2 gap-x-3 gap-y-2.5 border-t border-slate-100 pt-4 lg:grid-cols-4">
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
                                        <Field label="Instrumental" hint="No vocals">
                                            <select className={inputClass} value={data.is_instrumental ? 'yes' : 'no'} onChange={(e) => setField('is_instrumental', e.target.value === 'yes')}>
                                                <option value="no">No</option>
                                                <option value="yes">Yes</option>
                                            </select>
                                        </Field>
                                        <Field label="Made with AI" hint="Any AI voice or music">
                                            <select className={inputClass} value={data.contains_ai_generated_content ? 'yes' : 'no'} onChange={(e) => setField('contains_ai_generated_content', e.target.value === 'yes')}>
                                                <option value="no">No</option>
                                                <option value="yes">Yes</option>
                                            </select>
                                        </Field>
                                        <Field label="Genre" required error={err('genre')}>
                                            <SearchableTrackSelect value={data.genre} options={TRACK_GENRES} placeholder="Select genre" onChange={(value) => setField('genre', value)} />
                                        </Field>
                                        <Field label="Sub genre" required error={err('sub_genre')}>
                                            <SearchableTrackSelect value={data.sub_genre} options={TRACK_SUB_GENRES} placeholder="Select sub genre" onChange={(value) => setField('sub_genre', value)} />
                                        </Field>
                                        <Field label="Language" required error={err('language')}>
                                            <SearchableTrackSelect
                                                value={data.language}
                                                options={TRACK_LANGUAGES}
                                                placeholder="Select language"
                                                onChange={(value) => {
                                                    setData((d) => ({ ...d, language: value, title_language: value, lyrics_language: value }));
                                                }}
                                            />
                                        </Field>
                                        <div className="hidden lg:block" />
                                        <Field label="Track number" required error={err('track_number')}>
                                            <input type="number" min="1" className={inputClass} value={data.track_number ?? ''} onChange={(e) => setField('track_number', e.target.value === '' ? '' : Number(e.target.value))} />
                                        </Field>
                                        <Field label="Recording year" required error={err('release_year')}>
                                            <input type="number" min="1900" max={new Date().getFullYear() + 1} className={inputClass} value={data.release_year ?? ''} onChange={(e) => setField('release_year', e.target.value === '' ? '' : Number(e.target.value))} />
                                        </Field>
                                        <Field label="Disc number">
                                            <input type="number" min="1" className={inputClass} value={data.disc_number ?? 1} onChange={(e) => setField('disc_number', e.target.value === '' ? 1 : Number(e.target.value))} />
                                        </Field>
                                        <Field label="Generate ISRC" required>
                                            <select
                                                className={inputClass}
                                                value={data.isrc_is_auto_generated ? 'yes' : 'no'}
                                                onChange={(e) =>
                                                    e.target.value === 'yes'
                                                        ? setData((d) => ({ ...d, isrc_is_auto_generated: true, isrc: editingTrack?.isrc && !editingTrack?.isrc_is_auto_generated ? '' : d.isrc }))
                                                        : setField('isrc_is_auto_generated', false)
                                                }
                                            >
                                                <option value="yes">Yes</option>
                                                <option value="no">No - I have one</option>
                                            </select>
                                        </Field>
                                        {!data.isrc_is_auto_generated && (
                                            <div className="col-span-2">
                                                <Field label="ISRC" required error={err('isrc')} hint="12 characters, e.g. INA1C2600001">
                                                    <input className={`${inputClass} font-mono uppercase`} value={data.isrc ?? ''} onChange={(e) => setField('isrc', e.target.value.toUpperCase().replace(/[^A-Z0-9-]/g, ''))} placeholder="INXXX2600001" />
                                                </Field>
                                            </div>
                                        )}
                                    </div>

                                    {!data.is_instrumental && (
                                        <details className="mt-4 border-t border-slate-100 pt-3" open={Boolean(data.lyrics)}>
                                            <summary className="cursor-pointer list-none text-xs font-semibold text-violet-700 hover:underline">
                                                + Lyrics (optional){data.lyrics ? ` · ${String(data.lyrics).split('\n').filter(Boolean).length} lines` : ''}
                                            </summary>
                                            <textarea
                                                className={`${inputClass} mt-3 min-h-52 resize-y`}
                                                value={data.lyrics}
                                                onChange={(e) => setField('lyrics', e.target.value)}
                                                placeholder="Full lyrics, one line per line. Used by Apple Music, Spotify and Instagram."
                                            />
                                        </details>
                                    )}
                                </div>

                                <div className="flex flex-wrap items-center gap-2 border-t border-slate-200 bg-slate-50 px-5 py-3">
                                    <span className="min-w-0 flex-1 text-xs font-semibold text-amber-700">
                                        {missingList.length > 0 ? `Fill: ${missingList.join(', ')}` : ''}
                                    </span>
                                    {!editingTrack && (
                                        <button type="button" onClick={() => setModalStage('upload')} className="h-10 rounded-lg border border-slate-300 bg-white px-3 text-sm font-semibold text-slate-700">
                                            ← WAV
                                        </button>
                                    )}
                                    <button type="button" onClick={closeForm} className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700">
                                        Cancel
                                    </button>
                                    <button type="submit" disabled={processing || bgUpload.status === 'uploading'} className="h-10 rounded-lg bg-violet-600 px-5 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50">
                                        {bgUpload.status === 'uploading'
                                            ? `WAV uploading ${bgUpload.progress}%…`
                                            : processing ? 'Saving track...' : editingTrack ? 'Update track' : 'Save track'}
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
            <label className="mb-1 block text-xs font-semibold text-slate-700">
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

function MultiNameInput({ value, onChange, max = 5, noun = 'name', placeholder = '', disabled = false, suggestions = [] }) {
    const toRows = (v) => {
        const list = splitNames(v).filter(Boolean);
        return list.length ? list : [''];
    };

    const [rows, setRows] = useState(() => toRows(value));

    /* follow outside changes (open another track, copy credits) */
    useEffect(() => {
        if (joinNames(rows) !== joinNames(splitNames(value))) {
            setRows(toRows(value));
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value]);

    const push = (next) => {
        setRows(next);
        onChange(joinNames(next));
    };

    const update = (index, text) => {
        const next = [...rows];
        next[index] = text.replace(/,/g, ' ');
        push(next);
    };

    const add = () => {
        if (rows.length < max) setRows([...rows, '']);
    };

    const remove = (index) => {
        const next = rows.filter((_, i) => i !== index);
        push(next.length ? next : ['']);
    };

    return (
        <div className="space-y-1.5">
            {rows.map((name, index) => (
                <div key={index} className="flex items-center gap-1.5">
                    {/* W91: names used before are suggested while typing */}
                    <NameSuggestInput
                        className={inputClass}
                        value={name}
                        disabled={disabled}
                        suggestions={suggestions}
                        placeholder={index === 0 ? placeholder : `Another ${noun}`}
                        aria-label={index === 0 ? undefined : `${noun} ${index + 1}`}
                        onChange={(text) => update(index, text)}
                    />
                    {index === 0 ? (
                        <button
                            type="button"
                            onClick={add}
                            disabled={disabled || rows.length >= max}
                            title={rows.length >= max ? `Up to ${max}` : `Add another ${noun}`}
                            aria-label={`Add another ${noun}`}
                            className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-violet-200 bg-violet-50 text-lg font-semibold leading-none text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            +
                        </button>
                    ) : (
                        <button
                            type="button"
                            onClick={() => remove(index)}
                            disabled={disabled}
                            aria-label={`Remove ${noun} ${index + 1}`}
                            className="flex h-[38px] w-[38px] shrink-0 items-center justify-center rounded-lg border border-slate-200 bg-white text-base leading-none text-slate-500 transition hover:border-rose-200 hover:bg-rose-50 hover:text-rose-600"
                        >
                            ×
                        </button>
                    )}
                </div>
            ))}
        </div>
    );
}

function Toggle({ label, hint, checked, onChange }) {
    return (
        <div className="min-w-0">
            <span className="mb-1 block text-xs font-semibold text-slate-700">{label}</span>
            <button
                type="button"
                role="switch"
                aria-checked={checked}
                aria-label={label}
                onClick={() => onChange(!checked)}
                className={`flex min-h-[38px] w-full items-center justify-between gap-2 rounded-lg border px-3 text-[13px] transition ${checked ? 'border-violet-400 bg-violet-50 text-violet-800' : 'border-slate-300 bg-white text-slate-600'}`}
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
            {/* W85: dropdown arrow; click shows the whole list, typing narrows it */}
            <svg viewBox="0 0 20 20" fill="currentColor" aria-hidden="true" className={`pointer-events-none absolute right-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500 transition-transform ${open ? 'rotate-180' : ''}`}>
                <path fillRule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.17l3.71-3.94a.75.75 0 1 1 1.08 1.04l-4.25 4.5a.75.75 0 0 1-1.08 0l-4.25-4.5a.75.75 0 0 1 .02-1.06z" clipRule="evenodd" />
            </svg>
            <input
                type="text"
                autoComplete="off"
                className={`${inputClass} cursor-pointer pr-9`}
                value={open ? query : (value ?? '')}
                placeholder={open && value ? value : placeholder}
                onFocus={() => {
                    setQuery('');
                    setOpen(true);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter' && open && filteredOptions.length > 0) {
                        event.preventDefault();
                        onChange(filteredOptions[0]);
                        setOpen(false);
                        event.currentTarget.blur();
                    }
                    if (event.key === 'Escape') {
                        setOpen(false);
                    }
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
                                        setQuery('');
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

/* W119: what a saved track still needs (same rules as the server check and the Review checklist) */
export function trackMissingLabels(track) {
    const has = (v) => String(v ?? '').replace(/[,\s]/g, '') !== '';
    const out = [];
    if (!has(track?.title)) out.push('Title');
    if (!has(track?.primary_artist_name)) out.push('Primary artist');
    if (!track?.is_instrumental && !has(track?.author_name)) out.push('Lyricist');
    if (!has(track?.composer_name)) out.push(out.length ? 'composer' : 'Composer');
    return out;
}
