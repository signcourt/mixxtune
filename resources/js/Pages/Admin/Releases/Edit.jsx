import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import AdminLayout from '@/Layouts/AdminLayout';
import TracksStep from './Partials/TracksStep';
import DistributionStep from './Partials/DistributionStep';
import ReviewStep from './Partials/ReviewStep';

const steps = [
    { id: 1, label: 'Release Details' },
    { id: 2, label: 'Tracks' },
    { id: 3, label: 'Stores & Distribution' },
    { id: 4, label: 'Review & Publish' },
];

const inputClass =
    'w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100';

export default function Edit({
    release,
    artists = [],
    labels = [],
    distributionStores = [],
}) {
    const [currentStep, setCurrentStep] = useState(
        Math.min(Math.max(Number(release.wizard_step ?? 1), 1), 4)
    );

    const [selectedTrackId, setSelectedTrackId] = useState(null);

    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        artist_id: release.artist_id ?? '',
        label_id: release.label_id ?? '',
        catalog_number: release.catalog_number ?? '',
        release_type: release.release_type ?? 'single',
        title: release.title ?? '',
        version: release.version ?? '',
        primary_artist_name: release.primary_artist_name ?? '',
        featuring_artist_name: release.featuring_artist_name ?? '',
        language: release.language ?? '',
        primary_genre: release.primary_genre ?? '',
        sub_genre: release.sub_genre ?? '',
        upc: release.upc ?? '',
        original_release_date: release.original_release_date
            ? String(release.original_release_date).slice(0, 10)
            : '',
        digital_release_date: release.digital_release_date
            ? String(release.digital_release_date).slice(0, 10)
            : '',
        copyright_owner: release.copyright_owner ?? '',
        copyright_year: release.copyright_year ?? '',
        phonographic_owner: release.phonographic_owner ?? '',
        phonographic_year: release.phonographic_year ?? '',
        status: release.status ?? 'draft',
        wizard_step: release.wizard_step ?? 1,
        completion_percentage: release.completion_percentage ?? 20,
        stores: release.stores ?? [],
        excluded_store_ids: release.excluded_store_ids ?? [],
        territories: release.territories ?? [],
        worldwide: release.worldwide ?? true,
        release_timezone: release.release_timezone ?? 'Asia/Kolkata',
        pre_order: release.pre_order ?? false,
    });

    const saveDetails = () => {
        patch(`/releases/${release.id}`, {
            preserveScroll: true,
        });
    };

    const goToStep = (step) => {
        setCurrentStep(step);
    };

    const nextStep = () => {
        const next = Math.min(currentStep + 1, 4);
        setCurrentStep(next);

        if (next > Number(data.wizard_step)) {
            setData((current) => ({
                ...current,
                wizard_step: next,
                completion_percentage: next * 25,
            }));
        }
    };

    const previousStep = () => {
        setCurrentStep((step) => Math.max(step - 1, 1));
    };

    const submitForReview = () => {
        if (!window.confirm('Submit this release for review?')) {
            return;
        }

        router.post(`/releases/${release.id}/submit`, {}, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Edit Release">
            <Head title={`Edit ${release.title}`} />

            <div className="space-y-6">
                {release.status === 'changes_requested' &&
                    release.review_notes && (
                        <div className="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4">
                            <div className="flex items-start gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-amber-100 font-bold text-amber-700">
                                    !
                                </div>

                                <div>
                                    <h3 className="font-semibold text-amber-900">
                                        Changes Requested
                                    </h3>

                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-amber-800">
                                        {release.review_notes}
                                    </p>

                                    <p className="mt-3 text-xs text-amber-700">
                                        Correct the requested information and submit the release again.
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}

                {release.status === 'rejected' &&
                    release.rejection_reason && (
                        <div className="rounded-2xl border border-red-300 bg-red-50 px-5 py-4">
                            <div className="flex items-start gap-3">
                                <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-red-100 font-bold text-red-700">
                                    !
                                </div>

                                <div>
                                    <h3 className="font-semibold text-red-900">
                                        Release Rejected
                                    </h3>

                                    <p className="mt-1 whitespace-pre-wrap text-sm leading-6 text-red-800">
                                        {release.rejection_reason}
                                    </p>
                                </div>
                            </div>
                        </div>
                    )}
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            Edit Release
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Complete metadata, tracks and distribution settings.
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        <Link
                            href="/releases"
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Back to Releases
                        </Link>

                        <button
                            type="button"
                            onClick={saveDetails}
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save as Draft'}
                        </button>
                    </div>
                </div>

                {errors.submission && (
                    <div className="rounded-2xl border border-red-200 bg-red-50 px-5 py-4 text-sm font-medium text-red-700">
                        {errors.submission}
                    </div>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid grid-cols-1 border-b border-slate-200 px-6 py-5 md:grid-cols-4">
                        {steps.map((step) => (
                            <button
                                key={step.id}
                                type="button"
                                onClick={() => goToStep(step.id)}
                                className="flex items-center gap-3 py-2 text-left"
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
                            </button>
                        ))}
                    </div>

                    {currentStep === 1 && (
                        <div className="p-6">
                            <div className="mb-6">
                                <h2 className="text-xl font-semibold text-slate-900">
                                    Release Information
                                </h2>
                                <p className="mt-1 text-sm text-slate-500">
                                    Update the main metadata for this release.
                                </p>
                            </div>

                            <div className="grid gap-5 md:grid-cols-2">
                                <Field label="Release Title" required error={errors.title}>
                                    <input
                                        className={inputClass}
                                        value={data.title}
                                        onChange={(e) =>
                                            setData('title', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Release Type" required error={errors.release_type}>
                                    <select
                                        className={inputClass}
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
                                        className={inputClass}
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
                                            <option key={artist.id} value={artist.id}>
                                                {artist.stage_name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>

                                <Field label="Featuring Artists">
                                    <input
                                        className={inputClass}
                                        value={data.featuring_artist_name}
                                        onChange={(e) =>
                                            setData(
                                                'featuring_artist_name',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field label="Label" required error={errors.label_id}>
                                    <select
                                        className={inputClass}
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

                                <Field label="Catalogue Number" required error={errors.catalog_number}>
                                    <input
                                        className={inputClass}
                                        value={data.catalog_number}
                                        onChange={(e) =>
                                            setData(
                                                'catalog_number',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field label="UPC" error={errors.upc}>
                                    <input
                                        className={inputClass}
                                        value={data.upc}
                                        onChange={(e) =>
                                            setData('upc', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Digital Release Date">
                                    <input
                                        type="date"
                                        className={inputClass}
                                        value={data.digital_release_date}
                                        onChange={(e) =>
                                            setData(
                                                'digital_release_date',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field label="Language">
                                    <input
                                        className={inputClass}
                                        value={data.language}
                                        onChange={(e) =>
                                            setData('language', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Primary Genre">
                                    <input
                                        className={inputClass}
                                        value={data.primary_genre}
                                        onChange={(e) =>
                                            setData(
                                                'primary_genre',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field label="Sub Genre">
                                    <input
                                        className={inputClass}
                                        value={data.sub_genre}
                                        onChange={(e) =>
                                            setData('sub_genre', e.target.value)
                                        }
                                    />
                                </Field>

                                <Field label="Copyright Year">
                                    <input
                                        className={inputClass}
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
                                        className={inputClass}
                                        value={data.copyright_owner}
                                        onChange={(e) =>
                                            setData(
                                                'copyright_owner',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>

                                <Field label="Phonographic Owner">
                                    <input
                                        className={inputClass}
                                        value={data.phonographic_owner}
                                        onChange={(e) =>
                                            setData(
                                                'phonographic_owner',
                                                e.target.value
                                            )
                                        }
                                    />
                                </Field>
                            </div>
                        </div>
                    )}

                    {currentStep === 2 && (
                        <TracksStep
                            release={release}
                            selectedTrackId={selectedTrackId}
                            onTrackOpened={() => setSelectedTrackId(null)}
                        />
                    )}

                    {currentStep === 3 && (
                        <DistributionStep
                            data={data}
                            setData={setData}
                            errors={errors}
                            availableStores={distributionStores}
                        />
                    )}

                    {currentStep === 4 && (
                        <ReviewStep
                            release={release}
                            data={data}
                            availableStores={distributionStores}
                            onEditStep={(step, trackId = null) => {
                                setSelectedTrackId(trackId);
                                setCurrentStep(step);

                                window.scrollTo({
                                    top: 0,
                                    behavior: 'smooth',
                                });
                            }}
                        />
                    )}

                    <div className="flex items-center justify-between border-t border-slate-200 px-6 py-5">
                        <button
                            type="button"
                            onClick={previousStep}
                            disabled={currentStep === 1}
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 disabled:opacity-40"
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
                                onClick={submitForReview}
                                className="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white hover:bg-emerald-700"
                            >
                                {release.status === 'changes_requested'
                                    ? 'Resubmit for Review'
                                    : 'Submit for Review'}
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
