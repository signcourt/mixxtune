import ReleaseDeleteButton from '@/V2/Shared/Releases/ReleaseDeleteButton';
import {
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    Head,
    Link,
    router,
    usePage,
} from '@inertiajs/react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';
import StatusBadge from '@/V2/Shared/Components/StatusBadge';

const formatDate = (value) => {
    if (!value) {
        return '—';
    }

    const parsed = new Date(value);

    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return new Intl.DateTimeFormat(
        'en-IN',
        {
            day: '2-digit',
            month: 'short',
            year: 'numeric',
        }
    ).format(parsed);
};

const artworkUrl = (path) => {
    if (!path) {
        return null;
    }

    if (
        path.startsWith('http://')
        || path.startsWith('https://')
        || path.startsWith('/')
    ) {
        return path;
    }

    return `/storage/${path}`;
};

const readable = (value) =>
    String(value ?? '')
        .replaceAll('_', ' ')
        .trim();


function CompactTrackPlayer({ src, compact = false }) {
    const audioRef = useRef(null);

    const [playing, setPlaying] = useState(false);
    const [currentTime, setCurrentTime] = useState(0);
    const [duration, setDuration] = useState(0);

    useEffect(() => {
        const audio = audioRef.current;

        if (!audio) {
            return undefined;
        }

        const syncTime = () => {
            setCurrentTime(audio.currentTime || 0);
        };

        const syncDuration = () => {
            setDuration(
                Number.isFinite(audio.duration)
                    ? audio.duration
                    : 0
            );
        };

        const handleEnded = () => {
            setPlaying(false);
            setCurrentTime(0);
        };

        audio.addEventListener(
            'timeupdate',
            syncTime
        );

        audio.addEventListener(
            'loadedmetadata',
            syncDuration
        );

        audio.addEventListener(
            'durationchange',
            syncDuration
        );

        audio.addEventListener(
            'ended',
            handleEnded
        );

        return () => {
            audio.pause();

            audio.removeEventListener(
                'timeupdate',
                syncTime
            );

            audio.removeEventListener(
                'loadedmetadata',
                syncDuration
            );

            audio.removeEventListener(
                'durationchange',
                syncDuration
            );

            audio.removeEventListener(
                'ended',
                handleEnded
            );
        };
    }, [src]);

    const formatTime = (value) => {
        const seconds =
            Number.isFinite(value)
                ? Math.max(0, Math.floor(value))
                : 0;

        const minutes =
            Math.floor(seconds / 60);

        const remaining =
            seconds % 60;

        return `${String(minutes).padStart(2, '0')}:${String(remaining).padStart(2, '0')}`;
    };

    const togglePlayback = async () => {
        const audio = audioRef.current;

        if (!audio) {
            return;
        }

        try {
            if (audio.paused) {
                await audio.play();
                setPlaying(true);
            } else {
                audio.pause();
                setPlaying(false);
            }
        } catch (error) {
            console.error(
                'Audio playback failed:',
                error
            );
        }
    };

    const progress =
        duration > 0
            ? Math.min(
                100,
                Math.max(
                    0,
                    (currentTime / duration) * 100
                )
            )
            : 0;

    const circumference = 2 * Math.PI * 18;

    const dashOffset =
        circumference
        - (progress / 100) * circumference;

    return (
        <div className="flex items-center gap-2">
            <audio
                ref={audioRef}
                src={src}
                preload="metadata"
            />

            <button
                type="button"
                onClick={togglePlayback}
                className="group relative flex h-11 w-11 shrink-0 items-center justify-center rounded-full"
                title={
                    playing
                        ? 'Pause audio'
                        : 'Play audio'
                }
            >
                <svg
                    viewBox="0 0 44 44"
                    className="absolute inset-0 h-11 w-11 -rotate-90"
                    aria-hidden="true"
                >
                    <circle
                        cx="22"
                        cy="22"
                        r="18"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="3"
                        className="text-slate-200"
                    />

                    <circle
                        cx="22"
                        cy="22"
                        r="18"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="3"
                        strokeLinecap="round"
                        strokeDasharray={
                            circumference
                        }
                        strokeDashoffset={
                            dashOffset
                        }
                        className="text-violet-600 transition-[stroke-dashoffset] duration-150"
                    />
                </svg>

                <span className="relative z-10 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-[11px] text-white transition group-hover:bg-violet-600">
                    {playing ? (
                        <span className="font-bold">
                            ❚❚
                        </span>
                    ) : (
                        <span className="ml-0.5">
                            ▶
                        </span>
                    )}
                </span>
            </button>

            {!compact && (
                <div className="whitespace-nowrap font-mono text-[11px] font-semibold text-slate-500">
                    {formatTime(currentTime)}
                    <span className="mx-1 text-slate-300">
                        /
                    </span>
                    {formatTime(duration)}
                </div>
            )}
        </div>
    );
}

