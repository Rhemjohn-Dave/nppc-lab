import { Link } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import {
    jobAggregateStatus,
    jobMetaLine,
    jobProgress,
} from '@/components/analyst/types';
import type { AnalystTask } from '@/components/analyst/types';
import DashboardEmptyState from '@/components/dashboard/dashboard-empty-state';
import { DashboardViewAllLink } from '@/components/dashboard/dashboard-shell';
import { cn } from '@/lib/utils';
import type { AnalystDashboardJobGroup } from './types';

type Props = {
    groups: AnalystDashboardJobGroup[];
    empty: string;
    viewAllHref: string;
    viewAllLabel: string;
    compact?: boolean;
};

function toProgressTasks(
    tasks: AnalystDashboardJobGroup['tasks'],
): AnalystTask[] {
    return tasks.map((task) => ({
        ...task,
        report: {
            kind: 'unavailable',
            title: '',
            can_preview: false,
        },
        job_order: {
            id: 0,
            reference_no: '',
            customer_name: '',
        },
    })) as AnalystTask[];
}

function assigneeLabel(task: AnalystDashboardJobGroup['tasks'][number]): string {
    if (task.is_mine !== false) {
        return 'You';
    }

    if (task.assignee_name) {
        return `Suggested: ${task.assignee_name}`;
    }

    return 'Unassigned';
}

export default function AnalystJobPreviewCards({
    groups,
    empty,
    viewAllHref,
    viewAllLabel,
    compact = false,
}: Props) {
    const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());

    function toggle(jobId: number) {
        setExpandedIds((prev) => {
            const next = new Set(prev);
            if (next.has(jobId)) {
                next.delete(jobId);
            } else {
                next.add(jobId);
            }
            return next;
        });
    }

    if (groups.length === 0) {
        return <DashboardEmptyState body={empty} />;
    }

    return (
        <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <ul className="divide-y divide-slate-100">
                {groups.map(({ job, tasks }) => {
                    const expanded = expandedIds.has(job.id);
                    const progressTasks = toProgressTasks(tasks);
                    const progress = jobProgress(progressTasks);
                    const aggregate = jobAggregateStatus(progressTasks);
                    const workspaceHref = `/analyst?q=${encodeURIComponent(job.reference_no)}`;

                    return (
                        <li key={job.id}>
                            <Link
                                href={workspaceHref}
                                className={cn(
                                    'block transition hover:bg-[#f8fafc]',
                                    compact ? 'px-3 py-2.5' : 'px-4 py-4',
                                )}
                                onClick={(event) => {
                                    const target = event.target as HTMLElement;
                                    if (target.closest('[data-expand]')) {
                                        event.preventDefault();
                                        toggle(job.id);
                                    }
                                }}
                            >
                                <div className="flex items-start justify-between gap-3">
                                    <div className="min-w-0 flex-1">
                                        <div className="flex flex-wrap items-center gap-2">
                                            <button
                                                type="button"
                                                data-expand
                                                className="shrink-0 rounded p-0.5 text-slate-500 hover:bg-slate-100"
                                                aria-expanded={expanded}
                                                aria-label={
                                                    expanded
                                                        ? 'Collapse tests'
                                                        : 'Expand tests'
                                                }
                                                onClick={(event) => {
                                                    event.preventDefault();
                                                    event.stopPropagation();
                                                    toggle(job.id);
                                                }}
                                            >
                                                {expanded ? (
                                                    <ChevronDown className="size-4" />
                                                ) : (
                                                    <ChevronRight className="size-4" />
                                                )}
                                            </button>
                                            <span className="font-semibold text-[#1A3694]">
                                                {job.reference_no}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                · {tasks.length} test
                                                {tasks.length === 1 ? '' : 's'}
                                            </span>
                                            <AnalystStatusBadge
                                                status={aggregate.key}
                                                label={aggregate.label}
                                            />
                                        </div>
                                        <p
                                            className={cn(
                                                'truncate text-sm text-slate-800',
                                                compact ? 'mt-0.5' : 'mt-1',
                                            )}
                                        >
                                            {job.customer_name}
                                            {compact
                                                ? ` · ${progress.done}/${progress.total} yours`
                                                : ''}
                                        </p>
                                        {!compact && (
                                            <p className="truncate text-xs text-muted-foreground">
                                                {jobMetaLine(job)}
                                            </p>
                                        )}
                                        {!compact && (
                                            <div className="mt-3 flex flex-wrap items-center gap-3">
                                                <AnalystStatusBadge
                                                    status={aggregate.key}
                                                    label={aggregate.label}
                                                />
                                                <span className="text-xs text-slate-600">
                                                    {progress.done} /{' '}
                                                    {progress.total} yours
                                                </span>
                                            </div>
                                        )}
                                        <div
                                            className={cn(
                                                'h-1.5 max-w-xs overflow-hidden rounded-full bg-slate-100',
                                                compact ? 'mt-1.5' : 'mt-2',
                                            )}
                                        >
                                            <div
                                                className={cn(
                                                    'h-full rounded-full',
                                                    progress.percent === 100
                                                        ? 'bg-emerald-500'
                                                        : 'bg-[#1A3694]',
                                                )}
                                                style={{
                                                    width: `${progress.percent}%`,
                                                }}
                                            />
                                        </div>
                                    </div>
                                </div>
                                {expanded && (
                                    <ul
                                        className={cn(
                                            'space-y-2 rounded-lg border bg-[#fafbfc]',
                                            compact
                                                ? 'mt-2 p-2.5'
                                                : 'mt-3 p-3',
                                        )}
                                        onClick={(event) =>
                                            event.stopPropagation()
                                        }
                                    >
                                        {tasks.map((task) => (
                                            <li
                                                key={task.id}
                                                className="flex flex-wrap items-center justify-between gap-2 text-sm"
                                            >
                                                <span className="font-medium text-slate-900">
                                                    {task.name}
                                                </span>
                                                <div className="flex flex-wrap items-center gap-2">
                                                    <AnalystStatusBadge
                                                        status={task.status}
                                                        label={
                                                            task.status_label
                                                        }
                                                    />
                                                    <span className="text-xs text-muted-foreground">
                                                        {assigneeLabel(task)}
                                                    </span>
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Link>
                        </li>
                    );
                })}
            </ul>
            <DashboardViewAllLink href={viewAllHref} label={viewAllLabel} />
        </div>
    );
}
