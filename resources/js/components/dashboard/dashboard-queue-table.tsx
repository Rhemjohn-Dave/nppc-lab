import { Link } from '@inertiajs/react';
import { ArrowRight, FileText, PenLine, Search } from 'lucide-react';
import { useMemo, useState } from 'react';
import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import JoReferenceChip from '@/components/lims/jo-reference-chip';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import DashboardEmptyState from './dashboard-empty-state';
import type { DashboardQueue } from './types';

const COLUMN_LABELS: Record<string, string> = {
    form: 'Code & Form Title',
    revision: 'Revision',
    status: 'Status',
    effective_date: 'Effective',
    updated_at: 'Last Updated',
    reference_no: 'Job Order',
    customer_name: 'Customer',
    classification: 'Classification',
    samples_count: 'Samples',
    tests: 'Tests',
    completion: 'Completion',
};

type StatusTab = 'all' | 'active' | 'superseded' | 'draft';

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
            <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-700">
                Rev {String(value)}
            </span>
        );
    }

    if (column === 'effective_date' || column === 'updated_at') {
        if (value === null || value === undefined || value === '') {
            return (
                <span className="font-mono text-[11px] text-slate-500">—</span>
            );
        }
        return (
            <span className="text-slate-600">
                {String(value)}
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
            className="font-semibold text-slate-900 transition-colors group-hover:text-nppc-900"
        >
            {name}
        </Link>
    ) : (
        <span className="font-semibold text-slate-900">{name}</span>
    );

    return (
        <div className="flex items-start gap-2.5">
            <div className="mt-0.5 flex size-7 shrink-0 items-center justify-center rounded border border-nppc-100 bg-nppc-50 text-nppc-900">
                <FileText className="size-4" aria-hidden="true" />
            </div>
            <div className="min-w-0">
                {title}
                {code ? (
                    <p className="mt-0.5 font-mono text-[11px] text-slate-400">
                        {code}
                    </p>
                ) : null}
            </div>
        </div>
    );
}

