import { Head, Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';

const steps = [
    { id: 1, label: 'Release Details' },
    { id: 2, label: 'Tracks' },
    { id: 3, label: 'Stores & Distribution' },
    { id: 4, label: 'Review & Publish' },
];

const fieldClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

export default function Create({ artists = [], labels = [] }) {
    const [currentStep, setCurrentStep] = useState(1);
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

    const saveDraft = () => {
        post('/releases', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const nextStep = () => {
        if (currentStep === 1) {
            transform((formData) => ({
                ...formData,
                wizard_step: 2,
                completion_percentage: 50,
                status: 'draft',
            }));

            post('/releases', {
                forceFormData: true,
                preserveScroll: true,
            });

            return;
        }

        setCurrentStep((step) => Math.min(step + 1, 4));
    };

    const previousStep = () => {
        setCurrentStep((step) => Math.max(step - 1, 1));
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
                            Add release details, tracks, stores and submit for review.
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
                            onClick={saveDraft}
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save as Draft'}
                        </button>
                    </div>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid grid-cols-1 border-b border-slate-200 px-6 py-5 md:grid-cols-4">
                        {steps.map((step) => (
                            <div
                                key={step.id}
                                className="relative flex items-center gap-3 py-2"
                            >
                                <div
                                    className={`flex h-9 w-9 items-center justify-center rounded-full text-sm font-semibold ${
                                        currentStep === step.id
                                            ? 'bg-violet-600 text-white'
                                            : currentStep > step.id
                                              ? 'bg-emerald-100 text-emerald-700'
                                              : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {currentStep > step.id ? '✓' : step.id}
                                </div>

                                <span
                                    className={`text-sm font-semibold ${
                                        currentStep === step.id
                                            ? 'text-slate-900'
                                            : 'text-slate-500'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </div>
                        ))}
                    </div>

                    {currentStep === 1 && (
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

                    {currentStep === 2 && (
                        <PlaceholderStep
                            title="Tracks"
                            text="Track creation, audio upload, contributors, ISRC and royalty splits will be added here."
                        />
                    )}

                    {currentStep === 3 && (
                        <PlaceholderStep
                            title="Stores & Distribution"
                            text="Select DSPs, countries, release territories and delivery options here."
                        />
                    )}

                    {currentStep === 4 && (
                        <PlaceholderStep
                            title="Review & Publish"
                            text="Review metadata, tracks and stores before submitting the release."
                        />
                    )}

                    <div className="flex items-center justify-between border-t border-slate-200 px-6 py-5">
                        <button
                            type="button"
                            onClick={previousStep}
                            disabled={currentStep === 1}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Back
                        </button>

                        {currentStep < 4 ? (
                            <button
                                type="button"
                                onClick={nextStep}
                                className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white hover:bg-violet-700"
                            >
                                Next →
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                Submit for Review
                            </button>
                        )}
                    </div>
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

function PlaceholderStep({ title, text }) {
    return (
        <div className="px-6 py-20 text-center">
            <div className="text-4xl">♫</div>
            <h2 className="mt-4 text-xl font-semibold text-slate-900">
                {title}
            </h2>
            <p className="mx-auto mt-2 max-w-xl text-sm text-slate-500">
                {text}
            </p>
        </div>
    );
}
