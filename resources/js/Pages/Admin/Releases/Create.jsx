import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';


const fieldClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

export default function Create({ artists = [], labels = [] }) {
    const [artworkPreview, setArtworkPreview] = useState(null);

    const {
        data,
        setData,
        post,
        transform,
        processing,
        errors,
    } = useForm({
        title: '',
        release_type: 'single',
        artist_id: '',
        primary_artist_name: '',
        featuring_artist_name: '',
        label_id: '',
        catalog_number: '',
        upc: '',
        language: '',
        primary_genre: '',
        sub_genre: '',
        original_release_date: '',
        digital_release_date: '',
        copyright_owner: '',
        copyright_year: String(new Date().getFullYear()),
        phonographic_owner: '',
        phonographic_year: String(new Date().getFullYear()),
        description: '',
        artwork: null,
        status: 'draft',
        wizard_step: 1,
        completion_percentage: 20,
    });

    const selectedArtist = useMemo(
        () =>
            artists.find(
                (artist) => String(artist.id) === String(data.artist_id)
            ),
        [artists, data.artist_id]
    );

    const selectedLabel = useMemo(
        () =>
            labels.find(
                (label) => String(label.id) === String(data.label_id)
            ),
        [labels, data.label_id]
    );

    const handleArtwork = (event) => {
        const file = event.target.files?.[0];

        if (!file) return;

        setData('artwork', file);
        setArtworkPreview(URL.createObjectURL(file));
    };

    const createDraft = () => {
        post('/releases', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Create Release">
            <Head title="Create Release" />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Create Release
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Create the release draft first. Tracks, distribution and review continue on the next screen.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/releases"
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </Link>

                        <button
                            type="button"
                            onClick={createDraft}
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing ? 'Creating...' : 'Create Draft & Continue'}
                        </button>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex items-center gap-3">
                            <div className="flex h-9 w-9 items-center justify-center rounded-full bg-violet-600 text-sm font-semibold text-white">
                                1
                            </div>
                            <div>
                                <div className="text-sm font-semibold text-slate-900">
                                    Release Details
                                </div>
                                <div className="mt-0.5 text-xs text-slate-500">
                                    Save the draft to continue with tracks, stores and review.
                                </div>
                            </div>
                        </div>
                    </div>

                    {(
                        <div className="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_360px]">
                            <div className="space-y-6">
                                <div>
                                    <h2 className="text-lg font-semibold text-slate-900">
                                        Release Information
                                    </h2>
                                    <p className="mt-1 text-sm text-slate-500">
                                        Enter the main metadata for this release.
                                    </p>
                                </div>

                                <div className="grid gap-5 md:grid-cols-2">
                                    <Field label="Release Title" required error={errors.title}>
                                        <input
                                            className={fieldClass}
                                            value={data.title}
                                            onChange={(e) =>
                                                setData('title', e.target.value)
                                            }
                                            placeholder="Enter release title"
                                        />
                                    </Field>

                                    <Field label="Release Type" required error={errors.release_type}>
                                        <select
                                            className={fieldClass}
                                            value={data.release_type}
                                            onChange={(e) =>
                                                setData('release_type', e.target.value)
                                            }
                                        >
                                            <option value="single">Single</option>
                                            <option value="ep">EP</option>
                                            <option value="album">Album</option>
                                        </select>
                                    </Field>

                                    <Field label="Primary Artist" required error={errors.artist_id}>
                                        <select
                                            className={fieldClass}
                                            value={data.artist_id}
                                            onChange={(e) => {
                                                const artistId = e.target.value;
                                                const artist = artists.find(
                                                    (item) =>
                                                        String(item.id) === artistId
                                                );

                                                setData((current) => ({
                                                    ...current,
                                                    artist_id: artistId,
                                                    primary_artist_name:
                                                        artist?.stage_name ?? '',
                                                }));
                                            }}
                                        >
                                            <option value="">Select artist</option>
                                            {artists.map((artist) => (
                                                <option
                                                    key={artist.id}
                                                    value={artist.id}
                                                >
                                                    {artist.stage_name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Featuring Artists">
                                        <input
                                            className={fieldClass}
                                            value={data.featuring_artist_name}
                                            onChange={(e) =>
                                                setData(
                                                    'featuring_artist_name',
                                                    e.target.value
                                                )
                                            }
                                            placeholder="Enter featuring artists"
                                        />
                                    </Field>

                                    <Field label="Label" required error={errors.label_id}>
                                        <select
                                            className={fieldClass}
                                            value={data.label_id}
                                            onChange={(e) =>
                                                setData('label_id', e.target.value)
                                            }
                                        >
                                            <option value="">Select label</option>
                                            {labels.map((label) => (
                                                <option key={label.id} value={label.id}>
                                                    {label.name}
                                                </option>
                                            ))}
                                        </select>
                                    </Field>

                                    <Field label="Digital Release Date">
                                        <input
                                            type="date"
                                            className={fieldClass}
                                            value={data.digital_release_date}
                                            onChange={(e) =>
                                                setData(
                                                    'digital_release_date',
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="UPC">
                                        <input
                                            className={fieldClass}
                                            value={data.upc}
                                            onChange={(e) =>
                                                setData('upc', e.target.value)
                                            }
                                            placeholder="Enter UPC code"
                                        />
                                    </Field>

                                    <Field label="Catalogue Number" required error={errors.catalog_number}>
                                        <input
                                            className={fieldClass}
                                            value={data.catalog_number}
                                            onChange={(e) =>
                                                setData(
                                                    'catalog_number',
                                                    e.target.value
                                                )
                                            }
                                            placeholder="MXT-000001"
                                        />
                                    </Field>

                                    <Field label="Language">
                                        <input
                                            className={fieldClass}
                                            value={data.language}
                                            onChange={(e) =>
                                                setData('language', e.target.value)
                                            }
                                            placeholder="Hindi"
                                        />
                                    </Field>

                                    <Field label="Primary Genre">
                                        <input
                                            className={fieldClass}
                                            value={data.primary_genre}
                                            onChange={(e) =>
                                                setData(
                                                    'primary_genre',
                                                    e.target.value
                                                )
                                            }
                                            placeholder="Devotional"
                                        />
                                    </Field>

                                    <Field label="Sub Genre">
                                        <input
                                            className={fieldClass}
                                            value={data.sub_genre}
                                            onChange={(e) =>
                                                setData('sub_genre', e.target.value)
                                            }
                                            placeholder="Optional"
                                        />
                                    </Field>

                                    <Field label="Copyright Year">
                                        <input
                                            className={fieldClass}
                                            value={data.copyright_year}
                                            onChange={(e) =>
                                                setData(
                                                    'copyright_year',
                                                    e.target.value
                                                )
                                            }
                                        />
                                    </Field>

                                    <Field label="Copyright Owner">
                                        <input
                                            className={fieldClass}
                                            value={data.copyright_owner}
                                            onChange={(e) =>
                                                setData(
                                                    'copyright_owner',
                                                    e.target.value
                                                )
                                            }
                                            placeholder="Rights owner"
                                        />
                                    </Field>

                                    <Field label="Phonographic Owner">
                                        <input
                                            className={fieldClass}
                                            value={data.phonographic_owner}
                                            onChange={(e) =>
                                                setData(
                                                    'phonographic_owner',
                                                    e.target.value
                                                )
                                            }
                                            placeholder="℗ owner"
                                        />
                                    </Field>
                                </div>

                                <Field label="Description">
                                    <textarea
                                        className={`${fieldClass} min-h-32 resize-y`}
                                        value={data.description}
                                        onChange={(e) =>
                                            setData('description', e.target.value)
                                        }
                                        placeholder="Write a short description about this release..."
                                        maxLength={1000}
                                    />
                                </Field>
                            </div>

                            <div className="space-y-5">
                                <div className="rounded-2xl border border-slate-200 p-5">
                                    <h3 className="font-semibold text-slate-900">
                                        Cover Art
                                    </h3>

                                    <label className="mt-4 flex min-h-64 cursor-pointer flex-col items-center justify-center rounded-2xl border-2 border-dashed border-violet-300 bg-violet-50/40 p-6 text-center hover:bg-violet-50">
                                        {artworkPreview ? (
                                            <img
                                                src={artworkPreview}
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
                                                    JPG or PNG, square artwork recommended
                                                </div>
                                            </>
                                        )}

                                        <input
                                            type="file"
                                            accept="image/jpeg,image/png"
                                            className="hidden"
                                            onChange={handleArtwork}
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
                                                selectedArtist?.stage_name ||
                                                data.primary_artist_name ||
                                                '—'
                                            }
                                        />
                                        <PreviewRow
                                            label="Label"
                                            value={selectedLabel?.name || '—'}
                                        />
                                        <PreviewRow
                                            label="Release Date"
                                            value={
                                                data.digital_release_date || '—'
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
                    )}
                </div>
            </div>
        </AdminLayout>
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

function PreviewRow({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-slate-500">{label}</span>
            <span className="truncate font-medium capitalize text-slate-900">
                {value}
            </span>
        </div>
    );
}

