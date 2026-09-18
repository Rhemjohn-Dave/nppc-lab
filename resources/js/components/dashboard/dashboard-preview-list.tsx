import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import DashboardEmptyState from '@/components/dashboard/dashboard-empty-state';
import { DashboardViewAllLink } from '@/components/dashboard/dashboard-shell';
import type { DashboardQueue } from '@/components/dashboard/types';
import { cn } from '@/lib/utils';

type Props = {
    data: DashboardQueue;
    viewAllHref: string;
    viewAllLabel: string;
    compact?: boolean;
};

export default function DashboardPreviewList({
    data,
    viewAllHref,
    viewAllLabel,
    compact = false,
}: Props) {
    if (data.rows.length === 0) {
        return (
            <section className={compact ? 'space-y-2' : 'space-y-3'}>
                <h2 className="text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
                    {data.title}
                </h2>
                <DashboardEmptyState body={data.empty} />
            </section>
        );
    }

    return (
        <section className={compact ? 'space-y-2' : 'space-y-3'}>
            <h2 className="text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
                {data.title}
            </h2>
            <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <ul className="divide-y divide-slate-100">
                    {data.rows.map((row, index) => {
                        const href =
                            typeof row.href === 'string' ? row.href : null;
                        const reference =
                            typeof row.reference_no === 'string'
                                ? row.reference_no
                                : '—';
                        const customer =
                            typeof row.customer_name === 'string'
                                ? row.customer_name
                                : '';
                        const status =
                            typeof row.status === 'string' ? row.status : '';
                        const statusLabel =
                            typeof row.status_label === 'string'
                                ? row.status_label
                                : status;
                        const updated =
                            typeof row.updated_at === 'string'
                                ? row.updated_at
                                : null;
                        const meta =
                            typeof row.classification === 'string'
                                ? row.classification
                                : typeof row.tests === 'string'
                                  ? `${row.tests} tests`
                                  : null;
                        const key =
                            typeof row.id === 'number' ||
                            typeof row.id === 'string'
                                ? String(row.id)
                                : String(index);

                        const content = compact ? (
                            <>
                                <div className="min-w-0 flex-1">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <p className="font-semibold text-[#1A3694]">
                                            {reference}
                                        </p>
                                        <AnalystStatusBadge
                                            status={status}
                                            label={statusLabel}
                                        />
                                    </div>
                                    <p className="mt-0.5 truncate text-sm text-slate-700">
                                        {customer}
                                        {meta ? ` · ${meta}` : ''}
                                        {updated ? ` · ${updated}` : ''}
                                    </p>
                                </div>
                                <ChevronRight className="size-4 shrink-0 text-slate-400" />
                            </>
                        ) : (
                            <>
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold text-[#1A3694]">
                                        {reference}
                                    </p>
                                    <p className="truncate text-sm text-slate-700">
                                        {customer}
                                        {meta ? ` · ${meta}` : ''}
                                    </p>
                                </div>
                                <div className="flex shrink-0 items-center gap-3">
                                    <AnalystStatusBadge
                                        status={status}
                                        label={statusLabel}
                                    />
                                    {updated && (
                                        <span className="hidden text-xs text-muted-foreground sm:inline">
                                            {updated}
                                        </span>
                                    )}
                                    <ChevronRight className="size-4 text-slate-400" />
                                </div>
                            </>
                        );

                        return (
                            <li key={key}>
                                {href ? (
                                    <Link
                                        href={href}
                                        className={cn(
                                            'flex items-center gap-3 transition hover:bg-[#f8fafc]',
                                            compact
                                                ? 'px-3 py-2.5'
                                                : 'px-4 py-3.5',
                                        )}
                                    >
                                        {content}
                                    </Link>
                                ) : (
                                    <div
                                        className={cn(
                                            'flex items-center gap-3',
                                            compact
                                                ? 'px-3 py-2.5'
                                                : 'px-4 py-3.5',
                                        )}
                                    >
                                        {content}
                                    </div>
                                )}
                            </li>
                        );
                    })}
                </ul>
                <DashboardViewAllLink
                    href={viewAllHref}
                    label={viewAllLabel}
                />
            </div>
        </section>
    );
}
