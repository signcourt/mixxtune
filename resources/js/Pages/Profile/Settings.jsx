import PanelLayout from "@/V2/Shared/Layouts/PanelLayout";
import { Head, usePage } from "@inertiajs/react";
import UpdatePasswordForm from "./Partials/UpdatePasswordForm";

export default function Settings() {
    const { auth } = usePage().props;
    const user = auth?.user || {};
    const role = user.role || "label";

    return (
        <PanelLayout
            role={role}
            title="Settings"
            subtitle="Manage your account security and preferences."
        >
            <Head title="Settings" />

            <div className="space-y-6">
                <section className="w-full max-w-xl rounded-2xl border border-slate-200 bg-white p-6 shadow-sm">
                    <UpdatePasswordForm />
                </section>
            </div>
        </PanelLayout>
    );
}
