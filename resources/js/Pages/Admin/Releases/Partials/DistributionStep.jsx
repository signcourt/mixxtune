const territories = [
    'India',
    'United States',
    'United Kingdom',
    'Canada',
    'Australia',
    'Germany',
    'France',
    'Italy',
    'Spain',
    'United Arab Emirates',
    'Saudi Arabia',
    'Singapore',
    'Malaysia',
    'Indonesia',
    'Japan',
];

export default function DistributionStep({
    data,
    setData,
    errors = {},
    availableStores = [],
}) {
    const excludedStoreIds = (data.excluded_store_ids ?? []).map(String);
    const selectedTerritories = data.territories ?? [];

    const isStoreSelected = (store) =>
        !excludedStoreIds.includes(String(store.id));

    const toggleStore = (store) => {
        const id = String(store.id);

        setData(
            'excluded_store_ids',
            isStoreSelected(store)
                ? [...excludedStoreIds, id]
                : excludedStoreIds.filter((item) => item !== id)
        );
    };

    const selectAllStores = () => {
        setData('excluded_store_ids', []);
    };

    const clearAllStores = () => {
        setData(
            'excluded_store_ids',
            availableStores.map((store) => String(store.id))
        );
    };

    const toggleTerritory = (territory) => {
        setData(
            'territories',
            selectedTerritories.includes(territory)
                ? selectedTerritories.filter((item) => item !== territory)
                : [...selectedTerritories, territory]
        );
    };

    const selectedStoreCount = availableStores.filter(
        isStoreSelected
    ).length;

    return (
        <div className="grid gap-6 p-6 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div className="space-y-6">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">
                        Stores & Distribution
                    </h2>

                    <p className="mt-1 text-sm text-slate-500">
                        Choose DSPs, release territories and delivery preferences.
                    </p>
                </div>

                <section className="rounded-2xl border border-slate-200 p-5">
                    <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h3 className="font-semibold text-slate-900">
                                Digital Stores
                            </h3>

                            <p className="mt-1 text-sm text-slate-500">
                                All active DSPs are selected automatically.
                                Uncheck only the stores you do not want.
                            </p>
                        </div>

                        <div className="flex gap-2">
                            <button
                                type="button"
                                onClick={selectAllStores}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Select All
                            </button>

                            <button
                                type="button"
                                onClick={clearAllStores}
                                className="rounded-lg border border-slate-300 px-3 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50"
                            >
                                Clear All
                            </button>
                        </div>
                    </div>

                    {availableStores.length === 0 ? (
                        <div className="mt-5 rounded-xl bg-amber-50 px-4 py-4 text-sm text-amber-700">
                            No active DSPs are available. Add or activate stores
                            from DSP Management.
                        </div>
                    ) : (
                        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {availableStores.map((store) => (
                                <label
                                    key={store.id}
                                    className={`flex cursor-pointer items-center gap-3 rounded-xl border px-4 py-3 transition ${
                                        isStoreSelected(store)
                                            ? 'border-violet-200 bg-violet-50'
                                            : 'border-slate-200 bg-white hover:bg-slate-50'
                                    }`}
                                >
                                    <input
                                        type="checkbox"
                                        checked={isStoreSelected(store)}
                                        onChange={() => toggleStore(store)}
                                        className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                    />

                                    {store.logo_path ? (
                                        <img
                                            src={`/storage/${store.logo_path}`}
                                            alt=""
                                            className="h-7 w-7 rounded object-contain"
                                        />
                                    ) : (
                                        <div className="flex h-7 w-7 items-center justify-center rounded bg-slate-100 text-xs font-bold text-slate-500">
                                            {store.name
                                                .slice(0, 1)
                                                .toUpperCase()}
                                        </div>
                                    )}

                                    <span className="text-sm font-medium text-slate-700">
                                        {store.name}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}

                    {errors.excluded_store_ids && (
                        <p className="mt-3 text-sm text-red-600">
                            {errors.excluded_store_ids}
                        </p>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Distribution Territories
                    </h3>

                    <div className="mt-4 grid gap-3 sm:grid-cols-2">
                        <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <input
                                type="radio"
                                name="territory_mode"
                                checked={Boolean(data.worldwide)}
                                onChange={() => {
                                    setData((current) => ({
                                        ...current,
                                        worldwide: true,
                                        territories: [],
                                    }));
                                }}
                                className="text-violet-600 focus:ring-violet-500"
                            />

                            <div>
                                <div className="text-sm font-semibold text-slate-900">
                                    Worldwide
                                </div>

                                <div className="text-xs text-slate-500">
                                    Distribute to every supported territory.
                                </div>
                            </div>
                        </label>

                        <label className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <input
                                type="radio"
                                name="territory_mode"
                                checked={!data.worldwide}
                                onChange={() =>
                                    setData('worldwide', false)
                                }
                                className="text-violet-600 focus:ring-violet-500"
                            />

                            <div>
                                <div className="text-sm font-semibold text-slate-900">
                                    Selected Territories
                                </div>

                                <div className="text-xs text-slate-500">
                                    Choose only specific countries.
                                </div>
                            </div>
                        </label>
                    </div>

                    {!data.worldwide && (
                        <div className="mt-5 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                            {territories.map((territory) => (
                                <label
                                    key={territory}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-200 px-4 py-3 hover:bg-slate-50"
                                >
                                    <input
                                        type="checkbox"
                                        checked={selectedTerritories.includes(
                                            territory
                                        )}
                                        onChange={() =>
                                            toggleTerritory(territory)
                                        }
                                        className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                                    />

                                    <span className="text-sm text-slate-700">
                                        {territory}
                                    </span>
                                </label>
                            ))}
                        </div>
                    )}
                </section>

                <section className="rounded-2xl border border-slate-200 p-5">
                    <h3 className="font-semibold text-slate-900">
                        Delivery Settings
                    </h3>

                    <div className="mt-5 grid gap-5 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Release Timezone
                            </label>

                            <select
                                value={
                                    data.release_timezone ??
                                    'Asia/Kolkata'
                                }
                                onChange={(event) =>
                                    setData(
                                        'release_timezone',
                                        event.target.value
                                    )
                                }
                                className="w-full rounded-xl border-slate-300"
                            >
                                <option value="Asia/Kolkata">
                                    Asia/Kolkata
                                </option>
                                <option value="UTC">UTC</option>
                                <option value="Europe/London">
                                    Europe/London
                                </option>
                                <option value="America/New_York">
                                    America/New_York
                                </option>
                                <option value="America/Los_Angeles">
                                    America/Los_Angeles
                                </option>
                            </select>
                        </div>

                        <label className="flex items-center gap-3 rounded-xl border border-slate-200 px-4 py-3">
                            <input
                                type="checkbox"
                                checked={Boolean(data.pre_order)}
                                onChange={(event) =>
                                    setData(
                                        'pre_order',
                                        event.target.checked
                                    )
                                }
                                className="rounded border-slate-300 text-violet-600 focus:ring-violet-500"
                            />

                            <div>
                                <div className="text-sm font-semibold text-slate-900">
                                    Enable Pre-order
                                </div>

                                <div className="text-xs text-slate-500">
                                    Show the release before launch on supported stores.
                                </div>
                            </div>
                        </label>
                    </div>
                </section>
            </div>

            <aside className="h-fit rounded-2xl border border-slate-200 bg-slate-50 p-5">
                <h3 className="font-semibold text-slate-900">
                    Distribution Summary
                </h3>

                <div className="mt-5 space-y-4 text-sm">
                    <SummaryRow
                        label="Stores selected"
                        value={selectedStoreCount}
                    />

                    <SummaryRow
                        label="Stores excluded"
                        value={excludedStoreIds.length}
                    />

                    <SummaryRow
                        label="Territories"
                        value={
                            data.worldwide
                                ? 'Worldwide'
                                : selectedTerritories.length
                        }
                    />

                    <SummaryRow
                        label="Timezone"
                        value={
                            data.release_timezone ||
                            'Asia/Kolkata'
                        }
                    />

                    <SummaryRow
                        label="Pre-order"
                        value={
                            data.pre_order
                                ? 'Enabled'
                                : 'Disabled'
                        }
                    />
                </div>
            </aside>
        </div>
    );
}

function SummaryRow({ label, value }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <span className="text-slate-500">{label}</span>

            <span className="font-semibold text-slate-900">
                {value}
            </span>
        </div>
    );
}
