import { Link } from '@inertiajs/react';
import { History } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { DashboardActivity } from './types';

type Props = {
    data: DashboardActivity;
    compact?: boolean;
    /** Show ISO 8.4 label (admin document-control audit feed) */
    showIsoHint?: boolean;
};

export default function RecentActivity({
    data,
    compact = false,
    showIsoHint = false,
}: Props) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                compact ? 'p-3.5' : 'p-4',
            )}
        >
            <div
                className={cn(
                    'mb-2 flex flex-wrap items-center justify-between gap-2',
                    showIsoHint && 'mb-3 border-b border-slate-100 pb-2',
                )}
            >
                <div className="flex items-center gap-2">
                    {showIsoHint ? (
                        <History
                            className="size-4 text-[#1A3694]"
                            aria-hidden="true"
                        />
                    ) : null}
                    <h2
                        className={cn(
                            showIsoHint
                                ? 'text-xs font-semibold tracking-wider text-slate-900 uppercase'
                                : 'text-sm font-semibold text-[#1A3694]',
                        )}
                    >
                        {data.title}
                    </h2>
                </div>
                {showIsoHint ? (
                    <span className="font-mono text-[10px] text-muted-foreground">
                        ISO 17025 Sec 8.4
                    </span>
                ) : data.action ? (
                    <Link
                        href={data.action.href}
                        className="text-xs font-medium text-[#365BB0] hover:underline"
                    >
                        {data.action.label} →
                    </Link>
                ) : null}
            </div>
            {data.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{data.empty}</p>
            ) : showIsoHint ? (
                <ul className="space-y-3">
                    {data.items.map((item, index) => (
                        <li
                            key={`${item.title}-${index}`}
                            className="flex items-start gap-2.5 text-xs"
                        >
                            <span
                                className={cn(
                                    'mt-1.5 size-2 shrink-0 rounded-full ring-4',
                                    index === 0
                                        ? 'bg-emerald-500 ring-emerald-50'
                                        : index === 1
                                          ? 'bg-[#365BB0] ring-[#eef3fb]'
                                          : 'bg-slate-400 ring-slate-100',
                                )}
                                aria-hidden="true"
                            />
                            <div className="min-w-0 flex-1">
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
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    {item.meta}
                                </p>
                                {item.time ? (
                                    <p className="mt-0.5 font-mono text-[10px] text-muted-foreground">
                                        {item.time}
                                    </p>
                                ) : null}
                            </div>
                        </li>
                    ))}
                </ul>
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
            {showIsoHint && data.action ? (
                <div className="mt-3 border-t border-slate-100 pt-3">
                    <Link
                        href={data.action.href}
                        className="flex items-center justify-between text-xs font-medium text-[#365BB0] hover:underline"
                    >
                        <span>{data.action.label}</span>
                        <span aria-hidden="true">→</span>
                    </Link>
                </div>
            ) : null}
        </section>
    );
}

export function WorkflowOverviewStrip({
    items,
    compact = false,
    title = 'Workflow overview',
}: {
    items: Array<{ status: string; count: number }>;
    compact?: boolean;
    title?: string;
}) {
    if (items.length === 0) {
        return null;
    }

    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                compact ? 'p-3.5' : 'p-4',
            )}
        >
            <div className="mb-2 flex items-center justify-between gap-2 border-b border-slate-100 pb-2">
                <h2 className="text-xs font-semibold tracking-wider text-slate-900 uppercase">
                    {title}
                </h2>
                <span
                    className="size-2 rounded-full bg-emerald-500"
                    aria-hidden="true"
                />
            </div>
            <ul className={compact ? 'space-y-1.5' : 'space-y-2'}>
                {items.map((item) => (
                    <li
                        key={item.status}
                        className="flex items-center justify-between text-xs"
                    >
                        <span className="text-slate-600">{item.status}</span>
                        <span className="font-mono font-semibold tabular-nums text-[#1A3694]">
                            {item.count}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
