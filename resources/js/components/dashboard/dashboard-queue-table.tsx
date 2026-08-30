import { Link } from '@inertiajs/react';
import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import DashboardEmptyState from './dashboard-empty-state';
import type { DashboardQueue } from './types';

const COLUMN_LABELS: Record<string, string> = {
    form: 'Form',
    revision: 'Revision',
    status: 'Status',
    effective_date: 'Effective date',
    updated_at: 'Last updated',
    reference_no: 'Job Order',
    customer_name: 'Customer',
    classification: 'Classification',
    samples_count: 'Samples',
    tests: 'Tests',
    completion: 'Completion',
};

type Props = {
    data: DashboardQueue;
    compact?: boolean;
    viewAllHref?: string;
    viewAllLabel?: string;
};

function cellValue(row: Record<string, unknown>, column: string) {
    const value = row[column];

    if (column === 'status') {
        return (
            <AnalystStatusBadge
                status={String(row.status ?? '')}
                label={String(row.status_label ?? row.status ?? '')}
            />
        );
    }

    if (column === 'samples_count') {
        const count = Number(value ?? 0);
        return `${count} sample${count === 1 ? '' : 's'}`;
    }

    if (column === 'completion') {
        return `${Number(value ?? 0)}%`;
    }

    if (column === 'revision' && value) {
        return `Rev ${String(value)}`;
    }

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

export default function DashboardQueueTable({
    data,
    compact = false,
    viewAllHref,
    viewAllLabel = 'View all',
}: Props) {
    if (data.rows.length === 0) {
        return (
            <section className="space-y-2">
                <SectionHeader
                    title={data.title}
                    viewAllHref={viewAllHref}
                    viewAllLabel={viewAllLabel}
                />
                <DashboardEmptyState body={data.empty} />
            </section>
        );
    }

    return (
        <section className="space-y-2">
            <SectionHeader
                title={data.title}
                viewAllHref={viewAllHref}
                viewAllLabel={viewAllLabel}
            />
            <div className="overflow-x-auto rounded-xl border border-slate-200/80 bg-white shadow-sm">
                <table
                    className={cn(
                        'w-full border-collapse text-sm',
                        compact ? 'min-w-0' : 'min-w-[560px]',
                    )}
                >
                    <thead className="bg-[#f8fafc]">
                        <tr className="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            {data.columns.map((column) => (
                                <th
                                    key={column}
                                    className={cn(
                                        'px-3 font-semibold',
                                        compact ? 'py-2' : 'py-2.5',
                                        column === 'form' && 'min-w-[12rem]',
                                    )}
                                >
                                    {COLUMN_LABELS[column] ?? column}
                                </th>
                            ))}
                            <th
                                className={cn(
                                    'px-3 text-right font-semibold',
                                    compact ? 'py-2' : 'py-2.5',
                                )}
                            >
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {data.rows.map((row, index) => {
                            const href =
                                typeof row.href === 'string' ? row.href : null;
                            const actionLabel =
                                typeof row.action_label === 'string'
                                    ? row.action_label
                                    : 'Open';
                            const key =
                                typeof row.id === 'number' ||
                                typeof row.id === 'string'
                                    ? String(row.id)
                                    : String(index);

                            return (
                                <tr
                                    key={key}
                                    className="border-t border-slate-100 hover:bg-[#f8fafc]"
                                >
                                    {data.columns.map((column) => (
                                        <td
                                            key={column}
                                            className={cn(
                                                'px-3 align-middle',
                                                compact ? 'h-9 py-1.5' : 'h-11 py-1.5',
                                                column === 'form' &&
                                                    'max-w-md font-medium',
                                            )}
                                        >
                                            {column === 'reference_no' &&
                                            href ? (
                                                <Link
                                                    href={href}
                                                    className="font-semibold text-[#1A3694] hover:underline"
                                                >
                                                    {cellValue(row, column)}
                                                </Link>
                                            ) : column === 'form' && href ? (
                                                <Link
                                                    href={href}
                                                    className="line-clamp-2 text-[#1A3694] hover:underline"
                                                >
                                                    {cellValue(row, column)}
                                                </Link>
                                            ) : (
                                                cellValue(row, column)
                                            )}
                                        </td>
                                    ))}
                                    <td
                                        className={cn(
                                            'px-3 text-right align-middle',
                                            compact ? 'h-9 py-1.5' : 'h-11 py-1.5',
                                        )}
                                    >
                                        {href && (
                                            <Button
                                                asChild
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2.5"
                                            >
                                                <Link href={href}>
                                                    {actionLabel}
                                                </Link>
                                            </Button>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </section>
    );
}

function SectionHeader({
    title,
    viewAllHref,
    viewAllLabel,
}: {
    title: string;
    viewAllHref?: string;
    viewAllLabel: string;
}) {
    return (
        <div className="flex items-center justify-between gap-2">
            <h2 className="text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
                {title}
            </h2>
            {viewAllHref && (
                <Link
                    href={viewAllHref}
                    className="text-xs font-medium text-[#365BB0] hover:underline"
                >
                    {viewAllLabel} →
                </Link>
            )}
        </div>
    );
}
