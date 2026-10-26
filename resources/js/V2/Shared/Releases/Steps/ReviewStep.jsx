import { Link, router } from '@inertiajs/react';
import { useState } from 'react';
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
    && /^\/artist(\/|$)/.test(window.location.pathname)
        ? '/artist/releases'
        : '/releases';

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

/* W119: the same checks for the Submit in the checklist and the Submit in the top bar */
export function reviewChecks({ release, artist = null, label = null, distributionStores = [] }) {
    const tracks = Array.isArray(release?.tracks) ? release.tracks : [];
    const selectedStoreIds = Array.isArray(
        release?.stores
    )
        ? release.stores.map(Number)
        : [];

    const selectedStores = (distributionStores ?? []).filter((store) => selectedStoreIds.includes(Number(store.id)));

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
        /* W83: lyricist and composer are needed for every track */
        {
            label: 'Lyricist and composer for every track',
            passed:
                tracks.length > 0 &&
                tracks.every(
                    (track) =>
                        (Boolean(track.is_instrumental) || String(track.author_name ?? '').trim() !== '') &&
                        String(track.composer_name ?? '').trim() !== ''
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


    return { checks, allPassed: checks.every((c) => c.passed), selectedStores, effectiveSelectedStores, isArtistAccount };
}

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

    const { checks, allPassed, selectedStores, effectiveSelectedStores, isArtistAccount } = reviewChecks({ release, artist, label, distributionStores });

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

    const typeName = release?.release_type ? ({ single: 'Single', ep: 'EP', album: 'Album' }[String(release.release_type).toLowerCase()] ?? release.release_type) : 'Not provided';
    const featuring = Array.isArray(release?.featuring_artists) && release.featuring_artists.length
        ? release.featuring_artists.map((a) => (typeof a === 'string' ? a : a?.name)).filter(Boolean).join(', ')
        : release?.featuring_artist_name || '—';
    const line = (sign, year, owner) => (owner ? `${sign} ${year ? `${year} ` : ''}${owner}` : 'Not provided');
    const excludedNames = !isWorldwide && territoryCodes.length > COUNTRIES.length / 2
        ? COUNTRIES.filter((c) => !territoryCodes.includes(c.code)).map((c) => c.name)
        : [];
    const pct = Math.round((doneCount / checks.length) * 100);

    /* W119 MIXXTUNE_NEW_RELEASE_UI_N5: main column + checklist with Submit on the side (as N1) */
    return (
        <div className="flex flex-wrap items-start gap-5">
            <div className="min-w-0 flex-[999_1_640px] space-y-4">
                <section className="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    {artwork ? (
                        <img src={artwork} alt="" className="h-[110px] w-[110px] shrink-0 rounded-[10px] border border-slate-200 object-cover" />
                    ) : (
                        <button type="button" onClick={() => onEditStep(1)} className="flex h-[110px] w-[110px] shrink-0 items-center justify-center rounded-[10px] border border-dashed border-rose-300 bg-rose-50 text-center text-xs font-semibold text-rose-700">
                            Add cover art
                        </button>
                    )}
                    <div className="min-w-0 flex-[1_1_240px]">
                        <h2 className="break-words text-[22px] font-bold text-slate-900">{release?.title || 'Untitled release'}</h2>
                        <div className="mt-0.5 text-[13px] text-slate-600">
                            {release?.primary_artist_name || artist?.stage_name || 'No artist'}
                            {labelName ? ` · ${labelName}` : ''}
                        </div>
                        <div className="mt-3 flex flex-wrap gap-1.5 text-[11px] font-semibold">
                            {[
                                typeName !== 'Not provided' ? typeName : null,
                                release?.digital_release_date ? formatDate(release.digital_release_date) : null,
                                release?.language,
                                release?.primary_genre,
                                `${tracks.length} track${tracks.length === 1 ? '' : 's'}`,
                            ].filter(Boolean).map((t) => (
                                <span key={t} className="rounded-full bg-slate-100 px-2.5 py-1 text-slate-700">{t}</span>
                            ))}
                        </div>
                    </div>
                </section>

                <ReviewCard title="Release details" onEdit={() => onEditStep(1)}>
                    <div className="grid gap-x-6 gap-y-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        <Info label={isArtistAccount ? 'Artist account' : 'Catalogue label'} value={isArtistAccount ? (artist?.stage_name || release?.primary_artist_name || 'Not provided') : (labelName || 'Not provided')} warn={!isArtistAccount && !labelName} />
                        <Info label="Type" value={typeName} />
                        <Info label="Primary artist" value={release?.primary_artist_name || artist?.stage_name || 'Not provided'} />
                        <Info label="Featuring" value={featuring} />
                        <Info label="Genre" value={[release?.primary_genre, release?.sub_genre].filter(Boolean).join(' / ') || 'Not provided'} />
                        <Info label="Language" value={release?.language || 'Not provided'} />
                        <Info label="Release date" value={formatDate(release?.digital_release_date)} />
                        <Info label="Production year" value={release?.copyright_year || release?.phonographic_year || 'Not provided'} />
                        <Info label="C line" value={line('©', release?.copyright_year, release?.copyright_owner)} />
                        <Info label="P line" value={line('℗', release?.phonographic_year, release?.phonographic_owner)} />
                        <Info label="UPC · Catalog no." value={`${release?.upc || 'At approval'} · ${release?.catalog_number || 'Not provided'}`} warn={!release?.catalog_number} />
                        <Info label="Timezone · Pre-order" value={`${release?.release_timezone || 'Asia/Kolkata'} · ${release?.pre_order ? 'On' : 'Off'}`} />
                    </div>
                </ReviewCard>

                <ReviewCard title={`Tracks (${tracks.length})`} onEdit={() => onEditStep(2)}>
                    {tracks.length === 0 ? (
                        <EmptyMessage text="No tracks added yet - press Edit to upload the audio." />
                    ) : (
                        <div>
                            {tracks.map((track, index) => (
                                <div key={track.id} className="flex flex-wrap items-center gap-2.5 border-t border-slate-100 py-2.5 text-[13px] first:border-t-0">
                                    <span className="w-5 font-bold text-slate-700">{index + 1}</span>
                                    <div className="min-w-0 flex-[1_1_220px] truncate">
                                        <span className="font-semibold text-slate-900">{track.title}{track.version ? ` (${track.version})` : ''}</span>
                                        <span className="text-slate-500"> · {[track.is_instrumental ? 'Instrumental' : track.author_name, track.composer_name].map((v) => v || '—').join(' / ')}</span>
                                    </div>
                                    {((!track.is_instrumental && !String(track.author_name ?? '').trim()) || !String(track.composer_name ?? '').trim()) && (
                                        <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">Lyricist / composer missing</span>
                                    )}
                                    <span className="text-xs text-slate-500">{track.isrc || 'ISRC at approval'}</span>
                                    <span className={`text-xs font-semibold ${track.audio_path ? 'text-emerald-700' : 'text-rose-700'}`}>{track.audio_path ? 'WAV ✓' : 'WAV missing'}</span>
                                </div>
                            ))}
                        </div>
                    )}
                </ReviewCard>

                <ReviewCard title="Where it goes" onEdit={() => onEditStep(3)}>
                    <div className="grid gap-x-6 gap-y-3.5 sm:grid-cols-2 lg:grid-cols-3">
                        <div className="min-w-0 sm:col-span-2">
                            <div className="text-[11px] font-medium uppercase tracking-wide text-slate-500">Territories</div>
                            {isWorldwide ? (
                                <div className="mt-1 text-[13px] font-semibold text-slate-900">🌍 Worldwide · all countries</div>
                            ) : excludedNames.length > 0 ? (
                                <div className="mt-1 text-[13px] font-semibold text-slate-900">🌍 Worldwide except {excludedNames.slice(0, 8).join(', ')}{excludedNames.length > 8 ? ` +${excludedNames.length - 8} more` : ''}</div>
                            ) : (
                                <div className="mt-1.5 flex flex-wrap gap-1.5">
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
                        <div className="min-w-0">
                            <div className="text-[11px] font-medium uppercase tracking-wide text-slate-500">Stores</div>
                            <div className="mt-1 text-[13px] font-semibold text-slate-900" title={effectiveSelectedStores.map((st) => st.name).join(', ')}>
                                {selectedStores.length > 0 ? `${selectedStores.length} stores` : `All ${effectiveSelectedStores.length} active stores`}
                            </div>
                        </div>
                    </div>
                </ReviewCard>
            </div>

            <aside className="flex min-w-0 max-w-full flex-[1_1_300px] flex-col gap-4 lg:sticky lg:top-36 lg:max-w-[360px]">
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div className="flex items-center justify-between">
                        <h3 className="text-sm font-bold text-slate-900">Checklist</h3>
                        <span className={`text-xs font-semibold ${allPassed ? 'text-emerald-700' : 'text-slate-500'}`}>{doneCount} / {checks.length}</span>
                    </div>
                    <div className="mt-2.5 h-1.5 overflow-hidden rounded-full bg-emerald-100">
                        <div className="h-full rounded-full bg-emerald-600 transition-all" style={{ width: `${pct}%` }} />
                    </div>
                    <ul className="mt-2">
                        {checks.map((check) => (
                            <li key={check.label} className="flex items-center gap-2 border-b border-slate-100 py-2 text-xs last:border-b-0">
                                <span aria-hidden="true" className={check.passed ? 'font-bold text-emerald-700' : 'font-bold text-amber-700'}>{check.passed ? '✓' : '○'}</span>
                                <span className={`min-w-0 flex-1 ${check.passed ? 'text-slate-600' : 'font-semibold text-slate-900'}`}>{check.label}</span>
                                <span className="sr-only">{check.passed ? '(done)' : '(missing)'}</span>
                                {!check.passed && check.step && (
                                    <button type="button" onClick={() => onEditStep(check.step)} className="rounded-md border border-slate-300 px-2 py-0.5 text-[11px] font-semibold text-violet-700 hover:bg-violet-50">
                                        Fix
                                    </button>
                                )}
                            </li>
                        ))}
                    </ul>
                    {adminEdit ? (
                        <Link href={`/releases/${release.id}`} className="mt-3 block w-full rounded-lg bg-emerald-600 px-4 py-2.5 text-center text-sm font-semibold text-white hover:bg-emerald-700">
                            Done · changes are saved
                        </Link>
                    ) : (
                        <button
                            type="button"
                            onClick={submitForReview}
                            disabled={!allPassed || submitting}
                            className="mt-3 h-11 w-full rounded-lg bg-emerald-600 px-4 text-sm font-semibold text-white hover:bg-emerald-700 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                            {submitting ? 'Submitting...' : allPassed ? 'Submit for review' : `${checks.length - doneCount} thing${checks.length - doneCount === 1 ? '' : 's'} to fix first`}
                        </button>
                    )}
                </section>
                <p className="px-1 text-xs leading-5 text-slate-500">
                    After you submit, the Mixx Tune team checks the release. If something needs changing it comes back to you with a note; once approved it gets its UPC / ISRC and goes to the stores.
                </p>
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
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="mb-3 flex items-center justify-between gap-4">
                <div className="flex min-w-0 items-center gap-3">
                    <h3 className="truncate text-sm font-bold text-slate-900">
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
                    className="text-xs font-semibold text-violet-700 hover:underline"
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
            <div className="text-[11px] font-medium uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className={`mt-0.5 break-words text-[13px] font-semibold ${warn ? 'text-rose-700' : 'text-slate-900'}`}>
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