export default function Show({
    role = 'artist',
    release,
}) {
    const tracks = release.tracks ?? [];
    /* W119 MIXXTUNE_NEW_RELEASE_UI_N6: right after Submit */
    const justSubmitted = Boolean(usePage().props?.flash?.submitted) && release.status === 'submitted';

    if (justSubmitted) {
        return (
            <PanelLayout role={role} title="Sent for review" subtitle={release.title}>
                <Head title={release.title} />
                <SubmittedPage release={release} />
            </PanelLayout>
        );
    }

    return (
        <PanelLayout
            role={role}
            title="Release Details"
            subtitle={release.title}
        >
            <Head title={release.title} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/releases"
                        className="inline-flex rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        ← Back to Releases
                    </Link>

                    <div className="flex flex-wrap items-center gap-3">
                        <StatusBadge
                            status={release.status}
                        />

                        {release.editable && (
                            <Link
                                href={`/releases/${release.id}/edit`}
                                className="inline-flex rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-700"
                            >
                                Edit Release
                            </Link>
                        )}

                        {!release.editable && release.admin_edit && (
                            <Link
                                href={`/releases/${release.id}/edit`}
                                className="inline-flex rounded-xl bg-amber-500 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-600"
                            >
                                Edit (Super Admin)
                            </Link>
                        )}

                        {/* W120 MIXXTUNE_RELEASE_TRASH */}
                        {role === 'super_admin' && <ReleaseDeleteButton release={release} />}
                    </div>
                </div>

                {!release.editable && (
                    <div className="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4">
                        <div className="font-semibold text-blue-900">
                            This release is currently read-only.
                        </div>

                        <div className="mt-1 text-sm text-blue-700">
                            It has been submitted for review or is already being processed.
                            {release.admin_edit
                                ? ' As Super Admin you can still edit it with "Edit (Super Admin)".'
                                : ''}
                        </div>
                    </div>
                )}

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-7 lg:grid-cols-[240px_1fr]">
                        {/* W195: owner poster with request-change pencil */}
                        <OwnerPoster release={release} role={role} />

                        <div className="min-w-0">
                            <div className="flex flex-wrap items-start justify-between gap-4">
                                <div>
                                    <p className="text-sm font-semibold uppercase tracking-wide text-violet-600">
                                        {readable(
                                            release.release_type
                                        ) || 'Release'}
                                    </p>

                                    <h1 className="mt-2 text-3xl font-bold text-slate-950">
                                        {release.title}
                                    </h1>

                                    <p className="mt-2 text-lg text-slate-500">
                                        {release.primary_artist_name
                                            || release.artist_name
                                            || 'Unknown Artist'}
                                    </p>
                                </div>

                                <div className="rounded-2xl bg-slate-50 px-5 py-4 text-right">
                                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-400">
                                        Completion
                                    </div>

                                    <div className="mt-1 text-2xl font-bold text-slate-900">
                                        {release.completion_percentage ?? 0}%
                                    </div>
                                </div>
                            </div>

                            <div className="mt-7 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <Info
                                    label="Label"
                                    value={release.label_name}
                                />

                                <Info
                                    label="UPC"
                                    value={release.upc || 'Pending'}
                                />

                                <Info
                                    label="Catalogue Number"
                                    value={release.catalog_number}
                                />

                                <Info
                                    label="Release Date"
                                    value={formatDate(
                                        release.digital_release_date || release.original_release_date
                                    )}
                                />

                                <Info
                                    label="Language"
                                    value={release.language}
                                />

                                <Info
                                    label="Genre"
                                    value={release.primary_genre}
                                />

                                <Info
                                    label="Territory"
                                    value={
                                        release.worldwide
                                            ? 'Worldwide'
                                            : 'Selected territories'
                                    }
                                />

                                <Info
                                    label="Release ID"
                                    value={
                                        release.public_id
                                        || `#${release.id}`
                                    }
                                />
                            </div>
                        </div>
                    </div>
                </section>

                <section className="overflow-hidden rounded-3xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex items-center justify-between gap-4">
                            <div>
                                <h2 className="text-xl font-bold text-slate-900">
                                    Tracks
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    {tracks.length} track{tracks.length === 1 ? '' : 's'}
                                </p>
                            </div>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="min-w-full">
                            <thead className="bg-slate-50">
                                <tr>
                                    {[
                                        '#',
                                        '',
                                        'Track',
                                        'Artist',
                                        'ISRC',
                                        'Audio QC',
                                        'Status',
                                    ].map((heading, headingIndex) => (
                                        <th
                                            key={`${heading}-${headingIndex}`}
                                            className="px-5 py-4 text-left text-xs font-semibold uppercase tracking-wide text-slate-500"
                                        >
                                            {heading}
                                        </th>
                                    ))}
                                </tr>
                            </thead>

                            <tbody className="divide-y divide-slate-100">
                                {tracks.length > 0 ? (
                                    tracks.map(
                                        (track, index) => (
                                            <tr key={track.id}>
                                                <td className="px-5 py-4 text-sm text-slate-500">
                                                    {index + 1}
                                                </td>

                                                <td className="w-16 px-2 py-4">
                                                    {track.stream_url ? (
                                                        <CompactTrackPlayer
                                                            src={track.stream_url}
                                                            compact
                                                        />
                                                    ) : null}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <div className="font-semibold text-slate-900">
                                                        {track.title}
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        {track.audio_original_name
                                                            || 'WAV file'}
                                                    </div>
                                                </td>

                                                <td className="px-5 py-4 text-sm text-slate-600">
                                                    {track.primary_artist_name
                                                        || release.primary_artist_name
                                                        || '—'}
                                                </td>

                                                <td className="px-5 py-4 font-mono text-sm text-slate-700">
                                                    {track.isrc || 'Pending'}
                                                </td>

                                                <td className="px-5 py-4">
                                                    <AudioBadge
                                                        status={
                                                            track.audio_validation_status
                                                        }
                                                    />
                                                </td>

                                                <td className="px-5 py-4">
                                                    <span className="inline-flex rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold capitalize text-slate-700">
                                                        {readable(
                                                            track.status
                                                        ) || 'Draft'}
                                                    </span>
                                                </td>
                                            </tr>
                                        )
                                    )
                                ) : (
                                    <tr>
                                        <td
                                            colSpan="6"
                                            className="px-5 py-16 text-center text-sm text-slate-500"
                                        >
                                            No tracks found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </section>

                <div className="grid gap-6 xl:grid-cols-2">
                    <Section title="Rights Information">
                        <InfoGrid
                            rows={[
                                [
                                    'Copyright Owner',
                                    release.copyright_owner,
                                ],
                                [
                                    'Copyright Year',
                                    release.copyright_year,
                                ],
                                [
                                    'Publishing / Phonographic Owner',
                                    release.phonographic_owner,
                                ],
                                [
                                    'Phonographic Year',
                                    release.phonographic_year,
                                ],
                            ]}
                        />
                    </Section>

                    <Section title="Release Timeline">
                        <Timeline release={release} />
                    </Section>
                </div>

                {(release.review_notes
                    || release.rejection_reason) && (
                    <Section title="Review Notes">
                        <div className="rounded-xl bg-amber-50 p-4 text-sm text-amber-900">
                            {release.review_notes
                                || release.rejection_reason}
                        </div>
                    </Section>
                )}
            </div>
        </PanelLayout>
    );
}

function OwnerPoster({ release, role }) {
    /* W195b: label/artist REQUEST a poster change with a confirm step.
       pick -> local preview + uploading INSIDE the box -> Change request / Cancel.
       On submit the poster shows smoky (pending) and is locked until approved. */
    const fileRef = useRef(null);
    const [file, setFile] = useState(null);
    const [preview, setPreview] = useState(null);
    const [busy, setBusy] = useState(false);
    const canRequest = role !== 'super_admin' && role !== 'admin';
    const pending = Boolean(release.poster_change_pending);
    const url = artworkUrl(release.artwork_path);

    const pick = () => fileRef.current && fileRef.current.click();

    const onFile = (e) => {
        const f = e.target.files && e.target.files[0];
        e.target.value = '';
        if (!f) return;
        setFile(f);
        setPreview(URL.createObjectURL(f));
    };

    const cancel = () => {
        if (preview) URL.revokeObjectURL(preview);
        setFile(null);
        setPreview(null);
    };

    const submit = () => {
        if (!file || busy) return;
        setBusy(true);
        router.post(
            `/releases/${release.id}/artwork-request`,
            { artwork: file },
            {
                forceFormData: true,
                preserveScroll: true,
                onFinish: () => {
                    setBusy(false);
                    cancel();
                },
            }
        );
    };

    const Pencil = (
        <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
            <path d="M12 20h9" />
            <path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4Z" />
        </svg>
    );

    return (
        <div>
            <div className="group relative aspect-square overflow-hidden rounded-2xl bg-slate-100">
                {/* base image: current poster, or the just-picked preview */}
                {preview ? (
                    <img src={preview} alt="New poster" className="h-full w-full object-cover" />
                ) : url ? (
                    <img src={url} alt={release.title} className={`h-full w-full object-cover ${pending ? 'opacity-40 blur-[1px]' : ''}`} />
                ) : (
                    <div className="flex h-full items-center justify-center text-7xl text-slate-300">♪</div>
                )}

                {/* pencil: only when nothing is picked and nothing pending */}
                {canRequest && !preview && !pending && (
                    <button
                        type="button"
                        onClick={pick}
                        title="Request a poster change"
                        aria-label="Request a poster change"
                        className="absolute right-2 top-2 inline-flex h-9 w-9 items-center justify-center rounded-full bg-white/95 text-slate-800 shadow opacity-0 transition hover:bg-white group-hover:opacity-100"
                    >
                        {Pencil}
                    </button>
                )}

                {/* uploading overlay INSIDE the box */}
                {busy && (
                    <div className="absolute inset-0 flex items-center justify-center bg-black/40 text-sm font-semibold text-white">
                        Uploading...
                    </div>
                )}

                {/* pending (smoky) badge */}
                {canRequest && pending && !preview && (
                    <div className="absolute inset-x-0 bottom-0 bg-black/55 px-2 py-1 text-center text-[11px] font-semibold text-white">
                        Waiting for approval
                    </div>
                )}
            </div>

            {/* confirm step: Change request (proceed) + Cancel */}
            {canRequest && preview && !busy && (
                <div className="mt-2 flex items-center gap-2">
                    <button
                        type="button"
                        onClick={submit}
                        className="inline-flex items-center rounded-lg bg-violet-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-violet-700"
                    >
                        Change request
                    </button>
                    <button
                        type="button"
                        onClick={cancel}
                        className="inline-flex items-center rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Cancel
                    </button>
                </div>
            )}

            {canRequest && pending && !preview && (
                <p className="mt-2 rounded-lg bg-amber-50 px-3 py-1.5 text-xs font-semibold text-amber-700">
                    Poster change requested - waiting for approval.
                </p>
            )}

            <input
                ref={fileRef}
                type="file"
                accept="image/jpeg,image/png"
                className="hidden"
                onChange={onFile}
            />
        </div>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 className="text-xl font-bold text-slate-900">
                {title}
            </h2>

            <div className="mt-5">
                {children}
            </div>
        </section>
    );
}

function InfoGrid({ rows }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <Info
                    key={label}
                    label={label}
                    value={value}
                />
            ))}
        </div>
    );
}

