import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const RELEASES_BASE_PATH =
    typeof window !== 'undefined'
    && window.location.pathname.startsWith('/artist')
        ? '/artist/releases'
        : '/v2/releases';

const countries = [
    ['IN', 'India'],
    ['US', 'United States'],
    ['GB', 'United Kingdom'],
    ['CA', 'Canada'],
    ['AU', 'Australia'],
    ['AE', 'United Arab Emirates'],
    ['SG', 'Singapore'],
    ['DE', 'Germany'],
    ['FR', 'France'],
    ['JP', 'Japan'],
];

export default function DistributionStep({
    mode = 'combined',
    release,
    distributionStores = [],
    initialSection = null,
    onSaved = () => {},
    onMascotEvent = () => {},
}) {
    const showStores =
        mode === 'combined' ||
        mode === 'stores';

    const showTerritory =
        mode === 'combined' ||
        mode === 'territory';


    const allStoreIds = useMemo(
        () =>
            distributionStores.map(
                (store) => Number(store.id)
            ),
        [distributionStores]
    );

    const savedStores = Array.isArray(
        release?.stores
    )
        ? release.stores.map(Number)
        : [];

    const [selectedStores, setSelectedStores] =
        useState(
            savedStores.length > 0
                ? savedStores
                : allStoreIds
        );

    const [worldwide, setWorldwide] =
        useState(
            release?.worldwide ===
                undefined ||
                release?.worldwide === null
                ? true
                : Boolean(
                      release.worldwide
                  )
        );

    const [territories, setTerritories] =
        useState(
            Array.isArray(
                release?.territories
            )
                ? release.territories
                : []
        );

    const [processing, setProcessing] =
        useState(false);

    const [saved, setSaved] =
        useState(false);

    const toggleStore = (id) => {
        const storeId = Number(id);

        setSelectedStores((current) =>
            current.includes(storeId)
                ? current.filter(
                      (item) =>
                          item !== storeId
                  )
                : [...current, storeId]
        );

        setSaved(false);
    };

    const toggleCountry = (code) => {
        setTerritories((current) =>
            current.includes(code)
                ? current.filter(
                      (item) =>
                          item !== code
                  )
                : [...current, code]
        );

        setSaved(false);
    };

    const save = () => {
        setProcessing(true);
        onMascotEvent(
            'distribution-saving'
        );

        router.patch(
            `${RELEASES_BASE_PATH}/${release.id}/distribution`,
            {
                stores:
                    selectedStores.length > 0
                        ? selectedStores
                        : allStoreIds,

                worldwide,

                territories: worldwide
                    ? []
                    : territories,

                release_timezone:
                    release?.release_timezone ||
                    'Asia/Kolkata',

                pre_order: Boolean(
                    release?.pre_order
                ),
            },
            {
                preserveScroll: true,

                onSuccess: () => {
                    setSaved(true);
                    onMascotEvent(
                        'distribution-saved'
                    );

                    window.setTimeout(() => {
                        onSaved();
                    }, 700);
                },

                onError: () => {
                    onMascotEvent(
                        'distribution-error'
                    );
                },

                onFinish: () => {
                    setProcessing(false);
                },
            }
        );
    };

    return (
        <div className="space-y-5">
            <div className="sticky top-[84px] z-30 rounded-2xl border border-slate-200 bg-white/95 p-4 shadow-sm backdrop-blur">
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold text-slate-900">
                            {mode === 'stores' ? 'Stores' : mode === 'territory' ? 'Territories' : 'Stores & Territory'}
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            {mode === 'stores'
                                ? 'Choose where this release should be delivered. All available stores are enabled by default.'
                                : mode === 'territory'
                                    ? 'All territories are selected by default.'
                                    : 'All stores and territories are selected by default.'}
                        </p>
                    </div>

                    <div className="flex items-center gap-3">
                        {saved && (
                            <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">
                                Saved
                            </span>
                        )}

                        <button
                            type="button"
                            onClick={save}
                            disabled={processing}
                            className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                        >
                            {processing
                                ? 'Saving...'
                                : (
    mode === 'stores'
        ? 'Save Stores'
        : mode === 'territory'
            ? 'Save Territories'
            : 'Save Stores & Territory'
)}
                        </button>
                    </div>
                </div>
            </div>

            <div
                className={`grid gap-6 ${
                    showStores && showTerritory
                        ? 'xl:grid-cols-2'
                        : 'grid-cols-1'
                }`}
            >
                {showStores && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="text-lg font-semibold text-slate-900">
                                Distribution Stores
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                {selectedStores.length} of{' '}
                                {distributionStores.length}{' '}
                                stores enabled
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedStores(
                                        allStoreIds
                                    )
                                }
                                className="rounded-lg bg-violet-50 px-3 py-2 text-xs font-semibold text-violet-700"
                            >
                                Enable All
                            </button>

                            <button
                                type="button"
                                onClick={() =>
                                    setSelectedStores(
                                        []
                                    )
                                }
                                className="rounded-lg bg-slate-100 px-3 py-2 text-xs font-semibold text-slate-600"
                            >
                                Disable All
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                        {distributionStores.length === 0 ? (
                            <div className="col-span-full rounded-2xl border border-dashed border-slate-300 bg-slate-50 px-6 py-12 text-center">
                                <div className="text-sm font-semibold text-slate-700">
                                    No distribution stores are currently available.
                                </div>

                                <div className="mt-1 text-xs text-slate-400">
                                    Stores enabled by the administrator will appear here automatically.
                                </div>
                            </div>
                        ) : distributionStores.map(
                            (store) => {
                                const checked =
                                    selectedStores.includes(
                                        Number(
                                            store.id
                                        )
                                    );

                                return (
                                    <button
                                        key={
                                            store.id
                                        }
                                        type="button"
                                        role="switch"
                                        aria-checked={
                                            checked
                                        }
                                        onClick={() =>
                                            toggleStore(
                                                store.id
                                            )
                                        }
                                        className={`flex min-h-[74px] items-center gap-4 rounded-2xl border px-4 py-3 text-left transition-all ${
                                            checked
                                                ? 'border-violet-200 bg-white shadow-sm'
                                                : 'border-slate-200 bg-slate-50 opacity-75'
                                        }`}
                                    >
                                        <div className="flex h-11 w-11 shrink-0 items-center justify-center overflow-hidden rounded-xl border border-slate-100 bg-white">
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
                                                    className="h-full w-full object-contain p-1"
                                                />
                                            ) : (
                                                <span className="text-xs font-bold">
                                                    {store.name
                                                        ?.charAt(
                                                            0
                                                        )
                                                        ?.toUpperCase()}
                                                </span>
                                            )}
                                        </div>

                                        <div className="min-w-0 flex-1">
                                            <div className="truncate text-sm font-semibold text-slate-900">
                                                {store.name}
                                            </div>

                                            <div className="mt-0.5 text-[11px] text-slate-400">
                                                Distribution store
                                            </div>
                                        </div>

                                        <div className="ml-auto flex shrink-0 items-center gap-2">
                                            <span
                                                className={`text-[10px] font-bold uppercase tracking-wide ${
                                                    checked
                                                        ? 'text-emerald-600'
                                                        : 'text-slate-400'
                                                }`}
                                            >
                                                {checked
                                                    ? 'On'
                                                    : 'Off'}
                                            </span>

                                            <span
                                                className={`relative inline-flex h-6 w-11 shrink-0 rounded-full transition ${
                                                    checked
                                                        ? 'bg-violet-600'
                                                        : 'bg-slate-300'
                                                }`}
                                            >
                                                <span
                                                    className={`absolute top-1 h-4 w-4 rounded-full bg-white shadow-sm transition-all ${
                                                        checked
                                                            ? 'left-6'
                                                            : 'left-1'
                                                    }`}
                                                />
                                            </span>
                                        </div>
                                    </button>
                                );
                            }
                        )}
                    </div>
                </section>
                )}

                {showTerritory && (
                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 className="font-semibold text-slate-900">
                        Territories
                    </h3>

                    <label className="mt-5 flex cursor-pointer items-center justify-between rounded-2xl border border-violet-200 bg-violet-50 p-5">
                        <div>
                            <div className="font-semibold text-slate-900">
                                Worldwide
                            </div>

                            <p className="mt-1 text-sm text-slate-500">
                                Deliver in all supported
                                countries.
                            </p>
                        </div>

                        <input
                            type="checkbox"
                            checked={worldwide}
                            onChange={(event) => {
                                setWorldwide(
                                    event.target
                                        .checked
                                );
                                setSaved(false);
                            }}
                            className="h-6 w-6 rounded border-slate-300 text-violet-600"
                        />
                    </label>

                    {!worldwide && (
                        <div className="mt-5 grid gap-2 sm:grid-cols-2">
                            {countries.map(
                                ([code, name]) => {
                                    const checked =
                                        territories.includes(
                                            code
                                        );

                                    return (
                                        <button
                                            key={code}
                                            type="button"
                                            onClick={() =>
                                                toggleCountry(
                                                    code
                                                )
                                            }
                                            className={`flex items-center justify-between rounded-xl border p-3 text-sm ${
                                                checked
                                                    ? 'border-violet-400 bg-violet-50'
                                                    : 'border-slate-200'
                                            }`}
                                        >
                                            <span>
                                                {name}
                                            </span>

                                            <span
                                                className={`flex h-5 w-5 items-center justify-center rounded border text-[10px] ${
                                                    checked
                                                        ? 'border-violet-600 bg-violet-600 text-white'
                                                        : 'border-slate-300 text-transparent'
                                                }`}
                                            >
                                                ✓
                                            </span>
                                        </button>
                                    );
                                }
                            )}
                        </div>
                    )}

                    {worldwide && (
                        <div className="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 p-5">
                            <div className="font-semibold text-emerald-900">
                                Worldwide Selected
                            </div>

                            <p className="mt-2 text-sm text-emerald-700">
                                Your release will be delivered to all supported territories.
                            </p>
                        </div>
                    )}
                </section>
                )}
            </div>
        </div>
    );
}
