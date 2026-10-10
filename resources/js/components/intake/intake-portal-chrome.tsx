import { Link } from '@inertiajs/react';
import { Headphones, Phone, X } from 'lucide-react';
import type { ReactNode } from 'react';
import NppcLogo from '@/components/nppc-logo';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type WizardNav = {
    steps: readonly string[];
    step: number;
    onStepSelect: (index: number) => void;
};

type Props = {
    /** Compact bar for wizard (no accreditation strip). */
    compact?: boolean;
    /** Wizard: center breadcrumb pills. */
    wizard?: WizardNav;
    /** Extra right-side actions when not using built-in Exit. */
    actions?: ReactNode;
    /** Show Exit → /intake (wizard default). */
    showExit?: boolean;
    className?: string;
};

/**
 * Shared public intake portal chrome — Manrope / nppc tokens.
 */
export default function IntakePortalChrome({
    compact = false,
    wizard,
    actions,
    showExit = false,
    className,
}: Props) {
    return (
        <div className={cn('shrink-0', className)}>
            <header className="border-b border-slate-200/80 bg-white">
                <div className="mx-auto flex w-full max-w-[1920px] flex-wrap items-center justify-between gap-3 px-3 py-2 sm:px-4 lg:px-5 xl:px-6">
                    <Link
                        href="/intake"
                        className="flex min-w-0 items-center gap-2.5"
                    >
                        <div className="flex size-9 shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-xs ring-2 ring-nppc-900/10">
                            <NppcLogo className="h-[90%] w-[90%]" />
                        </div>
                        <div className="min-w-0">
                            <p className="truncate font-heading text-sm font-semibold text-nppc-900">
                                NPPC Lab
                                <span className="font-normal text-slate-500">
                                    {' '}
                                    / Customer Intake Portal
                                </span>
                            </p>
                        </div>
                    </Link>

                    {wizard ? (
                        <nav
                            className="order-last hidden w-full items-center justify-center gap-1 md:order-none md:flex md:w-auto"
                            aria-label="Intake steps"
                        >
                            {wizard.steps.map((label, index) => {
                                const done = index < wizard.step;
                                const current = index === wizard.step;
                                const clickable = done;

                                return (
                                    <button
                                        key={label}
                                        type="button"
                                        disabled={!clickable}
                                        onClick={() =>
                                            clickable &&
                                            wizard.onStepSelect(index)
                                        }
                                        className={cn(
                                            'rounded-md px-2.5 py-1 text-[11px] font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                            current &&
                                                'bg-nppc-900 text-white shadow-sm',
                                            done &&
                                                !current &&
                                                'cursor-pointer text-nppc-800 hover:bg-nppc-50',
                                            !done &&
                                                !current &&
                                                'text-slate-400',
                                        )}
                                    >
                                        {index + 1}. {label}
                                    </button>
                                );
                            })}
                        </nav>
                    ) : null}

                    <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5 text-[11px] text-slate-600">
                        {!compact && !wizard ? (
                            <>
                                <span className="inline-flex items-center gap-1.5 font-medium text-emerald-700">
                                    <span className="size-1.5 rounded-full bg-emerald-500" />
                                    ISO/IEC 17025 Accredited
                                </span>
                                <span className="hidden text-slate-500 sm:inline">
                                    Turnaround: 5–7 business days
                                </span>
                                <a
                                    href="#drop-off"
                                    className="hidden font-medium text-nppc-900 hover:underline md:inline"
                                >
                                    Sample Drop-off Guide
                                </a>
                                <Link
                                    href="/intake/create"
                                    className="hidden font-medium text-nppc-900 hover:underline md:inline"
                                >
                                    New Sample Order
                                </Link>
                            </>
                        ) : null}
                        <a
                            href="tel:0344332131"
                            className="inline-flex items-center gap-1.5 font-medium text-slate-700 hover:text-nppc-900"
                        >
                            {wizard ? (
                                <Headphones
                                    className="size-3.5 text-nppc-700"
                                    aria-hidden
                                />
                            ) : (
                                <Phone className="size-3" aria-hidden />
                            )}
                            {wizard ? 'Support: 034-4332131' : '034-4332131'}
                        </a>
                        {actions}
                        {showExit ? (
                            <Button
                                asChild
                                variant="outline"
                                className="h-8 gap-1.5 border-slate-200 bg-white px-2.5 text-xs text-nppc-900 hover:bg-slate-50"
                            >
                                <Link href="/intake">
                                    <X className="size-3.5" aria-hidden />
                                    Exit
                                </Link>
                            </Button>
                        ) : null}
                    </div>
                </div>
            </header>

            {!compact && !wizard ? (
                <div className="border-b border-nppc-100 bg-nppc-50/80">
                    <div className="mx-auto flex w-full max-w-[1920px] flex-wrap items-center justify-between gap-2 px-3 py-1.5 text-[10px] font-medium tracking-wide text-nppc-800 uppercase sm:px-4 lg:px-5 xl:px-6">
                        <span>ISO/IEC 17025 Accredited</span>
                        <span className="text-nppc-700/80">
                            Bacolod City, Negros Occidental
                        </span>
                        <span>Counter Receiving: 8:00 AM – 5:00 PM</span>
                    </div>
                </div>
            ) : null}
        </div>
    );
}