function Info({
    label,
    value,
}) {
    return (
        <div className="min-w-0 rounded-xl bg-slate-50 p-4">
            <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="mt-2 break-words text-sm font-semibold capitalize text-slate-900">
                {value || '—'}
            </div>
        </div>
    );
}

function AudioBadge({ status }) {
    const normalized = String(
        status || 'pending'
    ).toLowerCase();

    const className =
        normalized === 'passed'
            ? 'bg-emerald-100 text-emerald-700'
            : normalized === 'failed'
              ? 'bg-red-100 text-red-700'
              : 'bg-amber-100 text-amber-700';

    return (
        <span
            className={`inline-flex rounded-full px-3 py-1 text-xs font-semibold capitalize ${className}`}
        >
            {readable(normalized)}
        </span>
    );
}

function Timeline({ release }) {
    const items = [
        {
            label: 'Created',
            complete: true,
            value: 'Release draft created',
        },
        {
            label: 'Submitted',
            complete: Boolean(
                release.submitted_at
            ),
            value: formatDate(
                release.submitted_at
            ),
        },
        {
            label: 'Approved',
            complete: Boolean(
                release.approved_at
            ),
            value: formatDate(
                release.approved_at
            ),
        },
        {
            label: 'Delivered',
            complete: Boolean(
                release.delivered_at
            ),
            value: formatDate(
                release.delivered_at
            ),
        },
        {
            label: 'Live',
            complete: Boolean(
                release.live_at
            ),
            value: formatDate(
                release.live_at
            ),
        },
    ];

    return (
        <div className="space-y-4">
            {items.map((item) => (
                <div
                    key={item.label}
                    className="flex gap-3"
                >
                    <div
                        className={[
                            'mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold',
                            item.complete
                                ? 'bg-emerald-100 text-emerald-700'
                                : 'bg-slate-100 text-slate-400',
                        ].join(' ')}
                    >
                        {item.complete ? '✓' : '○'}
                    </div>

                    <div>
                        <div className="font-semibold text-slate-900">
                            {item.label}
                        </div>

                        <div className="mt-0.5 text-sm text-slate-500">
                            {item.value}
                        </div>
                    </div>
                </div>
            ))}
        </div>
    );
}

