import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const timezoneOptions = [
    'Asia/Kolkata',
    'UTC',
    'Asia/Dubai',
    'Asia/Singapore',
    'Asia/Tokyo',
    'Europe/London',
    'Europe/Paris',
    'America/New_York',
    'America/Los_Angeles',
    'Australia/Sydney',
];

const territoryOptions = [
    { code: 'IN', name: 'India' },
    { code: 'US', name: 'United States' },
    { code: 'GB', name: 'United Kingdom' },
    { code: 'CA', name: 'Canada' },
    { code: 'AU', name: 'Australia' },
    { code: 'AE', name: 'United Arab Emirates' },
    { code: 'SG', name: 'Singapore' },
    { code: 'DE', name: 'Germany' },
    { code: 'FR', name: 'France' },
    { code: 'JP', name: 'Japan' },
];

export default function DistributionStep({
    release,
    distributionStores = [],
}) {
    const defaultStoreIds = useMemo(
        () =>
            distributionStores
                .filter((store) =>
                    Boolean(store.default_selected)
                )
                .map((store) => Number(store.id)),
        [distributionStores]
    );

    const savedStoreIds = Array.isArray(release?.stores)
        ? release.stores.map(Number)
        : [];

    const savedTerritories = Array.isArray(
        release?.territories
    )
        ? release.territories
        : [];

    const [selectedStores, setSelectedStores] =
        useState(
            savedStoreIds.length > 0
                ? savedStoreIds
                : defaultStoreIds
        );

    const [worldwide, setWorldwide] = useState(
        release?.worldwide === null ||
            release?.worldwide === undefined
            ? true
            : Boolean(release.worldwide)
    );

    const [territories, setTerritories] =
        useState(savedTerritories);

    const [releaseTimezone, setReleaseTimezone] =
        useState(
            release?.release_timezone ||
                'Asia/Kolkata'
        );

    const [preOrder, setPreOrder] = useState(
        Boolean(release?.pre_order)
    );

    const [processing, setProcessing] =
        useState(false);

    const [message, setMessage] =
        useState('');

    const toggleStore = (storeId) => {
        const id = Number(storeId);

        setSelectedStores((current) =>
            current.includes(id)
                ? current.filter(
                      (item) => item !== id
                  )
                : [...current, id]
        );
    };

    const toggleTerritory = (code) => {
        setTerritories((current) =>
            current.includes(code)
                ? current.filter(
                      (item) => item !== code
                  )
                : [...current, code]
        );
    };

    const selectAll = () => {
        setSelectedStores(
            distributionStores.map(
                (store) => Number(store.id)
            )
        );
    };

    const clearAll = () => {
        setSelectedStores([]);
    };

    const restoreDefaults = () => {
        setSelectedStores(defaultStoreIds);
    };

    const saveDistribution = () => {
        if (!release?.id) {
            setMessage(
                'Save the release before selecting stores.'
            );
            return;
        }

        if (selectedStores.length === 0) {
            setMessage(
                'Select at least one distribution store.'
            );
            return;
        }

        if (
            !worldwide &&
            territories.length === 0
        ) {
            setMessage(
                'Select at least one territory or enable Worldwide.'
            );
            return;
        }

        setMessage('');
        setProcessing(true);

        router.patch(
            `/v2/releases/${release.id}/distribution`,
            {
                stores: selectedStores,
                worldwide,
                territories: worldwide
                    ? []
                    : territories,
                release_timezone:
                    releaseTimezone,
                pre_order: preOrder,
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setMessage(
                        'Stores and distribution settings saved successfully.'
                    );
                },

                onError: (errors) => {
                    const firstError =
                        Object.values(
                            errors ?? {}
                        )[0];

                    setMessage(
                        firstError ||
                            'Distribution settings could not be saved.'
                    );
                },

                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <div className="space-y-7">
            <div className="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Stores & Distribution
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Select DSPs, territories and
                        delivery options.
                    </p>
                </div>

                <div className="flex flex-wrap gap-2">
                    <button
                        type="button"
                        onClick={selectAll}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Select All
                    </button>

                    <button
                        type="button"
                        onClick={restoreDefaults}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Defaults
                    </button>

                    <button
                        type="button"
                        onClick={clearAll}
                        className="rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Clear All
                    </button>
                </div>
            </div>

            <section className="rounded-2xl border border-slate-200 bg-white p-5">
                <div className="mb-5 flex items-center justify-between gap-4">
                    <div>
                        <h3 className="font-semibold text-slate-900">
                            Distribution Stores
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            {selectedStores.length} of{' '}
                            {distributionStores.length}{' '}
                            selected
                        </p>
                    </div>

                    <span className="rounded-full bg-violet-100 px-3 py-1 text-xs font-semibold text-violet-700">
                        Minimum 1
                    </span>
                </div>

                {distributionStores.length === 0 ? (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                        No active distribution stores
                        are available.
                    </div>
                ) : (
                    <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                        {distributionStores.map(
                            (store) => {
                                const selected =
                                    selectedStores.includes(
                                        Number(
                                            store.id
                                        )
                                    );

                                return (
                                    <button
                                        key={store.id}
                                        type="button"
                                        onClick={() =>
                                            toggleStore(
                                                store.id
                                            )
                                        }
                                        className={`flex min-h-24 items-center gap-4 rounded-2xl border p-4 text-left transition ${
                                            selected
                                                ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100'
                                                : 'border-slate-200 bg-white hover:border-violet-300 hover:bg-slate-50'
                                        }`}
                                    >
                                        <div className="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-xl bg-slate-100">
                                            {store.logo_path ? (
                                                <img
                                                    src={
                                                        store.logo_path.startsWith(
                                                            'http'
                                                        )
                                                            ? store.logo_path
                                                            : `/storage/${store.logo_path}`
                                                    }
                                                    alt={
                                                        store.name
                                                    }
                                                    className="h-full w-full object-contain p-2"
                                                />
                                            ) : (
                                                <span className="text-lg font-bold text-slate-500">
                                                    {store.name
                                                        ?.charAt(
                                                            0
                                                        )
                                                        ?.toUpperCase()}
                                                </span>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="truncate font-semibold text-slate-900">
                                                {
                                                    store.name
                                                }
                                            </div>

                                            <div className="mt-1 text-xs text-slate-500">
                                                {store.default_selected
                                                    ? 'Default store'
                                                    : 'Optional store'}
                                            </div>
                                        </div>

                                        <div
                                            className={`flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs font-bold ${
                                                selected
                                                    ? 'border-violet-600 bg-violet-600 text-white'
                                                    : 'border-slate-300 bg-white text-transparent'
                                            }`}
                                        >
                                            ✓
                                        </div>
                                    </button>
                                );
                            }
                        )}
                    </div>
                )}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 className="font-semibold text-slate-900">
                    Release Territories
                </h3>

                <p className="mt-1 text-sm text-slate-500">
                    Choose worldwide delivery or
                    selected countries.
                </p>

                <label className="mt-5 flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 p-4">
                    <div>
                        <div className="font-semibold text-slate-900">
                            Worldwide Distribution
                        </div>

                        <div className="mt-1 text-sm text-slate-500">
                            Deliver in every supported
                            territory.
                        </div>
                    </div>

                    <input
                        type="checkbox"
                        checked={worldwide}
                        onChange={(event) =>
                            setWorldwide(
                                event.target.checked
                            )
                        }
                        className="h-5 w-5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                    />
                </label>

                {!worldwide && (
                    <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                        {territoryOptions.map(
                            (territory) => {
                                const selected =
                                    territories.includes(
                                        territory.code
                                    );

                                return (
                                    <button
                                        key={
                                            territory.code
                                        }
                                        type="button"
                                        onClick={() =>
                                            toggleTerritory(
                                                territory.code
                                            )
                                        }
                                        className={`rounded-xl border px-4 py-3 text-left text-sm transition ${
                                            selected
                                                ? 'border-violet-500 bg-violet-50 font-semibold text-violet-800'
                                                : 'border-slate-200 bg-white text-slate-700 hover:border-violet-300'
                                        }`}
                                    >
                                        <div>
                                            {
                                                territory.name
                                            }
                                        </div>

                                        <div className="mt-1 text-xs text-slate-400">
                                            {
                                                territory.code
                                            }
                                        </div>
                                    </button>
                                );
                            }
                        )}
                    </div>
                )}
            </section>

            <section className="rounded-2xl border border-slate-200 bg-white p-5">
                <h3 className="font-semibold text-slate-900">
                    Delivery Options
                </h3>

                <div className="mt-5 grid gap-5 md:grid-cols-2">
                    <div>
                        <label className="mb-2 block text-sm font-medium text-slate-700">
                            Release Timezone
                        </label>

                        <select
                            value={releaseTimezone}
                            onChange={(event) =>
                                setReleaseTimezone(
                                    event.target.value
                                )
                            }
                            className="w-full rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm text-slate-900 outline-none focus:border-violet-500 focus:ring-2 focus:ring-violet-100"
                        >
                            {timezoneOptions.map(
                                (timezone) => (
                                    <option
                                        key={
                                            timezone
                                        }
                                        value={
                                            timezone
                                        }
                                    >
                                        {timezone}
                                    </option>
                                )
                            )}
                        </select>
                    </div>

                    <label className="flex cursor-pointer items-center justify-between rounded-2xl border border-slate-200 p-4">
                        <div>
                            <div className="font-semibold text-slate-900">
                                Enable Pre-order
                            </div>

                            <div className="mt-1 text-sm text-slate-500">
                                Enable where supported.
                            </div>
                        </div>

                        <input
                            type="checkbox"
                            checked={preOrder}
                            onChange={(event) =>
                                setPreOrder(
                                    event.target.checked
                                )
                            }
                            className="h-5 w-5 rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                        />
                    </label>
                </div>
            </section>

            {message && (
                <div
                    className={`rounded-xl border p-4 text-sm ${
                        message.includes(
                            'successfully'
                        )
                            ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                            : 'border-amber-200 bg-amber-50 text-amber-800'
                    }`}
                >
                    {message}
                </div>
            )}

            <div className="flex justify-end">
                <button
                    type="button"
                    onClick={saveDistribution}
                    disabled={processing}
                    className="rounded-xl bg-violet-600 px-6 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:opacity-50"
                >
                    {processing
                        ? 'Saving...'
                        : 'Save Stores & Distribution'}
                </button>
            </div>
        </div>
    );
}
