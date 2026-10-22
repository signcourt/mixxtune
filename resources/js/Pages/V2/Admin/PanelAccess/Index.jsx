import { Head, Link, useForm, usePage } from "@inertiajs/react";
import { RotateCcw, Save, ShieldCheck } from "lucide-react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W81 MIXXTUNE_PANEL_ACCESS
 * Default sidebar tabs per role. A single account can still be allowed or
 * blocked per tab on Edit User.
 */
function Switch({ on, onChange, label }) {
    return (
        <button
            type="button"
            role="switch"
            aria-checked={on}
            aria-label={label}
            onClick={() => onChange(!on)}
            className={`relative inline-flex h-5 w-9 shrink-0 rounded-full transition-colors ${on ? "bg-violet-600" : "bg-slate-300"}`}
        >
            <span className={`absolute top-0.5 h-4 w-4 rounded-full bg-white shadow transition-all ${on ? "left-[18px]" : "left-0.5"}`} />
        </button>
    );
}

function PanelTable({ title, subtitle, roles, values, setValue }) {
    const keys = [];
    const info = {};
    roles.forEach((r) =>
        r.tabs.forEach((t) => {
            if (!info[t.key]) {
                keys.push(t.key);
                info[t.key] = t;
            }
        }),
    );

    return (
        <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="border-b border-slate-100 px-4 py-3 sm:px-5">
                <h2 className="text-[15px] font-bold text-slate-900">{title}</h2>
                <p className="text-xs text-slate-500">{subtitle}</p>
            </div>
            <div className="overflow-x-auto">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-slate-50 text-left text-[11px] font-bold uppercase tracking-wide text-slate-500">
                            <th className="px-3 py-2 sm:px-5">Sidebar tab</th>
                            {roles.map((r) => (
                                <th key={r.role} className="w-16 px-1.5 py-2 text-center sm:w-28 sm:px-3">
                                    {r.name}
                                    <div className="text-[10px] font-medium normal-case tracking-normal text-slate-400">
                                        {r.accounts}<span className="hidden sm:inline"> accounts</span>{r.own_settings ? ` · ${r.own_settings} own` : ""}
                                    </div>
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {keys.map((key) => (
                            <tr key={key}>
                                <td className="px-3 py-2 sm:px-5">
                                    <div className="font-semibold text-slate-800">{info[key].name}</div>
                                    <div className="text-[11px] text-slate-400">{info[key].help}</div>
                                </td>
                                {roles.map((r) => {
                                    const tab = r.tabs.find((t) => t.key === key);
                                    if (!tab) {
                                        return (
                                            <td key={r.role} className="px-1.5 py-2 text-center text-slate-300 sm:px-3">
                                                —
                                            </td>
                                        );
                                    }
                                    const on = Boolean(values[r.role]?.[key]);
                                    return (
                                        <td key={r.role} className="px-1.5 py-2 text-center sm:px-3">
                                            <div className="flex flex-col items-center gap-0.5">
                                                <Switch on={on} onChange={(v) => setValue(r.role, key, v)} label={`${info[key].name} for ${r.name}`} />
                                                {on !== tab.standard && <span className="text-[10px] font-semibold text-amber-600">changed</span>}
                                            </div>
                                        </td>
                                    );
                                })}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

export default function Index({ role = "super_admin", roles = [], ready = true }) {
    const { flash = {} } = usePage().props;
    const initial = Object.fromEntries(roles.map((r) => [r.role, Object.fromEntries(r.tabs.map((t) => [t.key, Boolean(t.value)]))]));
    const { data, setData, put, processing, isDirty, reset, setDefaults } = useForm({ defaults: initial });

    const setValue = (roleKey, key, value) => setData("defaults", { ...data.defaults, [roleKey]: { ...data.defaults[roleKey], [key]: value } });
    const useStandard = () =>
        setData("defaults", Object.fromEntries(roles.map((r) => [r.role, Object.fromEntries(r.tabs.map((t) => [t.key, Boolean(t.standard)]))])));

    const owners = roles.filter((r) => r.panel === "Owner panel");
    const managers = roles.filter((r) => r.panel !== "Owner panel");

    const submit = (event) => {
        event.preventDefault();
        put("/v2/admin/panel-access", { preserveScroll: true, onSuccess: () => setDefaults() });
    };

    return (
        <PanelLayout role={role} title="Panel Access" subtitle="Which sidebar tabs each account type gets">
            <Head title="Panel Access" />
            <form onSubmit={submit} className="mx-auto w-full max-w-[1100px] space-y-4 pb-24">
                <section className="flex flex-col gap-2 rounded-2xl border border-violet-100 bg-violet-50/60 p-4 sm:flex-row sm:items-center sm:justify-between sm:p-5">
                    <div className="flex items-start gap-3">
                        <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-white text-violet-700">
                            <ShieldCheck size={18} />
                        </span>
                        <p className="text-sm leading-6 text-slate-600">
                            Switch a tab off and every account of that type stops seeing it (the page is also blocked, not only hidden). To allow or block a tab
                            for <b>one</b> account, open that account on{" "}
                            <Link href="/v2/admin/users" className="font-semibold text-violet-700 underline">
                                Users
                            </Link>{" "}
                            → Edit → Panel access. Super Admin always sees everything.
                        </p>
                    </div>
                </section>

                {flash.success && <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-2.5 text-sm font-semibold text-emerald-800">{flash.success}</div>}
                {!ready && <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-2.5 text-sm text-amber-800">Panel access tables are not installed yet.</div>}

                <PanelTable title="Owner panel" subtitle="Label and Artist accounts" roles={owners} values={data.defaults} setValue={setValue} />
                <PanelTable title="Manager panel" subtitle="Manager (admin) accounts" roles={managers} values={data.defaults} setValue={setValue} />

                <div className="sticky bottom-4 z-20 flex flex-wrap items-center justify-between gap-2 rounded-2xl border border-slate-200 bg-white/95 p-3 shadow-lg backdrop-blur">
                    <button type="button" onClick={useStandard} className="inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-50">
                        <RotateCcw size={14} /> Use standard
                    </button>
                    <div className="flex gap-2">
                        {isDirty && (
                            <button type="button" onClick={() => reset()} className="rounded-lg border border-slate-200 px-4 py-2 text-sm font-semibold text-slate-700">
                                Undo
                            </button>
                        )}
                        <button
                            type="submit"
                            disabled={processing || !isDirty || !ready}
                            className="inline-flex items-center gap-1.5 rounded-lg bg-violet-600 px-5 py-2 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            <Save size={15} /> Save
                        </button>
                    </div>
                </div>
            </form>
        </PanelLayout>
    );
}
