import AnalystStatusBadge from '@/components/analyst/analyst-status-badge';
import AnalystWorkflowStepper from '@/components/analyst/analyst-workflow-stepper';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import type { AnalystTask, Consolidation, ReleasedPrint } from './types';
import {
    encodedResultLabel,
    jobAggregateStatus,
    jobMetaLine,
    jobProgress,
    taskActionLabel,
} from './types';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    job: AnalystTask['job_order'] | null;
    tasks: AnalystTask[];
    consolidation?: Consolidation;
    releasedPrint?: ReleasedPrint;
    onOpenTask: (task: AnalystTask) => void;
    onPreview: (url: string) => void;
    onSubmit: (jobId: number) => void;
};

export default function AnalystJobSheet({
    open,
    onOpenChange,
    job,
    tasks,
    consolidation,
    releasedPrint,
    onOpenTask,
    onPreview,
    onSubmit,
}: Props) {
    if (!job) {
        return null;
    }

    const progress = jobProgress(tasks);
    const aggregate = jobAggregateStatus(tasks);
    const returnedTasks = tasks.filter((t) => t.status === 'returned');
    const reviewNotes =
        job.review_notes || consolidation?.review_notes || null;

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="w-full gap-0 overflow-y-auto p-0 sm:max-w-[480px]"
            >
                <SheetHeader className="space-y-2 border-b px-4 pt-4 pb-3 pr-12 text-left">
                    <p className="text-xs text-muted-foreground">
                        Analyst / Job Orders / {job.reference_no}
                    </p>
                    <SheetTitle className="text-xl text-[#1A3694]">
                        {job.reference_no}
                    </SheetTitle>
                    <SheetDescription className="text-sm text-slate-700">
                        {job.customer_name}
                        {job.company_name ? ` · ${job.company_name}` : ''}
                    </SheetDescription>
                    <div className="flex flex-wrap items-center gap-2 pt-0.5">
                        <AnalystStatusBadge
                            status={aggregate.key}
                            label={aggregate.label}
                        />
                        <span className="text-xs text-muted-foreground">
                            {progress.done} / {progress.total} tests completed
                        </span>
                    </div>
                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                        <div
                            className={cn(
                                'h-full rounded-full',
                                progress.percent === 100
                                    ? 'bg-emerald-500'
                                    : 'bg-[#1A3694]',
                            )}
                            style={{ width: `${progress.percent}%` }}
                        />
                    </div>
                </SheetHeader>

                <div className="space-y-4 px-4 py-4">
                    <section>
                        <h3 className="mb-2 text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Workflow
                        </h3>
                        <AnalystWorkflowStepper job={job} />
                    </section>

                    <section className="rounded-lg border bg-[#f8fafc] px-3 py-2.5">
                        <h3 className="mb-2 text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Job information
                        </h3>
                        <dl className="grid grid-cols-2 gap-x-4 gap-y-2.5 text-sm">
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Classification
                                </dt>
                                <dd className="mt-0.5 font-medium text-slate-800">
                                    {job.classification || '—'}
                                </dd>
                            </div>
                            <div>
                                <dt className="text-xs text-muted-foreground">
                                    Storage
                                </dt>
                                <dd className="mt-0.5 font-medium text-slate-800">
                                    {job.sample_storage_temp || '—'}
                                </dd>
                            </div>
                            <div className="col-span-2">
                                <dt className="text-xs text-muted-foreground">
                                    Meta
                                </dt>
                                <dd className="mt-0.5 text-slate-800">
                                    {jobMetaLine(job)}
                                </dd>
                            </div>
                            {job.field_data && (
                                <div className="col-span-2">
                                    <dt className="text-xs text-muted-foreground">
                                        Field data
                                    </dt>
                                    <dd className="mt-0.5 text-slate-800">
                                        {job.field_data}
                                    </dd>
                                </div>
                            )}
                        </dl>
                    </section>

                    <section>
                        <h3 className="mb-1.5 text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Samples
                        </h3>
                        <ul className="space-y-1 rounded-lg border px-3 py-2 text-sm text-slate-700">
                            {job.samples.map((sample, index) => (
                                <li key={index}>
                                    {sample.sample_code
                                        ? `${sample.sample_code} — `
                                        : ''}
                                    {sample.description ||
                                        `Sample ${index + 1}`}
                                    {sample.matrix
                                        ? ` (${sample.matrix})`
                                        : ''}
                                </li>
                            ))}
                            {job.samples.length === 0 && (
                                <li className="text-muted-foreground">—</li>
                            )}
                        </ul>
                    </section>

                    <section>
                        <h3 className="mb-2 text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Test results
                        </h3>

                        {returnedTasks.length > 0 && (
                            <div className="mb-2 rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                <p className="font-semibold">
                                    ↩ Result returned
                                </p>
                                <p className="mt-0.5 text-xs">
                                    {returnedTasks.map((t) => t.name).join(', ')}
                                </p>
                                {reviewNotes && (
                                    <p className="mt-1.5 text-xs whitespace-pre-wrap">
                                        <span className="font-medium">
                                            Reason:{' '}
                                        </span>
                                        {reviewNotes}
                                    </p>
                                )}
                            </div>
                        )}

                        <ul className="divide-y divide-slate-100 rounded-lg border">
                            {tasks.map((task) => {
                                const done = task.status === 'completed';

                                return (
                                    <li
                                        key={task.id}
                                        className={cn(
                                            'flex items-center justify-between gap-3 px-3 py-2.5',
                                            task.status === 'returned' &&
                                                'bg-amber-50/60',
                                        )}
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="truncate text-sm font-medium">
                                                {task.name}
                                            </p>
                                            <div className="mt-1 flex flex-wrap items-center gap-1.5">
                                                <AnalystStatusBadge
                                                    status={task.status}
                                                    label={task.status_label}
                                                />
                                                {task.category_label && (
                                                    <span className="text-xs text-muted-foreground">
                                                        {task.category_label}
                                                    </span>
                                                )}
                                                {task.result_value && (
                                                    <span className="truncate text-xs text-muted-foreground">
                                                        {encodedResultLabel(
                                                            task,
                                                        )}
                                                    </span>
                                                )}
                                            </div>
                                        </div>
                                        <Button
                                            size="sm"
                                            className={cn(
                                                'h-8 shrink-0',
                                                !done &&
                                                    'bg-[#1A3694] hover:bg-[#365BB0]',
                                            )}
                                            variant={
                                                done ? 'outline' : 'default'
                                            }
                                            onClick={() => onOpenTask(task)}
                                        >
                                            {taskActionLabel(task)}
                                        </Button>
                                    </li>
                                );
                            })}
                        </ul>
                    </section>

                    <section className="space-y-2 border-t pt-3">
                        <h3 className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Actions
                        </h3>
                        <div className="flex flex-wrap gap-2">
                            {consolidation && (
                                <>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        disabled={
                                            !consolidation.can_preview ||
                                            !consolidation.preview_url
                                        }
                                        onClick={() => {
                                            if (consolidation.preview_url) {
                                                onPreview(
                                                    consolidation.preview_url,
                                                );
                                            }
                                        }}
                                    >
                                        Preview result form
                                    </Button>
                                    <Button
                                        size="sm"
                                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                                        disabled={!consolidation.can_submit}
                                        onClick={() => onSubmit(job.id)}
                                    >
                                        Send to Head
                                    </Button>
                                </>
                            )}
                            {releasedPrint && (
                                <Button
                                    size="sm"
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    disabled={
                                        !releasedPrint.can_print ||
                                        !releasedPrint.print_url
                                    }
                                    onClick={() => {
                                        if (releasedPrint.print_url) {
                                            onPreview(releasedPrint.print_url);
                                        }
                                    }}
                                >
                                    Print result form
                                </Button>
                            )}
                            {!consolidation && !releasedPrint && (
                                <p className="text-xs text-muted-foreground">
                                    Complete tests here, then open when ready to
                                    send or print.
                                </p>
                            )}
                        </div>
                        {consolidation && !consolidation.can_submit && (
                            <div className="text-xs text-amber-800">
                                <p>
                                    Complete all required test results before
                                    sending this Job Order to Head.
                                </p>
                                {consolidation.missing.length > 0 && (
                                    <ul className="mt-1 list-inside list-disc">
                                        {consolidation.missing.map((name) => (
                                            <li key={name}>{name}</li>
                                        ))}
                                    </ul>
                                )}
                            </div>
                        )}
                    </section>
                </div>
            </SheetContent>
        </Sheet>
    );
}
