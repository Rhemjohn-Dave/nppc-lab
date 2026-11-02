import type { ReactNode } from 'react';
import IntakePortalChrome from '@/components/intake/intake-portal-chrome';
import { limsIntakeShell } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type WizardNav = {
    steps: readonly string[];
    step: number;
    onStepSelect: (index: number) => void;
};

type Props = {
    /** Wizard navigation for step strip below navbar + Exit. */
    wizard?: WizardNav;
    /** Fallback actions when not using wizard Exit. */
    headerActions?: ReactNode;
    /** Intercept Exit from portal chrome. */
    onExit?: () => void;
    children: ReactNode;
    footer?: ReactNode;
    className?: string;
};

/**
 * Public Intake wizard layout.
 * Full-height column: header → flexible main → site footer.
 * Short steps can stretch their cards to fill the main area; the sticky
 * action bar stays at the bottom of the viewport.
 */
export default function IntakePageShell({
    wizard,
    headerActions,
    onExit,
    children,
    footer,
    className,
}: Props) {
    return (
        <div className="relative flex min-h-dvh flex-col bg-[#f3f6fa] text-slate-900">
            <IntakePortalChrome
                compact
                wizard={wizard}
                showExit={Boolean(wizard)}
                onExit={onExit}
                actions={wizard ? undefined : headerActions}
            />

            <div
                className={cn(
                    limsIntakeShell,
                    'relative z-10 flex min-h-0 w-full flex-1 flex-col py-2',
                    className,
                )}
            >
                <div className="flex min-h-0 min-w-0 flex-1 flex-col">
                    {children}
                </div>
                {footer}
            </div>

            <footer className="relative z-10 shrink-0 border-t border-slate-200 bg-white">
                <div className="mx-auto flex w-full max-w-[1600px] flex-wrap items-center justify-between gap-2 px-4 py-1.5 text-[10px] text-slate-500 sm:px-5 lg:px-6">
                    <p>
                        ISO/IEC 17025 · NPPC Analytical &amp; Diagnostic Testing
                    </p>
                    <p>Powered by FNDS · All rights reserved 2026</p>
                </div>
            </footer>
        </div>
    );
}
