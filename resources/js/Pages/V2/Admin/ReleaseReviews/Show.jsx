import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'admin',
    release,
    submissionChecklist = {},
    availableActions = {},
    statusLogs = [],
}) {
    const [notes, setNotes] = useState('');
    const [processing, setProcessing] = useState(false);



    const action = (endpoint, payload = {}) => {
        setProcessing(true);

        router.post(endpoint, payload, {
            preserveScroll: false,
            onFinish: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <PanelLayout
            role={role}
            title="Review Release"
            subtitle={release.title}
        >
            <Head title={`Review ${release.title}`} />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/release-reviews"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Back to Queue
                    </Link>

                    <span className="rounded-full bg-amber-100 px-4 py-2 text-sm font-semibold capitalize text-amber-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Section title="Release Information">
                            <InfoGrid
                                rows={[
                                    ['Title', release.title],
                                    [
                                        'Primary Artist',
                                        release.primary_artist_name,
                                    ],
                                    ['Label', release.label_name],
                                    ['UPC', release.upc || 'Pending'],
                                    [
                                        'Catalogue Number',
                                        release.catalog_number,
                                    ],
                                    [
                                        'Release Date',
                                        release.digital_release_date,
                                    ],
                                    ['Language', release.language],
                                    [
                                        'Genre',
                                        release.primary_genre,
                                    ],
                                ]}
                            />
                        </Section>

                        <Section title="Tracks">
                            <div className="space-y-3">
                                {(release.tracks ?? []).map(
                                    (track, index) => (
                                        <div
                                            key={track.id}
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="flex items-center justify-between gap-4">
                                                <div>
                                                    <div className="font-semibold text-slate-900">
                                                        {index + 1}.{' '}
                                                        {track.title}
                                                    </div>

                                                    <div className="mt-1 text-sm text-slate-500">
                                                        ISRC:{' '}
                                                        {track.isrc ||
                                                            'Pending'}
                                                    </div>
                                                </div>

                                                <div className="text-sm text-slate-500">
                                                    {track.audio_path
                                                        ? 'WAV uploaded'
                                                        : 'Audio missing'}
                                                </div>
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        <Section title="Validation Checklist">
                            <pre className="overflow-x-auto rounded-xl bg-slate-950 p-4 text-xs text-slate-100">
                                {JSON.stringify(
                                    submissionChecklist,
                                    null,
                                    2
                                )}
                            </pre>
                        </Section>

                        <Section title="Status History">
                            <div className="space-y-3">
                                {statusLogs.length > 0 ? (
                                    statusLogs.map((log) => (
                                        <div
                                            key={log.id}
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="font-semibold capitalize text-slate-900">
                                                {(
                                                    log.new_status ||
                                                    log.action ||
                                                    'Updated'
                                                ).replaceAll(
                                                    '_',
                                                    ' '
                                                )}
                                            </div>

                                            <div className="mt-1 text-sm text-slate-500">
                                                {log.remarks ||
                                                    log.notes ||
                                                    log.reason ||
                                                    'No remarks'}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <div className="text-sm text-slate-500">
                                        No status history available.
                                    </div>
                                )}
                            </div>
                        </Section>
                    </div>

                    <aside className="space-y-5">
                        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-slate-900">
                                Review Actions
                            </h3>

                            <textarea
                                value={notes}
                                onChange={(event) =>
                                    setNotes(
                                        event.target.value
                                    )
                                }
                                placeholder="Add remarks, rejection reason or change request..."
                                className="mt-4 min-h-32 w-full rounded-xl border border-slate-300 p-3 text-sm outline-none focus:border-violet-500"
                            />

                            <div className="mt-4 space-y-3">
                                {availableActions.approve && (
                                    <ActionButton
                                        label="Approve Release"
                                        className="bg-emerald-600 hover:bg-emerald-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/approve`,
                                                {
                                                    remarks:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {notes.trim().length < 3 &&
                                    (availableActions.request_changes ||
                                        availableActions.reject) && (
                                        <p className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm font-medium text-amber-700">
                                            Enter at least 3 characters in Review Remarks to enable Request Changes or Reject Release.
                                        </p>
                                    )}

                                {availableActions.request_changes && (
                                    <ActionButton
                                        label="Request Changes"
                                        className="bg-amber-500 hover:bg-amber-600"
                                        disabled={
                                            processing ||
                                            notes.trim()
                                                .length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/request-changes`,
                                                {
                                                    notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.reject && (
                                    <ActionButton
                                        label="Reject Release"
                                        className="bg-red-600 hover:bg-red-700"
                                        disabled={
                                            processing ||
                                            notes.trim()
                                                .length < 3
                                        }
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/reject`,
                                                {
                                                    reason:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}

                                {availableActions.start_processing && (
                                    <ActionButton
                                        label="Start Processing"
                                        className="bg-blue-600 hover:bg-blue-700"
                                        disabled={processing}
                                        onClick={() =>
                                            action(
                                                `/v2/admin/release-reviews/${release.id}/start-processing`,
                                                {
                                                    remarks:
                                                        notes,
                                                }
                                            )
                                        }
                                    />
                                )}
                            </div>
                        </div>
                    </aside>
                </div>
            </div>
        </PanelLayout>
    );
}

function Section({
    title,
    children,
}) {
    return (
        <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 className="mb-4 text-lg font-semibold text-slate-900">
                {title}
            </h2>

            {children}
        </section>
    );
}

function InfoGrid({ rows }) {
    return (
        <div className="grid gap-4 sm:grid-cols-2">
            {rows.map(([label, value]) => (
                <div
                    key={label}
                    className="rounded-xl bg-slate-50 p-4"
                >
                    <div className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                        {label}
                    </div>

                    <div className="mt-2 text-sm font-medium text-slate-900">
                        {value || '—'}
                    </div>
                </div>
            ))}
        </div>
    );
}

function ActionButton({
    label,
    className,
    disabled,
    onClick,
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onClick}
            className={[
                'w-full rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {label}
        </button>
    );
}
