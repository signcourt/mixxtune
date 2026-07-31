import { Head } from '@inertiajs/react';
import AdminLayout from '@/Layouts/AdminLayout';

export default function InvoiceShow() {
    return (
        <AdminLayout title="Invoice Detail">
            <Head title="Invoice Detail" />

            <div className="rounded-2xl border border-slate-200 bg-white p-6">
                <h1 className="text-3xl font-bold text-slate-900">
                    Invoice Detail
                </h1>
            </div>
        </AdminLayout>
    );
}
