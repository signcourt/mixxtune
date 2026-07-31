import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function CreateArtist() {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        phone: '',
        label_name: '',
        country: 'India',
        account_status: 'active',
        kyc_status: 'pending',
    });

    const submit = (event) => {
        event.preventDefault();
        post('/artists');
    };

    const inputClass = (field) =>
        `mt-2 w-full rounded-xl border px-4 py-3 text-sm outline-none transition ${
            errors[field]
                ? 'border-red-400 bg-red-50'
                : 'border-slate-200 bg-white focus:border-slate-400'
        }`;

    return (
        <AdminLayout title="Add Artist">
            <Head title="Add Artist" />

            <div className="mx-auto max-w-5xl">
                <div className="mb-6 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-2xl font-bold text-slate-900">
                            Invite New Artist
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Artist will verify the email and create their own password.
                        </p>
                    </div>

                    <Link
                        href="/artists"
                        className="inline-flex items-center justify-center rounded-xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700"
                    >
                        ← Back to Artists
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white shadow-sm"
                >
                    <div className="border-b border-slate-200 p-6">
                        <h3 className="text-lg font-bold text-slate-900">
                            Artist Information
                        </h3>

                        <p className="mt-1 text-sm text-slate-500">
                            Enter artist and label details.
                        </p>
                    </div>

                    <div className="grid gap-6 p-6 md:grid-cols-2">
                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Artist Name *
                            </label>

                            <input
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                className={inputClass('name')}
                                placeholder="Enter artist name"
                            />

                            {errors.name && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.name}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Email Address *
                            </label>

                            <input
                                type="email"
                                value={data.email}
                                onChange={(e) => setData('email', e.target.value)}
                                className={inputClass('email')}
                                placeholder="artist@example.com"
                            />

                            {errors.email && (
                                <p className="mt-1 text-xs text-red-600">
                                    {errors.email}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Phone Number
                            </label>

                            <input
                                value={data.phone}
                                onChange={(e) => setData('phone', e.target.value)}
                                className={inputClass('phone')}
                                placeholder="+91"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Label Name
                            </label>

                            <input
                                value={data.label_name}
                                onChange={(e) =>
                                    setData('label_name', e.target.value)
                                }
                                className={inputClass('label_name')}
                                placeholder="Enter label name"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Country
                            </label>

                            <input
                                value={data.country}
                                onChange={(e) => setData('country', e.target.value)}
                                className={inputClass('country')}
                            />
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                Account Status
                            </label>

                            <select
                                value={data.account_status}
                                onChange={(e) =>
                                    setData('account_status', e.target.value)
                                }
                                className={inputClass('account_status')}
                            >
                                <option value="active">Active</option>
                                <option value="suspended">Suspended</option>
                            </select>
                        </div>

                        <div>
                            <label className="text-sm font-semibold text-slate-700">
                                KYC Status
                            </label>

                            <select
                                value={data.kyc_status}
                                onChange={(e) =>
                                    setData('kyc_status', e.target.value)
                                }
                                className={inputClass('kyc_status')}
                            >
                                <option value="pending">Pending</option>
                                <option value="verified">Verified</option>
                                <option value="rejected">Rejected</option>
                            </select>
                        </div>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50 p-6">
                        <div className="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <p className="text-sm font-semibold text-blue-900">
                                Secure invitation flow
                            </p>
                            <p className="mt-1 text-sm text-blue-700">
                                Artist will receive an email, verify their email address,
                                and then create their own password.
                            </p>
                        </div>
                    </div>

                    <div className="flex justify-end gap-3 border-t border-slate-200 p-6">
                        <Link
                            href="/artists"
                            className="rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-[#0d1526] px-6 py-3 text-sm font-semibold text-white disabled:opacity-60"
                        >
                            {processing ? 'Sending...' : 'Send Invitation'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
