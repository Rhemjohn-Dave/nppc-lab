import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    hint?: ReactNode;
    className?: string;
};

/**
 * Bottom action bar for wizard steps.
 * With mt-auto from the parent flex column it sits at the viewport bottom
 * on short pages, and follows content when the step scrolls.
 */
export default function IntakeStickyFooter({
    children,
    hint,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 mt-auto shrink-0 rounded-lg border border-slate-200 bg-white px-3 py-2 shadow-xs',
                className,
            )}
        >
            {hint ? <div className="mb-1.5">{hint}</div> : null}
            {children}
        </div>
    );
}
