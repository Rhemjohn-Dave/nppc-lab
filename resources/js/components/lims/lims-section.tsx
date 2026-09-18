import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';
import { limsPanelPadding } from '@/lib/lims-page-shell';

type Props = {
    title?: string;
    children: ReactNode;
    className?: string;
    /** Omit the white bordered panel; title + children only (divider style). */
    flush?: boolean;
    action?: ReactNode;
};

/**
 * Single LIMS content section — prefer Page → Section over nested cards.
 */
export default function LimsSection({
    title,
    children,
    className,
    flush = false,
    action,
}: Props) {
    return (
        <section className={cn(className)}>
            {(title || action) && (
                <div className="mb-2 flex flex-wrap items-start justify-between gap-2">
                    {title ? (
                        <h2 className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            {title}
                        </h2>
                    ) : (
                        <span />
                    )}
                    {action}
                </div>
            )}
            {flush ? (
                children
            ) : (
                <div
                    className={cn(
                        'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                        limsPanelPadding,
                    )}
                >
                    {children}
                </div>
            )}
        </section>
    );
}
