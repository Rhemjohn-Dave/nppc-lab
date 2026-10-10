import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    hint?: ReactNode;
    className?: string;
};

/**
 * Sticky Back/Continue bar — stays under form content on short steps,
 * sticks to viewport bottom only when the step scrolls.
 */
export default function IntakeStickyFooter({
    children,
    hint,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-3 mt-1 border-t border-slate-200/80 bg-white/95 px-3 py-3 backdrop-blur sm:-mx-4 sm:px-4 lg:-mx-5 lg:px-5',
                className,
            )}
        >
            {hint ? <div className="mb-2">{hint}</div> : null}
            {children}
        </div>
    );
}
