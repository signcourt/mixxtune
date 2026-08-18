import {
    useMemo,
    useState,
} from 'react';

const RELEASE_TYPES = [
    { value: 'single', label: 'Single' },
    { value: 'ep', label: 'EP' },
    { value: 'album', label: 'Album' },
];

const GENRES = [
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

const SUB_GENRES = [
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

const LANGUAGES = [
    // Indian / regional
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

    // International
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

const CURRENT_YEAR = new Date().getFullYear();
const MAX_PRODUCTION_YEAR = CURRENT_YEAR + 1;

const PRODUCTION_YEARS = Array.from(
    {
        length:
            MAX_PRODUCTION_YEAR - 2000 + 1,
    },
    (_, index) =>
        MAX_PRODUCTION_YEAR - index
);

const fieldClass =
    'w-full rounded-xl border border-slate-200 bg-white px-3.5 py-3 text-sm text-slate-900 outline-none transition placeholder:text-slate-400 focus:border-violet-400 focus:ring-2 focus:ring-violet-100';

const selectClass =
    `${fieldClass} cursor-pointer`;

const normalizeArtist = (artist = {}) => ({
    name: artist?.name ?? '',
    spotify_url: artist?.spotify_url ?? '',
    apple_music_url: artist?.apple_music_url ?? '',
    youtube_topic_url: artist?.youtube_topic_url ?? '',
});

export default function ReleaseDetailsStep({
    role = 'artist',
    data,
    setData,
    errors = {},
    label = null,
    availableLabels = [],
    availableArtists = [],
}) {
    const primaryArtists =
        Array.isArray(data.primary_artists)
        && data.primary_artists.length > 0
            ? data.primary_artists.map(normalizeArtist)
            : [normalizeArtist({
                name: data.primary_artist_name ?? '',
            })];

    const featuringArtists =
        Array.isArray(data.featuring_artists)
            ? data.featuring_artists.map(normalizeArtist)
            : [];

    const setPrimaryArtists = (artists) => {
        const normalized = artists.map(normalizeArtist);

        setData('primary_artists', normalized);

        setData(
            'primary_artist_name',
            normalized
                .map((artist) => artist.name.trim())
                .filter(Boolean)
                .join(', ')
        );
    };

    const setFeaturingArtists = (artists) => {
        const normalized = artists.map(normalizeArtist);

        setData('featuring_artists', normalized);

        setData(
            'featuring_artist_name',
            normalized
                .map((artist) => artist.name.trim())
                .filter(Boolean)
                .join(', ')
        );
    };

    const updatePrimaryArtist = (index, value) => {
        const next = [...primaryArtists];

        next[index] = {
            ...next[index],
            name: value,
        };

        setPrimaryArtists(next);
    };

    const addPrimaryArtist = () => {
        if (primaryArtists.length >= 3) {
            return;
        }

        setPrimaryArtists([
            ...primaryArtists,
            normalizeArtist(),
        ]);
    };

    const removePrimaryArtist = (index) => {
        if (primaryArtists.length <= 1) {
            return;
        }

        setPrimaryArtists(
            primaryArtists.filter(
                (_, artistIndex) =>
                    artistIndex !== index
            )
        );
    };

    const updateFeaturingArtist = (
        index,
        value
    ) => {
        const next = [...featuringArtists];

        next[index] = {
            ...next[index],
            name: value,
        };

        setFeaturingArtists(next);
    };

    const addFeaturingArtist = () => {
        setFeaturingArtists([
            ...featuringArtists,
            normalizeArtist(),
        ]);
    };

    const removeFeaturingArtist = (index) => {
        setFeaturingArtists(
            featuringArtists.filter(
                (_, artistIndex) =>
                    artistIndex !== index
            )
        );
    };

    const isOperator =
        role === 'admin'
        || role === 'super_admin';

    const accountArtists = useMemo(() => {
        const list = Array.isArray(availableArtists)
            ? availableArtists
            : [];

        const selectedLabelId =
            String(data.label_id ?? '');

        if (!selectedLabelId) {
            return list;
        }

        return list.filter(
            (item) =>
                String(item.label_id ?? '')
                === selectedLabelId
        );
    }, [
        availableArtists,
        data.label_id,
    ]);

    const labels = useMemo(() => {
        const list = Array.isArray(availableLabels)
            ? [...availableLabels]
            : [];

        if (
            label?.id
            && !list.some(
                (item) =>
                    String(item.id)
                    === String(label.id)
            )
        ) {
            list.unshift(label);
        }

        return list;
    }, [availableLabels, label]);

    /*
     * Human-readable hierarchy path.
     *
     * Example:
     * Sanatan Records
     *   ↳ X
     *     ↳ X1
     */
    const labelDepth = (item) => {
        let depth = 0;
        let current = item;
        const visited = new Set();

        while (
            current?.parent_label_id
            && !visited.has(
                String(current.id)
            )
        ) {
            visited.add(
                String(current.id)
            );

            const parent = labels.find(
                (candidate) =>
                    String(candidate.id)
                    === String(
                        current.parent_label_id
                    )
            );

            if (!parent) {
                break;
            }

            depth += 1;
            current = parent;

            if (depth > 20) {
                break;
            }
        }

        return depth;
    };

    const labelOptionName = (item) => {
        const name =
            item.name
            ?? item.label_name
            ?? `Label #${item.id}`;

        const depth =
            labelDepth(item);

        if (depth === 0) {
            return name;
        }

        return `${'— '.repeat(depth)}${name}`;
    };

    const changeCatalogueLevel = (
        nextLabelId
    ) => {
        setData(
            'label_id',
            nextLabelId
        );

        const currentArtist =
            availableArtists.find(
                (item) =>
                    String(item.id)
                    === String(
                        data.artist_id
                        ?? ''
                    )
            );

        if (
            currentArtist
            && String(
                currentArtist.label_id
                ?? ''
            )
                !== String(
                    nextLabelId
                )
        ) {
            setData(
                'artist_id',
                ''
            );
        }
    };

    return (
        <div className="mixx-release-details-v7">
            {isOperator && (
                <div className="mb-7 rounded-2xl border border-violet-200 bg-violet-50/50 p-5">
                    <div className="mb-4">
                        <span className="text-[10px] font-black uppercase tracking-[0.18em] text-violet-600">
                            Release Ownership
                        </span>

                        <h3 className="mt-1 text-base font-black text-slate-950">
                            Catalog Account
                        </h3>

                        <p className="mt-1 text-xs text-slate-500">
                            Select the catalog owner for this release. Artist credits are entered separately below.
                        </p>
                    </div>

                    <div className="grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <Field
                            label="Catalogue Level"
                            required
                            error={errors.label_id}
                        >
                            <select
                                className={selectClass}
                                value={data.label_id ?? ''}
                                onChange={(event) =>
                                    changeCatalogueLevel(
                                        event.target.value
                                    )
                                }
                            >
                                <option value="">
                                    Select catalogue level
                                </option>

                                {labels.map(
                                    (item) => (
                                        <option
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {labelOptionName(
                                                item
                                            )}
                                        </option>
                                    )
                                )}
                            </select>
                        </Field>

                        <Field
                            label="Account Artist"
                            error={errors.artist_id}
                        >
                            <select
                                className={selectClass}
                                value={data.artist_id ?? ''}
                                onChange={(event) =>
                                    setData(
                                        'artist_id',
                                        event.target.value
                                    )
                                }
                            >
                                <option value="">
                                    No artist account
                                </option>

                                {accountArtists.map(
                                    (item) => (
                                        <option
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {item.stage_name
                                                || item.legal_name
                                                || `Artist #${item.id}`}
                                        </option>
                                    )
                                )}
                            </select>

                            <p className="mt-1.5 text-[11px] leading-4 text-slate-400">
                                Optional. Select only when this release should belong to an existing artist account.
                            </p>
                        </Field>
                    </div>
                </div>
            )}

            <div className="mb-6">
                <span className="text-[10px] font-black uppercase tracking-[0.18em] text-violet-600">
                    Release Metadata
                </span>

                <h2 className="mt-1 text-xl font-black tracking-tight text-slate-950">
                    Release Details
                </h2>

                <p className="mt-1 text-xs text-slate-500">
                    Enter the core metadata for this release.
                </p>
            </div>

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <Field
                    label="Release Title"
                    required
                    error={errors.title}
                >
                    <input
                        type="text"
                        className={fieldClass}
                        value={data.title ?? ''}
                        onChange={(event) =>
                            setData(
                                'title',
                                event.target.value
                            )
                        }
                        placeholder="Enter release title"
                    />
                </Field>

                <Field
                    label="Release Type"
                    required
                    error={errors.release_type}
                >
                    <select
                        className={selectClass}
                        value={
                            data.release_type
                            ?? 'single'
                        }
                        onChange={(event) =>
                            setData(
                                'release_type',
                                event.target.value
                            )
                        }
                    >
                        {RELEASE_TYPES.map(
                            (item) => (
                                <option
                                    key={item.value}
                                    value={item.value}
                                >
                                    {item.label}
                                </option>
                            )
                        )}
                    </select>
                </Field>
            </div>

            <SectionDivider title="Artists" />

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div>
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <label className="text-sm font-bold text-slate-700">
                            Primary Artist
                            <span className="ml-1 text-red-500">
                                *
                            </span>
                        </label>

                        <span className="text-[10px] font-bold text-slate-400">
                            Maximum 3
                        </span>
                    </div>

                    <div className="space-y-2.5">
                        {primaryArtists.map(
                            (artist, index) => (
                                <ArtistRow
                                    key={`primary-${index}`}
                                    value={artist.name}
                                    placeholder={
                                        index === 0
                                            ? 'Enter primary artist'
                                            : 'Enter additional primary artist'
                                    }
                                    onChange={(value) =>
                                        updatePrimaryArtist(
                                            index,
                                            value
                                        )
                                    }
                                    onAdd={
                                        index
                                            === primaryArtists.length - 1
                                        && primaryArtists.length < 3
                                            ? addPrimaryArtist
                                            : null
                                    }
                                    onRemove={
                                        primaryArtists.length > 1
                                            ? () =>
                                                removePrimaryArtist(
                                                    index
                                                )
                                            : null
                                    }
                                />
                            )
                        )}
                    </div>

                    <ErrorText
                        error={
                            errors.primary_artists
                            || errors.primary_artist_name
                        }
                    />
                </div>

                <div>
                    <div className="mb-2 flex items-center justify-between gap-3">
                        <label className="text-sm font-bold text-slate-700">
                            Featuring Artist
                        </label>

                        {featuringArtists.length === 0 && (
                            <button
                                type="button"
                                onClick={addFeaturingArtist}
                                className="inline-flex h-7 items-center gap-1 rounded-lg border border-violet-200 bg-violet-50 px-2.5 text-[10px] font-black text-violet-700 transition hover:bg-violet-100"
                            >
                                + Add
                            </button>
                        )}
                    </div>

                    {featuringArtists.length === 0 ? (
                        <div className="flex h-[46px] items-center rounded-xl border border-dashed border-slate-200 px-3.5 text-xs text-slate-400">
                            No featuring artist
                        </div>
                    ) : (
                        <div className="space-y-2.5">
                            {featuringArtists.map(
                                (artist, index) => (
                                    <ArtistRow
                                        key={`featuring-${index}`}
                                        value={artist.name}
                                        placeholder="Enter featuring artist"
                                        onChange={(value) =>
                                            updateFeaturingArtist(
                                                index,
                                                value
                                            )
                                        }
                                        onAdd={
                                            index
                                                === featuringArtists.length - 1
                                                ? addFeaturingArtist
                                                : null
                                        }
                                        onRemove={() =>
                                            removeFeaturingArtist(
                                                index
                                            )
                                        }
                                    />
                                )
                            )}
                        </div>
                    )}

                    <ErrorText
                        error={
                            errors.featuring_artists
                            || errors.featuring_artist_name
                        }
                    />
                </div>
            </div>

            <SectionDivider title="Classification" />

            <div className="grid grid-cols-1 gap-5 md:grid-cols-2 xl:grid-cols-3">
                {!isOperator && (
                    <Field
                        label="Catalogue Level"
                        required
                        error={errors.label_id}
                    >
                        <select
                            className={selectClass}
                            value={
                                data.label_id
                                ?? ''
                            }
                            onChange={(event) =>
                                changeCatalogueLevel(
                                    event.target.value
                                )
                            }
                        >
                            <option value="">
                                Select catalogue level
                            </option>

                            {labels.map(
                                (item) => (
                                    <option
                                        key={item.id}
                                        value={item.id}
                                    >
                                        {labelOptionName(
                                            item
                                        )}
                                    </option>
                                )
                            )}
                        </select>

                        {labels.length > 1 && (
                            <p className="mt-1.5 text-[11px] leading-4 text-slate-400">
                                Select the exact catalogue level that owns this release.
                            </p>
                        )}
                    </Field>
                )}

                {!isOperator && (
                    <Field
                        label="Account Artist"
                        required
                        error={errors.artist_id}
                    >
                        <select
                            className={selectClass}
                            value={
                                data.artist_id
                                ?? ''
                            }
                            onChange={(event) =>
                                setData(
                                    'artist_id',
                                    event.target.value
                                )
                            }
                        >
                            <option value="">
                                Select artist account
                            </option>

                            {accountArtists.map(
                                (item) => (
                                    <option
                                        key={item.id}
                                        value={item.id}
                                    >
                                        {item.stage_name
                                            || item.legal_name
                                            || `Artist #${item.id}`}
                                    </option>
                                )
                            )}
                        </select>

                        {data.label_id
                            && accountArtists.length === 0
                            && (
                                <p className="mt-1.5 text-[11px] leading-4 text-amber-600">
                                    No active release-enabled artist exists on this catalogue level.
                                </p>
                            )}
                    </Field>
                )}

                <Field
                    label="Genre"
                    required
                    error={errors.primary_genre}
                >
                    <SearchableSelect
                        value={
                            data.primary_genre ?? ''
                        }
                        options={GENRES}
                        placeholder="Search genre..."
                        onChange={(value) =>
                            setData(
                                'primary_genre',
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
                    <SearchableSelect
                        value={data.sub_genre ?? ''}
                        options={SUB_GENRES}
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
                    <SearchableSelect
                        value={data.language ?? ''}
                        options={LANGUAGES}
                        placeholder="Type language..."
                        onChange={(value) =>
                            setData(
                                'language',
                                value
                            )
                        }
                    />
                </Field>

                <Field
                    label="Release Date"
                    required
                    error={errors.digital_release_date}
                >
                    <input
                        type="date"
                        className={fieldClass}
                        value={
                            data.digital_release_date
                            ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'digital_release_date',
                                event.target.value
                            )
                        }
                    />
                </Field>

                <Field
                    label="Production Year"
                    required
                    error={
                        errors.copyright_year
                        || errors.phonographic_year
                    }
                >
                    <select
                        className={selectClass}
                        value={
                            data.copyright_year
                            || data.phonographic_year
                            || CURRENT_YEAR
                        }
                        onChange={(event) => {
                            const year =
                                event.target.value;

                            setData(
                                'copyright_year',
                                year
                            );

                            setData(
                                'phonographic_year',
                                year
                            );

                            // Old field intentionally cleared.
                            setData(
                                'original_release_date',
                                ''
                            );
                        }}
                    >
                        {PRODUCTION_YEARS.map(
                            (year) => (
                                <option
                                    key={year}
                                    value={String(year)}
                                >
                                    {year}
                                </option>
                            )
                        )}
                    </select>

                    <p className="mt-1.5 text-[10px] text-slate-400">
                        Select the production year.
                    </p>
                </Field>
            </div>

            <SectionDivider title="Rights & Identifiers" />

            <div className="grid grid-cols-1 gap-5 lg:grid-cols-2">
                <div className="space-y-5">
                    <Field
                        label="C Line"
                    required
                        error={errors.copyright_owner}
                        hint="Copyright line"
                    >
                        <input
                            type="text"
                            className={fieldClass}
                            value={
                                data.copyright_owner
                                ?? ''
                            }
                            onChange={(event) =>
                                setData(
                                    'copyright_owner',
                                    event.target.value
                                )
                            }
                            placeholder="Enter C Line"
                        />
                    </Field>

                    <Field
                        label="P Line"
                    required
                        error={
                            errors.phonographic_owner
                        }
                        hint="Phonographic copyright line"
                    >
                        <input
                            type="text"
                            className={fieldClass}
                            value={
                                data.phonographic_owner
                                ?? ''
                            }
                            onChange={(event) =>
                                setData(
                                    'phonographic_owner',
                                    event.target.value
                                )
                            }
                            placeholder="Enter P Line"
                        />
                    </Field>
                </div>

                <div className="space-y-5">
                    <Field
                        label="Ask to Generate UPC?"
                        error={errors.upc}
                        hint={
                            data.generate_upc === false
                                ? 'Enter your existing UPC below.'
                                : 'Mixx Tune will assign a UPC.'
                        }
                    >
                        <div className="flex h-[46px] items-center gap-5 rounded-xl border border-slate-200 bg-white px-4">
                            <label className="flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700">
                                <input
                                    type="radio"
                                    name="generate_upc"
                                    checked={
                                        data.generate_upc
                                        !== false
                                    }
                                    onChange={() => {
                                        setData(
                                            'generate_upc',
                                            true
                                        );

                                        setData(
                                            'upc',
                                            ''
                                        );
                                    }}
                                    className="h-4 w-4 accent-violet-600"
                                />

                                Yes
                            </label>

                            <label className="flex cursor-pointer items-center gap-2 text-sm font-bold text-slate-700">
                                <input
                                    type="radio"
                                    name="generate_upc"
                                    checked={
                                        data.generate_upc
                                        === false
                                    }
                                    onChange={() =>
                                        setData(
                                            'generate_upc',
                                            false
                                        )
                                    }
                                    className="h-4 w-4 accent-violet-600"
                                />

                                No
                            </label>
                        </div>
                    </Field>

                    {data.generate_upc === false && (
                        <Field
                            label="Existing UPC"
                            required
                            error={errors.upc}
                        >
                            <input
                                type="text"
                                inputMode="numeric"
                                className={fieldClass}
                                value={
                                    data.upc ?? ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'upc',
                                        event.target.value
                                    )
                                }
                                placeholder="Enter existing UPC"
                            />
                        </Field>
                    )}

                    <Field
                        label="Catalog Number"
                        error={errors.catalog_number}
                    >
                        <input
                            type="text"
                            className={`${fieldClass} bg-slate-50`}
                            value={
                                data.catalog_number
                                ?? ''
                            }
                            readOnly
                        />
                    </Field>
                </div>
            </div>
        </div>
    );
}

function SectionDivider({ title }) {
    return (
        <div className="my-6 flex items-center gap-3">
            <span className="text-[10px] font-black uppercase tracking-[0.16em] text-slate-400">
                {title}
            </span>

            <div className="h-px flex-1 bg-slate-100" />
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
        <div>
            <label className="mb-2 block text-sm font-bold text-slate-700">
                {label}

                {required && (
                    <span className="ml-1 text-red-500">
                        *
                    </span>
                )}
            </label>

            {children}

            {hint && !error && (
                <p className="mt-1.5 text-[10px] text-slate-400">
                    {hint}
                </p>
            )}

            <ErrorText error={error} />
        </div>
    );
}

function ErrorText({ error }) {
    if (!error) {
        return null;
    }

    return (
        <p className="mt-1.5 text-xs font-semibold text-red-600">
            {error}
        </p>
    );
}

function ArtistRow({
    value,
    placeholder,
    onChange,
    onAdd = null,
    onRemove = null,
}) {
    return (
        <div className="flex gap-2">
            <input
                type="text"
                className={fieldClass}
                value={value}
                onChange={(event) =>
                    onChange(event.target.value)
                }
                placeholder={placeholder}
            />

            {onAdd && (
                <button
                    type="button"
                    onClick={onAdd}
                    title="Add artist"
                    className="grid h-[46px] w-[46px] shrink-0 place-items-center rounded-xl border border-violet-200 bg-violet-50 text-xl font-bold text-violet-700 transition hover:bg-violet-100"
                >
                    +
                </button>
            )}

            {onRemove && (
                <button
                    type="button"
                    onClick={onRemove}
                    title="Remove artist"
                    className="grid h-[46px] w-[46px] shrink-0 place-items-center rounded-xl border border-red-100 bg-red-50 text-lg font-bold text-red-500 transition hover:bg-red-100"
                >
                    ×
                </button>
            )}
        </div>
    );
}

function SearchableSelect({
    value,
    options,
    placeholder,
    onChange,
}) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const filtered = useMemo(() => {
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

    const displayedValue =
        open ? query : value;

    return (
        <div className="relative">
            <input
                type="text"
                className={fieldClass}
                value={displayedValue}
                placeholder={placeholder}
                autoComplete="off"
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
                <div className="absolute left-0 right-0 top-[calc(100%+6px)] z-50 max-h-56 overflow-y-auto rounded-xl border border-slate-200 bg-white p-1.5 shadow-xl">
                    {filtered.length > 0 ? (
                        filtered.map((option) => (
                            <button
                                type="button"
                                key={option}
                                onMouseDown={(event) =>
                                    event.preventDefault()
                                }
                                onClick={() => {
                                    onChange(option);
                                    setQuery(option);
                                    setOpen(false);
                                }}
                                className={`block w-full rounded-lg px-3 py-2 text-left text-xs font-semibold transition ${
                                    value === option
                                        ? 'bg-violet-50 text-violet-700'
                                        : 'text-slate-700 hover:bg-slate-50'
                                }`}
                            >
                                {option}
                            </button>
                        ))
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
