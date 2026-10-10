import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import type {
    AnalystTask,
    Consolidation,
    JobGroup,
} from '@/components/analyst/types';
import {
    jobAggregateStatus,
    jobMetaLine,
    jobProgress,
} from '@/components/analyst/types';
import TablePagination from '@/components/table-pagination';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight, CircleAlert, Search, Shield } from 'lucide-react';

type EmptyCopy = {
    title: string;
    body: string;
};

type KpiCard = {
    id: string;
    label: string;
    value: number;
    unit?: string;
    tone?: 'default' | 'success' | 'warning' | 'info' | 'danger';
    share?: number;
    badge?: string | null;
};

type Props = {
    groups: JobGroup[];
    selectedJobId: number | null;
    empty: EmptyCopy;
    consolidationsById: Map<number, Consolidation>;
    kpis: KpiCard[];
    allCount: number;
    activeStatus: string;
    onSelectStatus: (id: string) => void;
    query: string;
    onQueryChange: (value: string) => void;
    sort: string;
    onSortChange: (sort: string) => void;
    onSelectJob: (jobId: number) => void;
    pagination: {
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
};

function shortTestName(name: string): string {
    const trimmed = name.trim();
    if (trimmed.length <= 28) {
        return trimmed;
    }

    return `${trimmed.slice(0, 26)}…`;
}

function openActionLabel(
    aggregateKey: string,
    canSubmit: boolean,
): { label: string; tone: 'primary' | 'amber' | 'rose' | 'outline' } {
    if (aggregateKey === 'returned') {
        return { label: 'Fix & Re-encode', tone: 'rose' };
    }

    if (aggregateKey === 'in_progress') {
        return { label: 'Resume Encoding', tone: 'amber' };
    }

    if (aggregateKey === 'completed' && !canSubmit) {
        return { label: 'View Encoded', tone: 'outline' };
    }

    return { label: 'Open JO Workbench', tone: 'primary' };
}

function progressStatusLabel(
    aggregateKey: string,
    progress: { done: number; total: number },
): string {
    if (aggregateKey === 'returned') {
        return 'Returned for revision';
    }

    if (aggregateKey === 'completed') {
        return 'Sent to Head review';
    }

    if (aggregateKey === 'in_progress' || progress.done > 0) {
        return 'Encoding in progress';
    }

    return 'Ready to encode';
}

function reviewReason(
    job: JobGroup['job'],
    consolidation?: Consolidation,
): string | null {
    const notes = (job.review_notes || consolidation?.review_notes || '').trim();
    if (!notes) {
        return null;
    }

    const oneLine = notes.replace(/\s+/g, ' ');
    return oneLine.length > 90 ? `${oneLine.slice(0, 88)}…` : oneLine;
}

export default function AnalystQueuePanel({
    groups,
    selectedJobId,
    empty,
    consolidationsById,
    kpis,
    allCount,
    activeStatus,
    onSelectStatus,
    query,
    onQueryChange,
    sort,
    onSortChange,
    onSelectJob,
    pagination,
}: Props) {
    const summaryCards = kpis.map((kpi) => ({
        id: kpi.id,
        label: kpi.label,
        value: kpi.value,
        unit: kpi.unit ?? 'jobs',
        tone: kpi.tone,
        share: kpi.share,
        badge: kpi.badge,
    }));

    return (
        <section className="space-y-5">
            <QueueSummaryCards
                cards={summaryCards}
                activeId={activeStatus}
                onSelect={onSelectStatus}
            />

            <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-xs md:flex-row md:items-center md:justify-between">
                    <div
                        className="flex items-center gap-1 overflow-x-auto pb-1 text-xs md:pb-0"
                        role="tablist"
                    >
                        <button
                            type="button"
                            onClick={() => onSelectStatus('')}
                            className={cn(
                                'whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition',
                                !activeStatus
                                    ? 'border border-nppc-200/70 bg-nppc-50 font-semibold text-nppc-900 shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                            )}
                        >
                            All{' '}
                            <span className="ml-1 text-[11px] font-semibold text-slate-400">
                                {allCount}
                            </span>
                        </button>
                        {kpis.map((kpi) => {
                            const active = activeStatus === kpi.id;

                            return (
                                <button
                                    key={kpi.id}
                                    type="button"
                                    onClick={() => onSelectStatus(kpi.id)}
                                    className={cn(
                                        'whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition',
                                        active
                                            ? 'border border-nppc-200/70 bg-nppc-50 font-semibold text-nppc-900 shadow-xs'
                                            : kpi.id === 'returned'
                                              ? 'text-rose-700 hover:bg-rose-50'
                                              : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                                    )}
                                >
                                    {kpi.label}{' '}
                                    <span
                                        className={cn(
                                            'ml-1 tabular-nums',
                                            active
                                                ? 'rounded-full bg-nppc-900 px-1.5 py-0.5 text-[10px] font-bold text-white'
                                                : kpi.id === 'returned' &&
                                                    kpi.value > 0
                                                  ? 'rounded-full bg-rose-100 px-1.5 py-0.5 text-[10px] font-bold text-rose-700'
                                                  : 'text-[11px] font-semibold text-slate-400',
                                        )}
                                    >
                                        {kpi.value}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <div className="relative min-w-[200px] flex-1 md:w-72 md:flex-none">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                            <input
                                className="w-full rounded-lg border border-slate-200 bg-slate-50 py-1.5 pr-3 pl-9 text-xs placeholder-slate-400 transition focus:border-nppc-500 focus:bg-white focus:ring-2 focus:ring-nppc-500/30 focus:outline-none"
                                placeholder="Search JO, customer, sample…"
                                value={query}
                                onChange={(e) =>
                                    onQueryChange(e.target.value)
                                }
                            />
                        </div>
                        <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600 shadow-xs">
                            <span className="text-slate-400">Sort:</span>
                            <select
                                className="cursor-pointer border-0 bg-transparent p-0 text-xs font-medium text-slate-800 focus:ring-0"
                                value={sort}
                                onChange={(e) => onSortChange(e.target.value)}
                            >
                                <option value="needs_action">
                                    Needs Action Priority
                                </option>
                                <option value="newest">Newest First</option>
                                <option value="oldest">Oldest First</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[920px] border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="w-52 px-4 py-3">
                                        Job Order &amp; Sample
                                    </th>
                                    <th className="w-72 px-4 py-3">
                                        Customer &amp; Matrix
                                    </th>
                                    <th className="px-4 py-3">
                                        Assigned Tests
                                    </th>
                                    <th className="w-56 px-4 py-3">
                                        Encoding Progress
                                    </th>
                                    <th className="w-44 px-4 py-3 text-right">
                                        Action
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {groups.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={5}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                {empty.title}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {empty.body}
                                            </p>
                                        </td>
                                    </tr>
                                ) : (
                                    groups.map(({ job, tasks }) => {
                                        const selected =
                                            selectedJobId === job.id;
                                        const progress = jobProgress(tasks);
                                        const aggregate =
                                            jobAggregateStatus(tasks);
                                        const consolidation =
                                            consolidationsById.get(job.id);
                                        const action = openActionLabel(
                                            aggregate.key,
                                            consolidation?.can_submit === true,
                                        );
                                        const reason = reviewReason(
                                            job,
                                            consolidation,
                                        );
                                        const returned =
                                            aggregate.key === 'returned';

                                        return (
                                            <tr
                                                key={job.id}
                                                className={cn(
                                                    'transition-colors',
                                                    returned
                                                        ? 'bg-rose-50/10 hover:bg-rose-50/30'
                                                        : 'hover:bg-slate-50',
                                                    selected &&
                                                        'border-l-4 border-l-nppc-900 bg-nppc-50/40',
                                                )}
                                            >
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex flex-col gap-1.5">
                                                        <div className="flex flex-wrap items-center gap-2">
                                                            <span
                                                                className={cn(
                                                                    'rounded border px-2 py-0.5 font-mono text-xs font-bold',
                                                                    returned
                                                                        ? 'border-rose-200 bg-rose-100/70 text-rose-700'
                                                                        : 'border-slate-200 bg-slate-100 text-slate-900',
                                                                )}
                                                            >
                                                                JO #
                                                                {
                                                                    job.reference_no
                                                                }
                                                            </span>
                                                            <AnalystStatusBadge
                                                                status={
                                                                    aggregate.key
                                                                }
                                                                label={
                                                                    aggregate.label
                                                                }
                                                            />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <p className="font-semibold text-slate-900">
                                                        {job.customer_name}
                                                    </p>
                                                    <p className="mt-0.5 max-w-xs truncate text-[11px] text-slate-500">
                                                        {jobMetaLine(job)}
                                                    </p>
                                                    {returned && reason ? (
                                                        <div className="mt-1 flex items-start gap-1.5 text-[10px] font-medium text-rose-600">
                                                            <CircleAlert className="mt-0.5 size-2.5 shrink-0" />
                                                            <span>
                                                                Reason: {reason}
                                                            </span>
                                                        </div>
                                                    ) : null}
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="flex flex-wrap items-center gap-1.5">
                                                        {tasks
                                                            .slice(0, 3)
                                                            .map(
                                                                (
                                                                    task: AnalystTask,
                                                                ) => (
                                                                    <span
                                                                        key={
                                                                            task.id
                                                                        }
                                                                        className="inline-flex items-center gap-1 rounded border border-slate-200 bg-slate-100 px-2 py-0.5 text-[11px] font-medium text-slate-700"
                                                                    >
                                                                        <span
                                                                            className={cn(
                                                                                'size-1.5 shrink-0 rounded-full',
                                                                                task.status ===
                                                                                    'completed'
                                                                                    ? 'bg-emerald-500'
                                                                                    : task.status ===
                                                                                        'returned'
                                                                                      ? 'bg-rose-500'
                                                                                      : 'bg-nppc-500',
                                                                            )}
                                                                        />
                                                                        {shortTestName(
                                                                            task.name,
                                                                        )}
                                                                    </span>
                                                                ),
                                                            )}
                                                        {tasks.length > 3 ? (
                                                            <span className="rounded border border-slate-200 bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">
                                                                +
                                                                {tasks.length -
                                                                    3}{' '}
                                                                more tests
                                                            </span>
                                                        ) : null}
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 align-top">
                                                    <div className="space-y-1.5">
                                                        <div className="flex items-center justify-between gap-2 text-[11px]">
                                                            <span
                                                                className={cn(
                                                                    'rounded px-2 py-0.5 text-[10px] font-medium',
                                                                    returned
                                                                        ? 'bg-rose-100 text-rose-700'
                                                                        : aggregate.key ===
                                                                            'completed'
                                                                          ? 'border border-emerald-200 bg-emerald-50 text-emerald-700'
                                                                          : aggregate.key ===
                                                                              'in_progress'
                                                                            ? 'border border-amber-200 bg-amber-50 text-amber-800'
                                                                            : 'bg-slate-100 text-slate-600',
                                                                )}
                                                            >
                                                                {progressStatusLabel(
                                                                    aggregate.key,
                                                                    progress,
                                                                )}
                                                            </span>
                                                            <span className="font-mono font-semibold tabular-nums text-slate-500">
                                                                {progress.done}{' '}
                                                                /{' '}
                                                                {progress.total}
                                                            </span>
                                                        </div>
                                                        <div className="h-2 w-full overflow-hidden rounded-full bg-slate-200">
                                                            <div
                                                                className={cn(
                                                                    'h-full rounded-full transition-all',
                                                                    returned
                                                                        ? 'bg-rose-500'
                                                                        : progress.percent ===
                                                                            100
                                                                          ? 'bg-emerald-500'
                                                                          : aggregate.key ===
                                                                              'in_progress'
                                                                            ? 'bg-amber-500'
                                                                            : 'bg-nppc-900',
                                                                )}
                                                                style={{
                                                                    width: `${progress.percent}%`,
                                                                }}
                                                            />
                                                        </div>
                                                    </div>
                                                </td>
                                                <td className="px-4 py-4 text-right align-top">
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant={
                                                            action.tone ===
                                                            'outline'
                                                                ? 'outline'
                                                                : 'default'
                                                        }
                                                        className={cn(
                                                            'gap-1.5 text-xs shadow-xs',
                                                            action.tone ===
                                                                'primary' &&
                                                                'bg-nppc-900 hover:bg-nppc-700',
                                                            action.tone ===
                                                                'amber' &&
                                                                'border border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100',
                                                            action.tone ===
                                                                'rose' &&
                                                                'bg-rose-600 hover:bg-rose-700',
                                                        )}
                                                        onClick={() =>
                                                            onSelectJob(job.id)
                                                        }
                                                    >
                                                        {action.label}
                                                        <ArrowRight className="size-3" />
                                                    </Button>
                                                </td>
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <TablePagination
                            links={pagination.links}
                            from={pagination.from}
                            to={pagination.to}
                            total={pagination.total}
                        />
                    </div>
                </div>

                <aside className="flex flex-col items-start justify-between gap-3 rounded-xl border border-nppc-100 bg-nppc-50/50 p-4 md:flex-row md:items-center">
                    <div className="flex items-center gap-3">
                        <div className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-nppc-900 text-white">
                            <Shield className="size-3.5" />
                        </div>
                        <div>
                            <h4 className="text-xs font-bold text-slate-900">
                                Good Laboratory Practice (GLP) Checklist Active
                            </h4>
                            <p className="mt-0.5 text-[11px] text-slate-600">
                                Ensure blank plates, positive/negative controls,
                                and incubator logs are cross-referenced before
                                final encoding sign-off.
                            </p>
                        </div>
                    </div>
                </aside>
        </section>
    );
}
