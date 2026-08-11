import { Head, Link, useForm } from "@inertiajs/react";
import AdminLayout from "@/Layouts/AdminLayout";

function Field({
    label,
    type = "text",
    value,
    error,
    required = false,
    onChange,
}) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">
                {label}
                {required && (
                    <span className="ml-1 text-red-500">*</span>
                )}
            </span>

            <input
                type={type}
                value={value ?? ""}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className={`mt-2 w-full rounded-xl border px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 ${
                    error
                        ? "border-red-300 focus:border-red-400 focus:ring-red-100"
                        : "border-slate-300 focus:border-indigo-400 focus:ring-indigo-100"
                }`}
            />

            {error && (
                <p className="mt-1 text-xs font-medium text-red-600">
                    {error}
                </p>
            )}
        </label>
    );
}

function SelectField({
    label,
    value,
    error,
    required = false,
    options,
    onChange,
}) {
    return (
        <label className="block">
            <span className="text-sm font-semibold text-slate-700">
                {label}
                {required && (
                    <span className="ml-1 text-red-500">*</span>
                )}
            </span>

            <select
                value={value ?? ""}
                required={required}
                onChange={(event) => onChange(event.target.value)}
                className={`mt-2 w-full rounded-xl border bg-white px-4 py-3 text-sm text-slate-900 outline-none transition focus:ring-2 ${
                    error
                        ? "border-red-300 focus:border-red-400 focus:ring-red-100"
                        : "border-slate-300 focus:border-indigo-400 focus:ring-indigo-100"
                }`}
            >
                {options.map((option) => (
                    <option
                        key={option.value}
                        value={option.value}
                    >
                        {option.label}
                    </option>
                ))}
            </select>

            {error && (
                <p className="mt-1 text-xs font-medium text-red-600">
                    {error}
                </p>
            )}
        </label>
    );
}

export default function Edit({ artist }) {
    const {
        data,
        setData,
        patch,
        processing,
        errors,
    } = useForm({
        name: artist?.name ?? "",
        email: artist?.email ?? "",
        phone: artist?.phone ?? "",
        label_name: artist?.label_name ?? "",
        country: artist?.country ?? "",
        account_status: artist?.account_status ?? "active",
        kyc_status: artist?.kyc_status ?? "pending",
    });

    const submit = (event) => {
        event.preventDefault();

        patch(`/artists/${artist.id}`, {
            preserveScroll: true,
        });
    };

    return (
        <AdminLayout title="Edit Artist">
            <Head title={`Edit Artist - ${artist?.name ?? ""}`} />

            <div className="mx-auto max-w-6xl space-y-6">
                <div className="flex flex-wrap items-center justify-between gap-4">
                    <div>
                        <p className="text-xs font-semibold uppercase tracking-[0.18em] text-indigo-600">
                            Artist Management
                        </p>

                        <h1 className="mt-1 text-2xl font-bold text-slate-900">
                            Edit Artist
                        </h1>

                        <p className="mt-1 text-sm text-slate-500">
                            Update artist account information, account status and KYC status.
                        </p>
                    </div>

                    <Link
                        href="/artists"
                        className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                    >
                        Back to Artists
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6"
                >
                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Artist Information
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Manage the primary information attached to this artist account.
                            </p>
                        </div>

                        <div className="mt-6 grid gap-5 md:grid-cols-2">
                            <Field
                                label="Artist Name"
                                value={data.name}
                                error={errors.name}
                                required
                                onChange={(value) =>
                                    setData("name", value)
                                }
                            />

                            <Field
                                label="Email"
                                type="email"
                                value={data.email}
                                error={errors.email}
                                required
                                onChange={(value) =>
                                    setData("email", value)
                                }
                            />

                            <Field
                                label="Phone"
                                value={data.phone}
                                error={errors.phone}
                                onChange={(value) =>
                                    setData("phone", value)
                                }
                            />

                            <Field
                                label="Label Name"
                                value={data.label_name}
                                error={errors.label_name}
                                onChange={(value) =>
                                    setData("label_name", value)
                                }
                            />

                            <Field
                                label="Country"
                                value={data.country}
                                error={errors.country}
                                onChange={(value) =>
                                    setData("country", value)
                                }
                            />
                        </div>
                    </section>

                    <section className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                        <div>
                            <h2 className="text-lg font-semibold text-slate-900">
                                Account Controls
                            </h2>

                            <p className="mt-1 text-sm text-slate-500">
                                Control account availability and KYC verification status.
                            </p>
                        </div>

                        <div className="mt-6 grid gap-5 md:grid-cols-2">
                            <SelectField
                                label="Account Status"
                                value={data.account_status}
                                error={errors.account_status}
                                required
                                options={[
                                    {
                                        value: "active",
                                        label: "Active",
                                    },
                                    {
                                        value: "suspended",
                                        label: "Suspended",
                                    },
                                ]}
                                onChange={(value) =>
                                    setData(
                                        "account_status",
                                        value
                                    )
                                }
                            />

                            <SelectField
                                label="KYC Status"
                                value={data.kyc_status}
                                error={errors.kyc_status}
                                required
                                options={[
                                    {
                                        value: "pending",
                                        label: "Pending",
                                    },
                                    {
                                        value: "verified",
                                        label: "Verified",
                                    },
                                    {
                                        value: "rejected",
                                        label: "Rejected",
                                    },
                                ]}
                                onChange={(value) =>
                                    setData(
                                        "kyc_status",
                                        value
                                    )
                                }
                            />
                        </div>
                    </section>

                    <div className="flex flex-wrap items-center justify-end gap-3">
                        <Link
                            href="/artists"
                            className="inline-flex items-center justify-center rounded-xl border border-slate-300 bg-white px-5 py-2.5 text-sm font-semibold text-slate-700 transition hover:bg-slate-50"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            disabled={processing}
                            className="inline-flex items-center justify-center rounded-xl bg-indigo-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {processing
                                ? "Saving..."
                                : "Save Changes"}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
