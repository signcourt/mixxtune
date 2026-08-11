import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { Head, usePage } from "@inertiajs/react";

function InfoField({ label, value }) {
    return (
        <div>
            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                {label}
            </div>

            <div className="min-h-[44px] rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-slate-800">
                {value || "—"}
            </div>
        </div>
    );
}

function StatusBadge({ value = "pending" }) {
    const normalized = String(value || "pending").toLowerCase();

    const classes = {
        active: "border-emerald-200 bg-emerald-50 text-emerald-700",
        verified: "border-emerald-200 bg-emerald-50 text-emerald-700",
        submitted: "border-blue-200 bg-blue-50 text-blue-700",
        pending: "border-amber-200 bg-amber-50 text-amber-700",
        rejected: "border-red-200 bg-red-50 text-red-700",
        suspended: "border-red-200 bg-red-50 text-red-700",
    };

    const label = normalized
        .replaceAll("_", " ")
        .replace(/\b\w/g, (letter) => letter.toUpperCase());

    return (
        <span
            className={[
                "inline-flex rounded-full border px-3 py-1 text-xs font-semibold",
                classes[normalized] ??
                    "border-slate-200 bg-slate-100 text-slate-700",
            ].join(" ")}
        >
            {label}
        </span>
    );
}

export default function Edit({ payoutProfile = null }) {
    const { auth } = usePage().props;
    const user = auth?.user || {};

    const role = user.role || "label";

    const accountType =
        role === "label"
            ? "Label"
            : role === "artist"
              ? "Artist"
              : role === "admin"
                ? "Admin"
                : role === "super_admin"
                  ? "Super Admin"
                  : role;

    const effectiveKycStatus =
        payoutProfile?.kyc_status ?? user.kyc_status ?? "pending";

    return (
        <PanelLayout
            role={role}
            title="Profile"
            subtitle="View your complete account, KYC and payout information."
        >
            <Head title="Profile" />

            <div className="space-y-6">
                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    Account Information
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Your permanent Mixx Tune account identity
                                    and contact information.
                                </p>
                            </div>

                            <span className="inline-flex w-fit rounded-full border border-slate-200 bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">
                                Read only
                            </span>
                        </div>
                    </div>

                    <div className="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
                        <InfoField
                            label="Username"
                            value={user.username || user.name}
                        />

                        <InfoField
                            label="Client ID"
                            value={user.client_id || "Client ID pending"}
                        />

                        <InfoField label="Display Name" value={user.name} />

                        <InfoField label="Email Address" value={user.email} />

                        <InfoField label="Phone Number" value={user.phone} />

                        <InfoField label="Account Type" value={accountType} />

                        <div>
                            <div className="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-500">
                                Account Status
                            </div>

                            <div className="min-h-[44px] rounded-lg border border-slate-200 bg-slate-50 px-4 py-2.5">
                                <StatusBadge
                                    value={user.account_status || "active"}
                                />
                            </div>
                        </div>

                        <InfoField label="Country" value={user.country} />

                        <InfoField
                            label="State / Region"
                            value={user.state_code}
                        />
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                            <div>
                                <h2 className="text-lg font-semibold text-slate-900">
                                    KYC & Tax Information
                                </h2>

                                <p className="mt-1 text-sm text-slate-500">
                                    Identity and tax information associated with
                                    this account.
                                </p>
                            </div>

                            <StatusBadge value={effectiveKycStatus} />
                        </div>
                    </div>

                    <div className="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
                        <InfoField
                            label="PAN Number"
                            value={payoutProfile?.pan_number}
                        />

                        <InfoField
                            label="GST Number"
                            value={payoutProfile?.gst_number}
                        />

                        <InfoField
                            label="KYC Reference"
                            value={payoutProfile?.public_id}
                        />

                        <InfoField
                            label="Verified At"
                            value={payoutProfile?.verified_at}
                        />
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Address Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Registered address linked to your payout and KYC
                                profile.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
                        <InfoField
                            label="Address Line 1"
                            value={payoutProfile?.address_line_1}
                        />

                        <InfoField
                            label="Address Line 2"
                            value={payoutProfile?.address_line_2}
                        />

                        <InfoField label="City" value={payoutProfile?.city} />

                        <InfoField label="State" value={payoutProfile?.state} />

                        <InfoField
                            label="Postal Code"
                            value={payoutProfile?.postal_code}
                        />

                        <InfoField
                            label="Country Code"
                            value={payoutProfile?.country_code}
                        />
                    </div>
                </section>

                <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div className="border-b border-slate-200 px-6 py-5">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Bank & Payout Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Read-only payout destination details registered
                                for this account.
                            </p>
                        </div>
                    </div>

                    <div className="grid gap-5 p-6 md:grid-cols-2 xl:grid-cols-3">
                        <InfoField
                            label="Account Holder Name"
                            value={payoutProfile?.account_holder_name}
                        />

                        <InfoField
                            label="Bank Name"
                            value={payoutProfile?.bank_name}
                        />

                        <InfoField
                            label="Bank Account Number"
                            value={payoutProfile?.bank_account_number}
                        />

                        <InfoField
                            label="IFSC Code"
                            value={payoutProfile?.ifsc_code}
                        />

                        <InfoField
                            label="Branch Name"
                            value={payoutProfile?.branch_name}
                        />

                        <InfoField
                            label="UPI ID"
                            value={payoutProfile?.upi_id}
                        />
                    </div>
                </section>
            </div>
        </PanelLayout>
    );
}
