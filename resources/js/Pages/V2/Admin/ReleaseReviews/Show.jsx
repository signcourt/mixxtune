import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

const LANGUAGES = [
    'Assamese','Awadhi','Bengali','Bhojpuri','Bodo','Dogri','English',
    'Garhwali','Gujarati','Haryanvi','Hindi','Kannada','Kashmiri','Khasi',
    'Konkani','Kumaoni','Maithili','Malayalam','Manipuri','Marathi',
    'Marwari','Mizo','Nagpuri','Nepali','Odia','Punjabi','Rajasthani',
    'Sanskrit','Santali','Sindhi','Tamil','Telugu','Tulu','Urdu',
    'Afrikaans','Albanian','Amharic','Arabic','Armenian','Azerbaijani',
    'Basque','Belarusian','Bosnian','Bulgarian','Burmese','Catalan',
    'Chinese (Cantonese)','Chinese (Mandarin)','Croatian','Czech',
    'Danish','Dutch','Estonian','Filipino','Finnish','French','Georgian',
    'German','Greek','Hausa','Hebrew','Hungarian','Icelandic',
    'Indonesian','Irish','Italian','Japanese','Javanese','Kazakh','Khmer',
    'Korean','Kurdish','Lao','Latin','Latvian','Lithuanian','Macedonian',
    'Malay','Maltese','Mongolian','Norwegian','Pashto','Persian','Polish',
    'Portuguese','Romanian','Russian','Serbian','Sinhala','Slovak',
    'Slovenian','Somali','Spanish','Swahili','Swedish','Thai','Turkish',
    'Ukrainian','Uzbek','Vietnamese','Welsh','Yoruba','Zulu',
];

const GENRES = [
    'Alternative','Anime','Blues','Bollywood','Children','Classical',
    'Comedy','Country','Dance','Devotional','Electronic','Folk','Ghazal',
    'Hip-Hop/Rap','Indian','Instrumental','Jazz','K-Pop','Latin','Metal',
    'New Age','Pop','Punjabi','R&B/Soul','Reggae','Regional Indian',
    'Rock','Singer/Songwriter','Soundtrack','Spiritual','World',
];

const SUB_GENRES = [
    'Acoustic','Ambient','Bhajan','Bhojpuri','Christian & Gospel',
    'Dance Pop','Desi Hip-Hop','Devotional & Spiritual','EDM','Folk Pop',
    'Haryanvi','Hindi Pop','House','Indie Pop','Indian Classical',
    'Indian Folk','Karaoke','Lo-Fi','Meditation','Punjabi Pop','Qawwali',
    'Rajasthani','Remix','Sufi','Tamil','Telugu','Trap','World Pop',
];

const TRACK_TYPES = [
    ['original', 'Original'],
    ['karaoke', 'Karaoke'],
    ['medley', 'Medley'],
    ['cover', 'Cover'],
    ['cover_by_cover_band', 'Cover by Cover Band'],
];

const empty = (value) =>
    value === null || value === undefined || value === ''
        ? '—'
        : value;

const yesNo = (value) =>
    value === null || value === undefined
        ? '—'
        : value
          ? 'Yes'
          : 'No';

const dateInput = (value) =>
    value ? String(value).slice(0, 10) : '';

const displayDate = (value) => {
    if (!value) return '—';
    const v = String(value).slice(0, 10);
    const [y, m, d] = v.split('-');
    return `${d}-${m}-${y}`;
};

const listValue = (value) => {
    if (!value) return '—';
    if (!Array.isArray(value)) return String(value);
    if (!value.length) return '—';

    return value.map((item) => {
        if (typeof item === 'string') return item;
        return item?.name || item?.artist_name || JSON.stringify(item);
    }).join(', ');
};


const STATUS_STYLES = {
    submitted: 'bg-amber-100 text-amber-700 ring-amber-200',
    approved: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    rejected: 'bg-red-100 text-red-700 ring-red-200',
    changes_requested: 'bg-orange-100 text-orange-700 ring-orange-200',
    processing: 'bg-blue-100 text-blue-700 ring-blue-200',
    delivered: 'bg-indigo-100 text-indigo-700 ring-indigo-200',
    live: 'bg-emerald-100 text-emerald-700 ring-emerald-200',
    draft: 'bg-slate-100 text-slate-700 ring-slate-200',
};

