import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const RELEASES_BASE_PATH =
    typeof window !== 'undefined'
    && window.location.pathname.startsWith('/artist')
        ? '/artist/releases'
        : '/v2/releases';

const formatDate = (value) => {
    if (!value) return 'Not provided';

    const date = new Date(value);

    if (Number.isNaN(date.getTime())) {
        return String(value);
    }

    return new Intl.DateTimeFormat('en-IN', {
        day: '2-digit',
        month: 'short',
        year: 'numeric',
    }).format(date);
};

export default function ReviewStep({
    release,
    artist = null,
    label = null,
    distributionStores = [],
    onEditStep = () => {},
    onMascotEvent = () => {},
}) {
    const [submitting, setSubmitting] =
        useState(false);

    const tracks = Array.isArray(
        release?.tracks
    )
        ? release.tracks
        : [];

    const selectedStoreIds = Array.isArray(
        release?.stores
    )
        ? release.stores.map(Number)
        : [];

    const selectedStores = useMemo(
        () =>
            distributionStores.filter(
                (store) =>
                    selectedStoreIds.includes(
                        Number(store.id)
                    )
            ),
        [
            distributionStores,
            selectedStoreIds.join(','),
        ]
    );

    const isArtistAccount =
        Boolean(artist?.id) &&
        !Boolean(label?.id);

    const effectiveSelectedStores =
        selectedStores.length > 0
            ? selectedStores
            : (
                  Array.isArray(
                      distributionStores
                  )
                      ? distributionStores
                      : []
              );

    const checks = [
        {
            label: 'Release title',
            passed: Boolean(release?.title),
            step: 1,
        },
        {
            label: 'Primary artist',
            passed: Boolean(
                release?.primary_artist_name ||
                    artist?.stage_name
            ),
            step: 1,
        },
        {
            label: isArtistAccount
                ? 'Artist account'
                : 'Label',
            passed: isArtistAccount
                ? Boolean(
                      release?.artist_id ||
                          artist?.id ||
                          release
                              ?.primary_artist_name ||
                          artist?.stage_name
                  )
                : Boolean(
                      release?.label_id ||
                          label?.id
                  ),
            step: 1,
        },
        {
            label: 'Catalogue number',
            passed: Boolean(
                release?.catalog_number
            ),
            step: 1,
        },
        {
            label: 'Digital release date',
            passed: Boolean(
                release?.digital_release_date
            ),
            step: 1,
        },
        {
            label: 'Cover artwork',
            passed: Boolean(
                release?.artwork_path
            ),
            step: 1,
        },
        {
            label: 'At least one track',
            passed: tracks.length > 0,
            step: 2,
        },
        {
            label: 'WAV uploaded for every track',
            passed:
                tracks.length > 0 &&
                tracks.every(
                    (track) =>
                        Boolean(
                            track.audio_path
                        )
                ),
            step: 2,
        },
        {
            label: 'At least one store',
            passed:
                effectiveSelectedStores.length > 0,
            step: 3,
        },
        {
            label: 'Release territory',
            passed:
                Boolean(release?.worldwide) ||
                (
                    Array.isArray(
                        release?.territories
                    ) &&
                    release.territories.length > 0
                ),
            step: 3,
        },
    ];

    const allPassed = checks.every(
        (check) => check.passed
    );

    const submitForReview = () => {
        if (!release?.id || submitting) {
            return;
        }

        if (!allPassed) {
            window.alert(
                'Complete all required items before submission.'
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

        setSubmitting(true);
        onMascotEvent('submitting');

        router.post(
            `${RELEASES_BASE_PATH}/${release.id}/submit`,
            {},
            {
                preserveScroll: true,

                onError: (errors) => {
                    onMascotEvent(
                        'submission-error'
                    );

                    const message =
                        Object.values(
                            errors ?? {}
                        )[0] ||
                        'Release could not be submitted.';

                    window.alert(message);
                },

                onFinish: () => {
                    setSubmitting(false);
                },
            }
        );
    };

    return (
        <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Review & Publish
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Review the release before submitting
                        it for approval.
                    </p>
                </div>

                <ReviewCard
                    title="Release Details"
                    onEdit={() =>
                        onEditStep(1)
                    }
                >
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <Info
                            label="Title"
                            value={
                                release?.title ||
                                'Not provided'
                            }
                        />

                        <Info
                            label="Type"
                            value={
                                release?.release_type ||
                                'Not provided'
                            }
                        />

                        <Info
                            label="Artist"
                            value={
                                release
                                    ?.primary_artist_name ||
                                artist?.stage_name ||
                                'Not provided'
                            }
                        />

                        <Info
                            label={
                                isArtistAccount
                                    ? 'Artist Account'
                                    : 'Label'
                            }
                            value={
                                isArtistAccount
                                    ? (
                                          artist
                                              ?.stage_name ||
                                          release
                                              ?.primary_artist_name ||
                                          'Not provided'
                                      )
                                    : (
                                          label?.name ||
                                          'Not provided'
                                      )
                            }
                        />

                        <Info
                            label="Catalogue"
                            value={
                                release
                                    ?.catalog_number ||
                                'Not provided'
                            }
                        />

                        <Info
                            label="UPC"
                            value={
                                release?.upc ||
                                'Pending'
                            }
                        />

                        <Info
                            label="Release Date"
                            value={formatDate(
                                release
                                    ?.digital_release_date
                            )}
                        />

                        <Info
                            label="Language"
                            value={
                                release?.language ||
                                'Not provided'
                            }
                        />

                        <Info
                            label="Genre"
                            value={
                                release
                                    ?.primary_genre ||
                                'Not provided'
                            }
                        />
                    </div>
                </ReviewCard>

                <ReviewCard
                    title="Tracks"
                    badge={`${tracks.length} track${
                        tracks.length === 1
                            ? ''
                            : 's'
                    }`}
                    onEdit={() =>
                        onEditStep(2)
                    }
                >
                    {tracks.length === 0 ? (
                        <EmptyMessage text="No tracks added." />
                    ) : (
                        <div className="space-y-3">
                            {tracks.map(
                                (
                                    track,
                                    index
                                ) => (
                                    <div
                                        key={
                                            track.id
                                        }
                                        className="flex flex-col gap-4 rounded-2xl border border-slate-200 p-4 sm:flex-row sm:items-center"
                                    >
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-slate-100 font-semibold text-slate-700">
                                            {index + 1}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-semibold text-slate-900">
                                                {
                                                    track.title
                                                }

                                                {track.version
                                                    ? ` (${track.version})`
                                                    : ''}
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                {
                                                    track.primary_artist_name
                                                }
                                            </div>
                                        </div>

                                        <div className="text-sm">
                                            <div className="font-medium text-slate-700">
                                                {track.isrc ||
                                                    'ISRC Pending'}
                                            </div>

                                            <div
                                                className={`mt-1 ${
                                                    track.audio_path
                                                        ? 'text-emerald-600'
                                                        : 'text-red-600'
                                                }`}
                                            >
                                                {track.audio_path
                                                    ? track.audio_original_name ||
                                                      'WAV uploaded'
                                                    : 'WAV missing'}
                                            </div>
                                        </div>
                                    </div>
                                )
                            )}
                        </div>
                    )}
                </ReviewCard>

                <ReviewCard
                    title="Stores & Distribution"
                    badge={`${effectiveSelectedStores.length} selected`}
                    onEdit={() =>
                        onEditStep(3)
                    }
                >
                    {effectiveSelectedStores.length ===
                    0 ? (
                        <EmptyMessage text="No stores selected." />
                    ) : (
                        <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {effectiveSelectedStores.map(
                                (store) => (
                                    <div
                                        key={
                                            store.id
                                        }
                                        className="rounded-xl border border-slate-200 p-3 text-sm font-semibold text-slate-800"
                                    >
                                        {store.name}
                                    </div>
                                )
                            )}
                        </div>
                    )}

                    <div className="mt-5 grid gap-5 border-t border-slate-200 pt-5 sm:grid-cols-3">
                        <Info
                            label="Territory"
                            value={
                                release?.worldwide
                                    ? 'Worldwide'
                                    : (
                                          release?.territories?.join(
                                              ', '
                                          ) ||
                                          'Not selected'
                                      )
                            }
                        />

                        <Info
                            label="Timezone"
                            value={
                                release
                                    ?.release_timezone ||
                                'Asia/Kolkata'
                            }
                        />

                        <Info
                            label="Pre-order"
                            value={
                                release?.pre_order
                                    ? 'Enabled'
                                    : 'Disabled'
                            }
                        />
                    </div>
                </ReviewCard>
            </div>

            <aside className="space-y-5">
                <div className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 className="font-semibold text-slate-900">
                        Submission Checklist
                    </h3>

                    <div className="mt-5 space-y-3">
                        {checks.map(
                            (check) => (
                                <div
                                    key={
                                        check.label
                                    }
                                    className="flex items-center gap-3 rounded-xl border border-slate-200 p-3"
                                >
                                    <div
                                        className={`flex h-8 w-8 shrink-0 items-center justify-center rounded-full text-sm font-bold ${
                                            check.passed
                                                ? 'bg-emerald-100 text-emerald-700'
                                                : 'bg-red-100 text-red-700'
                                        }`}
                                    >
                                        {check.passed
                                            ? '✓'
                                            : '!'}
                                    </div>

                                    <div className="min-w-0 flex-1 text-sm font-medium text-slate-700">
                                        {
                                            check.label
                                        }
                                    </div>

                                    <button
                                        type="button"
                                        onClick={() =>
                                            onEditStep(
                                                check.step
                                            )
                                        }
                                        className="flex h-8 w-8 items-center justify-center rounded-lg border border-slate-300 text-slate-500 hover:bg-violet-50"
                                    >
                                        ✎
                                    </button>
                                </div>
                            )
                        )}
                    </div>
                </div>

                <div
                    className={`rounded-2xl border p-5 ${
                        allPassed
                            ? 'border-emerald-200 bg-emerald-50'
                            : 'border-amber-200 bg-amber-50'
                    }`}
                >
                    <h3
                        className={`font-semibold ${
                            allPassed
                                ? 'text-emerald-900'
                                : 'text-amber-900'
                        }`}
                    >
                        {allPassed
                            ? 'Ready to Submit'
                            : 'Action Required'}
                    </h3>

                    <p
                        className={`mt-2 text-sm ${
                            allPassed
                                ? 'text-emerald-700'
                                : 'text-amber-700'
                        }`}
                    >
                        {allPassed
                            ? 'All required checks have passed.'
                            : 'Complete all missing information.'}
                    </p>

                    <button
                        type="button"
                        onClick={
                            submitForReview
                        }
                        disabled={
                            !allPassed ||
                            submitting
                        }
                        className="mt-5 w-full rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {submitting
                            ? 'Submitting...'
                            : 'Submit for Review'}
                    </button>
                </div>
            </aside>
        </div>
    );
}

function ReviewCard({
    title,
    badge = null,
    onEdit,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5">
            <div className="mb-5 flex items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-3">
                    <h3 className="truncate font-semibold text-slate-900">
                        {title}
                    </h3>

                    {badge && (
                        <span className="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                            {badge}
                        </span>
                    )}
                </div>

                <button
                    type="button"
                    onClick={onEdit}
                    className="flex h-10 w-10 items-center justify-center rounded-xl border border-slate-300 text-lg text-slate-600 hover:bg-violet-50"
                >
                    ✎
                </button>
            </div>

            {children}
        </section>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <div className="text-xs font-medium uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className="mt-1 break-words text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}

function EmptyMessage({ text }) {
    return (
        <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            {text}
        </div>
    );
}
