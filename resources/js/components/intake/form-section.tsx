import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    children: ReactNode;
    className?: string;
    /** First section in a step should omit the top divider. */
    first?: boolean;
    action?: ReactNode;
};

/**
 * Divider-based form section — no nested card chrome.
 * Use for dense LIMS intake steps; keep bordered cards only where grouping needs a clear boundary.
 */
export default function FormSection({
    title,
    description,
    children,
    className,
    first = false,
    action,
}: Props) {
    return (
        <section
            className={cn(
                'space-y-2',
                !first && 'border-t border-slate-100 pt-3',
                className,
            )}
        >
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0">
                    <h3 className="text-sm font-semibold text-[#1A3694]">
                        {title}
                    </h3>
                    {description ? (
                        <p className="mt-0.5 text-sm text-slate-600">
                            {description}
                        </p>
                    ) : null}
                </div>
                {action}
            </div>
            {children}
        </section>
    );
}
