import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import type { AnalystTask } from '@/components/analyst/types';
import {
    actionableTasks,
    jobAggregateStatus,
    jobMetaLine,
    jobProgress,
    taskActionLabel,
} from '@/components/analyst/types';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { cn } from '@/lib/utils';
import { ChevronDown, ChevronRight, MoreHorizontal } from 'lucide-react';
import { Fragment } from 'react';

type EmptyCopy = {
    title: string;
    body: string;
};

type JobGroup = {
    job: AnalystTask['job_order'];
    tasks: AnalystTask[];
};

type Props = {
    groups: JobGroup[];
    expandedIds: Set<number>;
    empty: EmptyCopy;
    onToggleExpand: (jobId: number) => void;
    onOpenJobDetails: (jobId: number) => void;
    onOpenTask: (task: AnalystTask) => void;
    onPreview: (url: string) => void;
};

function assigneeLabel(task: AnalystTask): string {
    if (task.is_mine !== false) {
        return 'You';
    }

    if (task.assignee_name) {
        return `Assigned to ${task.assignee_name}`;
    }

    return 'Unassigned';
}

export default function AnalystWorkQueueTable({
    groups,
    expandedIds,
    empty,
    onToggleExpand,
    onOpenJobDetails,
    onOpenTask,
    onPreview,
}: Props) {
    if (groups.length === 0) {
        return (
            <div className="rounded-xl border bg-white px-4 py-12 text-center">
                <p className="font-medium text-[#1A3694]">{empty.title}</p>
                <p className="mt-1 text-sm text-muted-foreground">{empty.body}</p>
            </div>
        );
    }

    return (
        <div className="overflow-hidden rounded-xl border bg-white">
            <div className="max-h-[min(70vh,720px)] overflow-auto">
                <table className="w-full min-w-[640px] border-collapse text-sm">
                    <thead className="sticky top-0 z-10 bg-[#f8fafc] shadow-[0_1px_0_0_rgb(226_232_240)]">
                        <tr className="text-left text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            <th className="px-3 py-2.5 font-semibold">
                                Job Order
                            </th>
                            <th className="px-3 py-2.5 font-semibold">
                                Customer
                            </th>
                            <th className="px-3 py-2.5 font-semibold">
                                Progress
                            </th>
                            <th className="px-3 py-2.5 font-semibold">
                                Status
                            </th>
                            <th className="px-3 py-2.5 text-right font-semibold">
                                Action
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {groups.map(({ job, tasks }) => {
                            const expanded = expandedIds.has(job.id);
                            const progress = jobProgress(tasks);
                            const aggregate = jobAggregateStatus(tasks);
                            const openMine = actionableTasks(tasks);
                            const primaryTask =
                                openMine.find((t) => t.status === 'returned') ??
                                openMine[0] ??
                                null;
                            const hasReturnedMine = openMine.some(
                                (t) => t.status === 'returned',
                            );

                            return (
                                <Fragment key={job.id}>
                                    <tr
                                        className={cn(
                                            'cursor-pointer border-t border-slate-100 transition hover:bg-[#f8fafc]',
                                            hasReturnedMine &&
                                                'border-l-2 border-l-amber-400 bg-amber-50/20',
                                            expanded && 'bg-[#f8fafc]',
                                        )}
                                        onClick={() => onToggleExpand(job.id)}
                                    >
                                        <td className="h-12 px-3 py-1.5 align-middle">
                                            <div className="flex items-center gap-1.5">
                                                {expanded ? (
                                                    <ChevronDown className="size-4 shrink-0 text-slate-500" />
                                                ) : (
                                                    <ChevronRight className="size-4 shrink-0 text-slate-500" />
                                                )}
                                                <span className="font-semibold text-[#1A3694]">
                                                    {job.reference_no}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    · {tasks.length} test
                                                    {tasks.length === 1
                                                        ? ''
                                                        : 's'}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="h-12 max-w-[14rem] px-3 py-1.5 align-middle">
                                            <p className="truncate text-slate-800">
                                                {job.customer_name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {jobMetaLine(job)}
                                            </p>
                                        </td>
                                        <td className="h-12 min-w-[8rem] px-3 py-1.5 align-middle">
                                            <p className="text-xs text-slate-600">
                                                {progress.done} /{' '}
                                                {progress.total} yours
                                            </p>
                                            <div className="mt-1 h-1.5 max-w-[7rem] overflow-hidden rounded-full bg-slate-100">
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
                                        </td>
                                        <td className="h-12 px-3 py-1.5 align-middle">
                                            <AnalystStatusBadge
                                                status={aggregate.key}
                                                label={aggregate.label}
                                            />
                                        </td>
                                        <td className="h-12 px-3 py-1.5 align-middle">
                                            <div
                                                className="flex items-center justify-end gap-1"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                {primaryTask && (
                                                    <Button
                                                        size="sm"
                                                        className="h-8 bg-[#1A3694] hover:bg-[#365BB0]"
                                                        onClick={() =>
                                                            onOpenTask(
                                                                primaryTask,
                                                            )
                                                        }
                                                    >
                                                        {taskActionLabel(
                                                            primaryTask,
                                                        )}
                                                    </Button>
                                                )}
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            type="button"
                                                            size="icon"
                                                            variant="ghost"
                                                            className="h-8 w-8"
                                                            aria-label="More actions"
                                                        >
                                                            <MoreHorizontal className="size-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            onClick={() =>
                                                                onOpenJobDetails(
                                                                    job.id,
                                                                )
                                                            }
                                                        >
                                                            View Job Order
                                                            details
                                                        </DropdownMenuItem>
                                                        {tasks[0]?.report
                                                            ?.can_preview && (
                                                            <DropdownMenuItem
                                                                onClick={() =>
                                                                    onPreview(
                                                                        `/analyst/tasks/${tasks[0].id}/report`,
                                                                    )
                                                                }
                                                            >
                                                                Preview report
                                                            </DropdownMenuItem>
                                                        )}
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </div>
                                        </td>
                                    </tr>

                                    {expanded && (
                                        <tr className="border-t border-slate-100 bg-white">
                                            <td
                                                colSpan={5}
                                                className="px-3 py-0"
                                            >
                                                <ul className="my-2 divide-y divide-slate-100 rounded-lg border bg-[#fafbfc]">
                                                    {tasks.map((task) => {
                                                        const mine =
                                                            task.is_mine !==
                                                            false;
                                                        const done =
                                                            task.status ===
                                                            'completed';

                                                        return (
                                                            <li
                                                                key={task.id}
                                                                className={cn(
                                                                    'flex flex-wrap items-center justify-between gap-2 px-3 py-2.5',
                                                                    task.status ===
                                                                        'returned' &&
                                                                        mine &&
                                                                        'bg-amber-50/70',
                                                                    !mine &&
                                                                        'opacity-90',
                                                                )}
                                                            >
                                                                <div className="min-w-0 flex-1">
                                                                    <p className="truncate text-sm font-medium text-slate-900">
                                                                        {
                                                                            task.name
                                                                        }
                                                                    </p>
                                                                    <div className="mt-1 flex flex-wrap items-center gap-2">
                                                                        <AnalystStatusBadge
                                                                            status={
                                                                                task.status
                                                                            }
                                                                            label={
                                                                                task.status_label
                                                                            }
                                                                        />
                                                                        <span className="text-xs text-muted-foreground">
                                                                            {assigneeLabel(
                                                                                task,
                                                                            )}
                                                                        </span>
                                                                        {mine &&
                                                                            task.status ===
                                                                                'returned' &&
                                                                            job.review_notes && (
                                                                                <span className="text-xs text-amber-800">
                                                                                    Correction
                                                                                    requested
                                                                                </span>
                                                                            )}
                                                                    </div>
                                                                </div>
                                                                {mine ? (
                                                                    <Button
                                                                        size="sm"
                                                                        className={cn(
                                                                            'h-8 shrink-0',
                                                                            !done &&
                                                                                'bg-[#1A3694] hover:bg-[#365BB0]',
                                                                        )}
                                                                        variant={
                                                                            done
                                                                                ? 'outline'
                                                                                : 'default'
                                                                        }
                                                                        onClick={() =>
                                                                            onOpenTask(
                                                                                task,
                                                                            )
                                                                        }
                                                                    >
                                                                        {taskActionLabel(
                                                                            task,
                                                                        )}
                                                                    </Button>
                                                                ) : (
                                                                    <span className="shrink-0 text-xs text-muted-foreground">
                                                                        View
                                                                        only
                                                                    </span>
                                                                )}
                                                            </li>
                                                        );
                                                    })}
                                                </ul>
                                            </td>
                                        </tr>
                                    )}
                                </Fragment>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}
