import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    children: ReactNode;
    hint?: ReactNode;
    className?: string;
};

/** Compact sticky Back/Continue bar at the bottom of the intake content column. */
export default function IntakeStickyFooter({
    children,
    hint,
    className,
}: Props) {
    return (
        <div
            className={cn(
                'sticky bottom-0 z-10 -mx-3 border-t border-slate-200/80 bg-white/95 px-3 py-2.5 backdrop-blur sm:-mx-4 sm:px-4',
                className,
            )}
        >
            {hint}
            {children}
        </div>
    );
}
