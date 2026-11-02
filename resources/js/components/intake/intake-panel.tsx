import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    action?: ReactNode;
    children: ReactNode;
    className?: string;
    bodyClassName?: string;
    icon?: LucideIcon;
    /** Dark navy summary card (wizard contact sidebar). */
    variant?: 'default' | 'summary';
};

/**
 * Bordered intake card for grouping related fields.
 * When given `flex flex-col` / `h-full`, the body grows to fill available height.
 */
export default function IntakePanel({
    title,
    description,
    action,
    children,
    className,
    bodyClassName,
    icon: Icon,
    variant = 'default',
}: Props) {
    const summary = variant === 'summary';

    return (
        <section
            className={cn(
                'overflow-hidden rounded-lg border shadow-xs',
                summary
                    ? 'border-[#112747]/20 bg-white'
                    : 'border-slate-200 bg-white',
                className,
            )}
        >
            <div
                data-intake-panel-head=""
                className={cn(
                    'flex shrink-0 flex-wrap items-start justify-between gap-2 px-3 py-2.5',
                    summary
                        ? 'bg-[#112747] text-white'
                        : 'border-b border-slate-200 bg-slate-50/70',
                    !description && 'items-center',
                )}
            >
                <div className="flex min-w-0 items-start gap-2">
                    {Icon ? (
                        <Icon
                            className={cn(
                                'mt-0.5 size-4 shrink-0',
                                summary ? 'text-white/90' : 'text-[#1d3b91]',
                            )}
                            aria-hidden
                        />
                    ) : null}
                    <div className="min-w-0">
                        <h3
                            className={cn(
                                'text-sm font-semibold',
                                summary ? 'text-white' : 'text-[#112747]',
                            )}
                        >
                            {title}
                        </h3>
                        {description ? (
                            <p
                                className={cn(
                                    'mt-0.5 text-xs leading-snug',
                                    summary
                                        ? 'text-white/70'
                                        : 'text-slate-500',
                                )}
                            >
                                {description}
                            </p>
                        ) : null}
                    </div>
                </div>
                {action}
            </div>
            <div
                data-intake-panel-body=""
                className={cn(
                    'min-h-0 flex-1 p-3 sm:p-3.5',
                    summary && 'bg-white',
                    bodyClassName,
                )}
            >
                {children}
            </div>
        </section>
    );
}
