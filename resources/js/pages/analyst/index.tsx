import { Head, router, useForm, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import type { FormEvent } from 'react';
import InputError from '@/components/input-error';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import ResultReportPreview from '@/components/result-report-preview';
import SummaryStat from '@/components/summary-stat';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { LabQueueRealtime } from '@/hooks/use-lab-queue-realtime';
import { cn } from '@/lib/utils';

type SampleSummary = {
    sample_code?: string | null;
    description?: string | null;
    matrix?: string | null;
};

type ReportSummary = {
    kind: 'combined' | 'individual' | 'waiting' | 'unavailable';
    title: string;
    message?: string | null;
    can_preview: boolean;
    can_print?: boolean;
};

type Task = {
    id: number;
    name: string;
    analysis_type_id?: number | null;
    category_label?: string | null;
    status: string;
    status_label: string;
    result_value?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
    result_remarks?: string | null;
    result_mode?: 'value' | 'pass_fail';
    report: ReportSummary;
    job_order: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name?: string | null;
        classification?: string | null;
        sample_storage_temp?: string | null;
        field_data?: string | null;
        samples: SampleSummary[];
    };
};

type Consolidation = {
    id: number;
    reference_no: string;
    customer_name: string;
    can_submit: boolean;
    can_preview?: boolean;
    preview_url?: string | null;
    preview_message?: string | null;
    missing: string[];
    lines: Array<{
        id: number;
        name: string;
        assignee_name?: string | null;
        status: string;
        status_label: string;
        result_value?: string | null;
        completed: boolean;
    }>;
};

type ReleasedPrint = {
    id: number;
    reference_no: string;
    customer_name: string;
    can_print: boolean;
    print_url: string | null;
};

type Props = {
    tasks: Task[];
    consolidations?: Consolidation[];
    releasedPrints?: ReleasedPrint[];
    counts: {
        all: number;
        returned: number;
        in_progress: number;
        assigned: number;
        completed: number;
    };
    jobs: {
        from: number | null;
        to: number | null;
        total: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        q: string;
        status: string;
    };
};

function statusBadgeClass(status: string) {
    if (status === 'returned') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (status === 'in_progress') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }

    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    return 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]';
}

function encodedResultLabel(task: {
    result_value?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
}): string {
    return [task.result_value, task.result_measurement, task.result_unit]
        .map((part) => (part ?? '').trim())
        .filter(Boolean)
        .join(' ');
}

