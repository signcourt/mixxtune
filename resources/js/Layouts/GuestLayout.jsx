import { Link, usePage } from '@inertiajs/react';

export default function GuestLayout({
    children,
}) {
    const { brand = {} } = usePage().props;

    const brandName = brand?.name || "MIXX TUNE";
    const logoUrl =
        brand?.login_logo_url ||
        brand?.logo_url ||
        "/images/mixx-tune-login-logo.svg";

    return (
        <main className="relative flex min-h-screen items-center justify-center overflow-hidden bg-slate-50 px-4 py-8">
            <div className="pointer-events-none absolute inset-0">
                <div className="absolute left-1/2 top-1/2 h-[520px] w-[520px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-blue-100/60 blur-3xl" />
            </div>

            <div className="relative w-full max-w-[420px]">
                <div className="mb-8 flex justify-center">
                    <Link
                        href="/"
                        className="inline-flex items-center"
                    >
                        <img
                            src={logoUrl}
                            alt={brandName}
                            className="h-auto w-[250px] max-w-full"
                        />
                    </Link>
                </div>

                <div className="rounded-2xl border border-slate-200 bg-white p-7 shadow-xl shadow-slate-200/50 sm:p-9">
                    {children}
                </div>
            </div>
        </main>
    );
}
