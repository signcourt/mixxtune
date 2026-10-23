import { router } from '@inertiajs/react';
import { useMemo, useState } from 'react';

/*
 * W64 MIXXTUNE_TERRITORIES_V2
 * One step for where the release is available: "Worldwide" on top and every
 * country below as a small flag + name chip. Unticking a country while
 * Worldwide is on turns it into "worldwide except these". Stores are no
 * longer chosen here (the Stores step was removed): the release keeps the
 * stores it already has, or all active stores.
 */
const RELEASES_BASE_PATH =
    typeof window !== 'undefined'
    && /^\/artist(\/|$)/.test(window.location.pathname)
        ? '/artist/releases'
        : '/releases';

/* ISO 3166-1 alpha-2 */
const CODES = ('AD AE AF AG AI AL AM AO AQ AR AS AT AU AW AX AZ BA BB BD BE BF BG BH BI BJ BL BM BN BO BQ BR BS BT BV BW BY BZ CA CC CD CF CG CH CI CK CL CM CN CO CR CU CV CW CX CY CZ DE DJ DK DM DO DZ EC EE EG EH ER ES ET FI FJ FK FM FO FR GA GB GD GE GF GG GH GI GL GM GN GP GQ GR GS GT GU GW GY HK HM HN HR HT HU ID IE IL IM IN IO IQ IR IS IT JE JM JO JP KE KG KH KI KM KN KP KR KW KY KZ LA LB LC LI LK LR LS LT LU LV LY MA MC MD ME MF MG MH MK ML MM MN MO MP MQ MR MS MT MU MV MW MX MY MZ NA NC NE NF NG NI NL NO NP NR NU NZ OM PA PE PF PG PH PK PL PM PN PR PS PT PW PY QA RE RO RS RU RW SA SB SC SD SE SG SH SI SJ SK SL SM SN SO SR SS ST SV SX SY SZ TC TD TF TG TH TJ TK TL TM TN TO TR TT TV TW TZ UA UG UM US UY UZ VA VC VE VG VI VN VU WF WS YE YT ZA ZM ZW').split(' ');

const QUICK = ['IN', 'NP', 'BD', 'LK', 'PK', 'AE', 'US', 'GB', 'CA', 'AU'];

const flag = (code) =>
    String.fromCodePoint(...[...code].map((c) => 127397 + c.charCodeAt(0)));

const regionName = (() => {
    try {
        const names = new Intl.DisplayNames(['en'], { type: 'region' });
        return (code) => names.of(code) || code;
    } catch (e) {
        return (code) => code;
    }
})();

export const COUNTRIES = CODES.map((code) => ({ code, name: regionName(code), flag: flag(code) }))
    .sort((a, b) => a.name.localeCompare(b.name));

