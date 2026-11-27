import { Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/*
 * W233 MIXXTUNE_WALLET_INLINE_REQUEST (W198)
 * "Request my payment" works right inside the Wallet card: amount (the whole balance by default),
 * the bank account it goes to, Confirm. No second page. The request itself is the same
 * POST /withdrawals as before, so every server rule (KYC, minimum, cycle, bank) still decides.
 */

const BTN = "inline-flex h-12 items-center justify-center rounded-xl bg-violet-600 px-6 text-[15px] font-semibold text-white shadow-sm hover:bg-violet-700";

export function WalletRequestStatus({ info, money }) {
    const active = info?.active;
    const paid = info?.last_paid;
    if (!active && !paid) return null;
    return (
        <div className="mb-3 space-y-2 lg:ml-auto lg:max-w-sm lg:text-left">
            {active && (
                <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-xs text-amber-900">
                    <div className="text-sm font-bold">Payment request {active.reference}</div>
                    <div className="mt-0.5">
                        {money(active.amount)} · {active.status === "approved" ? "Approved, payment on the way" : "Under process"}
                        {active.requested_on ? ` · asked on ${active.requested_on}` : ""}
                    </div>
                    {active.message && <div className="mt-1 text-amber-800">{active.message}</div>}
                    {info.active_count > 1 && <div className="mt-1 text-amber-800">{info.active_count} requests are open.</div>}
                    <Link href="/withdrawals" className="mt-1 inline-block font-semibold text-amber-900 underline">See all payment requests</Link>
                </div>
            )}
            {!active && paid && (
                <div className="rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-xs text-emerald-900">
                    <div className="text-sm font-bold">Payment complete</div>
                    <div className="mt-0.5">
                        {money(paid.amount)} paid{paid.paid_on ? ` on ${paid.paid_on}` : ""} · {paid.reference}
                    </div>
                    <Link href="/withdrawals" className="mt-1 inline-block font-semibold text-emerald-900 underline">See all payment requests</Link>
                </div>
            )}
        </div>
    );
}

export default function WalletPaymentRequest({ info, available: rawAvailable, minimum, money }) {
    const [open, setOpen] = useState(false);
    /* the balance has more than 2 decimals: always round DOWN, never ask for a paisa that is not there */
    const available = Math.floor(Number(rawAvailable || 0) * 100 + 1e-6) / 100;
    const form = useForm({ amount: available.toFixed(2), payment_method: "bank", request_note: "" });

    /* no bank on file (or an older server answer): the old page still handles it */
    if (!info || !info.bank_last4) {
        return <Link href="/withdrawals" className={BTN}>Request my payment</Link>;
    }

    if (info.cycle && info.cycle.allowed === false) {
        return (
            <div className="space-y-2">
                <button type="button" disabled className="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-6 text-[15px] font-semibold text-slate-500">Request my payment</button>
                <p className="max-w-xs text-xs text-slate-500 lg:ml-auto">{info.cycle.message || "Payments open at the end of the quarter."}</p>
            </div>
        );
    }

    const amount = Number(form.data.amount || 0);
    const ok = amount >= Number(minimum || 0) && amount > 0 && amount <= available + 0.000001;
    const firstError = Object.values(form.errors || {})[0];

    const submit = (e) => {
        e.preventDefault();
        if (!ok || form.processing) return;
        form.post("/withdrawals", { preserveScroll: true, onSuccess: () => setOpen(false) });
    };

    if (!open) {
        return (
            <button type="button" onClick={() => { form.setData("amount", Number(available || 0).toFixed(2)); form.clearErrors(); setOpen(true); }} className={BTN}>
                Request my payment
            </button>
        );
    }

    return (
        <form onSubmit={submit} className="w-full rounded-2xl border border-violet-200 bg-violet-50/60 p-4 text-left lg:ml-auto lg:w-[340px]">
            <div className="text-sm font-bold text-slate-900">Request my payment</div>
            <label className="mt-3 block text-xs font-semibold text-slate-600" htmlFor="w233-amount">Amount</label>
            <input
                id="w233-amount"
                type="number"
                step="0.01"
                min={Number(minimum || 0) || 1}
                max={Number(available || 0)}
                value={form.data.amount}
                onChange={(e) => form.setData("amount", e.target.value)}
                className="mt-1 h-11 w-full rounded-xl border border-slate-300 px-3 text-base font-semibold text-slate-900"
            />
            <div className="mt-1 text-[11px] text-slate-500">
                Between {money(minimum)} and {money(available)}.
            </div>
            <div className="mt-3 rounded-xl bg-white px-3 py-2 text-xs text-slate-700">
                Goes to your bank account ending <span className="font-bold">{info.bank_last4}</span>
                {info.bank_name ? ` (${info.bank_name})` : ""}.
            </div>
            {firstError && <div className="mt-2 rounded-lg bg-rose-50 px-3 py-2 text-xs font-semibold text-rose-700">{String(firstError)}</div>}
            <div className="mt-3 flex gap-2">
                <button type="submit" disabled={!ok || form.processing} className="h-11 flex-1 rounded-xl bg-violet-600 text-sm font-bold text-white hover:bg-violet-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                    {form.processing ? "Sending…" : `Confirm ${ok ? money(amount) : ""}`}
                </button>
                <button type="button" onClick={() => setOpen(false)} className="h-11 rounded-xl border border-slate-300 bg-white px-4 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                    Cancel
                </button>
            </div>
        </form>
    );
}
