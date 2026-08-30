import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { DashboardActivity } from './types';

type Props = {
    data: DashboardActivity;
    compact?: boolean;
};

export default function RecentActivity({ data, compact = false }: Props) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                compact ? 'p-3' : 'p-4',
            )}
        >
            <div className="mb-2 flex flex-wrap items-center justify-between gap-2">
                <h2 className="text-sm font-semibold text-[#1A3694]">
                    {data.title}
                </h2>
                {data.action && (
                    <Link
                        href={data.action.href}
                        className="text-xs font-medium text-[#365BB0] hover:underline"
                    >
                        {data.action.label} →
                    </Link>
                )}
            </div>
            {data.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{data.empty}</p>
            ) : (
                <ul className="relative space-y-0 border-l-2 border-slate-200 pl-4">
                    {data.items.map((item, index) => (
                        <li
                            key={`${item.title}-${index}`}
                            className={cn(
                                'relative',
                                compact ? 'pb-3 last:pb-0' : 'pb-4 last:pb-0',
                            )}
                        >
                            <span
                                className="absolute top-1.5 -left-[calc(0.5rem+5px)] size-2.5 rounded-full border-2 border-white bg-[#1A3694] ring-2 ring-slate-200"
                                aria-hidden="true"
                            />
                            <div className="flex items-start justify-between gap-3 text-sm">
                                <div className="min-w-0">
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="font-medium text-slate-900 hover:text-[#1A3694]"
                                        >
                                            {item.title}
                                        </Link>
                                    ) : (
                                        <p className="font-medium text-slate-900">
                                            {item.title}
                                        </p>
                                    )}
                                    <p className="truncate text-xs text-muted-foreground">
                                        {item.meta}
                                    </p>
                                </div>
                                {item.time && (
                                    <span className="shrink-0 text-xs text-muted-foreground">
                                        {item.time}
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

export function WorkflowOverviewStrip({
    items,
    compact = false,
}: {
    items: Array<{ status: string; count: number }>;
    compact?: boolean;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                compact ? 'p-3' : 'p-4',
            )}
        >
            <h2 className="mb-2 text-sm font-semibold text-[#1A3694]">
                Workflow overview
            </h2>
            <ul className={compact ? 'space-y-1.5' : 'space-y-2'}>
                {items.map((item) => (
                    <li
                        key={item.status}
                        className="flex items-center justify-between text-sm"
                    >
                        <span className="text-slate-700">{item.status}</span>
                        <span className="font-semibold tabular-nums text-[#1A3694]">
                            {item.count}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
