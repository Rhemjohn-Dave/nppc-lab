import type { ReactNode } from 'react';
import IntakePortalChrome from '@/components/intake/intake-portal-chrome';
import {
    limsIntakeShell,
    limsPageBackground,
} from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type WizardNav = {
    steps: readonly string[];
    step: number;
    onStepSelect: (index: number) => void;
};

type Props = {
    /** Wizard navigation for header breadcrumb pills + Exit. */
    wizard?: WizardNav;
    /** Fallback actions when not using wizard Exit. */
    headerActions?: ReactNode;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
};

/**
 * Public Intake wizard layout: portal chrome + wide white workspace card.
 * Card height follows content (no hollow mid-card stretch).
 */
export default function IntakePageShell({
    wizard,
    headerActions,
    children,
    footer,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'relative flex min-h-svh flex-col text-slate-900',
                limsPageBackground,
            )}
        >
            <IntakePortalChrome
                compact
                wizard={wizard}
                showExit={Boolean(wizard)}
                actions={wizard ? undefined : headerActions}
            />

            <div
                className={cn(
                    limsIntakeShell,
                    'relative w-full grow-0 py-3',
                    className,
                )}
            >
                <div className="intake-enter flex min-w-0 flex-col gap-3 rounded-xl border border-slate-200/80 bg-white p-3 shadow-xs sm:p-4">
                    {children}
                </div>

                {footer}
            </div>

            <footer className="mt-auto border-t border-nppc-100 bg-nppc-50/70">
                <div className="mx-auto flex w-full max-w-[1920px] flex-wrap items-center justify-between gap-2 px-3 py-2 text-[11px] text-slate-600 sm:px-4 lg:px-5 xl:px-6">
                    <p>
                        ISO/IEC 17025 Accredited Laboratory · NPPC Analytical
                        &amp; Diagnostic Testing
                    </p>
                    <div className="flex flex-wrap items-center gap-3">
                        <span className="text-slate-500">
                            Portal Ref: NPPC-INTAKE
                        </span>
                        <span className="inline-flex items-center gap-1.5 font-medium text-slate-600">
                            <span className="size-1.5 rounded-full bg-emerald-500" />
                            Secure SSL Encrypted
                        </span>
                    </div>
                </div>
            </footer>
        </div>
    );
}
