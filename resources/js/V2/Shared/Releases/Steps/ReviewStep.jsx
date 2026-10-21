import { Link, router } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import { COUNTRIES } from '@/V2/Shared/Releases/Steps/TerritoryStep';

/*
 * W64 MIXXTUNE_REVIEW_V2: a summary card on top with the Submit button,
 * details / tracks / where-it-goes on the left and a compact checklist on
 * the right; uses the full width (the empty side column is gone); label
 * name from the release itself; no forced capitals (a title "s" showed as
 * "S"); stores are a short summary, territories show flags.
 */

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
    adminEdit = false,
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
            label: 'Stores available',
            passed:
                effectiveSelectedStores.length > 0,
            step: null,
        },
        {
            label: 'Release territory',
            passed:
                release?.worldwide === null ||
                release?.worldwide === undefined ||
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

    const labelName =
        label?.name
        || release?.label?.name
        || release?.label_name
        || null;

    const doneCount = checks.filter((c) => c.passed).length;

    const territoryCodes = Array.isArray(release?.territories)
        ? release.territories.map((c) => String(c).toUpperCase())
        : [];
    const isWorldwide = release?.worldwide === undefined || release?.worldwide === null ? true : Boolean(release.worldwide);
    const territoryList = COUNTRIES.filter((c) => territoryCodes.includes(c.code));

    const artwork = release?.artwork_path ? `/storage/${release.artwork_path}` : null;

    return (
        <div className="space-y-6">
            <section className="flex flex-col gap-5 rounded-2xl border border-slate-200 bg-white p-5 lg:flex-row lg:items-center">
                {artwork ? (
                    <img src={artwork} alt="" className="h-28 w-28 shrink-0 rounded-xl border border-slate-200 object-cover" />
                ) : (
                    <button type="button" onClick={() => onEditStep(1)} className="flex h-28 w-28 shrink-0 items-center justify-center rounded-xl border border-dashed border-rose-300 bg-rose-50 text-center text-xs font-semibold text-rose-700">
                        Add cover art
                    </button>
                )}

                <div className="min-w-0 flex-1">
                    <div className="text-xs font-semibold uppercase tracking-wide text-violet-600">Review &amp; Publish</div>
                    <h2 className="mt-1 break-words text-2xl font-bold text-slate-900">{release?.title || 'Untitled release'}</h2>
                    <div className="mt-1 text-sm text-slate-600">
                        {release?.primary_artist_name || artist?.stage_name || 'No artist'}
                        {labelName ? ` · ${labelName}` : ''}
                    </div>
                    <div className="mt-3 flex flex-wrap gap-2 text-xs font-semibold">
                        {[
                            release?.release_type ? String(release.release_type).toUpperCase() === 'EP' ? 'EP' : String(release.release_type).charAt(0).toUpperCase() + String(release.release_type).slice(1) : null,
                            release?.digital_release_date ? formatDate(release.digital_release_date) : null,
                            release?.language,
                            release?.primary_genre,
                            `${tracks.length} track${tracks.length === 1 ? '' : 's'}`,
                        ].filter(Boolean).map((t) => (
                            <span key={t} className="rounded-full bg-slate-100 px-3 py-1 text-slate-700">{t}</span>
                        ))}
                    </div>
                </div>

                <div className={`w-full shrink-0 rounded-xl border p-4 lg:w-72 ${allPassed ? 'border-emerald-200 bg-emerald-50' : 'border-amber-200 bg-amber-50'}`}>
                    <div className={`text-sm font-bold ${allPassed ? 'text-emerald-900' : 'text-amber-900'}`}>
                        {allPassed ? 'Ready to submit' : `${checks.length - doneCount} thing${checks.length - doneCount === 1 ? '' : 's'} to fix`}
                    </div>
                    <div className="mt-2 h-2 overflow-hidden rounded-full bg-white">
                        <div className={`h-full rounded-full ${allPassed ? 'bg-emerald-500' : 'bg-amber-500'}`} style={{ width: `${Math.round((doneCount / checks.length) * 100)}%` }} />
                    </div>
                    <div className="mt-1 text-xs text-slate-600">{doneCount} of {checks.length} checks done</div>
                    {adminEdit ? (
                        <Link href={`/v2/releases/${release.id}`} className="mt-3 block w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-emerald-700">
                            Done · changes are saved
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={submitForReview}
                            disabled={!allPassed || submitting}
                            className="mt-3 w-full rounded-xl bg-emerald-600 px-4 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {submitting ? 'Submitting...' : 'Submit for Review'}
                        </button>
                    )}
                </div>
            </section>

            <div className="grid gap-6 xl:grid-cols-[minmax(0,1fr)_340px]">
                <div className="min-w-0 space-y-6">
                    <ReviewCard title="Release Details" onEdit={() => onEditStep(1)}>
                        <div className="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                            <Info label="Title" value={release?.title || 'Not provided'} />
                            <Info label="Type" value={release?.release_type ? ({ single: 'Single', ep: 'EP', album: 'Album' }[String(release.release_type).toLowerCase()] ?? release.release_type) : 'Not provided'} />
                            <Info label="Primary artist" value={release?.primary_artist_name || artist?.stage_name || 'Not provided'} />
                            <Info label={isArtistAccount ? 'Artist account' : 'Label'} value={isArtistAccount ? (artist?.stage_name || release?.primary_artist_name || 'Not provided') : (labelName || 'Not provided')} warn={!isArtistAccount && !labelName} />
                            <Info label="Catalogue no." value={release?.catalog_number || 'Not provided'} />
                            <Info label="UPC" value={release?.upc || 'Given at approval'} />
                            <Info label="Release date" value={formatDate(release?.digital_release_date)} />
                            <Info label="Language" value={release?.language || 'Not provided'} />
                            <Info label="Genre" value={[release?.primary_genre, release?.sub_genre].filter(Boolean).join(' / ') || 'Not provided'} />
                        </div>
                    </ReviewCard>

                    <ReviewCard title="Tracks" badge={`${tracks.length} track${tracks.length === 1 ? '' : 's'}`} onEdit={() => onEditStep(2)}>
                        {tracks.length === 0 ? (
                            <EmptyMessage text="No tracks added yet - press ✎ to upload the audio." />
                        ) : (
                            <div className="divide-y divide-slate-100 overflow-hidden rounded-xl border border-slate-200">
                                {tracks.map((track, index) => (
                                    <div key={track.id} className="flex flex-wrap items-center gap-3 px-4 py-3">
                                        <span className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-100 text-sm font-semibold text-slate-700">{index + 1}</span>
                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-semibold text-slate-900">
                                                {track.title}
                                                {track.version ? ` (${track.version})` : ''}
                                            </div>
                                            <div className="truncate text-xs text-slate-500">{track.primary_artist_name || '—'}</div>
                                        </div>
                                        <span className="text-xs font-medium text-slate-600">{track.isrc || 'ISRC at approval'}</span>
                                        <span className={`rounded-full px-2.5 py-1 text-xs font-semibold ${track.audio_path ? 'bg-emerald-50 text-emerald-700' : 'bg-rose-50 text-rose-700'}`}>
                                            {track.audio_path ? 'Audio ✓' : 'Audio missing'}
                                        </span>
                                    </div>
                                ))}
                            </div>
                        )}
                    </ReviewCard>

                    <ReviewCard title="Where it goes" onEdit={() => onEditStep(3)}>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <div className="text-xs font-medium uppercase tracking-wide text-slate-400">Territories</div>
                                {isWorldwide ? (
                                    <div className="mt-1 text-sm font-semibold text-slate-900">🌍 Worldwide · all countries</div>
                                ) : (
                                    <div className="mt-2 flex flex-wrap gap-1.5">
                                        {territoryList.slice(0, 24).map((c) => (
                                            <span key={c.code} className="inline-flex items-center gap-1 rounded-md border border-slate-200 px-1.5 py-0.5 text-xs text-slate-700" title={c.name}>
                                                <span aria-hidden="true">{c.flag}</span>{c.name}
                                            </span>
                                        ))}
                                        {territoryList.length > 24 && <span className="text-xs font-semibold text-slate-500">+{territoryList.length - 24} more</span>}
                                        {territoryList.length === 0 && <span className="text-sm font-semibold text-rose-700">No country selected</span>}
                                    </div>
                                )}
                            </div>
                            <div>
                                <div className="text-xs font-medium uppercase tracking-wide text-slate-400">Stores</div>
                                <div className="mt-1 text-sm font-semibold text-slate-900">
                                    {selectedStores.length > 0 ? `${selectedStores.length} stores` : `All ${effectiveSelectedStores.length} active stores`}
                                </div>
                                <div className="mt-1 text-xs leading-5 text-slate-500">
                                    {effectiveSelectedStores.slice(0, 8).map((st) => st.name).join(', ')}
                                    {effectiveSelectedStores.length > 8 ? ` +${effectiveSelectedStores.length - 8} more` : ''}
                                </div>
                            </div>
                            <Info label="Timezone" value={release?.release_timezone || 'Asia/Kolkata'} />
                            <Info label="Pre-order" value={release?.pre_order ? 'On' : 'Off'} />
                        </div>
                    </ReviewCard>
                </div>

                <aside className="space-y-4 xl:sticky xl:top-28 xl:self-start">
                    <div className="rounded-2xl border border-slate-200 bg-white p-4">
                        <div className="flex items-center justify-between gap-3">
                            <h3 className="font-semibold text-slate-900">Checklist</h3>
                            <span className="text-xs font-semibold text-slate-500">{doneCount}/{checks.length}</span>
                        </div>
                        <ul className="mt-3 divide-y divide-slate-100">
                            {checks.map((check) => (
                                <li key={check.label} className="flex items-center gap-3 py-2">
                                    <span className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full text-xs font-bold ${check.passed ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700'}`}>
                                        {check.passed ? '✓' : '!'}
                                    </span>
                                    <span className={`min-w-0 flex-1 text-sm ${check.passed ? 'text-slate-600' : 'font-semibold text-slate-900'}`}>{check.label}</span>
                                    {!check.passed && check.step && (
                                        <button type="button" onClick={() => onEditStep(check.step)} className="rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-violet-700 hover:bg-violet-50">
                                            Fix
                                        </button>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                    <p className="px-1 text-xs leading-5 text-slate-500">
                        After you submit, the Mixx Tune team checks the release. If something needs changing it comes back to you with a note; once approved it gets its UPC / ISRC and goes to the stores.
                    </p>
                </aside>
            </div>
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
            <div className="mb-4 flex items-center justify-between gap-4">
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
                    aria-label={`Edit ${title}`}
                    className="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-violet-50"
                >
                    Edit
                </button>
            </div>

            {children}
        </section>
    );
}

function Info({ label, value, warn = false }) {
    return (
        <div className="min-w-0">
            <div className="text-xs font-medium uppercase tracking-wide text-slate-400">
                {label}
            </div>

            <div className={`mt-1 break-words text-sm font-semibold ${warn ? 'text-rose-700' : 'text-slate-900'}`}>
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