function statusMatchesTab(status: string, tab: StatusTab): boolean {
    const s = status.toLowerCase();
    switch (tab) {
        case 'active':
            return s === 'active' || s === 'approved';
        case 'superseded':
            return s === 'superseded' || s === 'archived';
        case 'draft':
            return (
                s === 'draft' ||
                s === 'for_review' ||
                s === 'for_approval' ||
                s.includes('draft')
            );
        default:
            return true;
    }
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
    const [statusTab, setStatusTab] = useState<StatusTab>('all');

    const statusCounts = useMemo(() => {
        const counts = { all: data.rows.length, active: 0, superseded: 0, draft: 0 };
        for (const row of data.rows) {
            const status = String(row.status ?? '');
            if (statusMatchesTab(status, 'active')) {
                counts.active += 1;
            } else if (statusMatchesTab(status, 'superseded')) {
                counts.superseded += 1;
            } else if (statusMatchesTab(status, 'draft')) {
                counts.draft += 1;
            }
        }
        return counts;
    }, [data.rows]);

    const filteredRows = useMemo(() => {
        let rows = data.rows;

        if (registry && statusTab !== 'all') {
            rows = rows.filter((row) =>
                statusMatchesTab(String(row.status ?? ''), statusTab),
            );
        }

        if (!registry) {
            return rows;
        }

        const q = query.trim().toLowerCase();
        if (!q) {
            return rows;
        }

        return rows.filter((row) => {
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
    }, [data.rows, query, registry, statusTab]);

    const countLabel = registeredTotal ?? data.rows.length;

    const tabs: Array<{ id: StatusTab; label: string; count: number }> = [
        { id: 'all', label: 'All', count: statusCounts.all },
        { id: 'active', label: 'Active', count: statusCounts.active },
        {
            id: 'superseded',
            label: 'Superseded',
            count: statusCounts.superseded,
        },
        { id: 'draft', label: 'Drafts', count: statusCounts.draft },
    ];

    if (data.rows.length === 0) {
        return (
            <section
                className={cn(
                    registry &&
                        'overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs',
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
                            ? 'Authoritative repository of ISO 17025 accredited worksheets and analytical templates'
                            : undefined
                    }
                />
                <div className={registry ? 'px-4 pb-4' : undefined}>
                    <DashboardEmptyState body={data.empty} />
                </div>
            </section>
        );
    }

    return (
        <section
            className={cn(
                registry
                    ? 'flex flex-col overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs'
                    : 'space-y-2',
            )}
            id={registry ? 'registry-table' : undefined}
        >
            <SectionHeader
                title={data.title}
                viewAllHref={viewAllHref}
                viewAllLabel={viewAllLabel}
                registry={registry}
                countLabel={countLabel}
                subtitle={
                    registry
                        ? 'Authoritative repository of ISO 17025 accredited worksheets and analytical templates'
                        : undefined
                }
            />

            {registry ? (
                <div className="space-y-3 border-b border-slate-200 bg-white px-4 pb-4">
                    <div className="flex flex-col items-stretch gap-3 sm:flex-row sm:items-center">
                        <div className="relative w-full sm:flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                            <Input
                                className="h-8 rounded-lg border-slate-200 bg-slate-50 pl-9 text-xs transition-all placeholder:text-slate-400 focus:bg-white"
                                placeholder="Filter form title, code, or method..."
                                value={query}
                                onChange={(event) =>
                                    setQuery(event.target.value)
                                }
                            />
                        </div>
                        <div className="flex w-full shrink-0 items-center justify-center rounded-lg bg-slate-100 p-0.5 text-xs font-medium text-slate-600 sm:w-auto">
                            {tabs.map((tab) => (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => setStatusTab(tab.id)}
                                    className={cn(
                                        'rounded-md px-2.5 py-1 transition-colors',
                                        statusTab === tab.id
                                            ? 'bg-white font-semibold text-slate-900 shadow-sm'
                                            : 'hover:text-slate-900',
                                    )}
                                >
                                    {tab.label} {tab.count}
                                </button>
                            ))}
                        </div>
                    </div>
                </div>
            ) : null}

            <div
                className={cn(
                    'overflow-x-auto',
                    !registry &&
                        'rounded-xl border border-slate-200 bg-white shadow-xs',
                )}
            >
                <table
                    className={cn(
                        'w-full border-collapse text-left text-xs',
                        compact ? 'min-w-0' : 'min-w-[560px]',
                    )}
                >
                    <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                        <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                            {data.columns.map((column) => (
                                <th
                                    key={column}
                                    className={cn(
                                        'px-4 py-3',
                                        column === 'form' && 'min-w-[12rem]',
                                    )}
                                >
                                    {COLUMN_LABELS[column] ?? column}
                                </th>
                            ))}
                            <th className="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-slate-200/80">
                        {filteredRows.map((row, index) => {
                            const href =
                                typeof row.href === 'string' ? row.href : null;
                            const actionLabel =
                                typeof row.action_label === 'string'
                                    ? row.action_label
                                    : 'View';
                            const key =
                                typeof row.id === 'number' ||
                                typeof row.id === 'string'
                                    ? String(row.id)
                                    : String(index);
                            const referenceNo =
                                typeof row.reference_no === 'string'
                                    ? row.reference_no
                                    : null;

                            return (
                                <tr
                                    key={key}
                                    className="group transition-colors hover:bg-slate-50"
                                >
                                    {data.columns.map((column) => (
                                        <td
                                            key={column}
                                            className={cn(
                                                'px-4 align-top',
                                                compact ? 'py-4' : 'py-3.5',
                                            )}
                                        >
                                            {column === 'reference_no' ? (
                                                referenceNo ? (
                                                    href ? (
                                                        <Link href={href}>
                                                            <JoReferenceChip
                                                                referenceNo={
                                                                    referenceNo
                                                                }
                                                            />
                                                        </Link>
                                                    ) : (
                                                        <JoReferenceChip
                                                            referenceNo={
                                                                referenceNo
                                                            }
                                                        />
                                                    )
                                                ) : (
                                                    '—'
                                                )
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
                                                        className="line-clamp-2 font-medium text-nppc-900 hover:underline"
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
                                            'px-4 text-right align-top',
                                            compact ? 'py-4' : 'py-3.5',
                                        )}
                                    >
                                        {href && (
                                            <div className="inline-flex items-center gap-1.5">
                                                <Button
                                                    asChild
                                                    size="sm"
                                                    className="h-8 gap-1.5 bg-nppc-900 px-2.5 text-xs font-semibold shadow-xs hover:bg-nppc-700"
                                                >
                                                    <Link href={href}>
                                                        {actionLabel}
                                                        <ArrowRight className="size-3" />
                                                    </Link>
                                                </Button>
                                                {registry ? (
                                                    <Link
                                                        href={href}
                                                        title="Designer template"
                                                        className="rounded p-1 text-slate-400 transition-colors hover:bg-slate-100 hover:text-slate-600"
                                                    >
                                                        <PenLine
                                                            className="size-3.5"
                                                            aria-hidden="true"
                                                        />
                                                        <span className="sr-only">
                                                            Open designer
                                                        </span>
                                                    </Link>
                                                ) : null}
                                            </div>
                                        )}
                                    </td>
                                </tr>
                            );
                        })}
                        {filteredRows.length === 0 ? (
                            <tr>
                                <td
                                    colSpan={data.columns.length + 1}
                                    className="px-4 py-12 text-center"
                                >
                                    <p className="text-sm font-medium text-nppc-900">
                                        No matching forms
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        No forms match this filter.
                                    </p>
                                </td>
                            </tr>
                        ) : null}
                    </tbody>
                </table>
            </div>

            {registry ? (
                <div className="flex items-center justify-between gap-2 border-t border-slate-200 bg-slate-50/50 px-4 py-3 text-xs text-slate-500">
                    <span>
                        Showing{' '}
                        <strong className="font-semibold text-slate-700">
                            {filteredRows.length}
                        </strong>{' '}
                        of{' '}
                        <strong className="font-semibold text-slate-700">
                            {data.rows.length}
                        </strong>{' '}
                        controlled documents
                    </span>
                    {viewAllHref ? (
                        <Link
                            href={viewAllHref}
                            className="font-semibold text-nppc-900 hover:underline"
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
            <div className="border-b border-slate-200 bg-white p-4">
                <div className="flex flex-col justify-between gap-3 sm:flex-row sm:items-center">
                    <div>
                        <div className="flex flex-wrap items-center gap-2">
                            <h2 className="text-sm font-bold tracking-tight text-slate-900">
                                {title}
                            </h2>
                            {countLabel != null ? (
                                <span className="rounded-full border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-semibold text-slate-700">
                                    {countLabel} registered
                                </span>
                            ) : null}
                        </div>
                        {subtitle ? (
                            <p className="mt-0.5 text-xs text-slate-500">
                                {subtitle}
                            </p>
                        ) : null}
                    </div>
                    {viewAllHref ? (
                        <Link
                            href={viewAllHref}
                            className="inline-flex shrink-0 items-center gap-1 text-xs font-semibold text-nppc-900 hover:text-nppc-700"
                        >
                            {viewAllLabel}
                            <span aria-hidden="true">→</span>
                        </Link>
                    ) : null}
                </div>
            </div>
        );
    }

    return (
        <div className="flex items-center justify-between gap-2">
            <h2 className="text-xs font-semibold tracking-wide text-slate-900 uppercase">
                {title}
            </h2>
            {viewAllHref && (
                <Link
                    href={viewAllHref}
                    className="text-xs font-medium text-nppc-900 hover:underline"
                >
                    {viewAllLabel} →
                </Link>
            )}
        </div>
    );
}
