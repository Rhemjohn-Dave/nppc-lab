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

function auditDotClass(action: string, index: number): string {
    const lower = action.toLowerCase();
    if (lower.includes('superseded') || lower.includes('archived')) {
        return 'bg-amber-500 ring-amber-50';
    }
    if (
        lower.includes('sign') ||
        lower.includes('approved') ||
        lower.includes('active')
    ) {
        return 'bg-violet-500 ring-violet-50';
    }
    if (lower.includes('mapping') || lower.includes('field')) {
        return index % 2 === 0
            ? 'bg-emerald-500 ring-emerald-50'
            : 'bg-nppc-700 ring-nppc-50';
    }
    if (lower.includes('security') || lower.includes('backup')) {
        return 'bg-slate-400 ring-slate-100';
    }
    if (index === 0) {
        return 'bg-emerald-500 ring-emerald-50';
    }
    if (index === 1) {
        return 'bg-nppc-700 ring-nppc-50';
    }
    return 'bg-slate-400 ring-slate-100';
}

export default function RecentActivity({
    data,
    compact = false,
    showIsoHint = false,
}: Props) {
    return (
        <section
            className={cn(
                'rounded-xl border border-slate-200 bg-white shadow-sm',
                compact ? 'p-4' : 'p-4',
            )}
        >
            <div
                className={cn(
                    'mb-2 flex flex-wrap items-center justify-between gap-2',
                    showIsoHint && 'mb-3 border-b border-slate-100 pb-3',
                )}
            >
                <div className="flex items-center gap-2">
                    {showIsoHint ? (
                        <History
                            className="size-4 text-slate-500"
                            aria-hidden="true"
                        />
                    ) : null}
                    <h2
                        className={cn(
                            showIsoHint
                                ? 'text-xs font-bold tracking-tight text-slate-900 uppercase'
                                : 'text-sm font-semibold text-slate-900',
                        )}
                    >
                        {data.title}
                    </h2>
                </div>
                {showIsoHint ? (
                    <span className="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-medium text-slate-400">
                        ISO 17025 Sec 8.4
                    </span>
                ) : data.action ? (
                    <Link
                        href={data.action.href}
                        className="text-xs font-medium text-nppc-900 hover:underline"
                    >
                        {data.action.label} →
                    </Link>
                ) : null}
            </div>
            {data.items.length === 0 ? (
                <p className="text-sm text-muted-foreground">{data.empty}</p>
            ) : showIsoHint ? (
                <ul className="mt-3.5 space-y-3.5 text-xs">
                    {data.items.map((item, index) => (
                        <li
                            key={`${item.title}-${index}`}
                            className="flex items-start gap-2.5"
                        >
                            <span
                                className={cn(
                                    'mt-1.5 size-2 shrink-0 rounded-full ring-4',
                                    auditDotClass(item.title, index),
                                )}
                                aria-hidden="true"
                            />
                            <div className="min-w-0 flex-1">
                                <div className="flex items-center justify-between gap-2">
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="truncate font-mono text-[11px] font-semibold text-slate-800 hover:text-nppc-900"
                                        >
                                            {item.title}
                                        </Link>
                                    ) : (
                                        <p className="truncate font-mono text-[11px] font-semibold text-slate-800">
                                            {item.title}
                                        </p>
                                    )}
                                    {item.time ? (
                                        <span className="shrink-0 text-[10px] text-slate-400">
                                            {item.time}
                                        </span>
                                    ) : null}
                                </div>
                                <p className="mt-0.5 text-[11px] text-slate-500">
                                    {item.meta}
                                </p>
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
                                className="absolute top-1.5 -left-[calc(0.5rem+5px)] size-2.5 rounded-full border-2 border-white bg-nppc-900 ring-2 ring-slate-200"
                                aria-hidden="true"
                            />
                            <div className="flex items-start justify-between gap-3 text-sm">
                                <div className="min-w-0">
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="font-medium text-slate-900 hover:text-nppc-900"
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
                <div className="mt-4 border-t border-slate-100 pt-3">
                    <Link
                        href={data.action.href}
                        className="flex w-full items-center justify-center gap-1 rounded py-1.5 text-xs font-semibold text-nppc-900 transition-colors hover:bg-nppc-50 hover:text-nppc-700"
                    >
                        {data.action.label}
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
                'rounded-xl border border-slate-200 bg-white shadow-sm',
                compact ? 'p-4' : 'p-4',
            )}
        >
            <h3 className="mb-3 flex items-center justify-between text-xs font-bold tracking-tight text-slate-900 uppercase">
                <span>{title}</span>
                <span
                    className="size-2 rounded-full bg-emerald-500"
                    aria-hidden="true"
                />
            </h3>
            <ul className={cn('space-y-2.5 text-xs', compact && 'space-y-2')}>
                {items.map((item) => (
                    <li
                        key={item.status}
                        className="flex items-center justify-between border-b border-slate-100 py-1 last:border-0"
                    >
                        <span className="text-slate-500">{item.status}</span>
                        <span className="font-mono font-medium tabular-nums text-slate-700">
                            {item.count}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}
