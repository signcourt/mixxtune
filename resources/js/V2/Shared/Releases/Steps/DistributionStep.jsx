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
                            {mode === 'stores' ? 'Stores' : mode === 'territory' ? 'Territory' : 'Stores & Territory'}
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            All stores and Worldwide
                            are selected by default.
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
            ? 'Save Territory'
            : 'Save Stores & Territory'
)}
                        </button>
                    </div>
                </div>
            </div>

            <div className="grid gap-6 xl:grid-cols-2">
                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <div className="flex items-center justify-between gap-3">
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                Stores
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                {selectedStores.length}{' '}
                                selected
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
                                Select All
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
                                Clear
                            </button>
                        </div>
                    </div>

                    <div className="mt-5 grid max-h-[620px] gap-2 overflow-y-auto pr-1 sm:grid-cols-2">
                        {distributionStores.map(
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
                                        onClick={() =>
                                            toggleStore(
                                                store.id
                                            )
                                        }
                                        className={`flex items-center gap-3 rounded-xl border p-3 text-left transition ${
                                            checked
                                                ? 'border-violet-400 bg-violet-50'
                                                : 'border-slate-200 bg-white'
                                        }`}
                                    >
                                        <div className="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-slate-100">
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

                                        <span className="min-w-0 flex-1 truncate text-xs font-semibold text-slate-800">
                                            {store.name}
                                        </span>

                                        <span
                                            className={`flex h-5 w-5 shrink-0 items-center justify-center rounded border text-[10px] font-bold ${
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
                </section>

                <section className="rounded-2xl border border-slate-200 bg-white p-5">
                    <h3 className="font-semibold text-slate-900">
                        Territory
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
                                पूरी territory selected है।
                            </p>
                        </div>
                    )}
                </section>
            </div>
        </div>
    );
}
