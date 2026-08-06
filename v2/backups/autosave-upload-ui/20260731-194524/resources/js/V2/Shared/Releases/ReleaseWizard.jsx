import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import { useState } from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import ReleaseDetailsStep from '@/V2/Shared/Releases/Steps/ReleaseDetailsStep';
import TrackManager from '@/V2/Shared/Releases/Steps/TrackManager';
import DistributionStep from '@/V2/Shared/Releases/Steps/DistributionStep';
import ReviewStep from '@/V2/Shared/Releases/Steps/ReviewStep';

const steps = [
    { id: 1, label: 'Release Details' },
    { id: 2, label: 'Tracks' },
    { id: 3, label: 'Stores & Distribution' },
    { id: 4, label: 'Review & Publish' },
];

const dateValue = (value) => {
    if (!value) return '';

    return String(value).substring(0, 10);
};

export default function ReleaseWizard({
    role = 'artist',
    permissions = [],
    title = 'Create Release',
    release = null,
    artist = null,
    label = null,
    availableArtists = [],
    availableLabels = [],
    distributionStores = [],
}) {
    const [currentStep, setCurrentStep] = useState(() => {
        const step = Number(
            release?.wizard_step ?? 1
        );

        return Math.min(
            Math.max(step, 1),
            4
        );
    });

    const currentYear = String(
        new Date().getFullYear()
    );

    const {
        data,
        setData,
        post,
        processing,
        errors,
        transform,
    } = useForm({
        title: release?.title ?? '',
        release_type:
            release?.release_type ?? 'single',

        artist_id:
            release?.artist_id ??
            artist?.id ??
            '',

        label_id:
            release?.label_id ??
            label?.id ??
            '',

        primary_artist_name:
            release?.primary_artist_name ??
            artist?.stage_name ??
            '',

        featuring_artist_name:
            release?.featuring_artist_name ?? '',

        catalog_number:
            release?.catalog_number ?? '',

        upc: release?.upc ?? '',
        language: release?.language ?? '',

        primary_genre:
            release?.primary_genre ?? '',

        sub_genre:
            release?.sub_genre ?? '',

        original_release_date:
            dateValue(
                release?.original_release_date
            ),

        digital_release_date:
            dateValue(
                release?.digital_release_date
            ),

        copyright_owner:
            release?.copyright_owner ?? '',

        copyright_year:
            String(
                release?.copyright_year ??
                    currentYear
            ),

        phonographic_owner:
            release?.phonographic_owner ?? '',

        phonographic_year:
            String(
                release?.phonographic_year ??
                    currentYear
            ),

        artwork: null,

        artwork_preview:
            release?.artwork_path
                ? `/storage/${release.artwork_path}`
                : null,

        wizard_step:
            Number(release?.wizard_step ?? 1),

        completion_percentage:
            Number(
                release?.completion_percentage ??
                    20
            ),
    });

    const submitDraft = ({
        step = currentStep,
        completion = data.completion_percentage,
    } = {}) => {
        transform((formData) => ({
            ...formData,
            wizard_step: step,
            completion_percentage: completion,
            _method: release?.id
                ? 'patch'
                : undefined,
        }));

        if (release?.id) {
            post(
                `/v2/releases/${release.id}`,
                {
                    forceFormData: true,
                    preserveScroll: true,

                    onSuccess: () => {
                        setCurrentStep(
                            Math.min(
                                Math.max(
                                    Number(step),
                                    1
                                ),
                                4
                            )
                        );
                    },
                }
            );

            return;
        }

        post('/v2/releases', {
            forceFormData: true,
            preserveScroll: true,
        });
    };

    const nextStep = () => {
        if (currentStep === 1) {
            submitDraft({
                step: 2,
                completion: 50,
            });

            return;
        }

        setCurrentStep((step) =>
            Math.min(step + 1, 4)
        );
    };

    const previousStep = () => {
        setCurrentStep((step) =>
            Math.max(step - 1, 1)
        );
    };

    return (
        <PanelLayout
            role={role}
            title={title}
            subtitle="Shared Release Wizard V2"
        >
            <Head title={title} />

            <div className="space-y-6">
                <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                    <div>
                        <h1 className="text-3xl font-bold text-slate-900">
                            {title}
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            One shared release flow for
                            Artist, Label, Admin and Super
                            Admin.
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-3">
                        <Link
                            href="/v2/dashboard"
                            className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            Cancel
                        </Link>

                        <button
                            type="button"
                            onClick={() =>
                                submitDraft()
                            }
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : 'Save Draft'}
                        </button>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-3 border-b border-slate-200 px-6 py-5 md:grid-cols-4">
                        {steps.map((step) => (
                            <button
                                key={step.id}
                                type="button"
                                onClick={() =>
                                    setCurrentStep(
                                        step.id
                                    )
                                }
                                className="flex items-center gap-3 text-left"
                            >
                                <div
                                    className={`flex h-10 w-10 items-center justify-center rounded-full text-sm font-semibold ${
                                        currentStep ===
                                        step.id
                                            ? 'bg-violet-600 text-white'
                                            : currentStep >
                                                step.id
                                              ? 'bg-emerald-100 text-emerald-700'
                                              : 'bg-slate-100 text-slate-500'
                                    }`}
                                >
                                    {currentStep >
                                    step.id
                                        ? '✓'
                                        : step.id}
                                </div>

                                <span
                                    className={`text-sm font-semibold ${
                                        currentStep ===
                                        step.id
                                            ? 'text-slate-900'
                                            : 'text-slate-500'
                                    }`}
                                >
                                    {step.label}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="min-h-[420px] p-6">
                        {currentStep === 1 && (
                            <ReleaseDetailsStep
                                role={role}
                                data={data}
                                setData={setData}
                                errors={errors}
                                artist={artist}
                                label={label}
                                availableArtists={
                                    availableArtists
                                }
                                availableLabels={
                                    availableLabels
                                }
                            />
                        )}

                        {currentStep === 2 && (
                            release?.id ? (
                                <TrackManager
                                    release={release}
                                />
                            ) : (
                                <StepPlaceholder
                                    title="Tracks"
                                    description="Save release details first to add tracks."
                                />
                            )
                        )}

                        {currentStep === 3 && (
                            release?.id ? (
                                <DistributionStep
                                    release={release}
                                    distributionStores={
                                        distributionStores
                                    }
                                />
                            ) : (
                                <StepPlaceholder
                                    title="Stores & Distribution"
                                    description="Save release details before selecting stores."
                                />
                            )
                        )}

                        {currentStep === 4 && (
                            release?.id ? (
                                <ReviewStep
                                    release={release}
                                    artist={artist}
                                    label={label}
                                    distributionStores={
                                        distributionStores
                                    }
                                    onEditStep={
                                        setCurrentStep
                                    }
                                />
                            ) : (
                                <StepPlaceholder
                                    title="Review & Publish"
                                    description="Save the release before reviewing it."
                                />
                            )
                        )}
                    </div>

                    <div className="flex items-center justify-between border-t border-slate-200 px-6 py-5">
                        <button
                            type="button"
                            onClick={previousStep}
                            disabled={
                                currentStep === 1
                            }
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700 disabled:cursor-not-allowed disabled:opacity-40"
                        >
                            Back
                        </button>

                        {currentStep < 4 ? (
                            <button
                                type="button"
                                onClick={nextStep}
                                disabled={processing}
                                className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                            >
                                {processing
                                    ? 'Saving...'
                                    : 'Next →'}
                            </button>
                        ) : (
                            <button
                                type="button"
                                disabled
                                className="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white opacity-50"
                            >
                                Submit for Review
                            </button>
                        )}
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}

function StepPlaceholder({
    title,
    description,
}) {
    return (
        <div className="flex min-h-[360px] items-center justify-center">
            <div className="max-w-xl text-center">
                <div className="text-4xl">
                    ♫
                </div>

                <h2 className="mt-4 text-2xl font-bold text-slate-900">
                    {title}
                </h2>

                <p className="mt-2 text-sm leading-6 text-slate-500">
                    {description}
                </p>
            </div>
        </div>
    );
}
