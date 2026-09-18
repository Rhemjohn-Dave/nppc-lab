import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    hint?: ReactNode;
    action?: ReactNode;
    className?: string;
};

/** Step title + single-line hint used at the top of every intake step. */
export default function IntakeStepHeader({
    title,
    hint,
    action,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-wrap items-start justify-between gap-2',
                className,
            )}
        >
            <div className="min-w-0">
                <h2 className="font-heading text-lg font-semibold text-[#1A3694] sm:text-xl">
                    {title}
                </h2>
                {hint ? (
                    <p className="text-sm text-slate-600">{hint}</p>
                ) : null}
            </div>
            {action}
        </div>
    );
}
