const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

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
    const artistLocked = role === 'artist';
    const labelLocked = ['artist', 'label'].includes(role);

    const handleArtistChange = (event) => {
        const id = Number(event.target.value);

        const selected = availableArtists.find(
            (item) => Number(item.id) === id
        );

        setData('artist_id', id || '');
        setData(
            'primary_artist_name',
            selected?.stage_name ||
                selected?.legal_name ||
                ''
        );

        if (selected?.label_id) {
            setData(
                'label_id',
                Number(selected.label_id)
            );
        }
    };

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Release Details
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Enter the main metadata for this release.
                    </p>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        label="Release Title"
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
                            placeholder="Enter release title"
                        />
                    </Field>

                    <Field
                        label="Release Type"
                        required
                        error={errors.release_type}
                    >
                        <select
                            className={inputClass}
                            value={data.release_type}
                            onChange={(event) =>
                                setData(
                                    'release_type',
                                    event.target.value
                                )
                            }
                        >
                            <option value="single">
                                Single
                            </option>
                            <option value="ep">
                                EP
                            </option>
                            <option value="album">
                                Album
                            </option>
                        </select>
                    </Field>

                    <Field
                        label="Primary Artist"
                        required
                        error={errors.artist_id}
                    >
                        {artistLocked ? (
                            <input
                                className={`${inputClass} bg-slate-100`}
                                value={
                                    artist?.stage_name ||
                                    artist?.legal_name ||
                                    ''
                                }
                                readOnly
                            />
                        ) : (
                            <select
                                className={inputClass}
                                value={data.artist_id}
                                onChange={handleArtistChange}
                            >
                                <option value="">
                                    Select artist
                                </option>

                                {availableArtists.map(
                                    (item) => (
                                        <option
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {item.stage_name ||
                                                item.legal_name}
                                        </option>
                                    )
                                )}
                            </select>
                        )}
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
                            placeholder="Optional"
                        />
                    </Field>

                    <Field
                        label="Label"
                        required
                        error={errors.label_id}
                    >
                        {labelLocked ? (
                            <input
                                className={`${inputClass} bg-slate-100`}
                                value={label?.name || ''}
                                readOnly
                            />
                        ) : (
                            <select
                                className={inputClass}
                                value={data.label_id}
                                onChange={(event) =>
                                    setData(
                                        'label_id',
                                        Number(
                                            event.target.value
                                        ) || ''
                                    )
                                }
                            >
                                <option value="">
                                    Select label
                                </option>

                                {availableLabels.map(
                                    (item) => (
                                        <option
                                            key={item.id}
                                            value={item.id}
                                        >
                                            {item.name}
                                        </option>
                                    )
                                )}
                            </select>
                        )}
                    </Field>

                    <Field
                        label="Digital Release Date"
                        required
                        error={errors.digital_release_date}
                    >
                        <input
                            type="date"
                            className={inputClass}
                            value={
                                data.digital_release_date
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
                        label="UPC"
                        error={errors.upc}
                    >
                        <input
                            className={inputClass}
                            value={data.upc}
                            onChange={(event) =>
                                setData(
                                    'upc',
                                    event.target.value
                                )
                            }
                            placeholder="Leave blank if pending"
                        />
                    </Field>

                    <Field
                        label="Catalogue Number"
                        required
                        error={errors.catalog_number}
                    >
                        <input
                            className={inputClass}
                            value={data.catalog_number}
                            onChange={(event) =>
                                setData(
                                    'catalog_number',
                                    event.target.value
                                )
                            }
                            placeholder="MXT-000001"
                        />
                    </Field>

                    <Field label="Language">
                        <input
                            className={inputClass}
                            value={data.language}
                            onChange={(event) =>
                                setData(
                                    'language',
                                    event.target.value
                                )
                            }
                            placeholder="Hindi"
                        />
                    </Field>

                    <Field label="Primary Genre">
                        <input
                            className={inputClass}
                            value={data.primary_genre}
                            onChange={(event) =>
                                setData(
                                    'primary_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Devotional, Pop..."
                        />
                    </Field>

                    <Field label="Sub Genre">
                        <input
                            className={inputClass}
                            value={data.sub_genre}
                            onChange={(event) =>
                                setData(
                                    'sub_genre',
                                    event.target.value
                                )
                            }
                            placeholder="Optional"
                        />
                    </Field>

                    <Field label="Copyright Year">
                        <input
                            type="number"
                            min="1900"
                            max="2100"
                            className={inputClass}
                            value={data.copyright_year}
                            onChange={(event) =>
                                setData(
                                    'copyright_year',
                                    event.target.value
                                )
                            }
                        />
                    </Field>

                    <Field label="Copyright Owner">
                        <input
                            className={inputClass}
                            value={data.copyright_owner}
                            onChange={(event) =>
                                setData(
                                    'copyright_owner',
                                    event.target.value
                                )
                            }
                            placeholder="Rights owner"
                        />
                    </Field>

                    <Field label="Phonographic Owner">
                        <input
                            className={inputClass}
                            value={data.phonographic_owner}
                            onChange={(event) =>
                                setData(
                                    'phonographic_owner',
                                    event.target.value
                                )
                            }
                            placeholder="℗ owner"
                        />
                    </Field>
                </div>
            </div>

            <div className="space-y-5">
                <div className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Cover Artwork
                    </h3>

                    <label className="mt-4 flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-violet-300 bg-violet-50/40 p-6 text-center hover:bg-violet-50">
                        {data.artwork_preview ? (
                            <img
                                src={data.artwork_preview}
                                alt="Artwork preview"
                                className="aspect-square w-full rounded-xl object-cover"
                            />
                        ) : (
                            <>
                                <div className="text-4xl text-violet-600">
                                    ☁
                                </div>

                                <div className="mt-3 font-semibold text-slate-900">
                                    Upload Cover Art
                                </div>

                                <div className="mt-1 text-xs text-slate-500">
                                    JPG or PNG, maximum 20 MB
                                </div>
                            </>
                        )}

                        <input
                            type="file"
                            accept="image/jpeg,image/png"
                            className="hidden"
                            onChange={(event) => {
                                const file =
                                    event.target
                                        .files?.[0] ?? null;

                                setData('artwork', file);

                                setData(
                                    'artwork_preview',
                                    file
                                        ? URL.createObjectURL(
                                              file
                                          )
                                        : null
                                );
                            }}
                        />
                    </label>

                    {errors.artwork && (
                        <p className="mt-2 text-sm text-red-600">
                            {errors.artwork}
                        </p>
                    )}
                </div>

                <div className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Release Preview
                    </h3>

                    <div className="mt-4 space-y-3 text-sm">
                        <PreviewRow
                            label="Title"
                            value={data.title || '—'}
                        />

                        <PreviewRow
                            label="Artist"
                            value={
                                data.primary_artist_name ||
                                artist?.stage_name ||
                                '—'
                            }
                        />

                        <PreviewRow
                            label="Label"
                            value={
                                label?.name ||
                                availableLabels.find(
                                    (item) =>
                                        Number(item.id) ===
                                        Number(data.label_id)
                                )?.name ||
                                '—'
                            }
                        />

                        <PreviewRow
                            label="Release Date"
                            value={
                                data.digital_release_date ||
                                '—'
                            }
                        />

                        <PreviewRow
                            label="Type"
                            value={data.release_type}
                        />
                    </div>
                </div>
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

function PreviewRow({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-slate-500">
                {label}
            </span>

            <span className="truncate font-medium capitalize text-slate-900">
                {value}
            </span>
        </div>
    );
}
