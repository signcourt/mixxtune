import { Head, Link, router, usePage } from "@inertiajs/react";
import { ArrowLeft, CheckCircle2, Loader2, Percent, Search } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* IPRS royalty rates: per owner, per royalty type, the % the OWNER keeps.
   Resolution at publish: this owner's rate -> global default -> digital share -> 100%. */

const TYPE_LABEL = { publishing: "Publishing", ppl: "PPL", mechanical: "Mechanical" };

function RateInput({ ownerType, ownerId, type, initial, placeholder }) {
    const [val, setVal] = useState(initial === null || initial === undefined ? "" : String(initial));
    const [saving, setSaving] = useState(false);

    const save = () => {
        const norm = val.trim();
        const current = norm === "" ? null : Number(norm);
        const base = initial === null || initial === undefined ? null : Number(initial);
        if (current === base) return;
        if (norm !== "" && (Number.isNaN(current) || current < 0 || current > 100)) {
            setVal(base === null ? "" : String(base));
            return;
        }
        setSaving(true);
        router.post("/admin/iprs/rates", {
            owner_type: ownerType,
            owner_id: ownerId,
            royalty_type: type,
            owner_share_percentage: norm === "" ? null : norm,
        }, { preserveScroll: true, preserveState: true, onFinish: () => setSaving(false) });
    };

    return (
        <div className="relative">
            <input
                value={val}
                onChange={(e) => setVal(e.target.value)}
                onBlur={save}
                onKeyDown={(e) => e.key === "Enter" && e.currentTarget.blur()}
                inputMode="decimal"
                placeholder={placeholder}
                className="h-9 w-20 rounded-lg border border-slate-300 px-2 text-sm"
            />
            {saving && <Loader2 className="absolute right-1.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 animate-spin text-slate-400" />}
        </div>
    );
}

export default function Rates({ role = "admin", types = [], defaults = {}, owners = [], q = "" }) {
    const page = usePage();
    const flash = page.props?.flash ?? {};
    const [query, setQuery] = useState(q);

    const search = (e) => {
        e.preventDefault();
        router.get("/admin/iprs/rates", { q: query }, { preserveScroll: true, preserveState: true });
    };

    return (
        <PanelLayout role={role} title="IPRS royalty rates" subtitle="Set the % each owner keeps, per royalty type">
            <Head title="IPRS royalty rates" />

            <div className="space-y-5">
                <Link href="/admin/iprs" className="inline-flex items-center gap-1.5 text-sm font-semibold text-violet-700">
                    <ArrowLeft className="h-4 w-4" /> Back to IPRS statements
                </Link>

                {flash.success && (
                    <div className="flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                        <CheckCircle2 className="h-4 w-4" /> {flash.success}
                    </div>
                )}

                <div className="rounded-2xl border border-slate-200 bg-white p-4 text-xs text-slate-500">
                    <Percent className="mr-1 inline h-3.5 w-3.5" />
                    The number is the share the <span className="font-semibold text-slate-700">owner keeps</span> (e.g. 80 → owner gets 80%, MIXX TUNE retains 20%). Empty = use the next fallback: global default → owner’s digital share → 100%.
                </div>

                {/* global defaults */}
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <h2 className="text-base font-semibold text-slate-900">Global default rates</h2>
                    <p className="mb-3 text-xs text-slate-500">Applied when an owner has no rate of their own for that type.</p>
                    <div className="flex flex-wrap gap-5">
                        {types.map((t) => (
                            <label key={t} className="flex items-center gap-2">
                                <span className="text-sm font-medium text-slate-700">{TYPE_LABEL[t] || t}</span>
                                <RateInput ownerType="default" ownerId={0} type={t} initial={defaults[t]} placeholder="—" />
                                <span className="text-xs text-slate-400">%</span>
                            </label>
                        ))}
                    </div>
                </section>

                {/* per-label rates */}
                <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 px-4 py-3 sm:px-5">
                        <h2 className="text-base font-semibold text-slate-900">Label rates</h2>
                        <form onSubmit={search} className="relative">
                            <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" />
                            <input value={query} onChange={(e) => setQuery(e.target.value)} placeholder="Search label…" className="h-9 w-56 rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                        </form>
                    </div>

                    {owners.length === 0 ? (
                        <p className="px-5 py-10 text-center text-sm text-slate-500">No labels found.</p>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[640px] text-sm">
                                <thead>
                                    <tr className="border-b border-slate-100 text-left text-xs uppercase tracking-wide text-slate-400">
                                        <th className="px-4 py-2 font-semibold sm:px-5">Label</th>
                                        <th className="px-3 py-2 font-semibold">Digital %</th>
                                        {types.map((t) => <th key={t} className="px-3 py-2 font-semibold">{TYPE_LABEL[t] || t} %</th>)}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {owners.map((o) => (
                                        <tr key={`${o.owner_type}-${o.owner_id}`}>
                                            <td className="px-4 py-2 font-semibold text-slate-800 sm:px-5">{o.name}</td>
                                            <td className="px-3 py-2 text-slate-400">{o.digital_share ?? "—"}</td>
                                            {types.map((t) => (
                                                <td key={t} className="px-3 py-2">
                                                    <RateInput
                                                        ownerType={o.owner_type}
                                                        ownerId={o.owner_id}
                                                        type={t}
                                                        initial={o.rates?.[t]}
                                                        placeholder={defaults[t] ?? o.digital_share ?? "100"}
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </section>
            </div>
        </PanelLayout>
    );
}