/* W119: N6 - the release was sent for review */
function SubmittedPage({ release }) {
    const when = release.submitted_at
        ? new Intl.DateTimeFormat('en-IN', { day: '2-digit', month: 'short', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(release.submitted_at))
        : null;
    const art = release.artwork_url || (release.artwork_path ? `/storage/${release.artwork_path}` : null);
    const steps = [
        ['Draft', 'Done', 'bg-emerald-600', 'text-emerald-700'],
        ['In review', 'Now', 'bg-amber-500', 'text-amber-700'],
        ['Approved', 'UPC + ISRC given', 'bg-slate-200', 'text-slate-500'],
        ['Processing', 'Sent to stores', 'bg-slate-200', 'text-slate-500'],
        ['Live', 'Store links appear', 'bg-slate-200', 'text-slate-500'],
    ];

    return (
        <div className="mx-auto w-full max-w-[960px] space-y-4 py-6 sm:py-12">
            <section className="flex flex-wrap items-center gap-5 rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                {art ? (
                    <img src={art} alt="" className="h-24 w-24 shrink-0 rounded-xl border border-slate-200 object-cover" />
                ) : (
                    <div className="flex h-24 w-24 shrink-0 items-center justify-center rounded-xl bg-violet-50 text-3xl text-violet-400" aria-hidden="true">♪</div>
                )}
                <div className="min-w-0 flex-[1_1_260px]">
                    <span className="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold text-amber-800">In review</span>
                    <h1 className="mt-2 break-words text-[22px] font-bold text-slate-900">{release.title} was sent for review</h1>
                    <p className="mt-1 text-sm text-slate-600">
                        {when ? `Submitted ${when}. ` : ''}Editing is locked until the team answers.
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Link href="/releases/create" className="inline-flex h-10 items-center rounded-lg bg-violet-600 px-4 text-sm font-semibold text-white hover:bg-violet-700">
                        Create another
                    </Link>
                    <Link href="/releases" className="inline-flex h-10 items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                        All releases
                    </Link>
                </div>
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 className="text-sm font-bold text-slate-900">What happens next</h2>
                <ol className="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-5">
                    {steps.map(([name, note, bar, tone]) => (
                        <li key={name} className="min-w-0">
                            <div className={`h-1 rounded-full ${bar}`} />
                            <div className="mt-2 text-sm font-semibold text-slate-900">{name}</div>
                            <div className={`text-xs ${tone}`}>{note}</div>
                        </li>
                    ))}
                </ol>
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-6 text-sm leading-relaxed text-slate-600 shadow-sm">
                If something needs fixing, the release comes back as <b className="text-slate-900">Changes requested</b> with the team's note. Open it, fix it in the same 4 steps and press Submit again.
                <div className="mt-3">
                    <Link href={`/releases/${release.id}`} className="text-xs font-semibold text-violet-700 underline">
                        Open the release
                    </Link>
                </div>
            </section>
        </div>
    );
}
