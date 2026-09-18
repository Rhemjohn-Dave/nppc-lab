import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import type { AnalystTask, Consolidation } from '@/components/analyst/types';
import {
    actionableTasks,
    jobAggregateStatus,
    jobMetaLine,
    jobProgress,
    taskActionLabel,
} from '@/components/analyst/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    ChevronDown,
    ChevronRight,
    ClipboardPen,
    Eye,
    FileText,
    Play,
    RotateCcw,
    ScanSearch,
    Send,
    type LucideIcon,
} from 'lucide-react';
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
    consolidationsById: Map<number, Consolidation>;
    onToggleExpand: (jobId: number) => void;
    onOpenJobDetails: (jobId: number) => void;
    onOpenTask: (task: AnalystTask) => void;
    onPreview: (url: string) => void;
    onSendToHead: (jobId: number) => void;
};

function assigneeLabel(task: AnalystTask): string {
    if (task.is_mine !== false) {
        return 'You';
    }

    if (task.assignee_name) {
        return `Suggested: ${task.assignee_name}`;
    }

    return 'Unassigned';
}

function taskActionIcon(task: AnalystTask): LucideIcon {
    const label = taskActionLabel(task);

    switch (label) {
        case 'Correct result':
            return RotateCcw;
        case 'View result':
            return Eye;
        case 'Continue':
            return Play;
        default:
            return ClipboardPen;
    }
}

function TaskActionContent({ task }: { task: AnalystTask }) {
    const Icon = taskActionIcon(task);
    const label = taskActionLabel(task);

    return (
        <>
            <Icon className="size-3.5 shrink-0" aria-hidden="true" />
            {label}
        </>
    );
}

export default function AnalystWorkQueueTable({
    groups,
    expandedIds,
    empty,
    consolidationsById,
    onToggleExpand,
    onOpenJobDetails,
    onOpenTask,
    onPreview,
    onSendToHead,
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
                            <th className="px-2.5 py-2 font-semibold">
                                Job Order
                            </th>
                            <th className="px-2.5 py-2 font-semibold">
                                Customer
                            </th>
                            <th className="px-2.5 py-2 font-semibold">
                                Progress
                            </th>
                            <th className="px-2.5 py-2 font-semibold">
                                Status
                            </th>
                            <th className="px-2.5 py-2 text-right font-semibold">
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
                            const consolidation = consolidationsById.get(
                                job.id,
                            );
                            const canSendToHead = Boolean(
                                consolidation?.can_submit,
                            );
                            const previewUrl = consolidation?.preview_url
                                ? consolidation.preview_url
                                : tasks[0]?.report?.can_preview
                                  ? `/analyst/tasks/${tasks[0].id}/report`
                                  : null;
                            const canPreview = Boolean(
                                consolidation?.can_preview ||
                                    tasks[0]?.report?.can_preview,
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
                                        <td className="px-2.5 py-1 align-middle">
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
                                        <td className="max-w-[14rem] px-2.5 py-1 align-middle">
                                            <p className="truncate text-slate-800">
                                                {job.customer_name}
                                            </p>
                                            <p className="truncate text-xs text-muted-foreground">
                                                {jobMetaLine(job)}
                                            </p>
                                        </td>
                                        <td className="min-w-[8rem] px-2.5 py-1 align-middle">
                                            <p className="text-xs text-slate-600">
                                                {progress.done} /{' '}
                                                {progress.total} yours
                                            </p>
                                            <div className="mt-0.5 h-1.5 max-w-[7rem] overflow-hidden rounded-full bg-slate-100">
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
                                        <td className="px-2.5 py-1 align-middle">
                                            <AnalystStatusBadge
                                                status={aggregate.key}
                                                label={aggregate.label}
                                            />
                                        </td>
                                        <td className="px-2.5 py-1 align-middle">
                                            <div
                                                className="flex items-center justify-end gap-1"
                                                onClick={(event) =>
                                                    event.stopPropagation()
                                                }
                                            >
                                                {canSendToHead ? (
                                                    <Button
                                                        size="sm"
                                                        className="h-7 gap-1.5 bg-[#1A3694] hover:bg-[#365BB0]"
                                                        onClick={() =>
                                                            onSendToHead(job.id)
                                                        }
                                                    >
                                                        <Send
                                                            className="size-3.5 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        Send to Head
                                                    </Button>
                                                ) : (
                                                    primaryTask && (
                                                        <Button
                                                            size="sm"
                                                            className="h-7 gap-1.5 bg-[#1A3694] hover:bg-[#365BB0]"
                                                            onClick={() =>
                                                                onOpenTask(
                                                                    primaryTask,
                                                                )
                                                            }
                                                        >
                                                            <TaskActionContent
                                                                task={
                                                                    primaryTask
                                                                }
                                                            />
                                                        </Button>
                                                    )
                                                )}
                                                {canPreview && previewUrl && (
                                                    <Button
                                                        type="button"
                                                        size="sm"
                                                        variant="outline"
                                                        className="h-7 gap-1.5"
                                                        onClick={() =>
                                                            onPreview(
                                                                previewUrl,
                                                            )
                                                        }
                                                    >
                                                        <ScanSearch
                                                            className="size-3.5 shrink-0"
                                                            aria-hidden="true"
                                                        />
                                                        Preview report
                                                    </Button>
                                                )}
                                                <Button
                                                    type="button"
                                                    size="icon"
                                                    variant="ghost"
                                                    className="h-7 w-7"
                                                    aria-label="View Job Order details"
                                                    onClick={() =>
                                                        onOpenJobDetails(
                                                            job.id,
                                                        )
                                                    }
                                                >
                                                    <FileText className="size-4" />
                                                </Button>
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
                                                        const canWork =
                                                            task.can_work !==
                                                            undefined
                                                                ? task.can_work
                                                                : mine;
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
                                                                        canWork &&
                                                                        'bg-amber-50/70',
                                                                    !canWork &&
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
                                                                        {canWork &&
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
                                                                {canWork ? (
                                                                    <Button
                                                                        size="sm"
                                                                        className={cn(
                                                                            'h-8 shrink-0 gap-1.5',
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
                                                                        <TaskActionContent
                                                                            task={
                                                                                task
                                                                            }
                                                                        />
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
