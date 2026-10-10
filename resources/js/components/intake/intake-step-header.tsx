import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    hint?: ReactNode;
    action?: ReactNode;
    /** e.g. "STEP 5 OF 5 · FINAL VERIFICATION" */
    badge?: string;
    className?: string;
};

/** Step title + hint used at the top of every intake step. */
export default function IntakeStepHeader({
    title,
    hint,
    action,
    badge,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-start justify-between gap-3',
                className,
            )}
        >
            <div className="min-w-0">
                {badge ? (
                    <p className="mb-1 text-[11px] font-semibold tracking-[0.16em] text-nppc-700 uppercase">
                        {badge}
                    </p>
                ) : null}
                <h2 className="font-heading text-xl font-semibold text-nppc-900">
                    {title}
                </h2>
                {hint ? (
                    <p className="mt-1 text-xs leading-snug text-slate-600 sm:text-[13px]">
                        {hint}
                    </p>
                ) : null}
            </div>
            {action}
        </div>
    );
}
