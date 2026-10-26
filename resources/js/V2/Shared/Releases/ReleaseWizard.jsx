import {
    Head,
    Link,
    router,
    useForm,
    usePage,
} from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

import ReleaseCoverArtCard from '@/V2/Shared/Releases/V4/ReleaseCoverArtCard';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import ReleaseDetailsStep from '@/V2/Shared/Releases/Steps/ReleaseDetailsStep';
import TrackManager, { trackMissingLabels } from '@/V2/Shared/Releases/Steps/TrackManager';
import TerritoryStep from '@/V2/Shared/Releases/Steps/TerritoryStep';
import ReviewStep, { reviewChecks } from '@/V2/Shared/Releases/Steps/ReviewStep';


const RELEASES_BASE_PATH =
    typeof window !== 'undefined'
    && /^\/artist(\/|$)/.test(window.location.pathname)
        ? '/artist/releases'
        : '/releases';

/* W64: 4 steps - the Stores step was removed (all active stores are used) */
const wizardSteps = [
    {
        id: 1,
        label: 'Release details',
    },
    {
        id: 2,
        label: 'Tracks',
    },
    {
        id: 3,
        label: 'Territories',
    },
    {
        id: 4,
        label: 'Review & Publish',
    },
];
const LAST_STEP = wizardSteps.length;

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
    artistNameSuggestions = [],
    distributionStores = [],
    adminEdit = false,
}) {
    const pageErrors = usePage().props?.errors ?? {};

    const wizardStorageKey =
        typeof window !== 'undefined'
            ? `mixxtune-release-wizard4-step-${
                  release?.id ?? window.location.pathname
              }`
            : null;

    const [currentStep, setCurrentStep] = useState(() => {
        if (
            typeof window !== 'undefined'
            && wizardStorageKey
        ) {
            const rememberedStep =
                window.sessionStorage.getItem(
                    wizardStorageKey
                );

            if (rememberedStep) {
                const parsedStep =
                    Number(rememberedStep);

                if (
                    Number.isInteger(parsedStep)
                    && parsedStep >= 1
                    && parsedStep <= LAST_STEP
                ) {
                    return parsedStep;
                }
            }
        }

        const step = Number(
            release?.wizard_step ?? 1
        );

        return Math.min(
            Math.max(step, 1),
            LAST_STEP
        );
    });

    useEffect(() => {
        if (
            typeof window === 'undefined'
            || !wizardStorageKey
        ) {
            return;
        }

        try {
            window.sessionStorage.setItem(
                wizardStorageKey,
                String(currentStep)
            );
        } catch (e) {
            /* storage blocked */
        }
    }, [
        currentStep,
        wizardStorageKey,
    ]);

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
            (
                availableLabels.length === 1
                    ? availableLabels[0].id
                    : ''
            ),

        primary_artist_name:
            release?.primary_artist_name ??
            (
                role === 'artist'
                    ? (
                        artist?.stage_name ??
                        artist?.legal_name ??
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
                              (
                                  role === 'artist'
                                      ? (
                                          artist?.stage_name ??
                                          artist?.legal_name ??
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
        generate_upc:
            release?.upc
                ? false
                : true,
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
     * Release Wizard का canonical route अभी /releases है।
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
        /*
         * MIXXTUNE_SUPER_ADMIN_FULL_EDIT
         * No autosave while a Super Admin edits a locked release:
         * every save is deliberate and audit-logged.
         */
        if (
            adminEdit ||
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
        /* W119: the top-bar Submit follows the same checklist as the one beside the review */
        if (release?.id && !reviewChecks({ release, artist, label, distributionStores }).allPassed) {
            window.alert('Complete all items of the checklist before submitting.');

            return;
        }

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
                    Math.round(currentStep * 100 / LAST_STEP)
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

    /* W119: step 3 "Save & review →" runs the territory save (same request as before) */
    const territory = useRef(null);

    const nextStep = () => {
        if (currentStep === 3 && territory.current?.save) {
            territory.current.save();

            return;
        }

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
            Math.min(step + 1, LAST_STEP)
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
        >
            <Head title={title} />

            {/* W97 MIXXTUNE_NEW_RELEASE_UI: steps on top, form + side column, actions at the bottom (N1-N6) */}
            <div className="mx-auto w-full max-w-[1280px] space-y-5">
                <div className="sticky top-0 z-40 rounded-2xl border border-slate-200 bg-white/95 shadow-sm backdrop-blur-xl">
                    <div className="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-5">
                        <Link
                            href={RELEASES_BASE_PATH}
                            className="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-base font-bold text-slate-700 transition hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                            title="Back to releases"
                            aria-label="Back to releases"
                        >
                            ←
                        </Link>

                        <div className="min-w-0 flex-[1_1_200px]">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate text-base font-bold text-slate-950">
                                    {data.title?.trim() || release?.title || (release?.id ? 'Edit release' : 'New release')}
                                </h1>
                                <SaveStatus state={saveState} hasRelease={Boolean(release?.id)} />
                            </div>
                            <p className="mt-0.5 text-xs text-slate-500">
                                {typeLabel(data.release_type)}
                                {currentStep >= 3 && Array.isArray(release?.tracks) ? ` · ${release.tracks.length} track${release.tracks.length === 1 ? '' : 's'}` : ''}
                                {' · '}
                                {adminEdit ? statusLabel(release?.status) : 'Draft'}
                                {' · '}
                                Step {currentStep} of {LAST_STEP}
                            </p>
                        </div>

                        <WizardTopActions
                            currentStep={currentStep}
                            adminEdit={adminEdit}
                            releaseId={release?.id}
                            processing={processing}
                            onBack={null}
                            onSaveDraft={saveDraft}
                            onNext={nextStep}
                            onSubmit={submitForReview}
                        />
                    </div>

                    <StepNav
                        currentStep={currentStep}
                        canJump={Boolean(release?.id)}
                        onStepChange={(step) => {
                            setMascotEvent(null);
                            setCurrentStep(step);
                        }}
                    />
                </div>

                {adminEdit && (
                    <div className="rounded-2xl border border-amber-300 bg-amber-50 px-5 py-4">
                        <div className="font-bold text-amber-900">
                            Super Admin edit · this release is{' '}
                            <span className="capitalize">
                                {String(release?.status ?? '').replace(/_/g, ' ')}
                            </span>
                        </div>
                        <p className="mt-1 text-sm leading-6 text-amber-800">
                            You can change details, poster, UPC, tracks, ISRC, audio files and territories (stores: Distribution page).
                            Each save is written to the audit log and shows in All Catalogue at once.
                            The status does not change, and nothing is re-sent to the stores automatically.
                        </p>
                        {Object.keys(pageErrors).length > 0 && (
                            <ul className="mt-3 list-disc space-y-1 rounded-xl bg-rose-50 px-8 py-3 text-sm font-semibold text-rose-700 ring-1 ring-rose-200">
                                {Object.entries(pageErrors).map(([field, message]) => (
                                    <li key={field}>
                                        Not saved: {String(message)}
                                    </li>
                                ))}
                            </ul>
                        )}
                    </div>
                )}

                <div className="flex flex-wrap items-start gap-5">
                    <main className="min-w-0 flex-[999_1_640px] space-y-4">
                        {currentStep === 1 && (
                            <ReleaseDetailsStep
                                role={role}
                                data={data}
                                setData={setData}
                                errors={errors}
                                artist={artist}
                                label={label}
                                availableArtists={availableArtists}
                                availableLabels={availableLabels}
                                artistNameSuggestions={artistNameSuggestions}
                            />
                        )}

                        {currentStep > 1 && (
                            <>
                                {currentStep === 2 && (
                                    release?.id ? (
                                        <TrackManager
                                            release={release}
                                            onMascotEvent={handleMascotEvent}
                                            onSaved={() => {
                                                /* W119: stay on the track list after saving a track */
                                                setMascotEvent(null);
                                            }}
                                        />
                                    ) : (
                                        <StepPlaceholder title="Tracks" description="Save release details first to add tracks." />
                                    )
                                )}

                                {currentStep === 3 && (
                                    release?.id ? (
                                        <TerritoryStep
                                            release={release}
                                            distributionStores={distributionStores}
                                            onMascotEvent={handleMascotEvent}
                                            registerSave={(api) => {
                                                territory.current = api;
                                            }}
                                            onSaved={() => {
                                                setMascotEvent(null);
                                                setCurrentStep(4);
                                            }}
                                        />
                                    ) : (
                                        <StepPlaceholder title="Territories" description="Save the release before selecting territories." />
                                    )
                                )}

                                {currentStep === LAST_STEP && (
                                    release?.id ? (
                                        <ReviewStep
                                            release={release}
                                            adminEdit={adminEdit}
                                            artist={artist}
                                            label={label}
                                            distributionStores={distributionStores}
                                            onEditStep={setCurrentStep}
                                            onMascotEvent={handleMascotEvent}
                                        />
                                    ) : (
                                        <StepPlaceholder title="Review & Publish" description="Save the release before reviewing it." />
                                    )
                                )}
                            </>
                        )}
                    </main>

                    {(currentStep === 1 || currentStep === 2) && (
                        <aside className="flex min-w-0 max-w-full flex-[1_1_300px] flex-col gap-4 lg:max-w-[360px]">
                            {currentStep === 1 ? (
                                <>
                                    <ReleaseCoverArtCard
                                        data={data}
                                        setData={setData}
                                        error={errors.artwork ?? pageErrors.artwork}
                                        adminEdit={adminEdit}
                                    />
                                    <StepChecklist items={detailsChecklist(data)} />
                                </>
                            ) : (
                                <>
                                    <StepChecklist items={tracksChecklist(release)} />
                                    <section className="rounded-2xl border border-slate-200 bg-white p-5 text-xs leading-relaxed text-slate-600 shadow-sm">
                                        WAV only, 16 or 24-bit, 44.1 or 48 kHz. Fill the credits (lyricist and composer) for every song - stores in India need them.
                                    </section>
                                </>
                            )}
                        </aside>
                    )}
                </div>

                <div className="flex flex-wrap items-center justify-between gap-3 pb-8">
                    <div>
                        {currentStep > 1 && (
                            <button
                                type="button"
                                onClick={previousStep}
                                disabled={processing}
                                className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:opacity-50"
                            >
                                ← Back
                            </button>
                        )}
                    </div>
                    <WizardTopActions
                        currentStep={currentStep}
                        adminEdit={adminEdit}
                        releaseId={release?.id}
                        processing={processing}
                        onBack={null}
                        onSaveDraft={saveDraft}
                        onNext={nextStep}
                        onSubmit={submitForReview}
                        nextText={['', 'Continue to tracks →', 'Continue to territories →', 'Save & review →'][currentStep] || null}
                    />
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
    adminEdit = false,
    releaseId = null,
    processing = false,
    onBack,
    onSaveDraft,
    onNext,
    onSubmit,
    nextText = null,
}) {
    const isLastStep =
        currentStep === LAST_STEP;

    return (
        <div className="flex flex-wrap items-center justify-end gap-2">
            {currentStep > 1 && onBack && (
                <button
                    type="button"
                    onClick={onBack}
                    disabled={processing}
                    className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    Back
                </button>
            )}

            <button
                type="button"
                onClick={onSaveDraft}
                disabled={processing}
                className="h-10 rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-800 transition hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-50"
            >
                {processing
                    ? 'Saving...'
                    : adminEdit
                      ? 'Save Changes'
                      : 'Save draft'}
            </button>

            {isLastStep && adminEdit ? (
                <Link
                    href={`/releases/${releaseId}`}
                    className="inline-flex h-10 items-center rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700"
                >
                    Done
                </Link>
            ) : isLastStep ? (
                <button
                    type="button"
                    onClick={onSubmit}
                    disabled={processing}
                    className="h-10 rounded-lg bg-emerald-600 px-5 text-sm font-semibold text-white transition hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing
                        ? 'Submitting...'
                        : 'Submit for review'}
                </button>
            ) : (
                <button
                    type="button"
                    onClick={onNext}
                    disabled={processing}
                    className="h-10 rounded-lg bg-violet-600 px-5 text-sm font-semibold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {nextText ?? 'Continue →'}
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

const TYPE_LABELS = { single: 'Single', ep: 'EP', album: 'Album' };
const typeLabel = (v) => TYPE_LABELS[String(v ?? '').toLowerCase()] ?? (v ? String(v) : 'Single');
const statusLabel = (v) => String(v ?? 'draft').replace(/_/g, ' ').replace(/^./, (c) => c.toUpperCase());

/* W97: N1 "This step" - what is still missing on the details step */
function detailsChecklist(data) {
    const has = (v) => String(v ?? '').trim() !== '';
    const firstArtist = Array.isArray(data.primary_artists) ? data.primary_artists[0]?.name : data.primary_artist_name;
    const upcOk = data.generate_upc !== false || /^\d{12,13}$/.test(String(data.upc ?? '').trim());

    return [
        { label: 'Catalogue label', done: has(data.label_id) },
        { label: 'Title & primary artist', done: has(data.title) && has(firstArtist) },
        { label: 'Genre, language, date', done: has(data.primary_genre) && has(data.sub_genre) && has(data.language) && has(data.digital_release_date) },
        { label: 'C / P line', done: has(data.copyright_owner) && has(data.phonographic_owner) },
        { label: data.generate_upc === false ? 'Your UPC (12 or 13 digits)' : 'UPC: added by Mixx Tune', done: upcOk },
        { label: 'Cover art 3000 × 3000', done: Boolean(data.artwork || data.artwork_preview) },
    ];
}

/* W119: N2 "This step" - tracks */
function tracksChecklist(release) {
    const tracks = Array.isArray(release?.tracks) ? release.tracks : [];
    const items = [
        { label: 'At least one track', done: tracks.length > 0 },
        { label: 'WAV for every track', done: tracks.length > 0 && tracks.every((t) => Boolean(t.audio_path)) },
    ];
    const missing = tracks.filter((t) => trackMissingLabels(t).length > 0);
    if (missing.length === 0) {
        items.push({ label: 'Credits for every track', done: tracks.length > 0 });
    } else {
        missing.slice(0, 6).forEach((t) => items.push({ label: `Credits for track ${t.track_number || tracks.indexOf(t) + 1}`, done: false }));
    }

    return items;
}

function StepChecklist({ title = 'This step', items = [] }) {
    const done = items.filter((i) => i.done).length;

    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex items-center justify-between">
                <h2 className="text-sm font-bold text-slate-900">{title}</h2>
                <span className={`text-xs font-semibold ${done === items.length ? 'text-emerald-700' : 'text-slate-500'}`}>
                    {done} / {items.length}
                </span>
            </div>
            <ul className="mt-3 space-y-1.5">
                {items.map((item) => (
                    <li key={item.label} className="flex items-center gap-2 text-xs text-slate-700">
                        <span aria-hidden="true" className={item.done ? 'font-bold text-emerald-700' : 'text-amber-700'}>
                            {item.done ? '✓' : '○'}
                        </span>
                        <span>{item.label}</span>
                        <span className="sr-only">{item.done ? '(done)' : '(missing)'}</span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/* W97: N1 steps row - done steps get a tick; later steps open once the draft is saved */
function StepNav({ currentStep, canJump, onStepChange }) {
    return (
        <nav aria-label="Steps" className="flex gap-1.5 overflow-x-auto px-4 pb-3 sm:px-5">
            {wizardSteps.map((step) => {
                const active = step.id === currentStep;
                const done = step.id < currentStep;
                const enabled = step.id === 1 || canJump;

                return (
                    <button
                        key={step.id}
                        type="button"
                        disabled={!enabled}
                        aria-current={active ? 'step' : undefined}
                        onClick={() => enabled && onStepChange(step.id)}
                        className={`flex h-10 shrink-0 items-center gap-2 rounded-lg px-3 text-sm font-semibold transition disabled:cursor-not-allowed disabled:opacity-50 ${
                            active ? 'bg-violet-100 text-violet-900' : 'text-slate-600 hover:bg-slate-50'
                        }`}
                    >
                        <span
                            className={`flex h-6 w-6 items-center justify-center rounded-full border text-[11px] ${
                                active
                                    ? 'border-violet-600 bg-violet-600 text-white'
                                    : done
                                      ? 'border-emerald-600 bg-emerald-600 text-white'
                                      : 'border-slate-300 text-slate-600'
                            }`}
                        >
                            {done ? '✓' : step.id}
                        </span>
                        {step.id === LAST_STEP ? 'Review & submit' : step.label}
                    </button>
                );
            })}
        </nav>
    );
}
