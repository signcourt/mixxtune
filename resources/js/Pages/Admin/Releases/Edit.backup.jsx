import { Head, Link, useForm } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function Edit({ release, artists = [], labels = [] }) {
    const { data, setData, patch, processing, errors } = useForm({
        artist_id: release.artist_id ?? '',
        label_id: release.label_id ?? '',
        catalog_number: release.catalog_number ?? '',
        release_type: release.release_type ?? 'single',
        title: release.title ?? '',
        primary_artist_name: release.primary_artist_name ?? '',
        language: release.language ?? '',
        primary_genre: release.primary_genre ?? '',
        digital_release_date: release.digital_release_date
            ? String(release.digital_release_date).slice(0, 10)
            : '',
        copyright_owner: release.copyright_owner ?? '',
        copyright_year: release.copyright_year ?? '',
        phonographic_owner: release.phonographic_owner ?? '',
        phonographic_year: release.phonographic_year ?? '',
        status: release.status ?? 'draft',
        wizard_step: release.wizard_step ?? 1,
        completion_percentage: release.completion_percentage ?? 20,
    });

    const submit = (e) => {
        e.preventDefault();
        patch(`/releases/${release.id}`);
    };

    return (
        <AdminLayout title="Edit Release">
            <Head title={`Edit ${release.title}`} />

            <div className="space-y-6">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-slate-900">
                            Edit Release
                        </h1>
                        <p className="mt-1 text-sm text-slate-500">
                            Update release details and save your changes.
                        </p>
                    </div>

                    <Link
                        href="/releases"
                        className="rounded-xl border border-slate-300 bg-white px-5 py-3 text-sm font-semibold text-slate-700 hover:bg-slate-50"
                    >
                        Back to Releases
                    </Link>
                </div>

                <form
                    onSubmit={submit}
                    className="rounded-2xl border border-slate-200 bg-white p-6 shadow-sm"
                >
                    <div className="grid gap-6 md:grid-cols-2">
                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Release Title
                            </label>
                            <input
                                type="text"
                                value={data.title}
                                onChange={(e) => setData('title', e.target.value)}
                                className="w-full rounded-xl border-slate-300"
                            />
                            {errors.title && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.title}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Release Type
                            </label>
                            <select
                                value={data.release_type}
                                onChange={(e) =>
                                    setData('release_type', e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300"
                            >
                                <option value="single">Single</option>
                                <option value="ep">EP</option>
                                <option value="album">Album</option>
                            </select>
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Artist
                            </label>
                            <select
                                value={data.artist_id}
                                onChange={(e) => {
                                    const artistId = e.target.value;
                                    const artist = artists.find(
                                        (item) => String(item.id) === artistId
                                    );

                                    setData((current) => ({
                                        ...current,
                                        artist_id: artistId,
                                        primary_artist_name:
                                            artist?.stage_name ?? '',
                                    }));
                                }}
                                className="w-full rounded-xl border-slate-300"
                            >
                                <option value="">Select artist</option>
                                {artists.map((artist) => (
                                    <option key={artist.id} value={artist.id}>
                                        {artist.stage_name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Label
                            </label>
                            <select
                                value={data.label_id}
                                onChange={(e) =>
                                    setData('label_id', e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300"
                            >
                                <option value="">Select label</option>
                                {labels.map((label) => (
                                    <option key={label.id} value={label.id}>
                                        {label.name}
                                    </option>
                                ))}
                            </select>
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Catalogue Number
                            </label>
                            <input
                                type="text"
                                value={data.catalog_number}
                                onChange={(e) =>
                                    setData('catalog_number', e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300"
                            />
                            {errors.catalog_number && (
                                <p className="mt-1 text-sm text-red-600">
                                    {errors.catalog_number}
                                </p>
                            )}
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Digital Release Date
                            </label>
                            <input
                                type="date"
                                value={data.digital_release_date}
                                onChange={(e) =>
                                    setData(
                                        'digital_release_date',
                                        e.target.value
                                    )
                                }
                                className="w-full rounded-xl border-slate-300"
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Language
                            </label>
                            <input
                                type="text"
                                value={data.language}
                                onChange={(e) =>
                                    setData('language', e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300"
                            />
                        </div>

                        <div>
                            <label className="mb-2 block text-sm font-medium text-slate-700">
                                Primary Genre
                            </label>
                            <input
                                type="text"
                                value={data.primary_genre}
                                onChange={(e) =>
                                    setData('primary_genre', e.target.value)
                                }
                                className="w-full rounded-xl border-slate-300"
                            />
                        </div>
                    </div>

                    <div className="mt-8 flex justify-end gap-3">
                        <Link
                            href="/releases"
                            className="rounded-xl border border-slate-300 px-5 py-3 text-sm font-semibold text-slate-700"
                        >
                            Cancel
                        </Link>

                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-xl bg-slate-900 px-6 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AdminLayout>
    );
}
