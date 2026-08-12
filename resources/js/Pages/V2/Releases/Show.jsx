import {
    useEffect,
    useRef,
    useState,
} from 'react';

import {
    Head,
    Link,
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
                        href="/v2/releases"
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
                                href={`/v2/releases/${release.id}/edit`}
                                className="inline-flex rounded-xl bg-violet-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-violet-700"
                            >
                                Edit Release
                            </Link>
                        )}
                    </div>
                </div>

                {!release.editable && (
                    <div className="rounded-2xl border border-blue-200 bg-blue-50 px-5 py-4">
                        <div className="font-semibold text-blue-900">
                            This release is currently read-only.
                        </div>

                        <div className="mt-1 text-sm text-blue-700">
                            It has been submitted for review or is already being processed.
                        </div>
                    </div>
                )}

                <section className="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm">
                    <div className="grid gap-7 lg:grid-cols-[240px_1fr]">
                        <div className="aspect-square overflow-hidden rounded-2xl bg-slate-100">
                            {artworkUrl(
                                release.artwork_path
                            ) ? (
                                <img
                                    src={artworkUrl(
                                        release.artwork_path
                                    )}
                                    alt={release.title}
                                    className="h-full w-full object-cover"
                                />
                            ) : (
                                <div className="flex h-full items-center justify-center text-7xl text-slate-300">
                                    ♪
                                </div>
                            )}
                        </div>

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
