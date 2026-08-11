import { Link } from '@inertiajs/react';
import {
    ArrowRight,
    CheckCircle2,
    Landmark,
    Plus,
    Sparkles,
} from 'lucide-react';

export default function DashboardHero({
    name = 'Artist',
    eyebrow = 'ARTIST OVERVIEW',
    description = 'Manage releases, monitor royalties, track wallet activity and distribute your music from one professional workspace.',
    accountStatus = 'active',
    kycStatus = 'pending',
    primaryAction = {
        label: 'Create Release',
        href: '/artist/releases/create',
    },
    secondaryAction = {
        label: 'View Catalogue',
        href: '/artist/catalogue',
    },
}) {
    return (
        <section className="relative overflow-hidden rounded-[32px] bg-gradient-to-br from-[#090f1d] via-[#111a2e] to-[#22164b] px-6 py-8 text-white shadow-2xl sm:px-8 lg:px-10 lg:py-10">
            <div className="absolute -right-20 -top-24 h-72 w-72 rounded-full bg-violet-500/20 blur-3xl" />
            <div className="absolute -bottom-32 left-1/3 h-72 w-72 rounded-full bg-blue-500/10 blur-3xl" />

            <div className="relative flex flex-col justify-between gap-8 xl:flex-row xl:items-center">
                <div className="max-w-2xl">
                    <div className="inline-flex items-center gap-2 rounded-full border border-white/10 bg-white/10 px-3 py-1.5 text-xs font-semibold text-violet-100">
                        <Sparkles size={14} />
                        {eyebrow}
                    </div>

                    <h2 className="mt-5 text-3xl font-black tracking-tight sm:text-4xl lg:text-5xl">
                        Welcome, {name}
                    </h2>

                    <p className="mt-4 max-w-xl text-sm leading-7 text-slate-300 sm:text-base">
                        {description}
                    </p>

                </div>

                <div className="flex flex-col gap-3 sm:flex-row xl:flex-col">
                    <Link
                        href={primaryAction.href}
                        className="inline-flex items-center justify-center gap-2 rounded-2xl bg-white px-6 py-3.5 text-sm font-bold text-slate-950 shadow-lg transition hover:-translate-y-0.5 hover:bg-violet-50"
                    >
                        <Plus size={18} />
                        {primaryAction.label}
                    </Link>

                    <Link
                        href={secondaryAction.href}
                        className="inline-flex items-center justify-center gap-2 rounded-2xl border border-white/15 bg-white/10 px-6 py-3.5 text-sm font-bold text-white transition hover:bg-white/15"
                    >
                        {secondaryAction.label}
                        <ArrowRight size={17} />
                    </Link>
                </div>
            </div>
        </section>
    );
}
