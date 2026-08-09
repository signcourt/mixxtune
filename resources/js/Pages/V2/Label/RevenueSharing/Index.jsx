import {
    Head,
    router,
} from '@inertiajs/react';

import {
    useState,
} from 'react';

import PanelLayout from '@/V2/Shared/Layouts/PanelLayout';

function ShareRow({
    item,
}) {
    const [
        percent,
        setPercent,
    ] = useState(
        Number(
            item.revenue_share_percent ?? 0
        )
    );

    const [
        showShare,
        setShowShare,
    ] = useState(
        Boolean(
            item.show_revenue_share
        )
    );

    const [
        processing,
        setProcessing,
    ] = useState(false);

    const safePercent = Math.min(
        100,
        Math.max(
            0,
            Number(percent) || 0
        )
    );

    const masterPercent =
        100 - safePercent;

    const save = () => {
        setProcessing(true);

        router.patch(
            `/v2/label/revenue-sharing/${item.type}/${item.id}`,
            {
                revenue_share_percent:
                    safePercent,

                show_revenue_share:
                    showShare,
            },
            {
                preserveScroll: true,

                onFinish: () =>
                    setProcessing(false),
            }
        );
    };

    const toggle = () => {
        if (!item.share_id) {
            return;
        }

        router.patch(
            `/v2/label/revenue-sharing/${item.share_id}/toggle`,
            {},
            {
                preserveScroll: true,
            }
        );
    };

    return (
        <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
            <div className="flex flex-col gap-5 xl:flex-row xl:items-center xl:justify-between">
                <div className="min-w-0 xl:w-64">
                    <div className="flex items-center gap-2">
                        <h3 className="truncate text-base font-bold text-slate-900">
                            {item.name}
                        </h3>

                        <span className="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-semibold capitalize text-slate-600">
                            {item.type}
                        </span>
                    </div>

                    <p className="mt-1 text-xs capitalize text-slate-500">
                        {item.status ?? 'active'}
                    </p>

                    {!item.share_configured && (
                        <p className="mt-2 text-xs font-medium text-amber-600">
                            No share configured — master keeps 100%
                        </p>
                    )}
                </div>

                <div className="grid flex-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Child Share
                        </label>

                        <div className="relative">
                            <input
                                type="number"
                                min="0"
                                max="100"
                                step="0.01"
                                value={percent}
                                onChange={(event) =>
                                    setPercent(
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border border-slate-300 px-4 py-3 pr-10 text-sm font-semibold outline-none focus:border-violet-500"
                            />

                            <span className="absolute right-4 top-3 text-sm font-semibold text-slate-400">
                                %
                            </span>
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Master Keeps
                        </label>

                        <div className="rounded-xl bg-violet-50 px-4 py-3 text-sm font-bold text-violet-700">
                            {masterPercent.toFixed(2)}%
                        </div>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Percentage Visibility
                        </label>

                        <button
                            type="button"
                            onClick={() =>
                                setShowShare(
                                    (current) =>
                                        !current
                                )
                            }
                            className={[
                                'w-full rounded-xl border px-4 py-3 text-sm font-semibold transition',
                                showShare
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-300 bg-slate-50 text-slate-600',
                            ].join(' ')}
                        >
                            {showShare
                                ? 'Visible to beneficiary'
                                : 'Hidden from beneficiary'}
                        </button>
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Split Status
                        </label>

                        <div
                            className={[
                                'rounded-xl px-4 py-3 text-center text-sm font-bold',
                                item.is_active
                                    ? 'bg-emerald-50 text-emerald-700'
                                    : 'bg-slate-100 text-slate-600',
                            ].join(' ')}
                        >
                            {item.is_active
                                ? 'Active'
                                : 'Inactive'}
                        </div>
                    </div>
                </div>

                <div className="flex gap-2 xl:w-56 xl:justify-end">
                    <button
                        type="button"
                        disabled={processing}
                        onClick={save}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-bold text-white transition hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                    >
                        {processing
                            ? 'Saving...'
                            : 'Save Share'}
                    </button>

                    {item.share_id && (
                        <button
                            type="button"
                            onClick={toggle}
                            className="rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                        >
                            {item.is_active
                                ? 'Disable'
                                : 'Enable'}
                        </button>
                    )}
                </div>
            </div>

            <div className="mt-4 rounded-xl bg-slate-50 px-4 py-3 text-sm text-slate-600">
                ₹100 revenue example:
                {' '}
                <strong className="text-slate-900">
                    ₹{safePercent.toFixed(2)}
                </strong>
                {' '}
                goes to {item.name} and
                {' '}
                <strong className="text-violet-700">
                    ₹{masterPercent.toFixed(2)}
                </strong>
                {' '}
                remains with the master label.
            </div>
        </div>
    );
}

export default function Index({
    master,
    beneficiaries = [],
}) {
    const labels = beneficiaries.filter(
        (item) =>
            item.type === 'label'
    );

    const artists = beneficiaries.filter(
        (item) =>
            item.type === 'artist'
    );

    return (
        <PanelLayout
            role="label"
            title="Revenue Sharing"
            subtitle={`${master.name} — manage direct beneficiary shares`}
        >
            <Head title="Revenue Sharing" />

            <div className="space-y-6">
                <div className="grid gap-4 md:grid-cols-3">
                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Master Label
                        </p>

                        <p className="mt-2 text-xl font-bold text-slate-900">
                            {master.name}
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Direct Sub-Labels
                        </p>

                        <p className="mt-2 text-3xl font-bold text-violet-700">
                            {labels.length}
                        </p>
                    </div>

                    <div className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        <p className="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Direct Artists
                        </p>

                        <p className="mt-2 text-3xl font-bold text-violet-700">
                            {artists.length}
                        </p>
                    </div>
                </div>

                <div className="rounded-2xl border border-violet-200 bg-violet-50 p-5">
                    <h2 className="font-bold text-violet-900">
                        Master Revenue Rule
                    </h2>

                    <p className="mt-2 text-sm leading-6 text-violet-800">
                        All catalogue revenue belongs to the master first.
                        A configured percentage is credited to the direct
                        Sub-Label or Artist. The remaining percentage stays
                        with the master. Sub-Labels and Artists cannot create
                        another hierarchy.
                    </p>
                </div>

                <section>
                    <div className="mb-4">
                        <h2 className="text-lg font-bold text-slate-900">
                            Sub-Labels
                        </h2>

                        <p className="text-sm text-slate-500">
                            Revenue shares for direct child labels.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {labels.length ? (
                            labels.map(
                                (item) => (
                                    <ShareRow
                                        key={`label-${item.id}`}
                                        item={item}
                                    />
                                )
                            )
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                                No direct Sub-Labels found.
                            </div>
                        )}
                    </div>
                </section>

                <section>
                    <div className="mb-4">
                        <h2 className="text-lg font-bold text-slate-900">
                            Artists
                        </h2>

                        <p className="text-sm text-slate-500">
                            Revenue shares for artists directly owned by this master.
                        </p>
                    </div>

                    <div className="space-y-3">
                        {artists.length ? (
                            artists.map(
                                (item) => (
                                    <ShareRow
                                        key={`artist-${item.id}`}
                                        item={item}
                                    />
                                )
                            )
                        ) : (
                            <div className="rounded-2xl border border-dashed border-slate-300 bg-white p-10 text-center text-sm text-slate-500">
                                No direct Artists found.
                            </div>
                        )}
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
