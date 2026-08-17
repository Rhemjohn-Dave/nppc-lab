import { Link } from '@inertiajs/react';
import NppcLogo from '@/components/nppc-logo';
import type { AuthLayoutProps } from '@/types';

export default function AuthSplitLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="relative grid min-h-dvh lg:grid-cols-2">
            <aside className="relative hidden overflow-hidden lg:block">
                <img
                    src="/nppc.jpg"
                    alt="NPPC Analytical & Diagnostic Laboratory"
                    className="absolute inset-0 size-full object-cover"
                />
                <div className="absolute inset-0 bg-[linear-gradient(160deg,rgba(15,42,120,0.72)_0%,rgba(26,54,148,0.55)_45%,rgba(15,42,120,0.78)_100%)]" />
                <div className="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(255,255,255,0.16),transparent_35%)]" />

                <div className="relative z-10 flex h-full flex-col justify-between p-10 text-white">
                    <div className="flex items-center gap-3">
                        <div className="flex size-12 items-center justify-center overflow-hidden rounded-full bg-white shadow-lg ring-2 ring-white/40">
                            <NppcLogo className="h-[88%] w-[88%]" />
                        </div>
                        <div>
                            <p className="text-xs font-medium tracking-[0.22em] text-white/75 uppercase">
                                Laboratory System
                            </p>
                            <p className="font-heading text-lg font-semibold">
                                NPPC Lab LMS
                            </p>
                        </div>
                    </div>

                    <div className="max-w-md">
                        <p className="font-heading text-4xl font-semibold tracking-tight text-white xl:text-5xl">
                            Precision analysis.
                            <span className="mt-2 block font-normal text-white/80">
                                Secure staff access.
                            </span>
                        </p>
                        <p className="mt-5 text-sm leading-relaxed text-white/70">
                            Receiving, analysis, review, and administration — in
                            one laboratory workspace.
                        </p>
                    </div>

                    <p className="text-xs text-white/55">
                        NPPC Analytical & Diagnostic Laboratory, Inc. · Bacolod
                        City
                    </p>
                </div>
            </aside>

            <main className="flex items-center justify-center bg-[linear-gradient(180deg,#f7f9fd_0%,#eef3fb_100%)] px-6 py-10">
                <div className="w-full max-w-sm">
                    <Link
                        href="/login"
                        className="mb-8 flex items-center justify-center gap-3 lg:justify-start"
                    >
                        <div className="flex size-12 items-center justify-center overflow-hidden rounded-full bg-white shadow-sm ring-1 ring-[#1A3694]/15 lg:hidden">
                            <NppcLogo className="h-[88%] w-[88%]" />
                        </div>
                        <span className="font-heading text-xl font-semibold text-[#1A3694] lg:hidden">
                            NPPC Lab
                        </span>
                    </Link>

                    <div className="mb-8 space-y-2 text-center lg:text-left">
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            {title}
                        </h1>
                        {description ? (
                            <p className="text-sm text-slate-600">
                                {description}
                            </p>
                        ) : null}
                    </div>

                    {children}
                </div>
            </main>
        </div>
    );
}
