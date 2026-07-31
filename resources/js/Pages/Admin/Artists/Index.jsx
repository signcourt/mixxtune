import AdminLayout from '@/Layouts/AdminLayout';
import { Head, Link, router } from '@inertiajs/react';

export default function ArtistsIndex({ artists = [] }) {
    const deleteArtist = (artist) => {
        if (!window.confirm(`Delete ${artist.name}?`)) {
            return;
        }

        router.delete(`/artists/${artist.id}`);
    };

    const resendInvitation = (artist) => {
        if (!window.confirm(`Resend invitation to ${artist.email}?`)) {
            return;
        }

        router.post(
            `/artists/${artist.id}/resend-invitation`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    window.alert('Invitation resend request completed.');
                },
                onError: (errors) => {
                    window.alert(
                        errors.invitation ||
                            'Invitation could not be resent.'
                    );
                },
            }
        );
    };

    const statusClass = (status) => {
        if (
            status === 'active' ||
            status === 'verified' ||
            status === 'accepted'
        ) {
            return 'bg-emerald-50 text-emerald-700';
        }

        if (
            status === 'suspended' ||
            status === 'rejected' ||
            status === 'failed' ||
            status === 'expired'
        ) {
            return 'bg-red-50 text-red-700';
        }

        return 'bg-amber-50 text-amber-700';
    };

    const statusLabel = (status) => {
        if (!status) {
            return 'Pending';
        }

        return status
            .replaceAll('_', ' ')
            .replace(/\b\w/g, (letter) => letter.toUpperCase());
    };

    return (
        <AdminLayout title="Artists">
            <Head title="Artists" />

            <section className="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div className="flex flex-col gap-4 border-b border-slate-200 p-6 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h2 className="text-xl font-bold text-slate-900">
                            Artists
                        </h2>

                        <p className="mt-1 text-sm text-slate-500">
                            Manage artist accounts, invitations, KYC and status.
                        </p>
                    </div>

                    <Link
                        href="/artists/create"
                        className="inline-flex items-center justify-center rounded-xl bg-[#0d1526] px-5 py-3 text-sm font-semibold text-white"
                    >
                        + Add Artist
                    </Link>
                </div>

                <div className="overflow-x-auto">
                    <table className="min-w-full divide-y divide-slate-200">
                        <thead className="bg-slate-50">
                            <tr>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    Artist
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    Label
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    Account
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    KYC
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    Invitation
                                </th>
                                <th className="px-6 py-4 text-left text-xs font-semibold uppercase text-slate-500">
                                    Wallet
                                </th>
                                <th className="px-6 py-4 text-right text-xs font-semibold uppercase text-slate-500">
                                    Actions
                                </th>
                            </tr>
                        </thead>

                        <tbody className="divide-y divide-slate-100">
                            {artists.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={7}
                                        className="px-6 py-20 text-center"
                                    >
                                        <h3 className="font-semibold text-slate-800">
                                            No artists found
                                        </h3>

                                        <p className="mt-1 text-sm text-slate-500">
                                            Send your first artist invitation.
                                        </p>

                                        <Link
                                            href="/artists/create"
                                            className="mt-5 inline-flex rounded-xl border border-slate-200 px-5 py-3 text-sm font-semibold text-slate-700"
                                        >
                                            Add First Artist
                                        </Link>
                                    </td>
                                </tr>
                            ) : (
                                artists.map((artist) => (
                                    <tr
                                        key={artist.id}
                                        className="hover:bg-slate-50"
                                    >
                                        <td className="px-6 py-5">
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-slate-100 font-bold text-slate-700">
                                                    {artist.name
                                                        ?.charAt(0)
                                                        ?.toUpperCase() || 'A'}
                                                </div>

                                                <div>
                                                    <p className="font-semibold text-slate-900">
                                                        {artist.name}
                                                    </p>

                                                    <p className="text-sm text-slate-500">
                                                        {artist.email}
                                                    </p>
                                                </div>
                                            </div>
                                        </td>

                                        <td className="px-6 py-5 text-sm text-slate-600">
                                            {artist.label_name || '—'}
                                        </td>

                                        <td className="px-6 py-5">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                                                    artist.account_status
                                                )}`}
                                            >
                                                {statusLabel(
                                                    artist.account_status
                                                )}
                                            </span>
                                        </td>

                                        <td className="px-6 py-5">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                                                    artist.kyc_status
                                                )}`}
                                            >
                                                {statusLabel(
                                                    artist.kyc_status
                                                )}
                                            </span>
                                        </td>

                                        <td className="px-6 py-5">
                                            <span
                                                className={`rounded-full px-3 py-1 text-xs font-semibold ${statusClass(
                                                    artist.invitation_status
                                                )}`}
                                            >
                                                {statusLabel(
                                                    artist.invitation_status
                                                )}
                                            </span>

                                            {artist.invitation_sent_at && (
                                                <p className="mt-2 text-xs text-slate-400">
                                                    Sent:{' '}
                                                    {
                                                        artist.invitation_sent_at
                                                    }
                                                </p>
                                            )}

                                            {artist.invitation_error && (
                                                <p className="mt-2 max-w-xs text-xs text-red-600">
                                                    {
                                                        artist.invitation_error
                                                    }
                                                </p>
                                            )}
                                        </td>

                                        <td className="px-6 py-5 text-sm font-semibold text-slate-700">
                                            ₹
                                            {Number(
                                                artist.wallet_balance || 0
                                            ).toFixed(2)}
                                        </td>

                                        <td className="px-6 py-5">
                                            <div className="flex justify-end gap-2">
                                                <Link
                                                    href={`/artists/${artist.id}/edit`}
                                                    className="rounded-lg border border-slate-200 px-3 py-2 text-xs font-semibold text-slate-700"
                                                >
                                                    Edit
                                                </Link>

                                                {artist.invitation_status !==
                                                    'active' &&
                                                    artist.invitation_status !==
                                                        'accepted' && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                resendInvitation(
                                                                    artist
                                                                )
                                                            }
                                                            className="rounded-lg border border-blue-200 px-3 py-2 text-xs font-semibold text-blue-700"
                                                        >
                                                            Resend Invite
                                                        </button>
                                                    )}

                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        deleteArtist(artist)
                                                    }
                                                    className="rounded-lg border border-red-200 px-3 py-2 text-xs font-semibold text-red-600"
                                                >
                                                    Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>
            </section>
        </AdminLayout>
    );
}
