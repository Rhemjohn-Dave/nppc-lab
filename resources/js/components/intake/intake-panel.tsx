import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
};

/**
 * Bordered intake card for grouping related fields (Details step panels).
 */
export default function IntakePanel({
    title,
    description,
    action,
    children,
    className,
}: Props) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200 bg-white p-4 shadow-xs',
                className,
            )}
        >
            <div
                className={cn(
                    'mb-3 flex flex-wrap items-start justify-between gap-2',
                    !description && 'mb-2 items-center',
                )}
            >
                <div className="min-w-0">
                    <h3 className="text-sm font-semibold text-nppc-900">
                        {title}
                    </h3>
                    {description ? (
                        <p className="mt-0.5 text-xs leading-snug text-slate-500">
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
