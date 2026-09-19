import { Link } from '@inertiajs/react';
import { FileText, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import DashboardEmptyState from './dashboard-empty-state';
import type { DashboardQueue } from './types';

const COLUMN_LABELS: Record<string, string> = {
    form: 'Code & form title',
    revision: 'Revision',
    status: 'Status',
    effective_date: 'Effective',
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
    /** Registry card with count badge + client filter (admin dashboard) */
    registry?: boolean;
    registeredTotal?: number;
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
        return (
            <span className="font-mono text-xs font-medium text-slate-700">
                Rev {String(value)}
            </span>
        );
    }

    if (value === null || value === undefined || value === '') {
        return '—';
    }

    return String(value);
}

function FormTitleCell({
    row,
    href,
}: {
    row: Record<string, unknown>;
    href: string | null;
}) {
    const name = String(row.form ?? '—');
    const code =
        typeof row.form_code === 'string' && row.form_code.trim() !== ''
            ? row.form_code
            : null;

    const title = href ? (
        <Link
            href={href}
            className="font-medium text-slate-900 hover:text-[#1A3694] hover:underline"
        >
            {name}
        </Link>
    ) : (
        <span className="font-medium text-slate-900">{name}</span>
    );

    return (
        <div className="flex items-start gap-2">
            <FileText
                className="mt-0.5 size-4 shrink-0 text-muted-foreground"
                aria-hidden="true"
            />
            <div className="min-w-0">
                {title}
                {code ? (
                    <div className="mt-0.5">
                        <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] text-slate-600">
                            {code}
                        </span>
                    </div>
                ) : null}
            </div>
        </div>
    );
}

export default function DashboardQueueTable({
    data,
    compact = false,
    viewAllHref,
    viewAllLabel = 'View all',
    registry = false,
    registeredTotal,
}: Props) {
    const [query, setQuery] = useState('');

    const filteredRows = useMemo(() => {
        if (!registry) {
            return data.rows;
        }
        const q = query.trim().toLowerCase();
        if (!q) {
            return data.rows;
        }
        return data.rows.filter((row) => {
            const haystack = [
                String(row.form ?? ''),
                String(row.form_code ?? ''),
                String(row.status_label ?? ''),
                String(row.revision ?? ''),
            ]
                .join(' ')
                .toLowerCase();
            return haystack.includes(q);
        });
    }, [data.rows, query, registry]);

    const countLabel = registeredTotal ?? data.rows.length;

    if (data.rows.length === 0) {
        return (
            <section
                className={cn(
                    registry &&
                        'overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm',
                )}
            >
                <SectionHeader
                    title={data.title}
                    viewAllHref={viewAllHref}
                    viewAllLabel={viewAllLabel}
                    registry={registry}
                    countLabel={countLabel}
                    subtitle={
                        registry
                            ? 'Authoritative repository of ISO 17025 accredited worksheets'
                            : undefined
                    }
                />
                <div className={registry ? 'px-3 pb-3' : undefined}>
                    <DashboardEmptyState body={data.empty} />
                </div>
            </section>
        );
    }

    return (
        <section
            className={cn(
                registry
                    ? 'flex flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm'
                    : 'space-y-2',
            )}
        >
            <SectionHeader
                title={data.title}
                viewAllHref={viewAllHref}
                viewAllLabel={viewAllLabel}
                registry={registry}
                countLabel={countLabel}
                subtitle={
                    registry
                        ? 'Authoritative repository of ISO 17025 accredited worksheets'
                        : undefined
                }
            />

            {registry ? (
                <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/70 px-3 py-2">
                    <div className="relative max-w-xs flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-2 size-3.5 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            className="h-7 pl-7 text-xs"
                            placeholder="Filter form title or code…"
                            value={query}
                            onChange={(event) => setQuery(event.target.value)}
                        />
                    </div>
                    {viewAllHref ? (
                        <Link
                            href={viewAllHref}
                            className="text-xs font-medium text-[#365BB0] hover:underline"
                        >
                            View all catalog →
                        </Link>
                    ) : null}
                </div>
            ) : null}

            <div
                className={cn(
                    'overflow-x-auto',
                    !registry &&
                        'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                )}
            >
                <table
                    className={cn(
                        'w-full border-collapse text-sm',
                        compact ? 'min-w-0 text-xs' : 'min-w-[560px]',
                    )}
                >
                    <thead className="bg-[#f8fafc]">
                        <tr className="text-left text-[11px] font-semibold tracking-wide text-slate-500 uppercase">
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
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-100">
                        {filteredRows.map((row, index) => {
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
                                    className="hover:bg-slate-50/80"
                                >
                                    {data.columns.map((column) => (
                                        <td
                                            key={column}
                                            className={cn(
                                                'px-3 align-middle',
                                                compact
                                                    ? 'py-2.5'
                                                    : 'h-11 py-1.5',
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
                                            ) : column === 'form' ? (
                                                registry ||
                                                typeof row.form_code ===
                                                    'string' ? (
                                                    <FormTitleCell
                                                        row={row}
                                                        href={href}
                                                    />
                                                ) : href ? (
                                                    <Link
                                                        href={href}
                                                        className="line-clamp-2 font-medium text-[#1A3694] hover:underline"
                                                    >
                                                        {cellValue(row, column)}
                                                    </Link>
                                                ) : (
                                                    cellValue(row, column)
                                                )
                                            ) : (
                                                cellValue(row, column)
                                            )}
                                        </td>
                                    ))}
                                    <td
                                        className={cn(
                                            'px-3 text-right align-middle',
                                            compact ? 'py-2.5' : 'h-11 py-1.5',
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
                        {filteredRows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={data.columns.length + 1}
                                    className="px-3 py-6 text-center text-muted-foreground"
                                >
                                    No forms match this filter.
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>

            {registry ? (
                <div className="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/50 px-3 py-2 text-[11px] text-muted-foreground">
                    <span>
                        Showing{' '}
                        <span className="font-medium text-slate-800">
                            {filteredRows.length}
                        </span>{' '}
                        of{' '}
                        <span className="font-medium text-slate-800">
                            {data.rows.length}
                        </span>{' '}
                        templates in preview
                    </span>
                    {viewAllHref ? (
                        <Link
                            href={viewAllHref}
                            className="font-medium text-[#365BB0] hover:underline"
                        >
                            Open full catalog →
                        </Link>
                    ) : null}
                </div>
            ) : null}
        </section>
    );
}

function SectionHeader({
    title,
    viewAllHref,
    viewAllLabel,
    registry,
    countLabel,
    subtitle,
}: {
    title: string;
    viewAllHref?: string;
    viewAllLabel: string;
    registry?: boolean;
    countLabel?: number;
    subtitle?: string;
}) {
    if (registry) {
        return (
            <div className="flex flex-col gap-1 border-b border-slate-200 px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-sm font-semibold text-slate-900">
                            {title}
                        </h2>
                        {countLabel != null ? (
                            <span className="rounded border bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-medium text-slate-700">
                                {countLabel} registered
                            </span>
                        ) : null}
                    </div>
                    {subtitle ? (
                        <p className="mt-0.5 text-[11px] text-muted-foreground">
                            {subtitle}
                        </p>
                    ) : null}
                </div>
            </div>
        );
    }

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
