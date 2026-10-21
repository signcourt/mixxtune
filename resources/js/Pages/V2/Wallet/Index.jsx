import { Head, Link, router } from "@inertiajs/react";
import { Download, History, ReceiptText, Search, WalletCards, X } from "lucide-react";
import { useEffect, useRef, useState } from "react";

import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";

/* MIXXTUNE_WALLET_V2 (W44) - W70: the 4 small cards (on the way / in withdrawal / lifetime / paid out) removed */

export default function Index({
    role = "artist",
    wallet = {},
    transactions = {},
    minimumWithdrawal = 0,
    withdrawalEnabled = true,
    owners = null,
    ownerSearch = "",
    advances = [],
    historyWallet = null,
    historyOwner = null,
    sort = "available",
}) {
    const staff = role === "admin" || role === "super_admin";
    const currency = wallet.currency ?? "INR";
    const money = (v) => {
        try {
            return new Intl.NumberFormat("en-IN", { style: "currency", currency, minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(v ?? 0));
        } catch {
            return `${currency} ${Number(v ?? 0).toFixed(2)}`;
        }
    };

    const available = Number(wallet.available_balance ?? 0);
    const minimum = Number(minimumWithdrawal ?? 0);
    const canRequest = withdrawalEnabled && minimum > 0 && available >= minimum;
    const today = new Intl.DateTimeFormat("en-GB", { day: "2-digit", month: "long", year: "numeric" }).format(new Date());

    return (
        <PanelLayout role={role} title="Wallet" subtitle={staff ? "Every owner's balance and wallet history" : "Balance, payments and transaction history"}>
            <Head title="Wallet" />

            <div className="mx-auto w-full max-w-[1500px] space-y-5">
                {/* balance */}
                <section className="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-6">
                    <div className="flex flex-col gap-5 lg:flex-row lg:items-start lg:justify-between">
                        <div>
                            <div className="flex items-center gap-2 text-sm font-semibold text-slate-500">
                                <WalletCards className="h-4 w-4" /> {staff ? "All owner wallets · available now" : `Available balance on ${today}`}
                            </div>
                            <div className="mt-1 text-[34px] font-black tracking-tight text-slate-950 sm:text-[40px]">{money(available)}</div>
                            {staff ? (
                                <p className="text-sm text-slate-500">
                                    {wallet.owners_with_money ?? 0} of {wallet.wallet_count ?? 0} owners have money to withdraw. This page only shows balances — nothing here moves money.
                                </p>
                            ) : (
                                <p className="text-sm text-slate-500">Money you can ask to be paid to your bank now.</p>
                            )}
                        </div>

                        {!staff && (
                            <div className="lg:text-right">
                                {canRequest ? (
                                    <Link href="/v2/withdrawals" className="inline-flex h-12 items-center justify-center rounded-xl bg-violet-600 px-6 text-[15px] font-semibold text-white shadow-sm hover:bg-violet-700">
                                        Request my payment
                                    </Link>
                                ) : (
                                    <div className="space-y-2">
                                        <button type="button" disabled className="inline-flex h-12 cursor-not-allowed items-center justify-center rounded-xl bg-slate-200 px-6 text-[15px] font-semibold text-slate-500">
                                            Request my payment
                                        </button>
                                        {!withdrawalEnabled ? (
                                            <p className="max-w-xs rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-xs text-amber-800 lg:ml-auto">
                                                Withdrawals are switched off for this account. Please contact support.
                                            </p>
                                        ) : minimum > 0 ? (
                                            <p className="text-xs text-slate-500">
                                                Minimum {money(minimum)} · you need {money(Math.max(minimum - available, 0))} more.
                                            </p>
                                        ) : (
                                            <p className="text-xs text-slate-500">Payments are not open yet.</p>
                                        )}
                                    </div>
                                )}
                            </div>
                        )}
                    </div>
                </section>

                {!staff && advances.length > 0 && <AdvanceCard advances={advances} />}

                {staff && owners && <OwnerBalances owners={owners} initialSearch={ownerSearch} sort={sort} money={money} role={role} activeWallet={historyWallet} />}

                <HistorySection transactions={transactions} money={money} staff={staff} historyWallet={historyWallet} historyOwner={historyOwner} role={role} />
            </div>
        </PanelLayout>
    );
}


function Pager({ page, name }) {
    if (!page || !(page.last_page > 1)) return null;
    return (
        <div className="flex items-center justify-between border-t border-slate-100 px-4 py-3 text-sm">
            {page.prev_page_url ? <Link href={page.prev_page_url} preserveScroll preserveState className="font-semibold text-violet-700">← Previous</Link> : <span />}
            <span className="text-xs text-slate-500">{name} page {page.current_page} of {page.last_page}</span>
            {page.next_page_url ? <Link href={page.next_page_url} preserveScroll preserveState className="font-semibold text-violet-700">Next →</Link> : <span />}
        </div>
    );
}

function HistorySection({ transactions, money, staff, historyWallet, historyOwner, role }) {
    const rows = transactions.data ?? [];
    const clear = () => router.get(window.location.pathname, { wallet: undefined, owner_search: new URLSearchParams(window.location.search).get("owner_search") || undefined }, { preserveScroll: true, preserveState: true });

    return (
        <section id="history" className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 px-4 py-3 sm:px-5">
                <div className="flex items-center gap-2">
                    <History className="h-4 w-4 text-slate-500" />
                    <h2 className="text-base font-semibold text-slate-900">
                        {staff ? (historyWallet ? `History · ${historyOwner ?? "owner"}` : "Latest wallet entries (all owners)") : "Transaction history"}
                    </h2>
                    {historyWallet && (
                        <button type="button" onClick={clear} className="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-semibold text-slate-600">
                            <X className="h-3 w-3" /> show all
                        </button>
                    )}
                </div>
                <Link href={role === "super_admin" ? "/v2/admin/invoices" : "/v2/invoices"} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">
                    <ReceiptText className="h-3.5 w-3.5" /> Invoices
                </Link>
            </div>

            {rows.length === 0 ? (
                <div className="px-5 py-12 text-center">
                    <ReceiptText className="mx-auto h-9 w-9 text-slate-300" />
                    <p className="mt-2 text-sm font-semibold text-slate-700">No wallet entries yet</p>
                    <p className="mt-1 text-xs text-slate-500">{staff ? "Royalties, adjustments and withdrawals of owners appear here." : "Your royalties and payments will appear here."}</p>
                </div>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {rows.map((t) => {
                        const credit = t.direction === "credit";
                        return (
                            <li key={t.id} className="flex items-start justify-between gap-3 px-4 py-3 sm:px-5">
                                <div className="min-w-0">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="text-sm font-semibold text-slate-900">{t.type}</span>
                                        {t.month && <span className="rounded-full bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-600">{t.month}</span>}
                                    </div>
                                    {staff && t.owner && <div className="text-xs font-medium text-slate-700">{t.owner}</div>}
                                    {t.description && <div className="break-words text-xs text-slate-500">{t.description}</div>}
                                    <div className="text-[11px] text-slate-400">{t.date}</div>
                                </div>
                                <div className="shrink-0 text-right">
                                    <div className={`whitespace-nowrap text-sm font-bold ${credit ? "text-emerald-700" : "text-slate-800"}`}>{credit ? "+" : "−"}{money(t.amount)}</div>
                                    {t.balance_after !== null && <div className="whitespace-nowrap text-[11px] text-slate-500">balance {money(t.balance_after)}</div>}
                                </div>
                            </li>
                        );
                    })}
                </ul>
            )}
            <Pager page={transactions} name="History" />
        </section>
    );
}

/* MIXXTUNE_FINANCE_CLEANUP_V1 + W44: per-owner wallet list for staff */
function OwnerBalances({ owners, initialSearch = "", sort, money, role, activeWallet }) {
    const [search, setSearch] = useState(initialSearch ?? "");
    const first = useRef(true);
    const rows = owners.data ?? [];

    const go = (patch) => router.get(window.location.pathname, { owner_search: search.trim() || undefined, sort: sort !== "available" ? sort : undefined, wallet: activeWallet || undefined, ...patch }, { preserveState: true, preserveScroll: true, replace: true });

    useEffect(() => {
        if (first.current) {
            first.current = false;
            return;
        }
        const t = setTimeout(() => go({ owner_search: search.trim() || undefined, owners_page: undefined }), 400);
        return () => clearTimeout(t);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const showHistory = (id) => {
        go({ wallet: id, tx_page: undefined });
        setTimeout(() => document.getElementById("history")?.scrollIntoView({ behavior: "smooth" }), 400);
    };

    return (
        <section className="rounded-2xl border border-slate-200 bg-white shadow-sm">
            <div className="flex flex-col gap-2 border-b border-slate-100 p-3 sm:px-5 lg:flex-row lg:items-center">
                <div className="min-w-0 flex-1">
                    <h2 className="text-base font-semibold text-slate-900">Balance per owner</h2>
                    <p className="text-xs text-slate-500">{Number(owners.total ?? rows.length).toLocaleString("en-IN")} wallets</p>
                </div>
                <div className="relative lg:w-72">
                    <Search className="pointer-events-none absolute left-3 top-3 h-4 w-4 text-slate-400" />
                    <input type="search" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Owner name, e-mail or client ID" className="h-10 w-full rounded-xl border border-slate-300 pl-9 pr-3 text-sm" />
                </div>
                <div className="grid grid-cols-2 gap-2 sm:flex">
                    <select value={sort} onChange={(e) => go({ sort: e.target.value === "available" ? undefined : e.target.value, owners_page: undefined })} className="h-10 min-w-0 rounded-xl border border-slate-300 bg-white pl-3 pr-8 text-sm">
                        <option value="available">Most available</option>
                        <option value="pending">Most on the way</option>
                        <option value="earned">Most earned</option>
                        <option value="recent">Recently changed</option>
                    </select>
                    <a href="/v2/admin/wallet/export" className="inline-flex h-10 items-center justify-center gap-1.5 whitespace-nowrap rounded-xl border border-slate-300 px-3 text-sm font-semibold text-slate-700">
                        <Download className="h-4 w-4" /> CSV
                    </a>
                </div>
            </div>

            {rows.length === 0 ? (
                <p className="px-5 py-10 text-center text-sm text-slate-500">No owner wallets found.</p>
            ) : (
                <ul className="divide-y divide-slate-100">
                    {rows.map((w) => (
                        <li key={w.id} className={`flex flex-col gap-2 px-4 py-3 sm:px-5 lg:flex-row lg:items-center ${activeWallet === w.id ? "bg-violet-50/60" : ""}`}>
                            <div className="min-w-0 flex-1">
                                <div className="truncate text-sm font-semibold text-slate-900">{w.owner_name || `User #${w.user_id}`}</div>
                                <div className="truncate text-xs text-slate-500">{w.owner_email || "—"} · {w.owner_role || "—"}{w.client_id ? ` · ${w.client_id}` : ""}{w.updated ? ` · changed ${w.updated}` : ""}</div>
                            </div>
                            <div className="grid grid-cols-3 gap-2 text-right text-xs lg:w-[420px]">
                                <div><div className="text-slate-500">Available</div><div className="text-sm font-black text-slate-900">{money(w.available_balance)}</div></div>
                                <div><div className="text-slate-500">On the way</div><div className="text-sm font-semibold text-slate-700">{money(w.royalty_pending)}</div></div>
                                <div><div className="text-slate-500">In withdrawal</div><div className={`text-sm font-semibold ${w.in_withdrawal > 0 ? "text-amber-700" : "text-slate-700"}`}>{money(w.in_withdrawal)}</div></div>
                            </div>
                            <div className="flex shrink-0 gap-1.5">
                                <button type="button" onClick={() => showHistory(w.id)} className="inline-flex h-9 items-center gap-1 rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">
                                    <History className="h-3.5 w-3.5" /> History
                                </button>
                                {role === "super_admin" && (
                                    <Link href="/super-admin/finance/adjustments" className="inline-flex h-9 items-center rounded-lg border border-slate-200 px-3 text-xs font-semibold text-slate-700">
                                        Adjust
                                    </Link>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
            <Pager page={owners} name="Owners" />
        </section>
    );
}

/* MIXXTUNE_RECOUPMENT_V2: the owner sees the advance being repaid */
function AdvanceCard({ advances }) {
    const inr = (v) => new Intl.NumberFormat("en-IN", { style: "currency", currency: "INR", maximumFractionDigits: 2 }).format(Number(v || 0));

    return (
        <section className="rounded-2xl border border-amber-200 bg-amber-50/60 px-5 py-5 sm:px-6">
            <h2 className="text-[16px] font-semibold text-slate-950">Advance being repaid</h2>
            <p className="mt-1 text-sm text-slate-600">
                A part of your royalties goes to repay the advance / costs below until they are fully recovered. Questions? Open a support ticket.
            </p>
            <div className="mt-4 space-y-3">
                {advances.map((a) => {
                    const pct = a.total > 0 ? Math.min(100, Math.round((a.recovered / a.total) * 100)) : 0;
                    return (
                        <div key={a.plan_number} className="rounded-lg border border-amber-200 bg-white px-4 py-3">
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <div className="text-sm font-semibold text-slate-900">{a.title} <span className="font-normal text-slate-500">· {a.plan_number}</span></div>
                                <div className="text-sm font-semibold text-amber-800">Still to repay {inr(a.outstanding)}</div>
                            </div>
                            <div className="mt-2 h-2 overflow-hidden rounded-full bg-slate-100">
                                <div className="h-full rounded-full bg-emerald-500" style={{ width: `${pct}%` }} />
                            </div>
                            <div className="mt-1.5 flex flex-wrap justify-between gap-2 text-xs text-slate-500">
                                <span>Repaid {inr(a.recovered)} of {inr(a.total)} ({pct}%)</span>
                                <span>{a.recovery}% of revenue used for repayment</span>
                            </div>
                        </div>
                    );
                })}
            </div>
        </section>
    );
}
