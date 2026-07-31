import { Head, Link } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function ModulePage({
    title = 'Module',
    description = '',
}) {
    return (
        <AdminLayout title={title}>
            <Head title={title} />

            <div className="space-y-6">
                <div>
                    <h1 className="text-3xl font-bold text-slate-900">
                        {title}
                    </h1>

                    <p className="mt-2 text-sm text-slate-500">
                        {description}
                    </p>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white px-6 py-16 text-center shadow-sm">
                    <div className="text-5xl">♫</div>

                    <h2 className="mt-5 text-xl font-semibold text-slate-900">
                        {title}
                    </h2>

                    <p className="mt-2 text-sm text-slate-500">
                        This module is connected and ready for development.
                    </p>

                    <Link
                        href="/dashboard"
                        className="mt-6 inline-flex rounded-xl bg-slate-900 px-5 py-3 text-sm font-semibold text-white"
                    >
                        Back to Dashboard
                    </Link>
                </div>
            </div>
        </AdminLayout>
    );
}