const STATUS_LABELS = {
    submitted: 'In Review',
    approved: 'Approved',
    rejected: 'Rejected',
    changes_requested: 'Changes Requested',
    processing: 'Processing',
    delivered: 'Delivered',
    live: 'Live',
    draft: 'Draft',
};

const formatDateTime = (value) => {
    if (!value) return '—';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(date);
};

export default function Show({
    role = 'admin',
    release,
    availableActions = {},
    statusLogs = [],
}) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);

    const action = (endpoint, payload = {}) => {
        setProcessing(true);

        router.post(endpoint, payload, {
            preserveScroll: false,
            onFinish: () => setProcessing(false),
        });
    };

    const releaseFields = [
        { key: 'title', label: 'Title' },
        { key: 'version', label: 'Version' },
        {
            key: 'release_type',
            label: 'Release Type',
            type: 'select',
            options: ['single', 'ep', 'album'],
        },
        {
            key: 'primary_artist_name',
            label: 'Primary Artist',
        },
        {
            key: 'primary_artists',
            label: 'Primary Artists',
            readonly: true,
            display: listValue(release.primary_artists),
        },
        {
            key: 'featuring_artist_name',
            label: 'Featuring Artist',
        },
        {
            key: 'featuring_artists',
            label: 'Featuring Artists',
            readonly: true,
            display: listValue(release.featuring_artists),
        },
        {
            key: 'label_name',
            label: 'Label',
            readonly: true,
        },
        {
            key: 'upc',
            label: 'UPC / EAN',
            readonly: true,
            display: release.upc || 'Pending',
        },
        {
            key: 'catalog_number',
            label: 'Catalogue Number',
            readonly: true,
        },
        {
            key: 'language',
            label: 'Language',
            type: 'select',
            options: LANGUAGES,
        },
        {
            key: 'primary_genre',
            label: 'Primary Genre',
            type: 'select',
            options: GENRES,
        },
        {
            key: 'sub_genre',
            label: 'Sub Genre',
            type: 'select',
            options: SUB_GENRES,
        },
        {
            key: 'original_release_date',
            label: 'Original Release Date',
            type: 'date',
            display: displayDate(release.original_release_date),
        },
        {
            key: 'digital_release_date',
            label: 'Digital Release Date',
            type: 'date',
            display: displayDate(release.digital_release_date),
        },
        {
            key: 'release_timezone',
            label: 'Release Timezone',
        },
        {
            key: 'pre_order',
            label: 'Pre-order',
            type: 'boolean',
            display: yesNo(release.pre_order),
        },
        {
            key: 'copyright_owner',
            label: 'Copyright Owner',
        },
        {
            key: 'copyright_year',
            label: 'Copyright Year',
            type: 'number',
        },
        {
            key: 'phonographic_owner',
            label: 'Phonographic Owner',
        },
        {
            key: 'phonographic_year',
            label: 'Phonographic Year',
            type: 'number',
        },
        {
            key: 'public_id',
            label: 'Release Public ID',
            readonly: true,
        },
        {
            key: 'submitted_at',
            label: 'Submitted At',
            readonly: true,
        },
        {
            key: 'approved_at',
            label: 'Approved At',
            readonly: true,
        },
    ];

    return (
        <PanelLayout
            role={role}
            title="Review Release"
            subtitle={release.title}
        >
            <Head title={`Review ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/release-reviews"
                        className="inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 shadow-sm transition hover:border-violet-200 hover:text-violet-700"
                    >
                        <span aria-hidden="true">←</span>
                        <span>Review Queue</span>
                    </Link>

                    <StatusBadge status={release.status} />
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="p-5 sm:p-6">
                        <div className="flex flex-col gap-5 lg:flex-row lg:items-center">
                            {release.artwork_path ? (
                                <img
                                    src={`/storage/${release.artwork_path}`}
                                    alt={release.title}
                                    className="h-32 w-32 shrink-0 rounded-2xl border border-slate-200 object-cover shadow-sm sm:h-36 sm:w-36"
                                />
                            ) : (
                                <div className="flex h-32 w-32 shrink-0 items-center justify-center rounded-2xl border border-dashed border-slate-300 bg-slate-50 text-center text-xs font-semibold uppercase tracking-wide text-slate-400 sm:h-36 sm:w-36">
                                    No Artwork
                                </div>
                            )}

                            <div className="min-w-0 flex-1">
                                <div className="text-xs font-bold uppercase tracking-[0.16em] text-violet-600">
                                    {release.release_type || 'Release'}
                                </div>

                                <h1 className="mt-1 break-words text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
                                    {release.title}
                                </h1>

                                <div className="mt-2 text-sm font-medium text-slate-600">
                                    {release.primary_artist_name || 'Artist not specified'}

                                    {release.label_name && (
                                        <span>
                                            {' '}• {release.label_name}
                                        </span>
                                    )}
                                </div>

                                <div className="mt-5 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                    <SummaryItem
                                        label="UPC / EAN"
                                        value={release.upc || 'Pending'}
                                    />

                                    <SummaryItem
                                        label="Release Date"
                                        value={displayDate(
                                            release.digital_release_date
                                        )}
                                    />

                                    <SummaryItem
                                        label="Tracks"
                                        value={`${release.tracks?.length ?? 0}`}
                                    />

                                    <SummaryItem
                                        label="Submitted"
                                        value={formatDateTime(
                                            release.submitted_at
                                        )}
                                    />
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Section
                            title="Release Metadata"
                            subtitle="Review and correct release-level information."
                        >
                            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                                {releaseFields.map((field) => (
                                    <EditableField
                                        key={field.key}
                                        release={release}
                                        field={field}
                                    />
                                ))}
                            </div>
                        </Section>


                        <Section
                            title="Tracks & Audio QC"
                            subtitle={`${release.tracks?.length ?? 0} ${
                                (release.tracks?.length ?? 0) === 1
                                    ? 'track'
                                    : 'tracks'
                            } attached to this release.`}
                        >
                            <div className="space-y-5">
                                {(release.tracks ?? []).map(
                                    (track, index) => (
                                        <TrackEditor
                                            key={track.id}
                                            release={release}
                                            track={track}
                                            index={index}
                                        />
                                    )
                                )}
                            </div>
                        </Section>

                        <Section
                            title="Distribution"
                            subtitle="Store selection and territory configuration."
                        >
                            <div className="grid gap-4 md:grid-cols-3">
                                <ReadOnlyBox
                                    label="Territory Mode"
                                    value={
                                        release.worldwide
                                            ? 'Worldwide'
                                            : 'Selected Territories'
                                    }
                                />

                                <ReadOnlyBox
                                    label="Stores"
                                    value={listValue(
                                        release.stores
                                    )}
                                />

                                <ReadOnlyBox
                                    label="Territories"
                                    value={
                                        release.worldwide
                                            ? 'Worldwide'
                                            : listValue(
                                                  release.territories
                                              )
                                    }
                                />
                            </div>
                        </Section>

                        <Section
                            title="Review History"
                            subtitle="Workflow activity for this release."
                        >
                            <ReviewHistory
                                logs={statusLogs}
                            />
                        </Section>
                    </div>

                    <aside className="space-y-5">
                        <div className="sticky top-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <div className="flex items-start justify-between gap-3">
                                <div>
                                    <h3 className="font-bold text-slate-950">
                                        Review Actions
                                    </h3>

                                    <p className="mt-1 text-xs leading-5 text-slate-500">
                                        Available actions depend on the current release status.
                                    </p>
                                </div>

                                <StatusBadge
                                    status={release.status}
                                />
                            </div>

                            <div className="mt-4 rounded-xl border border-slate-200 bg-slate-50 p-3">
                                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                                    Submitted
                                </div>

                                <div className="mt-1 text-sm font-semibold text-slate-800">
                                    {formatDateTime(
                                        release.submitted_at
                                    )}
                                </div>
                            </div>

                            <textarea
                                value={notes}
                                onChange={(e) =>
                                    setNotes(e.target.value)
                                }
                                placeholder="Add remarks, rejection reason or change request..."
                                className="mt-4 min-h-32 w-full resize-y rounded-xl border border-slate-300 bg-white p-3 text-sm text-slate-800 outline-none transition placeholder:text-slate-400 focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                            />

                            <div className="mt-4 space-y-3">
                                {availableActions.approve && (
                                    <ActionButton
                                        label="Approve Release"
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/approve`,
                                                { remarks: notes }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.request_changes && (
                                    <ActionButton
                                        label="Request Changes"
                                        className="bg-amber-500 hover:bg-amber-600"
                                        disabled={
                                            processing ||
                                            notes.trim().length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/request-changes`,
                                                { notes }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.reject && (
                                    <ActionButton
                                        label="Reject Release"
                                        className="bg-red-600 hover:bg-red-700"
                                        disabled={
                                            processing ||
                                            notes.trim().length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/reject`,
                                                { reason: notes }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.start_processing && (
                                    <ActionButton
                                        label="Start Processing"
                                        className="bg-blue-600 hover:bg-blue-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/start-processing`,
                                                { remarks: notes }
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </PanelLayout>
    );
}

function EditableField({ release, field }) {
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);

    const initial =
        field.type === 'boolean'
            ? Boolean(release[field.key])
            : field.type === 'date'
              ? dateInput(release[field.key])
              : release[field.key] ?? '';

    const [value, setValue] = useState(initial);

    const save = () => {
        setSaving(true);

        router.patch(
            `/v2/admin/release-reviews/${release.id}/metadata`,
            {
                [field.key]:
                    field.type === 'number'
                        ? Number(value)
                        : value,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onFinish: () => setSaving(false),
            }
        );
    };

    const display =
        field.display ??
        (field.type === 'boolean'
            ? yesNo(release[field.key])
            : empty(release[field.key]));

    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="flex items-start justify-between gap-2">
                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {field.label}
                </div>

                {!field.readonly && !editing && (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="text-sm text-violet-600"
                        title="Edit"
                    >
                        ✎
                    </button>
                )}
            </div>

            {!editing ? (
                <div className="mt-2 break-words text-sm font-medium text-slate-900">
                    {display}
                </div>
            ) : (
                <div className="mt-3 space-y-2">
                    <FieldInput
                        field={field}
                        value={value}
                        setValue={setValue}
                    />

                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={saving}
                            onClick={save}
                            className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white"
                        >
                            {saving ? 'Saving...' : 'Save'}
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setValue(initial);
                                setEditing(false);
                            }}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function FieldInput({ field, value, setValue }) {
    const cls =
        'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm outline-none focus:border-violet-500';

    if (field.type === 'select') {
        return (
            <select
                className={cls}
                value={value}
                onChange={(e) => setValue(e.target.value)}
            >
                <option value="">Select</option>
                {(field.options ?? []).map((option) => {
                    const v = Array.isArray(option)
                        ? option[0]
                        : option;
                    const label = Array.isArray(option)
                        ? option[1]
                        : option;

                    return (
                        <option key={v} value={v}>
                            {label}
                        </option>
                    );
                })}
            </select>
        );
    }

    if (field.type === 'boolean') {
        return (
            <select
                className={cls}
                value={value ? '1' : '0'}
                onChange={(e) =>
                    setValue(e.target.value === '1')
                }
            >
                <option value="0">No</option>
                <option value="1">Yes</option>
            </select>
        );
    }

    return (
        <input
            type={field.type || 'text'}
            className={cls}
            value={value}
            onChange={(e) => setValue(e.target.value)}
        />
    );
}

function TrackEditor({ release, track, index }) {
    const fields = [
        { key: 'track_number', label: 'Track Number', type: 'number' },
        { key: 'disc_number', label: 'Disc Number', type: 'number' },
        { key: 'title', label: 'Title' },
        { key: 'version', label: 'Version' },
        { key: 'subtitle', label: 'Subtitle' },
        {
            key: 'track_type',
            label: 'Track Type',
            type: 'select',
            options: TRACK_TYPES,
        },
        { key: 'primary_artist_name', label: 'Primary Artist' },
        { key: 'featuring_artist_name', label: 'Featuring Artist' },
        { key: 'author_name', label: 'Author / Lyricist' },
        { key: 'composer_name', label: 'Composer' },
        { key: 'arranger_name', label: 'Arranger' },
        { key: 'producer_name', label: 'Producer' },
        { key: 'music_director_name', label: 'Music Director' },
        { key: 'publisher_name', label: 'Publisher' },
        { key: 'p_line', label: 'P-Line' },
        { key: 'release_year', label: 'Release Year', type: 'number' },
        {
            key: 'language',
            label: 'Language',
            type: 'select',
            options: LANGUAGES,
        },
        {
            key: 'title_language',
            label: 'Title Language',
            type: 'select',
            options: LANGUAGES,
        },
        {
            key: 'lyrics_language',
            label: 'Lyrics Language',
            type: 'select',
            options: LANGUAGES,
        },
        {
            key: 'genre',
            label: 'Genre',
            type: 'select',
            options: GENRES,
        },
        {
            key: 'sub_genre',
            label: 'Sub Genre',
            type: 'select',
            options: SUB_GENRES,
        },
        {
            key: 'is_explicit',
            label: 'Explicit',
            type: 'boolean',
        },
        {
            key: 'is_instrumental',
            label: 'Instrumental',
            type: 'boolean',
        },
        {
            key: 'contains_ai_generated_content',
            label: 'AI Generated Content',
            type: 'boolean',
        },
        {
            key: 'parental_advisory',
            label: 'Parental Advisory',
            type: 'select',
            options: [
                ['no', 'No'],
                ['yes', 'Yes'],
                ['cleaned', 'Cleaned'],
            ],
        },
        {
            key: 'price_tier',
            label: 'Price Tier',
            type: 'select',
            options: [
                ['premium', 'Premium'],
                ['standard', 'Standard'],
            ],
        },
        {
            key: 'preview_start_seconds',
            label: 'Preview Start',
            type: 'number',
        },
    ];

    return (
        <div className="overflow-hidden rounded-2xl border border-slate-200">
            <div className="flex flex-wrap items-center justify-between gap-4 bg-slate-50 px-5 py-4">
                <div>
                    <div className="font-bold text-slate-900">
                        {index + 1}. {track.title}
                    </div>
                    <div className="mt-1 text-sm text-slate-500">
                        ISRC: {track.isrc || 'Pending'}
                    </div>
                </div>

                {track.audio_path && track.stream_url ? (
                    <audio
                        controls
                        preload="none"
                        src={track.stream_url}
                        className="h-10 max-w-[320px]"
                    />
                ) : (
                    <span className="text-sm text-red-600">
                        Audio missing
                    </span>
                )}
            </div>

            <div className="grid gap-3 p-5 sm:grid-cols-2 lg:grid-cols-3">
                {fields.map((field) => (
                    <TrackEditableField
                        key={field.key}
                        release={release}
                        track={track}
                        field={field}
                    />
                ))}

                <ReadOnlyBox
                    label="ISRC"
                    value={track.isrc || 'Pending'}
                />
                <ReadOnlyBox
                    label="Audio File"
                    value={track.audio_original_name}
                />
                <ReadOnlyBox
                    label="Audio Validation"
                    value={track.audio_validation_status}
                />

                <ReadOnlyBox
                    label="Audio Codec"
                    value={track.audio_codec}
                />

                <ReadOnlyBox
                    label="Sample Rate"
                    value={
                        track.audio_sample_rate || track.sample_rate
                            ? `${track.audio_sample_rate || track.sample_rate} Hz`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Bit Depth"
                    value={
                        track.audio_bit_depth || track.bit_depth
                            ? `${track.audio_bit_depth || track.bit_depth} bit`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Channels"
                    value={
                        track.audio_channels || track.channels
                    }
                />

                <ReadOnlyBox
                    label="Channel Layout"
                    value={track.audio_channel_layout}
                />

                <ReadOnlyBox
                    label="Duration"
                    value={
                        track.audio_duration_seconds || track.duration_seconds
                            ? `${track.audio_duration_seconds || track.duration_seconds} sec`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Peak"
                    value={
                        track.audio_peak_db !== null &&
                        track.audio_peak_db !== undefined
                            ? `${track.audio_peak_db} dB`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Mean Volume"
                    value={
                        track.audio_mean_volume_db !== null &&
                        track.audio_mean_volume_db !== undefined
                            ? `${track.audio_mean_volume_db} dB`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Leading Silence"
                    value={
                        track.audio_silence_start_seconds !== null &&
                        track.audio_silence_start_seconds !== undefined
                            ? `${track.audio_silence_start_seconds} sec`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Trailing Silence"
                    value={
                        track.audio_silence_end_seconds !== null &&
                        track.audio_silence_end_seconds !== undefined
                            ? `${track.audio_silence_end_seconds} sec`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Audio Size"
                    value={
                        track.audio_size_bytes
                            ? `${(
                                  Number(track.audio_size_bytes) /
                                  1024 /
                                  1024
                              ).toFixed(2)} MB`
                            : '—'
                    }
                />

                <ReadOnlyBox
                    label="Track Status"
                    value={track.status}
                />

                <ReadOnlyBox
                    label="Track Public ID"
                    value={track.public_id}
                />
            </div>

            {track.audio_validation_errors &&
                Object.keys(track.audio_validation_errors || {}).length > 0 && (
                    <div className="mx-5 mb-5 rounded-xl border border-red-200 bg-red-50 p-4">
                        <div className="text-xs font-bold uppercase tracking-wide text-red-700">
                            Audio Validation Issues
                        </div>

                        <div className="mt-2 space-y-1">
                            {Object.entries(
                                track.audio_validation_errors || {}
                            ).map(([key, message]) => (
                                <div
                                    key={key}
                                    className="text-sm text-red-700"
                                >
                                    <span className="font-semibold">
                                        {key.replaceAll('_', ' ')}:
                                    </span>{' '}
                                    {String(message)}
                                </div>
                            ))}
                        </div>
                    </div>
                )}

            {track.lyrics && (
                <div className="px-5 pb-5">
                    <TrackEditableField
                        release={release}
                        track={track}
                        field={{
                            key: 'lyrics',
                            label: 'Lyrics',
                            type: 'textarea',
                        }}
                    />
                </div>
            )}
        </div>
    );
}

function TrackEditableField({
    release,
    track,
    field,
}) {
    const [editing, setEditing] = useState(false);
    const [saving, setSaving] = useState(false);

    const initial =
        field.type === 'boolean'
            ? Boolean(track[field.key])
            : track[field.key] ?? '';

    const [value, setValue] = useState(initial);

    const save = () => {
        setSaving(true);

        router.patch(
            `/v2/admin/release-reviews/${release.id}/tracks/${track.id}/metadata`,
            {
                [field.key]:
                    field.type === 'number'
                        ? Number(value)
                        : value,
            },
            {
                preserveScroll: true,
                onSuccess: () => setEditing(false),
                onFinish: () => setSaving(false),
            }
        );
    };

    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="flex justify-between gap-2">
                <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                    {field.label}
                </div>

                {!editing && (
                    <button
                        type="button"
                        onClick={() => setEditing(true)}
                        className="text-sm text-violet-600"
                    >
                        ✎
                    </button>
                )}
            </div>

            {!editing ? (
                <div className="mt-2 break-words text-sm font-medium text-slate-900">
                    {field.type === 'boolean'
                        ? yesNo(track[field.key])
                        : empty(track[field.key])}
                </div>
            ) : (
                <div className="mt-3 space-y-2">
                    {field.type === 'textarea' ? (
                        <textarea
                            className="min-h-32 w-full rounded-lg border border-slate-300 p-3 text-sm"
                            value={value}
                            onChange={(e) =>
                                setValue(e.target.value)
                            }
                        />
                    ) : (
                        <FieldInput
                            field={field}
                            value={value}
                            setValue={setValue}
                        />
                    )}

                    <div className="flex gap-2">
                        <button
                            type="button"
                            disabled={saving}
                            onClick={save}
                            className="rounded-lg bg-violet-600 px-3 py-2 text-xs font-semibold text-white"
                        >
                            {saving ? 'Saving...' : 'Save'}
                        </button>

                        <button
                            type="button"
                            onClick={() => {
                                setValue(initial);
                                setEditing(false);
                            }}
                            className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-600"
                        >
                            Cancel
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function ReadOnlyBox({ label, value }) {
    return (
        <div className="rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>
            <div className="mt-2 break-words text-sm font-medium text-slate-900">
                {empty(value)}
            </div>
        </div>
    );
}

function Section({ title, subtitle, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
            <div className="mb-5">
                <h2 className="text-lg font-bold text-slate-950">
                    {title}
                </h2>

                {subtitle && (
                    <p className="mt-1 text-sm text-slate-500">
                        {subtitle}
                    </p>
                )}
            </div>

            {children}
        </section>
    );
}

function ReviewHistory({ logs = [] }) {
    if (!logs.length) {
        return (
            <div className="rounded-xl border border-dashed border-slate-300 bg-slate-50 px-5 py-8 text-center text-sm text-slate-500">
                No workflow history is available yet.
            </div>
        );
    }

    return (
        <div className="divide-y divide-slate-100">
            {logs.map((log, index) => {
                const from =
                    log.from_status ||
                    log.old_status ||
                    null;

                const to =
                    log.to_status ||
                    log.new_status ||
                    null;

                const message =
                    log.remarks ||
                    log.notes ||
                    log.reason ||
                    null;

                const action =
                    log.action
                        ? String(log.action)
                              .replaceAll('_', ' ')
                        : null;

                return (
                    <div
                        key={log.id ?? index}
                        className="flex gap-4 py-4 first:pt-0 last:pb-0"
                    >
                        <div className="mt-1 flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-violet-50 text-violet-700">
                            <HistoryIcon />
                        </div>

                        <div className="min-w-0 flex-1">
                            <div className="flex flex-wrap items-center gap-2">
                                {from && (
                                    <span className="text-sm font-medium text-slate-500">
                                        {STATUS_LABELS[from] ??
                                            String(from).replaceAll(
                                                '_',
                                                ' '
                                            )}
                                    </span>
                                )}

                                {from && to && (
                                    <span className="text-slate-300">
                                        →
                                    </span>
                                )}

                                {to && (
                                    <span className="text-sm font-bold text-slate-900">
                                        {STATUS_LABELS[to] ??
                                            String(to).replaceAll(
                                                '_',
                                                ' '
                                            )}
                                    </span>
                                )}
                            </div>

                            {action && (
                                <div className="mt-1 text-xs font-semibold capitalize text-violet-600">
                                    {action}
                                </div>
                            )}

                            {message && (
                                <div className="mt-1 break-words text-sm leading-6 text-slate-600">
                                    {String(message)}
                                </div>
                            )}

                            <div className="mt-1 text-xs text-slate-400">
                                {formatDateTime(
                                    log.created_at ||
                                        log.changed_at ||
                                        log.updated_at
                                )}
                            </div>
                        </div>
                    </div>
                );
            })}
        </div>
    );
}

function HistoryIcon() {
    return (
        <svg
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="1.8"
            className="h-4 w-4"
            aria-hidden="true"
        >
            <path
                strokeLinecap="round"
                strokeLinejoin="round"
                d="M12 8v4l3 2"
            />
            <circle
                cx="12"
                cy="12"
                r="9"
            />
        </svg>
    );
}

function StatusBadge({ status }) {
    return (
        <span
            className={[
                'inline-flex items-center rounded-full px-3.5 py-1.5 text-sm font-semibold ring-1 ring-inset',
                STATUS_STYLES[status] ??
                    'bg-slate-100 text-slate-700 ring-slate-200',
            ].join(' ')}
        >
            {STATUS_LABELS[status] ??
                String(status || 'Unknown').replaceAll('_', ' ')}
        </span>
    );
}

function SummaryItem({ label, value }) {
    return (
        <div>
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-words text-sm font-semibold text-slate-800">
                {empty(value)}
            </div>
        </div>
    );
}

function ActionButton({
    label,
    className,
    disabled,
    onClick,
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={[
                'w-full rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {label}
        </button>
    );
}
