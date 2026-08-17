import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import InputError from '@/components/input-error';
import TablePagination from '@/components/table-pagination';
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
import { cn } from '@/lib/utils';

type SampleSummary = {
    sample_code?: string | null;
    description?: string | null;
    matrix?: string | null;
};

type Task = {
    id: number;
    name: string;
    category_label?: string | null;
    status: string;
    status_label: string;
    result_value?: string | null;
    result_unit?: string | null;
    result_remarks?: string | null;
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

type Props = {
    tasks: Task[];
    counts: {
        all: number;
        returned: number;
        in_progress: number;
        assigned: number;
        completed: number;
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

export default function AnalystIndex({ tasks, counts, filters }: Props) {
    const { flash, errors } = usePage().props as {
        flash?: { success?: string };
        errors?: Record<string, string>;
    };
    const [query, setQuery] = useState(filters.q ?? '');
    const [active, setActive] = useState<Task | null>(null);
    const [page, setPage] = useState(1);
    const pageSize = 10;
    const form = useForm({
        result_value: '',
        result_unit: '',
        result_remarks: '',
    });

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        setPage(1);
    }, [filters.q, filters.status, tasks.length]);

    useEffect(() => {
        const id = window.setInterval(() => {
            router.reload({ only: ['tasks', 'counts'] });
        }, 15000);

        return () => window.clearInterval(id);
    }, []);

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

    const totalPages = Math.max(1, Math.ceil(groups.length / pageSize));

    useEffect(() => {
        if (page > totalPages) {
            setPage(totalPages);
        }
    }, [page, totalPages]);

    const pageGroups = useMemo(() => {
        const start = (page - 1) * pageSize;

        return groups.slice(start, start + pageSize);
    }, [groups, page, pageSize]);

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

    function submitSearch(event: FormEvent) {
        event.preventDefault();
        applyFilters({ q: query.trim() });
    }

    function openTask(task: Task) {
        setActive(task);
        form.clearErrors();
        form.setData({
            result_value: task.result_value ?? '',
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
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Analyst workspace
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Enter results for assigned parameters after Receiving
                        has finalized pricing and marked the job received. Save
                        a draft anytime, then mark complete when finished.
                        Auto-refreshes every 15 seconds.
                    </p>
                    {flash?.success && (
                        <p className="mt-2 text-sm text-emerald-700">
                            {flash.success}
                        </p>
                    )}
                </div>

                <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Open tasks</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.all}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-amber-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Returned by head
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-amber-800">
                            {counts.returned}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-sky-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            In progress
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-sky-800">
                            {counts.in_progress}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Not started
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {counts.assigned}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Completed
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {counts.completed}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
                    <div className="flex flex-wrap gap-2">
                        {statusChips.map((chip) => {
                            const activeChip =
                                (filters.status || '') === chip.id;

                            return (
                                <button
                                    key={chip.id || 'all'}
                                    type="button"
                                    onClick={() =>
                                        applyFilters({
                                            status: chip.id,
                                            q: query.trim(),
                                        })
                                    }
                                    className={cn(
                                        'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                        activeChip
                                            ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                            : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                    )}
                                >
                                    {chip.label}
                                    <span
                                        className={cn(
                                            'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                            activeChip
                                                ? 'bg-white/20'
                                                : 'bg-slate-100 text-slate-600',
                                        )}
                                    >
                                        {chip.count}
                                    </span>
                                </button>
                            );
                        })}
                    </div>

                    <form
                        onSubmit={submitSearch}
                        className="flex w-full max-w-md items-center gap-2"
                    >
                        <div className="relative flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search reference, customer, test…"
                                className="h-10 pl-9"
                            />
                        </div>
                        <Button
                            type="submit"
                            variant="outline"
                            className="h-10"
                        >
                            Search
                        </Button>
                    </form>
                </div>

                <div className="space-y-4">
                    {pageGroups.map(({ job, tasks: jobTasks }) => (
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
                                                className="border-t transition hover:bg-[#f8fafc]"
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
                                                            {task.result_value}
                                                            {task.result_unit
                                                                ? ` ${task.result_unit}`
                                                                : ''}
                                                        </span>
                                                    ) : (
                                                        '—'
                                                    )}
                                                </td>
                                                <td className="px-4 py-3 text-right">
                                                    <div className="flex justify-end gap-2">
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            asChild
                                                        >
                                                            <a
                                                                href={`/analyst/tasks/${task.id}/pdf`}
                                                            >
                                                                Download PDF
                                                            </a>
                                                        </Button>
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
                        mode="client"
                        page={page}
                        totalPages={totalPages}
                        totalItems={groups.length}
                        pageSize={pageSize}
                        onPageChange={setPage}
                        label="job orders"
                    />
                </div>
            </div>

            <Dialog
                open={!!active}
                onOpenChange={(open) => !open && setActive(null)}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
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
                                    {active.job_order.samples.length === 0 && (
                                        <li>—</li>
                                    )}
                                </ul>
                            </div>
                        </div>
                    )}

                    <form className="space-y-3" onSubmit={completeTask}>
                        {(errors?.analysis || form.errors.analysis) && (
                            <InputError
                                message={
                                    errors?.analysis || form.errors.analysis
                                }
                            />
                        )}
                        <div>
                            <Label htmlFor="result_value">Result value *</Label>
                            <Input
                                id="result_value"
                                className="mt-1"
                                required
                                autoFocus
                                value={form.data.result_value}
                                onChange={(e) =>
                                    form.setData('result_value', e.target.value)
                                }
                            />
                            <InputError
                                className="mt-1"
                                message={form.errors.result_value}
                            />
                        </div>
                        <div>
                            <Label htmlFor="result_unit">Unit</Label>
                            <Input
                                id="result_unit"
                                className="mt-1"
                                placeholder="e.g. mg/L, %, CFU/100mL"
                                value={form.data.result_unit}
                                onChange={(e) =>
                                    form.setData('result_unit', e.target.value)
                                }
                            />
                        </div>
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
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setActive(null)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <div className="flex flex-wrap gap-2">
                                {active && (
                                    <Button
                                        type="button"
                                        variant="outline"
                                        asChild
                                    >
                                        <a
                                            href={`/analyst/tasks/${active.id}/pdf`}
                                        >
                                            Download PDF
                                        </a>
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
        </>
    );
}

AnalystIndex.layout = {
    breadcrumbs: [{ title: 'Analyst', href: '/analyst' }],
};
