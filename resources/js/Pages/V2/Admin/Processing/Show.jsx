import {
    Head,
    Link,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

export default function Show({
    role = 'admin',
    release,
    checks = {},
    validationErrors = {},
    deliverySummary = {},
}) {
    const [
        processing,
        setProcessing,
    ] = useState('');

    const runAction = (
        name,
        endpoint
    ) => {
        setProcessing(name);

        router.post(
            endpoint,
            {},
            {
                preserveScroll: true,

                onFinish: () =>
                    setProcessing(''),
            }
        );
    };

    const checklist = [
        [
            'Metadata',
            checks.metadata,
        ],
        [
            'Artists',
            checks.artists,
        ],
        [
            'Tracks',
            checks.tracks,
        ],
        [
            'Stores & Territory',
            checks.distribution,
        ],
        [
            'Audio Validation',
            checks.audio,
        ],
        [
            'ISRC Assigned',
            checks.isrc,
        ],
        [
            'UPC Assigned',
            checks.upc,
        ],
        [
            'DSP Delivery Created',
            checks.delivery_initialised,
        ],
    ];

    return (
        <PanelLayout
            role={role}
            title="Process Release"
            subtitle={release.title}
        >
            <Head
                title={`Process ${release.title}`}
            />

            <div className="space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href="/v2/admin/processing"
                        className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Processing Queue
                    </Link>

                    <span className="rounded-full bg-blue-100 px-4 py-2 text-sm font-semibold capitalize text-blue-700">
                        {release.status.replaceAll(
                            '_',
                            ' '
                        )}
                    </span>
                </div>

                <div className="grid gap-6 xl:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <Section title="Processing Checklist">
                            <div className="grid gap-3 sm:grid-cols-2">
                                {checklist.map(
                                    ([
                                        label,
                                        passed,
                                    ]) => (
                                        <div
                                            key={
                                                label
                                            }
                                            className={[
                                                'flex items-center justify-between rounded-xl border p-4',
                                                passed
                                                    ? 'border-emerald-200 bg-emerald-50'
                                                    : 'border-amber-200 bg-amber-50',
                                            ].join(
                                                ' '
                                            )}
                                        >
                                            <span className="text-sm font-semibold text-slate-800">
                                                {
                                                    label
                                                }
                                            </span>

                                            <span
                                                className={[
                                                    'flex h-7 w-7 items-center justify-center rounded-full text-sm font-bold text-white',
                                                    passed
                                                        ? 'bg-emerald-600'
                                                        : 'bg-amber-500',
                                                ].join(
                                                    ' '
                                                )}
                                            >
                                                {passed
                                                    ? '✓'
                                                    : '!'}
                                            </span>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        <Section title="Tracks">
                            <div className="space-y-3">
                                {(release.tracks ??
                                    []).map(
                                    (
                                        track,
                                        index
                                    ) => (
                                        <div
                                            key={
                                                track.id
                                            }
                                            className="rounded-xl border border-slate-200 p-4"
                                        >
                                            <div className="flex flex-wrap items-center justify-between gap-3">
                                                <div>
                                                    <div className="font-semibold text-slate-900">
                                                        {index +
                                                            1}
                                                        .{' '}
                                                        {
                                                            track.title
                                                        }
                                                    </div>

                                                    <div className="mt-1 text-xs text-slate-500">
                                                        ISRC:{' '}
                                                        {track.isrc ||
                                                            'Pending'}
                                                    </div>
                                                </div>

                                                <span
                                                    className={[
                                                        'rounded-full px-3 py-1 text-xs font-semibold capitalize',
                                                        track.audio_validation_status ===
                                                        'passed'
                                                            ? 'bg-emerald-100 text-emerald-700'
                                                            : track.audio_validation_status ===
                                                                'failed'
                                                              ? 'bg-red-100 text-red-700'
                                                              : 'bg-amber-100 text-amber-700',
                                                    ].join(
                                                        ' '
                                                    )}
                                                >
                                                    Audio:{' '}
                                                    {track.audio_validation_status ||
                                                        'pending'}
                                                </span>
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>

                        {Object.keys(
                            validationErrors
                        ).length > 0 && (
                            <Section title="Validation Errors">
                                <div className="space-y-2">
                                    {Object.entries(
                                        validationErrors
                                    ).map(
                                        ([
                                            field,
                                            message,
                                        ]) => (
                                            <div
                                                key={
                                                    field
                                                }
                                                className="rounded-xl border border-red-200 bg-red-50 p-4"
                                            >
                                                <div className="text-xs font-semibold uppercase text-red-600">
                                                    {
                                                        field
                                                    }
                                                </div>

                                                <div className="mt-1 text-sm text-red-700">
                                                    {
                                                        message
                                                    }
                                                </div>
                                            </div>
                                        )
                                    )}
                                </div>
                            </Section>
                        )}

                        <Section title="DSP Delivery Summary">
                            <div className="grid gap-3 sm:grid-cols-3">
                                {[
                                    'total',
                                    'pending',
                                    'processing',
                                    'delivered',
                                    'live',
                                    'failed',
                                ].map(
                                    (
                                        status
                                    ) => (
                                        <div
                                            key={
                                                status
                                            }
                                            className="rounded-xl bg-slate-50 p-4"
                                        >
                                            <div className="text-xs font-semibold uppercase text-slate-500">
                                                {
                                                    status
                                                }
                                            </div>

                                            <div className="mt-2 text-2xl font-bold text-slate-900">
                                                {deliverySummary[
                                                    status
                                                ] ??
                                                    0}
                                            </div>
                                        </div>
                                    )
                                )}
                            </div>
                        </Section>
                    </div>

                    <aside>
                        <div className="sticky top-24 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                            <h3 className="font-semibold text-slate-900">
                                Processing Actions
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                Complete each step before
                                starting DSP delivery.
                            </p>

                            <div className="mt-5 space-y-3">
                                <Action
                                    label="Generate ISRC & UPC"
                                    loading={
                                        processing ===
                                        'identifiers'
                                    }
                                    onClick={() =>
                                        runAction(
                                            'identifiers',
                                            `/v2/admin/processing/${release.id}/generate-identifiers`
                                        )
                                    }
                                />

                                <Action
                                    label="Validate All Audio"
                                    loading={
                                        processing ===
                                        'audio'
                                    }
                                    onClick={() =>
                                        runAction(
                                            'audio',
                                            `/v2/admin/processing/${release.id}/validate-audio`
                                        )
                                    }
                                />

                                <Action
                                    label={
                                        checks.delivery_initialised
                                            ? 'Open DSP Delivery'
                                            : 'Initialize DSP Delivery'
                                    }
                                    loading={
                                        processing ===
                                        'delivery'
                                    }
                                    className="bg-emerald-600 hover:bg-emerald-700"
                                    onClick={() => {
                                        if (
                                            checks.delivery_initialised
                                        ) {
                                            window.location.href =
                                                `/v2/admin/distribution/${release.id}`;

                                            return;
                                        }

                                        runAction(
                                            'delivery',
                                            `/v2/admin/processing/${release.id}/initialise-delivery`
                                        );
                                    }}
                                />
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

function Action({
    label,
    loading,
    onClick,
    className =
        'bg-violet-600 hover:bg-violet-700',
}) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={loading}
            className={[
                'w-full rounded-xl px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-50',
                className,
            ].join(' ')}
        >
            {loading
                ? 'Processing...'
                : label}
        </button>
    );
}
