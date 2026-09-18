import type { ReactNode } from 'react';
import {
    limsPageBackground,
    limsPageShellWide,
} from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type Props = {
    header: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
};

/**
 * Public Intake layout: Dashboard-wide shell, natural document height (page scrolls),
 * optional sticky action footer in the content column.
 */
export default function IntakePageShell({
    header,
    children,
    footer,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'relative min-h-svh text-slate-900',
                limsPageBackground,
            )}
        >
            <div
                aria-hidden
                className="pointer-events-none absolute inset-x-0 top-0 h-28 overflow-hidden sm:h-36"
            >
                <img
                    src="/nppc.jpg"
                    alt=""
                    className="size-full object-cover object-[center_28%] opacity-40"
                />
                <div className="absolute inset-0 bg-[linear-gradient(180deg,rgba(15,42,120,0.72)_0%,rgba(244,247,251,0.92)_72%,#f4f7fb_100%)]" />
            </div>

            <div
                className={cn(
                    limsPageShellWide,
                    'relative gap-3 py-3 sm:gap-4 sm:py-4',
                    className,
                )}
            >
                <div className="intake-enter shrink-0">{header}</div>

                <div className="intake-enter-delay min-w-0 space-y-3 rounded-xl border border-slate-200/80 bg-white/95 p-3 shadow-sm sm:space-y-4 sm:p-4">
                    {children}
                </div>

                {footer}

                <p className="text-center text-xs text-slate-500">
                    NPPC Analytical & Diagnostic Laboratory · Typical turnaround
                    5–7 working days
                </p>
            </div>
        </div>
    );
}
