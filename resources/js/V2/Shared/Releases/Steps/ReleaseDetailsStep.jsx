const inputClass =
    'mixx-reference-input';

const tomorrow = () => {
    const date = new Date();

    date.setDate(date.getDate() + 1);

    return date.toISOString().slice(0, 10);
};

const emptyPrimaryArtist = () => ({
    name: '',
    spotify_url: '',
    apple_music_url: '',
    youtube_topic_url: '',
});

export default function ReleaseDetailsStep({
    role = 'artist',
    data,
    setData,
    errors = {},
    artist = null,
    label = null,
    availableArtists = [],
    availableLabels = [],
}) {
    const primaryArtists =
        Array.isArray(data.primary_artists) &&
        data.primary_artists.length > 0
            ? data.primary_artists
            : [
                  {
                      ...emptyPrimaryArtist(),
                      name:
                          data.primary_artist_name ||
                          artist?.stage_name ||
                          artist?.legal_name ||
                          '',
                  },
              ];

    const featuringArtists =
        Array.isArray(data.featuring_artists)
            ? data.featuring_artists
            : [];

    const primaryArtist =
        primaryArtists[0] ??
        emptyPrimaryArtist();

    const featuringArtist =
        featuringArtists[0] ?? {
            name: '',
        };

    const selectedLabel =
        label?.name ||
        availableLabels.find(
            (item) =>
                Number(item.id) ===
                Number(data.label_id)
        )?.name ||
        '';

    const updatePrimary = (
        field,
        value
    ) => {
        const updated = [
            {
                ...primaryArtist,
                [field]: value,
            },
            ...primaryArtists.slice(1),
        ];

        setData(
            'primary_artists',
            updated
        );

        if (field === 'name') {
            setData(
                'primary_artist_name',
                value
            );
        }
    };

    const updateFeaturing = (value) => {
        if (!value) {
            setData(
                'featuring_artists',
                []
            );

            return;
        }

        setData(
            'featuring_artists',
            [
                {
                    ...featuringArtist,
                    name: value,
                },
            ]
        );
    };

    const setArtwork = (file) => {
        setData(
            'artwork',
            file
        );

        setData(
            'artwork_preview',
            file
                ? URL.createObjectURL(file)
                : null
        );
    };

    return (
        <div className="mixx-reference-release-details">
            <div className="mixx-reference-release-fields">
                <div className="mixx-reference-grid mixx-reference-grid-two">
                    <Field
                        label="Release Title"
                        required
                        error={errors.title}
                    >
                        <input
                            className={inputClass}
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
                        label="Primary Artist"
                        required
                        error={
                            errors[
                                'primary_artists.0.name'
                            ] ||
                            errors.primary_artist_name
                        }
                    >
                        <input
                            className={inputClass}
                            value={
                                primaryArtist.name ?? ''
                            }
                            onChange={(event) =>
                                updatePrimary(
                                    'name',
                                    event.target.value
                                )
                            }
                            placeholder="Enter primary artist name"
                        />
                    </Field>
                </div>

                <Field
                    label="Featuring Artist"
                    hint="Optional"
                    error={
                        errors[
                            'featuring_artists.0.name'
                        ]
                    }
                >
                    <input
                        className={inputClass}
                        value={
                            featuringArtist.name ?? ''
                        }
                        onChange={(event) =>
                            updateFeaturing(
                                event.target.value
                            )
                        }
                        placeholder="Enter featuring artist name (optional)"
                    />
                </Field>

                <Field
                    label="Release Type"
                    required
                    error={errors.release_type}
                >
                    <div className="mixx-reference-release-types">
                        {[
                            {
                                value: 'single',
                                title: 'Single',
                                icon: '♫',
                            },
                            {
                                value: 'ep',
                                title: 'EP',
                                icon: '▣',
                            },
                            {
                                value: 'album',
                                title: 'Album',
                                icon: '◎',
                            },
                        ].map((option) => {
                            const active =
                                data.release_type ===
                                option.value;

                            return (
                                <button
                                    key={option.value}
                                    type="button"
                                    onClick={() =>
                                        setData(
                                            'release_type',
                                            option.value
                                        )
                                    }
                                    className={`mixx-reference-release-type ${
                                        active
                                            ? 'mixx-reference-release-type-active'
                                            : ''
                                    }`}
                                >
                                    <span className="mixx-reference-radio">
                                        {active
                                            ? '●'
                                            : '○'}
                                    </span>

                                    <span className="mixx-reference-release-icon">
                                        {option.icon}
                                    </span>

                                    <strong>
                                        {option.title}
                                    </strong>
                                </button>
                            );
                        })}
                    </div>
                </Field>

                {role === 'label' && (
                    <Field
                        label="Artist Account"
                        required
                        error={errors.artist_id}
                    >
                        <select
                            className={inputClass}
                            value={
                                data.artist_id ?? ''
                            }
                            onChange={(event) => {
                                const artistId =
                                    event.target.value;

                                const selectedArtist =
                                    availableArtists.find(
                                        (item) =>
                                            Number(
                                                item.id
                                            ) ===
                                            Number(
                                                artistId
                                            )
                                    );

                                setData(
                                    'artist_id',
                                    artistId
                                );

                                if (
                                    selectedArtist
                                ) {
                                    updatePrimary(
                                        'name',
                                        selectedArtist.stage_name ||
                                            selectedArtist.legal_name ||
                                            selectedArtist.name ||
                                            ''
                                    );
                                }
                            }}
                        >
                            <option value="">
                                Select artist account
                            </option>

                            {availableArtists.map(
                                (item) => (
                                    <option
                                        key={item.id}
                                        value={item.id}
                                    >
                                        {item.stage_name ||
                                            item.legal_name ||
                                            item.name}
                                    </option>
                                )
                            )}
                        </select>
                    </Field>
                )}

                <div className="mixx-reference-grid mixx-reference-grid-three">
                    <Field
                        label="Label Name"
                        required
                        error={errors.label_id}
                    >
                        <input
                            className={`${inputClass} mixx-reference-readonly`}
                            value={selectedLabel}
                            readOnly
                            placeholder="Select or enter label name"
                        />
                    </Field>

                    <Field
                        label="Genre"
                        required
                    >
                        <input
                            className={inputClass}
                            value={
                                data.primary_genre ?? ''
                            }
                            onChange={(event) =>
                                setData(
                                    'primary_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Select genre"
                        />
                    </Field>

                    <Field label="Sub Genre">
                        <input
                            className={inputClass}
                            value={
                                data.sub_genre ?? ''
                            }
                            onChange={(event) =>
                                setData(
                                    'sub_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Select sub genre"
                        />
                    </Field>
                </div>

                <div className="mixx-reference-grid mixx-reference-grid-four">
                    <Field label="Language">
                        <input
                            className={inputClass}
                            value={data.language ?? ''}
                            onChange={(event) =>
                                setData(
                                    'language',
                                    event.target.value
                                )
                            }
                            placeholder="Select language"
                        />
                    </Field>

                    <Field
                        label="Release Date"
                        required
                        error={
                            errors.digital_release_date
                        }
                    >
                        <input
                            type="date"
                            min={tomorrow()}
                            className={inputClass}
                            value={
                                data.digital_release_date ??
                                ''
                            }
                            onChange={(event) =>
                                setData(
                                    'digital_release_date',
                                    event.target.value
                                )
                            }
                        />
                    </Field>

                    <ToggleField
                        label="Original Release"
                        checked={
                            Boolean(
                                data.is_original_release
                            )
                        }
                        onChange={(value) =>
                            setData(
                                'is_original_release',
                                value
                            )
                        }
                    />

                    <ToggleField
                        label="Explicit Content"
                        checked={
                            Boolean(
                                data.is_explicit
                            )
                        }
                        onChange={(value) =>
                            setData(
                                'is_explicit',
                                value
                            )
                        }
                    />
                </div>

                <div className="mixx-reference-artwork-rights">
                    <Field
                        label="Cover Art"
                        required
                        error={errors.artwork}
                    >
                        <label className="mixx-reference-cover-upload">
                            {data.artwork_preview ? (
                                <img
                                    src={
                                        data.artwork_preview
                                    }
                                    alt="Artwork preview"
                                    className="mixx-reference-cover-preview"
                                />
                            ) : (
                                <>
                                    <span className="mixx-reference-cover-icon">
                                        ⇧
                                    </span>

                                    <strong>
                                        Upload Cover Art
                                    </strong>

                                    <small>
                                        JPG or PNG · 3000×3000px
                                    </small>

                                    <span className="mixx-reference-browse-button">
                                        Browse Files
                                    </span>
                                </>
                            )}

                            <input
                                type="file"
                                accept="image/jpeg,image/png"
                                className="hidden"
                                onChange={(event) =>
                                    setArtwork(
                                        event.target.files?.[0] ??
                                            null
                                    )
                                }
                            />
                        </label>
                    </Field>

                    <div className="mixx-reference-rights-fields">
                        <Field
                            label="Copyright Owner"
                            required
                        >
                            <input
                                className={inputClass}
                                value={
                                    data.copyright_owner ??
                                    ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'copyright_owner',
                                        event.target.value
                                    )
                                }
                                placeholder="Enter copyright owner"
                            />

                            <HelperText>
                                Usually the label or the artist
                            </HelperText>
                        </Field>

                        <Field label="Publishing Owner">
                            <input
                                className={inputClass}
                                value={
                                    data.phonographic_owner ??
                                    ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'phonographic_owner',
                                        event.target.value
                                    )
                                }
                                placeholder="Enter publishing owner (optional)"
                            />

                            <HelperText>
                                Leave blank if same as copyright owner
                            </HelperText>
                        </Field>
                    </div>
                </div>

                <Field
                    label="Release Description"
                    hint="Optional"
                >
                    <textarea
                        className="mixx-reference-textarea"
                        value={
                            data.description ?? ''
                        }
                        onChange={(event) =>
                            setData(
                                'description',
                                event.target.value
                            )
                        }
                        maxLength={1000}
                        placeholder="Tell listeners about your release, the story behind it, and any special credits."
                    />

                    <div className="mixx-reference-character-count">
                        {
                            String(
                                data.description ?? ''
                            ).length
                        }{' '}
                        / 1000
                    </div>
                </Field>

                <details className="mixx-reference-advanced">
                    <summary>
                        Advanced Release Details
                    </summary>

                    <div className="mixx-reference-grid mixx-reference-grid-three">
                        <Field label="UPC">
                            <input
                                className={inputClass}
                                value={data.upc ?? ''}
                                onChange={(event) =>
                                    setData(
                                        'upc',
                                        event.target.value
                                    )
                                }
                                placeholder="Leave blank if pending"
                            />
                        </Field>

                        <Field label="Catalogue Number">
                            <input
                                className={inputClass}
                                value={
                                    data.catalog_number ?? ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'catalog_number',
                                        event.target.value
                                    )
                                }
                                placeholder="MXT-000001"
                            />
                        </Field>

                        <Field label="Copyright Year">
                            <input
                                type="number"
                                min="1900"
                                max="2100"
                                className={inputClass}
                                value={
                                    data.copyright_year ?? ''
                                }
                                onChange={(event) =>
                                    setData(
                                        'copyright_year',
                                        event.target.value
                                    )
                                }
                            />
                        </Field>

                        <Field label="Spotify Artist URL">
                            <input
                                className={inputClass}
                                value={
                                    primaryArtist.spotify_url ??
                                    ''
                                }
                                onChange={(event) =>
                                    updatePrimary(
                                        'spotify_url',
                                        event.target.value
                                    )
                                }
                                placeholder="https://open.spotify.com/artist/..."
                            />
                        </Field>

                        <Field label="Apple Music Artist URL">
                            <input
                                className={inputClass}
                                value={
                                    primaryArtist.apple_music_url ??
                                    ''
                                }
                                onChange={(event) =>
                                    updatePrimary(
                                        'apple_music_url',
                                        event.target.value
                                    )
                                }
                                placeholder="https://music.apple.com/..."
                            />
                        </Field>

                        <Field label="YouTube Topic URL">
                            <input
                                className={inputClass}
                                value={
                                    primaryArtist.youtube_topic_url ??
                                    ''
                                }
                                onChange={(event) =>
                                    updatePrimary(
                                        'youtube_topic_url',
                                        event.target.value
                                    )
                                }
                                placeholder="https://youtube.com/channel/..."
                            />
                        </Field>
                    </div>
                </details>
            </div>
        </div>
    );
}

function Field({
    label,
    required = false,
    hint = null,
    error = null,
    children,
}) {
    return (
        <div className="mixx-reference-field">
            <label>
                {label}

                {required && (
                    <span className="mixx-reference-required">
                        *
                    </span>
                )}

                {hint && (
                    <small>
                        {hint}
                    </small>
                )}
            </label>

            {children}

            {error && (
                <p className="mixx-reference-error">
                    {error}
                </p>
            )}
        </div>
    );
}

function HelperText({
    children,
}) {
    return (
        <p className="mixx-reference-helper">
            {children}
        </p>
    );
}

function ToggleField({
    label,
    checked,
    onChange,
}) {
    return (
        <div className="mixx-reference-toggle-field">
            <span>{label}</span>

            <button
                type="button"
                onClick={() =>
                    onChange(!checked)
                }
                className={`mixx-reference-toggle ${
                    checked
                        ? 'mixx-reference-toggle-active'
                        : ''
                }`}
                aria-pressed={checked}
            >
                <span />
            </button>

            <small>
                {checked ? 'Yes' : 'No'}
            </small>
        </div>
    );
}