export default function AnalystIndex({
    tasks,
    consolidations = [],
    releasedPrints = [],
    counts,
    jobs,
    filters,
}: Props) {
    const { flash, errors } = usePage().props as {
        flash?: { success?: string };
        errors?: Record<string, string>;
    };
    const [query, setQuery] = useState(filters.q ?? '');
    const [active, setActive] = useState<Task | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const form = useForm({
        result_value: '',
        result_measurement: '',
        result_unit: '',
        result_remarks: '',
    });

    const pauseQueueReload = Boolean(active) || Boolean(previewUrl);

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const trimmed = query.trim();
        const activeQuery = filters.q ?? '';
        const id = window.setTimeout(() => {
            if (trimmed === activeQuery) {
                return;
            }

            applyFilters({ q: trimmed });
        }, 350);

        return () => window.clearTimeout(id);
    }, [query, filters.q, filters.status]);

    const groups = useMemo(() => {
        const map = new Map<
            number,
            { job: Task['job_order']; tasks: Task[] }
        >();

        tasks.forEach((task) => {
            const existing = map.get(task.job_order.id);

            if (existing) {
                existing.tasks.push(task);
            } else {
                map.set(task.job_order.id, {
                    job: task.job_order,
                    tasks: [task],
                });
            }
        });

        return Array.from(map.values());
    }, [tasks]);

    function applyFilters(next: { q?: string; status?: string }) {
        router.get(
            '/analyst',
            {
                q: next.q ?? query,
                status: next.status ?? filters.status,
            },
            {
                preserveState: true,
                replace: true,
            },
        );
    }

    function submitSearch() {
        applyFilters({ q: query.trim() });
    }

    function openTask(task: Task) {
        setActive(task);
        form.clearErrors();
        form.setData({
            result_value: task.result_value ?? '',
            result_measurement: task.result_measurement ?? '',
            result_unit: task.result_unit ?? '',
            result_remarks: task.result_remarks ?? '',
        });
    }

    function saveDraft() {
        if (!active) {
            return;
        }

        form.post(`/analyst/tasks/${active.id}/draft`, {
            preserveScroll: true,
            onSuccess: () => setActive(null),
        });
    }

    function completeTask(event: FormEvent) {
        event.preventDefault();

        if (!active) {
            return;
        }

        form.post(`/analyst/tasks/${active.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => setActive(null),
        });
    }

    const statusChips = [
        { id: '', label: 'All open', count: counts.all },
        { id: 'returned', label: 'Returned', count: counts.returned },
        {
            id: 'in_progress',
            label: 'In progress',
            count: counts.in_progress,
        },
        { id: 'assigned', label: 'Not started', count: counts.assigned },
        { id: 'completed', label: 'Completed', count: counts.completed },
    ] as const;

    return (
        <>
            <Head title="Analyst tasks" />
            <LabQueueRealtime
                role="analyst"
                only={[
                    'tasks',
                    'counts',
                    'jobs',
                    'consolidations',
                    'releasedPrints',
                ]}
                pause={pauseQueueReload}
            />
            <div className="flex flex-col gap-5 p-4">
                <WorkspaceHeader
                    title="Analyst workspace"
                    description="Encode assigned tests, then the designated analyst previews the result form and sends the job to Head after every result is complete. Updates live when the queue changes."
                    flash={flash?.success}
                    hint="Returned tasks should usually be handled first."
                />

                {errors?.job_order && (
                    <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {errors.job_order}
                    </p>
                )}

                {consolidations.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="font-medium text-[#1A3694]">
                            Send to Head
                        </h2>
                        {consolidations.map((job) => (
                            <section
                                key={job.id}
                                className="rounded-xl border bg-white p-4"
                            >
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <p className="font-semibold text-[#1A3694]">
                                            {job.reference_no}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {job.customer_name}
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            disabled={!job.can_preview || !job.preview_url}
                                            title={
                                                job.can_preview
                                                    ? 'Preview the filled result form'
                                                    : (job.preview_message ??
                                                      'Preview is available after every result is complete.')
                                            }
                                            onClick={() => {
                                                if (job.preview_url) {
                                                    setPreviewUrl(job.preview_url);
                                                }
                                            }}
                                        >
                                            Preview result form
                                        </Button>
                                        <Button
                                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                                            disabled={!job.can_submit}
                                            onClick={() =>
                                                router.post(
                                                    `/analyst/job-orders/${job.id}/submit-for-review`,
                                                    {},
                                                    { preserveScroll: true },
                                                )
                                            }
                                        >
                                            Send to Head for signature
                                        </Button>
                                    </div>
                                </div>
                                {!job.can_submit && (
                                    <p className="mt-2 text-sm text-amber-800">
                                        Waiting for encoded results
                                        {job.missing.length
                                            ? `: ${job.missing.join(', ')}`
                                            : '.'}
                                    </p>
                                )}
                                <ul className="mt-3 space-y-1 text-sm">
                                    {job.lines.map((line) => (
                                        <li
                                            key={line.id}
                                            className="flex flex-wrap justify-between gap-2 border-t pt-1"
                                        >
                                            <span>
                                                {line.name}
                                                {line.assignee_name
                                                    ? ` · ${line.assignee_name}`
                                                    : ''}
                                            </span>
                                            <span
                                                className={
                                                    line.completed
                                                        ? 'text-emerald-700'
                                                        : 'text-amber-800'
                                                }
                                            >
                                                {line.completed
                                                    ? line.result_value ||
                                                      line.status_label
                                                    : line.status_label}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </section>
                        ))}
                    </div>
                )}

                {releasedPrints.length > 0 && (
                    <div className="space-y-3">
                        <h2 className="font-medium text-[#1A3694]">
                            Print result form
                        </h2>
                        <p className="text-sm text-muted-foreground">
                            Head has released these results. Print the dated
                            form, wet-sign it, and give it to Head with the
                            customer packet.
                        </p>
                        {releasedPrints.map((job) => (
                            <section
                                key={job.id}
                                className="flex flex-wrap items-center justify-between gap-3 rounded-xl border bg-white p-4"
                            >
                                <div>
                                    <p className="font-semibold text-[#1A3694]">
                                        {job.reference_no}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {job.customer_name}
                                    </p>
                                </div>
                                <Button
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    disabled={!job.can_print || !job.print_url}
                                    onClick={() => {
                                        if (job.print_url) {
                                            setPreviewUrl(job.print_url);
                                        }
                                    }}
                                >
                                    Print result form
                                </Button>
                            </section>
                        ))}
                    </div>
                )}

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <SummaryStat label="Open tasks" value={counts.all} />
                    <SummaryStat
                        label="Returned by head"
                        value={counts.returned}
                        tone="warning"
                    />
                    <SummaryStat
                        label="In progress"
                        value={counts.in_progress}
                        tone="info"
                    />
                    <SummaryStat
                        label="Not started"
                        value={counts.assigned}
                    />
                    <SummaryStat
                        label="Completed"
                        value={counts.completed}
                        tone="success"
                    />
                </div>

                <QueueFilterBar
                    chips={statusChips}
                    activeId={filters.status || ''}
                    onChip={(id) =>
                        applyFilters({ status: id, q: query.trim() })
                    }
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={submitSearch}
                    onClear={() => {
                        setQuery('');
                        applyFilters({ q: '' });
                    }}
                    placeholder="Search reference, customer, test…"
                />

                <QueueRangeNote
                    from={jobs.from}
                    to={jobs.to}
                    total={jobs.total}
                    suffix={`job orders with ${tasks.length} task${tasks.length === 1 ? '' : 's'} on this page.`}
                />

                <div className="space-y-4">
                    {groups.map(({ job, tasks: jobTasks }) => (
                        <section
                            key={job.id}
                            className="overflow-hidden rounded-xl border bg-white"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2 border-b bg-[#f8fafc] px-4 py-3">
                                <div>
                                    <p className="font-semibold text-[#1A3694]">
                                        {job.reference_no}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {job.customer_name}
                                        {job.company_name
                                            ? ` · ${job.company_name}`
                                            : ''}
                                        {job.classification
                                            ? ` · ${job.classification}`
                                            : ''}
                                    </p>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        {(job.samples?.length ?? 0)} sample
                                        {(job.samples?.length ?? 0) === 1
                                            ? ''
                                            : 's'}
                                        {job.sample_storage_temp
                                            ? ` · Storage ${job.sample_storage_temp}`
                                            : ''}
                                        {job.field_data
                                            ? ` · ${job.field_data}`
                                            : ''}
                                    </p>
                                </div>
                                <Badge
                                    variant="outline"
                                    className="border-[#c5d4f0] bg-white text-[#1A3694]"
                                >
                                    {jobTasks.length}{' '}
                                    {filters.status === 'completed'
                                        ? 'completed'
                                        : 'open'}
                                </Badge>
                                {jobTasks[0]?.report &&
                                    jobTasks[0].report.kind !==
                                        'individual' && (
                                    <div className="w-full sm:ml-auto sm:w-auto">
                                        {jobTasks[0].report.can_preview ? (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() =>
                                                    setPreviewUrl(
                                                        `/analyst/tasks/${jobTasks[0].id}/report`,
                                                    )
                                                }
                                            >
                                                Preview report
                                            </Button>
                                        ) : (
                                            <p className="max-w-md text-xs text-amber-800">
                                                {jobTasks[0].report.message}
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>

                            <div className="overflow-x-auto">
                                <table className="w-full text-sm">
                                    <thead className="bg-white text-left">
                                        <tr className="border-b">
                                            <th className="px-4 py-2.5 font-medium text-slate-600">
                                                Analysis
                                            </th>
                                            <th className="px-4 py-2.5 font-medium text-slate-600">
                                                Status
                                            </th>
                                            <th className="px-4 py-2.5 font-medium text-slate-600">
                                                Draft
                                            </th>
                                            <th className="px-4 py-2.5" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        {jobTasks.map((task) => (
                                            <tr
                                                key={task.id}
                                                className={
                                                    task.status === 'returned'
                                                        ? 'border-t bg-amber-50/40 transition hover:bg-amber-50/70'
                                                        : 'border-t transition hover:bg-[#f8fafc]'
                                                }
                                            >
                                                <td className="px-4 py-3">
                                                    <p className="font-medium">
                                                        {task.name}
                                                    </p>
                                                    {task.category_label && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {
                                                                task.category_label
                                                            }
                                                        </p>
                                                    )}
                                                </td>
                                                <td className="px-4 py-3">
                                                    <Badge
                                                        variant="outline"
                                                        className={statusBadgeClass(
                                                            task.status,
                                                        )}
                                                    >
                                                        {task.status_label}
                                                    </Badge>
                                                </td>
                                                <td className="px-4 py-3 text-muted-foreground">
                                                    {task.result_value ? (
                                                        <span>
                                                            {encodedResultLabel(
                                                                task,
                                                            )}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        {task.report?.kind ===
                                                            'individual' && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setPreviewUrl(
                                                                        `/analyst/tasks/${task.id}/report`,
                                                                    )
                                                                }
                                                            >
                                                                Preview report
                                                            </Button>
                                                        )}
                                                        {task.status !==
                                                            'completed' && (
                                                            <Button
                                                                size="sm"
                                                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                                                                onClick={() =>
                                                                    openTask(
                                                                        task,
                                                                    )
                                                                }
                                                            >
                                                                {task.result_value
                                                                    ? 'Continue'
                                                                    : 'Enter result'}
                                                            </Button>
                                                        )}
                                                    </div>
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    ))}

                    {groups.length === 0 && (
                        <div className="rounded-xl border bg-white px-4 py-12 text-center text-muted-foreground">
                            {filters.q || filters.status
                                ? 'No tasks match your filters.'
                                : 'No open tasks right now. Completed results are under the Completed filter.'}
                        </div>
                    )}

                    <TablePagination
                        links={jobs.links}
                        from={jobs.from}
                        to={jobs.to}
                        total={jobs.total}
                        label="job orders"
                    />
                </div>
            </div>

            <Dialog
                open={!!active}
                onOpenChange={(open) => !open && setActive(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>
                            {active?.name}
                        </DialogTitle>
                        <DialogDescription>
                            {active?.job_order.reference_no} ·{' '}
                            {active?.job_order.customer_name}
                            {active?.category_label
                                ? ` · ${active.category_label}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    {active && (
                        <div className="grid gap-3 md:grid-cols-[1.1fr_0.9fr]">
                            <div className="rounded-lg border bg-[#f8fafc] px-3 py-2 text-sm">
                                <p>
                                    <span className="text-muted-foreground">
                                        Classification:{' '}
                                    </span>
                                    {active.job_order.classification || '—'}
                                </p>
                                <p>
                                    <span className="text-muted-foreground">
                                        Storage temp:{' '}
                                    </span>
                                    {active.job_order.sample_storage_temp || '—'}
                                </p>
                                {active.job_order.field_data && (
                                    <p>
                                        <span className="text-muted-foreground">
                                            Field data:{' '}
                                        </span>
                                        {active.job_order.field_data}
                                    </p>
                                )}
                                <div className="mt-2 border-t border-slate-200 pt-2">
                                    <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                        Samples
                                    </p>
                                    <ul className="mt-1 space-y-1 text-xs text-slate-700">
                                        {active.job_order.samples.map(
                                            (sample, index) => (
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
                                            ),
                                        )}
                                        {active.job_order.samples.length ===
                                            0 && <li>—</li>}
                                    </ul>
                                </div>
                            </div>
                            <div className="rounded-lg border border-[#1A3694]/10 bg-white px-3 py-2 text-sm">
                                <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                    Editing guidance
                                </p>
                                <ul className="mt-2 space-y-2 text-xs text-muted-foreground">
                                    <li>
                                        Save draft if the result is still in
                                        progress or waiting for confirmation.
                                    </li>
                                    <li>
                                        Use Save &amp; complete only when the
                                        value is final and ready for release.
                                    </li>
                                    <li>
                                        If this task was returned, check review
                                        notes before completing it again.
                                    </li>
                                </ul>
                                <p className="mt-3 rounded-md border border-slate-200 bg-[#f8fafc] px-2.5 py-2 text-xs text-slate-600">
                                    {form.isDirty
                                        ? 'You have unsaved result changes.'
                                        : 'No unsaved changes yet.'}
                                </p>
                            </div>
                        </div>
                    )}

                    <form className="space-y-3" onSubmit={completeTask}>
                        {(errors?.analysis ||
                            (
                                form.errors as typeof form.errors & {
                                    analysis?: string;
                                }
                            ).analysis) && (
                            <InputError
                                message={
                                    errors?.analysis ||
                                    (
                                        form.errors as typeof form.errors & {
                                            analysis?: string;
                                        }
                                    ).analysis
                                }
                            />
                        )}
                        <div>
                            {active?.result_mode === 'pass_fail' ? (
                                <>
                                    <Label>Result *</Label>
                                    <div className="mt-2 grid grid-cols-2 gap-2">
                                        {(['Passed', 'Failed'] as const).map(
                                            (option) => {
                                                const selected =
                                                    form.data.result_value ===
                                                    option;

                                                return (
                                                    <button
                                                        key={option}
                                                        type="button"
                                                        className={cn(
                                                            'min-h-11 rounded-lg border px-3 text-sm font-semibold transition',
                                                            selected &&
                                                                option ===
                                                                    'Passed'
                                                                ? 'border-emerald-600 bg-emerald-50 text-emerald-900'
                                                                : selected
                                                                  ? 'border-red-600 bg-red-50 text-red-900'
                                                                  : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                                        )}
                                                        onClick={() =>
                                                            form.setData(
                                                                'result_value',
                                                                option,
                                                            )
                                                        }
                                                    >
                                                        {option}
                                                    </button>
                                                );
                                            },
                                        )}
                                    </div>
                                    <p className="mt-2 text-xs text-muted-foreground">
                                        The official PDF prints Passed or Failed
                                        only. You may still record the measured
                                        value below for the lab file.
                                    </p>
                                </>
                            ) : (
                                <>
                                    <Label htmlFor="result_value">
                                        Result value *
                                    </Label>
                                    <Input
                                        id="result_value"
                                        className="mt-1"
                                        required
                                        autoFocus
                                        value={form.data.result_value}
                                        onChange={(e) =>
                                            form.setData(
                                                'result_value',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </>
                            )}
                            <InputError
                                className="mt-1"
                                message={form.errors.result_value}
                            />
                        </div>
                        {active?.result_mode === 'pass_fail' && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="result_measurement">
                                        Measured value (optional)
                                    </Label>
                                    <Input
                                        id="result_measurement"
                                        className="mt-1"
                                        placeholder="e.g. &lt;1.8"
                                        value={form.data.result_measurement}
                                        onChange={(e) =>
                                            form.setData(
                                                'result_measurement',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="result_unit">Unit</Label>
                                    <Input
                                        id="result_unit"
                                        className="mt-1"
                                        placeholder="MPN/100ml"
                                        value={form.data.result_unit}
                                        onChange={(e) =>
                                            form.setData(
                                                'result_unit',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                        )}
                        {active?.result_mode !== 'pass_fail' && (
                            <div>
                                <Label htmlFor="result_unit">Unit</Label>
                                <Input
                                    id="result_unit"
                                    className="mt-1"
                                    placeholder="e.g. mg/L, %, CFU/100mL"
                                    value={form.data.result_unit}
                                    onChange={(e) =>
                                        form.setData(
                                            'result_unit',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                        )}
                        <div>
                            <Label htmlFor="result_remarks">Remarks</Label>
                            <Textarea
                                id="result_remarks"
                                className="mt-1"
                                placeholder="Method notes, detection limits, observations…"
                                value={form.data.result_remarks}
                                onChange={(e) =>
                                    form.setData(
                                        'result_remarks',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>
                        <DialogFooter className="gap-2 sm:justify-between">
                            <div className="text-xs text-muted-foreground">
                                {form.processing
                                    ? 'Saving result…'
                                    : form.isDirty
                                      ? 'Unsaved changes'
                                      : 'Saved values loaded'}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => setActive(null)}
                                    disabled={form.processing}
                                >
                                    Cancel
                                </Button>
                                {active && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() =>
                                            setPreviewUrl(
                                                `/analyst/tasks/${active.id}/report`,
                                            )
                                        }
                                    >
                                        Preview report
                                    </Button>
                                )}
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={form.processing}
                                    onClick={saveDraft}
                                >
                                    Save draft
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Save & complete'}
                                </Button>
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ResultReportPreview
                open={Boolean(previewUrl)}
                onOpenChange={(open) => {
                    if (!open) {
                        setPreviewUrl(null);
                    }
                }}
                reportUrl={previewUrl}
            />
        </>
    );
}

AnalystIndex.layout = {
    breadcrumbs: [{ title: 'Analyst', href: '/analyst' }],
};