export default function TerritoryStep({
    release,
    distributionStores = [],
    onSaved = () => {},
    onMascotEvent = () => {},
}) {
    const allCodes = useMemo(() => COUNTRIES.map((c) => c.code), []);

    const startWorldwide = release?.worldwide === undefined || release?.worldwide === null ? true : Boolean(release.worldwide);
    const savedTerritories = Array.isArray(release?.territories) ? release.territories.map((c) => String(c).toUpperCase()) : [];

    const [selected, setSelected] = useState(() => new Set(startWorldwide ? allCodes : savedTerritories));
    const [search, setSearch] = useState('');
    const [processing, setProcessing] = useState(false);
    const [saved, setSaved] = useState(false);
    const [error, setError] = useState(null);

    const worldwide = selected.size === allCodes.length;
    const term = search.trim().toLowerCase();
    const shown = term
        ? COUNTRIES.filter((c) => c.name.toLowerCase().includes(term) || c.code.toLowerCase() === term)
        : COUNTRIES;

    const change = (next) => {
        setSelected(next);
        setSaved(false);
        setError(null);
    };

    const toggle = (code) => {
        const next = new Set(selected);
        if (next.has(code)) next.delete(code);
        else next.add(code);
        change(next);
    };

    const setWorldwide = () => change(new Set(allCodes));
    const clearAll = () => change(new Set());
    const onlyThese = (codes) => change(new Set(codes));

    const storeIds = (Array.isArray(release?.stores) && release.stores.length > 0
        ? release.stores
        : distributionStores.map((s) => s.id)
    ).map(Number);

    const save = () => {
        if (selected.size === 0) {
            setError('Pick at least one country, or choose Worldwide.');
            return;
        }
        if (storeIds.length === 0) {
            setError('No active stores are set up yet. Ask Mixx Tune to add stores.');
            return;
        }

        setProcessing(true);
        onMascotEvent('distribution-saving');

        router.patch(
            `${RELEASES_BASE_PATH}/${release.id}/distribution`,
            {
                stores: storeIds,
                worldwide,
                territories: worldwide ? [] : [...selected],
                release_timezone: release?.release_timezone || 'Asia/Kolkata',
                pre_order: Boolean(release?.pre_order),
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSaved(true);
                    onMascotEvent('distribution-saved');
                    window.setTimeout(() => onSaved(), 500);
                },
                onError: (errs) => {
                    onMascotEvent('distribution-error');
                    setError(Object.values(errs || {})[0] || 'Not saved.');
                },
                onFinish: () => setProcessing(false),
            }
        );
    };

    const excluded = allCodes.length - selected.size;

    return (
        <div className="space-y-5">
            <div className="flex flex-col gap-3 rounded-2xl border border-slate-200 bg-white p-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 className="text-xl font-semibold text-slate-900">Territories</h2>
                    <p className="mt-1 text-sm text-slate-500">Where listeners can find this release. Worldwide is recommended.</p>
                </div>
                <div className="flex items-center gap-3">
                    {saved && <span className="rounded-full bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-700">Saved</span>}
                    <button
                        type="button"
                        onClick={save}
                        disabled={processing}
                        className="rounded-xl bg-violet-600 px-5 py-3 text-sm font-semibold text-white hover:bg-violet-700 disabled:opacity-50"
                    >
                        {processing ? 'Saving...' : 'Save & continue'}
                    </button>
                </div>
            </div>

            {error && <div className="rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm font-medium text-rose-700">{error}</div>}

            <div className="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    onClick={setWorldwide}
                    aria-pressed={worldwide}
                    className={`flex items-center gap-4 rounded-2xl border p-4 text-left transition ${worldwide ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 bg-white hover:border-violet-300'}`}
                >
                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-2xl shadow-sm" aria-hidden="true">🌍</span>
                    <span className="min-w-0">
                        <span className="block font-semibold text-slate-900">Worldwide</span>
                        <span className="block text-sm text-slate-500">All {allCodes.length} countries and regions</span>
                    </span>
                    <span className={`ml-auto flex h-6 w-6 shrink-0 items-center justify-center rounded-full border text-xs ${worldwide ? 'border-violet-600 bg-violet-600 text-white' : 'border-slate-300 text-transparent'}`}>✓</span>
                </button>

                <div className={`flex items-center gap-4 rounded-2xl border p-4 ${!worldwide ? 'border-violet-500 bg-violet-50 ring-2 ring-violet-100' : 'border-slate-200 bg-white'}`}>
                    <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-white text-lg font-bold text-violet-700 shadow-sm">{selected.size}</span>
                    <span className="min-w-0">
                        <span className="block font-semibold text-slate-900">
                            {worldwide ? 'Leave some countries out?' : excluded > 0 && selected.size > allCodes.length / 2 ? `Worldwide except ${excluded}` : `${selected.size} countries selected`}
                        </span>
                        <span className="block text-sm text-slate-500">{worldwide ? 'Untick them in the list below' : 'Press Worldwide to select every country again'}</span>
                    </span>
                </div>
            </div>

            <section className="rounded-2xl border border-slate-200 bg-white p-4 sm:p-5">
                <div className="flex flex-col gap-3 lg:flex-row lg:items-center">
                    <input
                        type="search"
                        value={search}
                        onChange={(e) => setSearch(e.target.value)}
                        placeholder="Search country"
                        aria-label="Search country"
                        className="h-11 w-full rounded-xl border border-slate-300 px-4 text-sm outline-none focus:border-violet-500 lg:w-72"
                    />
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                        <span className="mr-1 text-xs font-semibold uppercase tracking-wide text-slate-500">Quick</span>
                        <button type="button" onClick={() => onlyThese(['IN'])} className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">{flag('IN')} Only India</button>
                        <button type="button" onClick={() => onlyThese(QUICK)} className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">South Asia + top markets</button>
                        <button type="button" onClick={setWorldwide} className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50">Select all</button>
                        <button type="button" onClick={clearAll} className="rounded-lg border border-slate-300 px-2.5 py-1.5 text-xs font-semibold text-rose-600 hover:bg-rose-50">Clear</button>
                    </div>
                    <span className="text-xs font-semibold text-slate-500">{selected.size} of {allCodes.length}</span>
                </div>

                <div className="mt-4 grid grid-cols-2 gap-1.5 sm:grid-cols-3 lg:grid-cols-4 2xl:grid-cols-5">
                    {shown.map((c) => {
                        const on = selected.has(c.code);
                        return (
                            <label
                                key={c.code}
                                className={`flex min-h-[36px] cursor-pointer items-center gap-2 rounded-lg border px-2 py-1.5 text-xs transition ${on ? 'border-violet-200 bg-violet-50/60 text-slate-800' : 'border-slate-200 bg-white text-slate-400'}`}
                                title={c.name}
                            >
                                <input type="checkbox" checked={on} onChange={() => toggle(c.code)} className="h-3.5 w-3.5 shrink-0 rounded border-slate-300 text-violet-600" />
                                <span className="text-base leading-none" aria-hidden="true">{c.flag}</span>
                                <span className="truncate font-medium">{c.name}</span>
                            </label>
                        );
                    })}
                    {shown.length === 0 && <div className="col-span-full py-8 text-center text-sm text-slate-500">No country matches “{search}”.</div>}
                </div>
            </section>

            <p className="text-xs text-slate-500">
                Stores: {Array.isArray(release?.stores) && release.stores.length > 0 ? `${release.stores.length} chosen earlier` : `all ${distributionStores.length} active stores`} — Mixx Tune manages store delivery.
            </p>
        </div>
    );
}
