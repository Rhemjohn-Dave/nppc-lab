import { Link } from '@inertiajs/react';
import { Check, Headphones, MapPin, Phone, X } from 'lucide-react';
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
    /** Wizard: step nav rendered below the top bar. */
    wizard?: WizardNav;
    /** Extra right-side actions when not using built-in Exit. */
    actions?: ReactNode;
    /** Show Exit → /intake (wizard default). */
    showExit?: boolean;
    /** Intercept Exit (e.g. confirm before leaving unfinished request). */
    onExit?: () => void;
    className?: string;
};

/**
 * Shared public intake portal chrome.
 * Wizard mode: slim top bar + step strip underneath.
 */
export default function IntakePortalChrome({
    compact = false,
    wizard,
    actions,
    showExit = false,
    onExit,
    className,
}: Props) {
    const landing = !compact && !wizard;

    return (
        <div className={cn('relative z-20 shrink-0', className)}>
            <header className="border-b border-slate-200/80 bg-white">
                <div
                    className={cn(
                        'mx-auto flex w-full items-center justify-between',
                        landing
                            ? 'max-w-6xl flex-wrap gap-3 px-5 py-2.5 sm:px-8 lg:px-10'
                            : wizard
                              ? 'h-12 max-w-[1600px] gap-3 px-4 sm:px-5 lg:px-6'
                              : 'max-w-[1920px] flex-wrap gap-3 px-3 py-1.5 sm:px-4 lg:px-5 xl:px-6',
                    )}
                >
                    <Link
                        href="/intake"
                        className="flex min-w-0 items-center gap-2.5"
                    >
                        <div
                            className={cn(
                                'flex shrink-0 items-center justify-center overflow-hidden rounded-full bg-white shadow-xs ring-2 ring-nppc-900/10',
                                landing
                                    ? 'size-11'
                                    : wizard
                                      ? 'size-7'
                                      : 'size-9',
                            )}
                        >
                            <NppcLogo className="h-[90%] w-[90%]" />
                        </div>
                        {landing ? (
                            <div className="min-w-0">
                                <p className="font-heading text-base font-bold tracking-tight text-nppc-900 sm:text-lg">
                                    NPPC Laboratory
                                </p>
                                <p className="text-[10px] font-semibold tracking-[0.16em] text-slate-500 uppercase">
                                    Customer Intake Portal
                                </p>
                            </div>
                        ) : (
                            <div className="min-w-0">
                                <p
                                    className={cn(
                                        'truncate font-heading font-semibold text-nppc-900',
                                        wizard ? 'text-[13px]' : 'text-sm',
                                    )}
                                >
                                    NPPC Lab
                                    <span className="font-normal text-slate-500">
                                        {' '}
                                        / Customer Intake Portal
                                    </span>
                                </p>
                            </div>
                        )}
                    </Link>

                    <div
                        className={cn(
                            'flex items-center text-slate-600',
                            landing
                                ? 'flex-wrap gap-x-4 gap-y-2 text-xs sm:gap-x-5'
                                : wizard
                                  ? 'shrink-0 gap-2 text-[11px]'
                                  : 'flex-wrap gap-x-3 gap-y-1.5 text-[11px]',
                        )}
                    >
                        {landing ? (
                            <>
                                <span className="inline-flex items-center gap-2 font-medium text-emerald-700">
                                    <span className="size-2 rounded-full bg-emerald-500" />
                                    ISO/IEC 17025 Accredited
                                </span>
                                <span
                                    className="hidden h-4 w-px bg-slate-200 sm:block"
                                    aria-hidden
                                />
                                <span className="hidden items-center gap-1.5 text-slate-600 sm:inline-flex">
                                    <MapPin
                                        className="size-3.5 text-nppc-800"
                                        aria-hidden
                                    />
                                    Bacolod City, Negros Occidental
                                </span>
                                <span
                                    className="hidden h-4 w-px bg-slate-200 md:block"
                                    aria-hidden
                                />
                                <a
                                    href="tel:0344332131"
                                    className="inline-flex items-center gap-1.5 font-medium text-slate-700 hover:text-nppc-900"
                                >
                                    <Phone className="size-3.5" aria-hidden />
                                    0948-104-8084
                                </a>
                            </>
                        ) : (
                            <>
                                {!compact ? (
                                    <>
                                        <span className="inline-flex items-center gap-1.5 font-medium text-emerald-700">
                                            <span className="size-1.5 rounded-full bg-emerald-500" />
                                            ISO/IEC 17025 Accredited
                                        </span>
                                        <span className="hidden text-slate-500 sm:inline">
                                            Turnaround: 5–7 business days
                                        </span>
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
                                    {wizard
                                        ? 'Support: 034-4332131'
                                        : '034-4332131'}
                                </a>
                            </>
                        )}
                        {actions}
                        {showExit ? (
                            onExit ? (
                                <Button
                                    type="button"
                                    variant="outline"
                                    className={cn(
                                        'gap-1.5 border-slate-200 bg-white text-xs text-nppc-900 hover:bg-slate-50',
                                        wizard ? 'h-7 px-2' : 'h-8 px-2.5',
                                    )}
                                    onClick={onExit}
                                >
                                    <X className="size-3.5" aria-hidden />
                                    Exit
                                </Button>
                            ) : (
                                <Button
                                    asChild
                                    variant="outline"
                                    className={cn(
                                        'gap-1.5 border-slate-200 bg-white text-xs text-nppc-900 hover:bg-slate-50',
                                        wizard ? 'h-7 px-2' : 'h-8 px-2.5',
                                    )}
                                >
                                    <Link href="/intake">
                                        <X className="size-3.5" aria-hidden />
                                        Exit
                                    </Link>
                                </Button>
                            )
                        ) : null}
                    </div>
                </div>
            </header>

            {wizard ? (
                <div className="border-b border-slate-200 bg-white">
                    <div className="mx-auto flex h-10 w-full max-w-[1600px] items-center justify-between gap-3 px-4 sm:px-5 lg:px-6">
                        <p className="hidden shrink-0 text-[11px] font-medium text-slate-500 sm:block">
                            Intake Form / Step {wizard.step + 1}:{' '}
                            <span className="text-[#112747]">
                                {wizard.steps[wizard.step]}
                            </span>
                        </p>
                        <nav
                            className="flex min-w-0 flex-1 items-center justify-center gap-1 overflow-x-auto"
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
                                        aria-current={
                                            current ? 'step' : undefined
                                        }
                                        className={cn(
                                            'inline-flex shrink-0 items-center gap-1 rounded-md px-2.5 py-1 text-[11px] font-semibold transition focus-visible:ring-2 focus-visible:ring-[#1d3b91]/35 focus-visible:outline-none',
                                            current &&
                                                'bg-[#1d3b91] text-white shadow-sm',
                                            done &&
                                                !current &&
                                                'cursor-pointer text-nppc-800 hover:bg-nppc-50',
                                            !done &&
                                                !current &&
                                                'text-slate-400',
                                        )}
                                    >
                                        {done ? (
                                            <Check
                                                className="size-3"
                                                aria-hidden
                                            />
                                        ) : (
                                            <span>{index + 1}.</span>
                                        )}
                                        {label}
                                    </button>
                                );
                            })}
                        </nav>
                        <p className="hidden shrink-0 text-[11px] tabular-nums text-slate-500 lg:block">
                            {wizard.step + 1} / {wizard.steps.length}
                        </p>
                    </div>
                </div>
            ) : null}

            {!landing && !compact && !wizard ? (
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
