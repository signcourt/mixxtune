import { Head, Link, router, useForm, usePage } from "@inertiajs/react";
import { AlertTriangle, CalendarPlus, CheckCircle2, Pencil, Plus, Users, X } from "lucide-react";
import { useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/*
 * W115 MIXXTUNE_STAFF_SALARY
 * Staff list (designation, bank, monthly salary) + one salary sheet per month.
 */
const inr = (v) => "₹" + new Intl.NumberFormat("en-IN", { minimumFractionDigits: 0, maximumFractionDigits: 2 }).format(Number(v || 0));
const monthName = (m) => new Intl.DateTimeFormat("en-IN", { month: "long", year: "numeric" }).format(new Date(`${m}-01T00:00:00`));
const RUN = { draft: ["Draft - check it", "bg-amber-50 text-amber-800"], approved: ["Approved", "bg-sky-50 text-sky-700"], paying: ["Paying", "bg-violet-50 text-violet-700"], done: ["All paid", "bg-emerald-50 text-emerald-700"] };
const input = "h-10 w-full rounded-xl border border-slate-300 bg-white px-3 text-sm";

const EMPTY = { name: "", designation: "", email: "", phone: "", account_holder_name: "", bank_account_number: "", ifsc_code: "", bank_name: "", pan_number: "", monthly_salary: "", joined_on: "", status: "active", notes: "" };

function StaffForm({ staff, onClose }) {
    const editing = Boolean(staff?.id);
    const form = useForm(
        editing
            ? { ...EMPTY, ...Object.fromEntries(Object.entries(staff).map(([k, v]) => [k, v ?? ""])), bank_account_number: "", pan_number: "", monthly_salary: String(staff.monthly_salary ?? "") }
            : EMPTY,
    );
    const F = ({ name, label, hint, ...rest }) => (
        <label className="block">
            <span className="text-xs font-semibold text-slate-600">{label}</span>
            <input value={form.data[name]} onChange={(e) => form.setData(name, e.target.value)} className={`mt-1 ${input}`} {...rest} />
            {form.errors[name] ? <span className="mt-1 block text-[11px] font-semibold text-red-600">{form.errors[name]}</span> : hint ? <span className="mt-1 block text-[11px] text-slate-400">{hint}</span> : null}
        </label>
    );
    const submit = (e) => {
        e.preventDefault();
        form.post(editing ? `/admin/staff/${staff.id}` : "/admin/staff", { preserveScroll: true, onSuccess: () => onClose() });
    };

    return (
        <form onSubmit={submit} className="rounded-2xl border border-violet-200 bg-violet-50/40 p-4">
            <div className="mb-3 flex items-center justify-between">
                <h3 className="text-sm font-bold text-slate-900">{editing ? `Change ${staff.name}` : "Add a staff member"}</h3>
                <button type="button" onClick={onClose} aria-label="Close" className="rounded-full p-1 text-slate-500 hover:bg-white">
                    <X className="h-4 w-4" />
                </button>
            </div>
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
                {F({ name: "name", label: "Name *", required: true })}
                {F({ name: "designation", label: "Designation", placeholder: "e.g. Chief Operating Officer" })}
                {F({ name: "monthly_salary", label: "Monthly salary (₹) *", type: "number", min: 0, step: "0.01", required: true })}
                {F({ name: "email", label: "E-mail", type: "email" })}
                {F({ name: "phone", label: "Phone" })}
                {F({ name: "joined_on", label: "Joined on", type: "date" })}
                {F({ name: "account_holder_name", label: "Account holder name", hint: "Exactly as at the bank" })}
                {F({ name: "bank_account_number", label: `Bank account number${editing && staff.masked_bank_account ? ` (saved ${staff.masked_bank_account})` : ""}`, inputMode: "numeric", placeholder: editing && staff.masked_bank_account ? "keep" : "", hint: editing && staff.masked_bank_account ? "Empty = keep. A change needs the e-mailed code." : "Digits only", autoComplete: "off" })}
                {F({ name: "ifsc_code", label: "IFSC", placeholder: "ABCD0123456", autoComplete: "off" })}
                {F({ name: "bank_name", label: "Bank name" })}
                {F({ name: "pan_number", label: `PAN${editing && staff.has_pan ? " (saved ✓)" : ""}`, placeholder: editing && staff.has_pan ? "keep" : "ABCDE1234F", autoComplete: "off" })}
                <label className="block">
                    <span className="text-xs font-semibold text-slate-600">Status</span>
                    <select value={form.data.status} onChange={(e) => form.setData("status", e.target.value)} className={`mt-1 ${input}`}>
                        <option value="active">Active - in the salary sheet</option>
                        <option value="inactive">Left / not paid</option>
                    </select>
                </label>
            </div>
            <label className="mt-3 block">
                <span className="text-xs font-semibold text-slate-600">Notes</span>
                <textarea value={form.data.notes} onChange={(e) => form.setData("notes", e.target.value)} rows={2} className="mt-1 w-full rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm" />
            </label>
            <div className="mt-3 flex gap-2">
                <button type="submit" disabled={form.processing} className="h-10 rounded-xl bg-violet-600 px-5 text-sm font-semibold text-white disabled:opacity-50">
                    {editing ? "Save" : "Add"}
                </button>
                <button type="button" onClick={onClose} className="h-10 rounded-xl px-4 text-sm font-semibold text-slate-500">
                    Cancel
                </button>
            </div>
        </form>
    );
}

export default function Index({ staff = [], runs = [], monthlyTotal = 0, razorpayReady = false, nextMonth = "" }) {
    const { flash = {}, errors = {} } = usePage().props;
    const [editing, setEditing] = useState(null);
    const [month, setMonth] = useState(nextMonth);
    const active = staff.filter((s) => s.status === "active");
    const noBank = active.filter((s) => !s.bank_ready).length;

    return (
        <PanelLayout role="super_admin" title="Staff & Salary" subtitle="Staff list and the monthly salary sheet - paid through Razorpay">
            <Head title="Staff & Salary" />

            {flash.success && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-semibold text-emerald-700">
                    <CheckCircle2 className="h-4 w-4 shrink-0" /> {flash.success}
                </div>
            )}
            {errors.month && (
                <div className="mb-4 flex items-center gap-2 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm font-semibold text-red-700">
                    <AlertTriangle className="h-4 w-4 shrink-0" /> {errors.month}
                </div>
            )}
            {!razorpayReady && (
                <div className="mb-4 rounded-2xl border border-slate-200 bg-white px-4 py-3 text-sm text-slate-600">
                    Razorpay is not connected yet - salaries can still be approved and marked paid by hand. <Link href="/admin/razorpay" className="font-semibold text-violet-700 underline">Connect Razorpay</Link>
                </div>
            )}

            <div className="grid gap-3 sm:grid-cols-3">
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Active staff</div>
                    <div className="mt-1 text-2xl font-black text-slate-900">{active.length}</div>
                    <div className={`text-xs ${noBank ? "font-semibold text-red-600" : "text-slate-500"}`}>{noBank ? `${noBank} without bank details` : "all have bank details"}</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">Salary per month</div>
                    <div className="mt-1 text-2xl font-black text-slate-900">{inr(monthlyTotal)}</div>
                    <div className="text-xs text-slate-500">of the active staff</div>
                </div>
                <div className="rounded-2xl border border-slate-200 bg-white p-4 shadow-sm">
                    <div className="text-xs font-semibold text-slate-500">New salary sheet</div>
                    <div className="mt-2 flex gap-2">
                        <input type="month" value={month} onChange={(e) => setMonth(e.target.value)} aria-label="Month" className="h-10 min-w-0 flex-1 rounded-xl border border-slate-300 px-3 text-sm" />
                        <button type="button" disabled={!month} onClick={() => router.post("/admin/salaries/runs", { month }, { preserveScroll: true })} className="inline-flex h-10 items-center gap-1.5 rounded-xl bg-violet-600 px-3 text-sm font-semibold text-white disabled:opacity-40">
                            <CalendarPlus className="h-4 w-4" /> Make
                        </button>
                    </div>
                </div>
            </div>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 className="text-sm font-bold text-slate-900">Salary sheets</h2>
                </div>
                {runs.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-slate-500">No salary sheet yet. Add the staff, pick a month and press Make.</p>
                ) : (
                    <ul className="divide-y divide-slate-100">
                        {runs.map((r) => {
                            const [label, cls] = RUN[r.status] ?? [r.status, "bg-slate-100 text-slate-600"];
                            return (
                                <li key={r.id}>
                                    <Link href={`/admin/salaries/${r.id}`} className="flex flex-wrap items-center gap-3 px-4 py-3 hover:bg-slate-50">
                                        <span className="w-36 font-semibold text-slate-900">{monthName(r.month)}</span>
                                        <span className={`rounded-full px-2 py-0.5 text-xs font-semibold ${cls}`}>{label}</span>
                                        <span className="text-xs text-slate-500">{r.people} people</span>
                                        <span className="ml-auto text-sm font-bold text-slate-900">{inr(r.total)}</span>
                                        <span className="w-32 text-right text-xs text-slate-500">paid {inr(r.paid)}</span>
                                    </Link>
                                </li>
                            );
                        })}
                    </ul>
                )}
            </section>

            <section className="mt-5 rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex items-center justify-between border-b border-slate-100 px-4 py-3">
                    <h2 className="flex items-center gap-2 text-sm font-bold text-slate-900">
                        <Users className="h-4 w-4" /> Staff
                    </h2>
                    {!editing && (
                        <button type="button" onClick={() => setEditing({})} className="inline-flex h-9 items-center gap-1.5 rounded-lg bg-violet-600 px-3 text-xs font-semibold text-white">
                            <Plus className="h-3.5 w-3.5" /> Add staff
                        </button>
                    )}
                </div>
                {editing && (
                    <div className="p-3">
                        <StaffForm key={editing.id ?? "new"} staff={editing} onClose={() => setEditing(null)} />
                    </div>
                )}
                {staff.length === 0 ? (
                    <p className="px-5 py-8 text-center text-sm text-slate-500">No staff yet.</p>
                ) : (
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[720px] text-left text-sm">
                            <thead className="bg-slate-50 text-[11px] uppercase tracking-wide text-slate-400">
                                <tr>
                                    <th className="px-4 py-2">Name</th>
                                    <th className="px-4 py-2">Designation</th>
                                    <th className="px-4 py-2">Bank</th>
                                    <th className="px-4 py-2 text-right">Monthly salary</th>
                                    <th className="px-4 py-2" />
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {staff.map((s) => (
                                    <tr key={s.id} className={s.status !== "active" ? "text-slate-400" : ""}>
                                        <td className="px-4 py-3">
                                            <div className="font-semibold">{s.name}</div>
                                            <div className="text-[11px] text-slate-400">{[s.email, s.phone].filter(Boolean).join(" · ")}</div>
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.designation || "—"}
                                            {s.status !== "active" && <span className="ml-2 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold">left</span>}
                                        </td>
                                        <td className="px-4 py-3">
                                            {s.bank_ready ? (
                                                <span className="font-mono text-xs">
                                                    {s.masked_bank_account} · {s.ifsc_code}
                                                </span>
                                            ) : (
                                                <span className="text-xs font-semibold text-red-600">no bank details</span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right font-bold">{inr(s.monthly_salary)}</td>
                                        <td className="px-4 py-3 text-right">
                                            <button type="button" onClick={() => setEditing(s)} className="inline-flex items-center gap-1 rounded-lg border border-slate-300 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-50">
                                                <Pencil className="h-3 w-3" /> Change
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </PanelLayout>
    );
}
