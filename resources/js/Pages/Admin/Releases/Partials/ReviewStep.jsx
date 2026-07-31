function formatDate(value) {
    if (!value) return '—';

    return new Date(value).toLocaleDateString('en-GB', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    });
}

export default function ReviewStep({
    release,
    data,
    onEditStep,
    availableStores = [],
}) {
    const tracks = release?.tracks ?? [];
    const excludedStoreIds = (data.excluded_store_ids ?? []).map(String);

    const stores = availableStores.filter(
        (store) => !excludedStoreIds.includes(String(store.id))
    );

    const territories = data.territories ?? [];

    const checks = [
        {
            label: 'Release title',
            passed: Boolean(data.title),
            step: 1,
        },
        {
            label: 'Primary artist',
            passed: Boolean(data.artist_id || data.primary_artist_name),
            step: 1,
        },
        {
            label: 'Label',
            passed: Boolean(data.label_id),
            step: 1,
        },
        {
            label: 'Catalogue number',
            passed: Boolean(data.catalog_number),
            step: 1,
        },
        {
            label: 'Digital release date',
            passed: Boolean(data.digital_release_date),
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
                tracks.every((track) => Boolean(track.audio_path)),
            step: 2,
        },
        {
            label: 'At least one store selected',
            passed: stores.length > 0,
            step: 3,
        },
        {
            label: 'Territory selected',
            passed:
                Boolean(data.worldwide) ||
                territories.length > 0,
            step: 3,
        },
    ];

    const allPassed = checks.every((check) => check.passed);

    return (
        <div className="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Review & Publish
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Review release metadata, tracks and distribution settings.
                    </p>
                </div>

                <ReviewCard
                    title="Release Information"
                    onEdit={() => onEditStep(1)}
                >
                    <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        <Info label="Title" value={data.title || '—'} />

                        <Info
                            label="Type"
                            value={data.release_type || '—'}
                        />

                        <Info
                            label="Primary Artist"
                            value={data.primary_artist_name || '—'}
                        />

                        <Info
                            label="Label ID"
                            value={data.label_id || '—'}
                        />

                        <Info
                            label="Catalogue Number"
                            value={data.catalog_number || '—'}
                        />

                        <Info
                            label="UPC"
                            value={data.upc || 'Pending'}
                        />

                        <Info
                            label="Release Date"
                            value={formatDate(data.digital_release_date)}
                        />

                        <Info
                            label="Language"
                            value={data.language || '—'}
                        />

                        <Info
                            label="Genre"
                            value={data.primary_genre || '—'}
                        />
                    </div>
                </ReviewCard>

                <ReviewCard
                    title="Tracks"
                    onEdit={() => onEditStep(2)}
                    badge={`${tracks.length} ${tracks.length === 1 ? 'Track' : 'Tracks'}`}
                >
                    <div className="space-y-3">
                        {tracks.length === 0 ? (
                            <button
                                type="button"
                                onClick={() => onEditStep(2)}
                                className="w-full rounded-xl bg-amber-50 px-4 py-4 text-left text-sm text-amber-700 hover:bg-amber-100"
                            >
                                No tracks added. Click here to add tracks.
                            </button>
                        ) : (
                            tracks.map((track, index) => (
                                <div
                                    key={track.id}
                                    className="flex flex-col gap-3 rounded-xl border border-slate-200 px-4 py-3 sm:flex-row sm:items-center"
                                >
                                    <div className="flex h-10 w-10 items-center justify-center rounded-lg bg-slate-100 text-sm font-semibold text-slate-700">
                                        {index + 1}
                                    </div>

                                    <div className="min-w-0 flex-1">
                                        <div className="truncate font-semibold text-slate-900">
                                            {track.title}
                                        </div>

                                        <div className="mt-1 text-xs text-slate-500">
                                            {track.primary_artist_name}
                                            {' · '}
                                            {track.isrc || 'ISRC pending'}
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-3">
                                        <div className="max-w-xs truncate text-xs text-slate-500">
                                            {track.audio_original_name ||
                                                'WAV missing'}
                                        </div>

                                        <button
                                            type="button"
                                            onClick={() =>
                                                onEditStep(2, track.id)
                                            }
                                            title="Edit track"
                                            aria-label={`Edit ${track.title}`}
                                            className="flex h-9 w-9 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                                        >
                                            ✎
                                        </button>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ReviewCard>

                <ReviewCard
                    title="Distribution"
                    onEdit={() => onEditStep(3)}
                >
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Info
                            label="Stores Selected"
                            value={stores.length}
                        />

                        <Info
                            label="Territories"
                            value={
                                data.worldwide
                                    ? 'Worldwide'
                                    : `${territories.length} selected`
                            }
                        />

                        <Info
                            label="Timezone"
                            value={
                                data.release_timezone || 'Asia/Kolkata'
                            }
                        />

                        <Info
                            label="Pre-order"
                            value={data.pre_order ? 'Enabled' : 'Disabled'}
                        />
                    </div>

                    {stores.length > 0 && (
                        <div className="mt-5 flex flex-wrap gap-2">
                            {stores.map((store) => (
                                <span
                                    key={store.id}
                                    className="rounded-full bg-violet-50 px-3 py-1 text-xs font-semibold text-violet-700"
                                >
                                    {store.name}
                                </span>
                            ))}
                        </div>
                    )}
                </ReviewCard>
            </div>

            <aside className="space-y-5">
                <div className="rounded-2xl border border-slate-200 bg-slate-50 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Validation Checklist
                    </h3>

                    <div className="mt-4 space-y-3">
                        {checks.map((check) => (
                            <div
                                key={check.label}
                                className="flex items-center gap-3"
                            >
                                <div
                                    className={`flex h-7 w-7 shrink-0 items-center justify-center rounded-full text-xs font-bold ${
                                        check.passed
                                            ? 'bg-emerald-100 text-emerald-700'
                                            : 'bg-red-100 text-red-700'
                                    }`}
                                >
                                    {check.passed ? '✓' : '!'}
                                </div>

                                <span
                                    className={`min-w-0 flex-1 text-sm ${
                                        check.passed
                                            ? 'text-slate-700'
                                            : 'text-red-700'
                                    }`}
                                >
                                    {check.label}
                                </span>

                                <button
                                    type="button"
                                    onClick={() => onEditStep(check.step)}
                                    title="Edit this section"
                                    aria-label={`Edit ${check.label}`}
                                    className="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg border border-slate-300 bg-white text-slate-500 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
                                >
                                    ✎
                                </button>
                            </div>
                        ))}
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
                            : 'Use the pencil buttons to correct missing information.'}
                    </p>
                </div>
            </aside>
        </div>
    );
}

function ReviewCard({ title, onEdit, badge, children }) {
    return (
        <section className="rounded-2xl border border-slate-200 p-5">
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
                    title={`Edit ${title}`}
                    aria-label={`Edit ${title}`}
                    className="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-300 bg-white text-lg text-slate-600 hover:border-violet-300 hover:bg-violet-50 hover:text-violet-700"
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

            <div className="mt-1 truncate text-sm font-semibold capitalize text-slate-900">
                {value}
            </div>
        </div>
    );
}
