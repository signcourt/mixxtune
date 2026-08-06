import {
    Head,
    Link,
    router,
    useForm,
} from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import ReleaseWizardSidebar from '@/V2/Shared/Releases/V3/ReleaseWizardSidebar';
import ReleaseStepHeader from '@/V2/Shared/Releases/V4/ReleaseStepHeader';
import ReleaseHelpfulTip from '@/V2/Shared/Releases/V4/ReleaseHelpfulTip';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import ReleaseDetailsStep from '@/V2/Shared/Releases/Steps/ReleaseDetailsStep';
import TrackManager from '@/V2/Shared/Releases/Steps/TrackManager';
import DistributionStep from '@/V2/Shared/Releases/Steps/DistributionStep';
import ReviewStep from '@/V2/Shared/Releases/Steps/ReviewStep';

import {
    MascotStage,
    getMascotState,
} from '@/V2/Shared/Mascot';

const RELEASES_BASE_PATH =
    typeof window !== 'undefined'
    && window.location.pathname.startsWith('/artist')
        ? '/artist/releases'
        : '/v2/releases';

const wizardSteps = [
    {
        id: 1,
        label: 'Release Details',
    },
    {
        id: 2,
        label: 'Tracks',
    },
    {
        id: 3,
        label: 'Stores',
    },
    {
        id: 4,
        label: 'Territory',
    },
    {
        id: 5,
        label: 'Review & Publish',
    },
];

const dateValue = (value) => {
    if (!value) return '';

    return String(value).substring(0, 10);
};

const generateCatalogNumber = () => {
    const now = new Date();

    const year = now.getFullYear();

    const stamp = String(
        now.getTime()
    ).slice(-7);

    return `MXT-${year}-${stamp}`;
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
            5
        );
    });

    const currentYear = String(
        new Date().getFullYear()
    );

    const {
        data,
        setData,
        post,
        patch,
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
            (
                availableArtists.length === 1
                    ? availableArtists[0].id
                    : ''
            ),

        label_id:
            release?.label_id ??
            label?.id ??
            '',

        primary_artist_name:
            release?.primary_artist_name ??
            artist?.stage_name ??
            (
                availableArtists.length === 1
                    ? (
                        availableArtists[0].stage_name ??
                        availableArtists[0].legal_name ??
                        availableArtists[0].name ??
                        ''
                    )
                    : ''
            ),

        primary_artists:
            Array.isArray(
                release?.primary_artists
            ) &&
            release.primary_artists.length > 0
                ? release.primary_artists
                : [
                      {
                          name:
                              release?.primary_artist_name ??
                              artist?.stage_name ??
                              (
                                  availableArtists.length === 1
                                      ? (
                                          availableArtists[0].stage_name ??
                                          availableArtists[0].legal_name ??
                                          availableArtists[0].name ??
                                          ''
                                      )
                                      : ''
                              ),
                          spotify_url: '',
                          apple_music_url: '',
                          youtube_topic_url: '',
                      },
                  ],

        featuring_artist_name:
            release?.featuring_artist_name ?? '',

        featuring_artists:
            Array.isArray(
                release?.featuring_artists
            )
                ? release.featuring_artists
                : (
                      release?.featuring_artist_name
                          ? [
                                {
                                    name:
                                        release.featuring_artist_name,
                                },
                            ]
                          : []
                  ),

        catalog_number:
            release?.catalog_number ??
            generateCatalogNumber(),

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

    const [saveState, setSaveState] = useState(
        release?.id ? 'saved' : 'manual'
    );

    const [
        mascotEvent,
        setMascotEvent,
    ] = useState(null);

    const mascotEventTimer = useRef(null);

    const handleMascotEvent = (
        eventName,
        duration = 1800
    ) => {
        if (mascotEventTimer.current) {
            window.clearTimeout(
                mascotEventTimer.current
            );
        }

        setMascotEvent(eventName);

        if (
            eventName === 'track-uploading' ||
            eventName ===
                'distribution-saving' ||
            eventName === 'submitting'
        ) {
            return;
        }

        mascotEventTimer.current =
            window.setTimeout(() => {
                setMascotEvent(null);
            }, duration);
    };

    /*
     * Release Wizard का canonical route अभी /v2/releases है।
     * Artist role को /artist/releases पर redirect नहीं करना,
     * क्योंकि edit और wizard routes V2 namespace में registered हैं.
     */

    const autosaveTimer = useRef(null);
    const skipInitialAutosave = useRef(true);

    const autosaveSignature = JSON.stringify({
        title: data.title,
        release_type: data.release_type,
        artist_id: data.artist_id,
        label_id: data.label_id,
        primary_artist_name:
            data.primary_artist_name,
        featuring_artist_name:
            data.featuring_artist_name,
        catalog_number: data.catalog_number,
        upc: data.upc,
        language: data.language,
        primary_genre: data.primary_genre,
        sub_genre: data.sub_genre,
        original_release_date:
            data.original_release_date,
        digital_release_date:
            data.digital_release_date,
        copyright_owner:
            data.copyright_owner,
        copyright_year:
            data.copyright_year,
        phonographic_owner:
            data.phonographic_owner,
        phonographic_year:
            data.phonographic_year,
    });

    useEffect(() => {
        if (
            !release?.id ||
            currentStep !== 1
        ) {
            return undefined;
        }

        if (skipInitialAutosave.current) {
            skipInitialAutosave.current = false;
            return undefined;
        }

        if (
            !data.title?.trim() ||
            !data.catalog_number?.trim() ||
            !data.digital_release_date
        ) {
            setSaveState('unsaved');
            return undefined;
        }

        setSaveState('unsaved');

        window.clearTimeout(
            autosaveTimer.current
        );

        autosaveTimer.current =
            window.setTimeout(() => {
                setSaveState('saving');

                transform((formData) => ({
                    ...formData,
                    wizard_step:
                        currentStep,
                    completion_percentage:
                        Math.max(
                            Number(
                                formData
                                    .completion_percentage
                                    ?? 20
                            ),
                            20
                        ),
                    _method: 'patch',
                }));

                post(
                    `${RELEASES_BASE_PATH}/${release.id}`,
                    {
                        forceFormData: true,
                        preserveScroll: true,
                        preserveState: true,

                        onSuccess: () => {
                            setSaveState(
                                'saved'
                            );
                        },

                        onError: () => {
                            setSaveState(
                                'error'
                            );
                        },
                    }
                );
            }, 1500);

        return () => {
            window.clearTimeout(
                autosaveTimer.current
            );
        };
    }, [
        autosaveSignature,
        release?.id,
        currentStep,
    ]);


    const submitDraft = ({
        step = currentStep,
        completion =
            data.completion_percentage,
        onSuccess = null,
    } = {}) => {
        setSaveState('saving');

        const payload = {
            ...data,
            save_mode: 'draft',
            wizard_step: step,
            completion_percentage:
                completion,
        };

        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,

            onSuccess: () => {
                setSaveState('saved');

                if (
                    typeof onSuccess ===
                    'function'
                ) {
                    onSuccess();
                }
            },

            onError: (submitErrors) => {
                setSaveState('error');

                const message =
                    Object.values(
                        submitErrors ?? {}
                    )[0];

                if (message) {
                    window.alert(message);
                }
            },
        };

        if (release?.id) {
            router.post(
                `${RELEASES_BASE_PATH}/${release.id}`,
                {
                    ...payload,
                    _method: 'patch',
                },
                requestOptions
            );

            return;
        }

        router.post(
            RELEASES_BASE_PATH,
            payload,
            requestOptions
        );
    };

    const submitForReview = () => {
        if (!release?.id) {
            setSaveState('error');

            window.alert(
                'Please save the release draft before submitting it for review.'
            );

            return;
        }

        if (
            !window.confirm(
                'Submit this release for review? Editing will be locked after submission.'
            )
        ) {
            return;
        }

        setSaveState('saving');

        /*
         * Submit endpoint accepts POST only.
         * router.post is intentionally used here so draft
         * update data such as _method=patch cannot leak
         * into the submission request.
         */
        router.post(
            `${RELEASES_BASE_PATH}/${release.id}/submit`,
            {},
            {
                preserveScroll: true,
                preserveState: false,

                onSuccess: () => {
                    setSaveState('saved');
                },

                onError: (errors) => {
                    setSaveState('error');

                    const message =
                        Object.values(
                            errors ?? {}
                        )[0] ||
                        'Release could not be submitted.';

                    window.alert(message);
                },
            }
        );
    };


    const saveDraft = () => {
        setSaveState('saving');

        const payload = {
            ...data,
            save_mode: 'draft',
            wizard_step: currentStep,
            completion_percentage:
                Math.max(
                    Number(
                        data.completion_percentage
                        ?? 20
                    ),
                    currentStep * 20
                ),
        };

        const requestOptions = {
            forceFormData: true,
            preserveScroll: true,
            preserveState: true,

            onSuccess: () => {
                setSaveState('saved');
            },

            onError: (saveErrors) => {
                setSaveState('error');

                const message =
                    Object.values(
                        saveErrors ?? {}
                    )[0];

                if (message) {
                    window.alert(message);
                }
            },
        };

        if (release?.id) {
            router.post(
                `${RELEASES_BASE_PATH}/${release.id}`,
                {
                    ...payload,
                    _method: 'patch',
                },
                requestOptions
            );

            return;
        }

        router.post(
            RELEASES_BASE_PATH,
            payload,
            requestOptions
        );
    };

    const nextStep = () => {
        if (currentStep === 1) {
            submitDraft({
                step: 2,
                completion: 50,

                onSuccess: () => {
                    setCurrentStep(2);
                },
            });

            return;
        }

        setCurrentStep((step) =>
            Math.min(step + 1, 5)
        );
    };

    const previousStep = () => {
        setCurrentStep((step) =>
            Math.max(step - 1, 1)
        );
    };

    const mascotState = getMascotState({
        currentStep,
        saveState,
        processing,
        mascotEvent,
    });

    return (
        <PanelLayout
            role={role}
            title={title}
        >
            <Head title={title} />

            <div className="mixx-v3-release-shell">
                <ReleaseWizardSidebar
                    currentStep={currentStep}
                    onStepChange={(step) => {
                        setMascotEvent(null);
                        setCurrentStep(step);
                    }}
                    release={release}
                />

                <div className="mixx-v3-main min-w-0 space-y-6">
                    <div className="sticky top-0 z-40 overflow-hidden rounded-3xl border border-slate-200 bg-white/95 shadow-lg shadow-slate-200/50 backdrop-blur-xl">
                    <div className="h-1.5 bg-slate-100">
                        <div
                            className={`h-full rounded-r-full bg-gradient-to-r from-violet-600 via-purple-500 to-blue-500 ${
saveState === 'saving'
? 'transition-all duration-500'
: ''
}` }
                            style={{
                                width: `${Math.max(
                                    20,
                                    Math.min(
                                        currentStep * 20,
                                        100
                                    )
                                )}%`,
                            }}
                        />
                    </div>

                    <div className="flex flex-col gap-4 px-5 py-4 xl:flex-row xl:items-center xl:justify-between">
                        <div className="flex min-w-0 items-center gap-4">
                            <Link
                                href={RELEASES_BASE_PATH}
                                className="inline-flex h-11 w-11 shrink-0 items-center justify-center rounded-xl border border-slate-200 bg-white text-lg font-bold text-slate-600 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                                title="Back to releases"
                            >
                                ←
                            </Link>

                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-3">
                                    <h1 className="truncate text-lg font-black text-slate-950 sm:text-xl">
                                        {release?.id
                                            ? 'Edit Release'
                                            : 'Create Release'}
                                    </h1>

                                    <SaveStatus
                                        state={saveState}
                                        hasRelease={Boolean(
                                            release?.id
                                        )}
                                    />
                                </div>

                                <p className="mt-1 text-xs font-medium text-slate-500 sm:text-sm">
                                    Step {currentStep} of 5
                                    {' · '}
                                    {wizardSteps[
                                        currentStep - 1
                                    ]?.label}
                                    {' · '}
                                    {Math.min(
                                        currentStep * 20,
                                        100
                                    )}% complete
                                </p>
                            </div>
                        </div>

                        <div className="overflow-x-auto pb-1 xl:pb-0">
                            <WizardTopActions
                                currentStep={currentStep}
                                processing={processing}
                                onBack={previousStep}
                                onSaveDraft={saveDraft}
                                onNext={nextStep}
                                onSubmit={submitForReview}
                            />
                        </div>
                    </div>
                </div>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="grid gap-3 border-b border-slate-200 px-4 py-5 sm:grid-cols-2 xl:grid-cols-5 xl:px-6">
                        {wizardSteps.map((step) => (
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
                        <ReleaseStepHeader
                            currentStep={currentStep}
                            saveState={saveState}
                            processing={processing}
                            onSave={() => {
                                saveDraft();
                            }}
                        />

                        <div className="mixx-v4-step-grid">
                            <div className="mixx-v4-step-main">
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
                                    onMascotEvent={
                                        handleMascotEvent
                                    }
                                    onSaved={() => {
                                        setMascotEvent(null);
                                        setCurrentStep(3);
                                    }}
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
                                    initialSection="stores"
                                    onMascotEvent={
                                        handleMascotEvent
                                    }
                                    onSaved={() => {
                                        setMascotEvent(null);
                                        setCurrentStep(4);
                                    }}
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
                                <DistributionStep
                                    release={release}
                                    distributionStores={
                                        distributionStores
                                    }
                                    initialSection="territory"
                                    onMascotEvent={
                                        handleMascotEvent
                                    }
                                    onSaved={() => {
                                        setMascotEvent(null);
                                        setCurrentStep(5);
                                    }}
                                />
                            ) : (
                                <StepPlaceholder
                                    title="Territory"
                                    description="Save the release before configuring territories."
                                />
                            )
                        )}

                        {currentStep === 5 && (
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
                                    onMascotEvent={
                                        handleMascotEvent
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

                            {currentStep < 5 && (
                                <ReleaseHelpfulTip
                                    currentStep={
                                        currentStep
                                    }
                                />
                            )}
                        </div>
                    </div>

                </section>
                </div>

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


function WizardTopActions({
    currentStep,
    processing = false,
    onBack,
    onSaveDraft,
    onNext,
    onSubmit,
}) {
    const isLastStep =
        currentStep === 5;

    return (
        <div className="flex items-center justify-end gap-3">
            {currentStep > 1 && (
                <button
                    type="button"
                    onClick={onBack}
                    disabled={processing}
                    className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Back
                </button>
            )}

            <button
                type="button"
                onClick={onSaveDraft}
                disabled={processing}
                className="rounded-xl border border-violet-300 bg-violet-50 px-5 py-3 text-sm font-semibold text-violet-700 transition hover:bg-violet-100 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {processing
                    ? 'Saving...'
                    : 'Save Draft'}
            </button>

            {isLastStep ? (
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={processing}
                    className="rounded-xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing
                        ? 'Submitting...'
                        : 'Submit for Review'}
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onNext}
                    disabled={processing}
                    className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Next →
                </button>
            )}
        </div>
    );
}

function SaveStatus({
    state,
    hasRelease,
}) {
    if (!hasRelease) {
        return null;
    }

    const settings = {
        saved: {
            text: 'All changes saved',
            className:
                'bg-emerald-50 text-emerald-700',
        },

        saving: {
            text: 'Auto-saving...',
            className:
                'bg-blue-50 text-blue-700',
        },

        unsaved: {
            text: 'Unsaved changes',
            className:
                'bg-amber-50 text-amber-700',
        },

        error: {
            text: 'Save failed',
            className:
                'bg-red-50 text-red-700',
        },

        manual: {
            text: 'Not saved yet',
            className:
                'bg-slate-100 text-slate-600',
        },
    };

    const current =
        settings[state] ??
        settings.manual;

    return (
        <span
            className={`rounded-full px-3 py-1.5 text-xs font-semibold ${current.className}`}
        >
            {current.text}
        </span>
    );
}
