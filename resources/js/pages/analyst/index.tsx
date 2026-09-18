import AnalystJobSheet from '@/components/analyst/analyst-job-sheet';
import LimsWorkspace from '@/components/lims/lims-workspace';
import AnalystSummaryCards from '@/components/analyst/analyst-summary-cards';
import AnalystWorkQueueTable from '@/components/analyst/analyst-work-queue-table';
import type {
    AnalystTask,
    Consolidation,
    ReleasedPrint,
} from '@/components/analyst/types';
import { encodedResultLabel, canWorkTask } from '@/components/analyst/types';
import ConfirmDialog from '@/components/confirm-dialog';
import InputError from '@/components/input-error';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import QueueFilterBar from '@/components/queue-filter-bar';
import QueueRangeNote from '@/components/queue-range-note';
import ResultReportPreview from '@/components/result-report-preview';
import ResultSignatoryPanel, {
    type ResultSignatoryConfig,
    type ResultSignatoryRow,
} from '@/components/result-signatory-dialog';
import TablePagination from '@/components/table-pagination';
import WorkspaceHeader from '@/components/workspace-header';
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
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { cn } from '@/lib/utils';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Check, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

type Props = {
    tasks: AnalystTask[];
    consolidations?: Consolidation[];
    releasedPrints?: ReleasedPrint[];
    counts: {
        all: number;
        needs_action?: number;
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
        sort?: string;
    };
};

function emptyMessage(filters: Props['filters']): {
    title: string;
    body: string;
} {
    if (filters.q) {
        return {
            title: 'No matching laboratory tests found',
            body: 'Try searching by Job Order, customer, sample, or test.',
        };
    }

    if (filters.status === 'returned') {
        return {
            title: 'No returned results',
            body: 'Head has not sent any tests back for correction.',
        };
    }

    if (filters.status === 'completed') {
        return {
            title: 'No completed tests yet',
            body: 'Completed results for your assignments will appear here.',
        };
    }

    if (filters.status === 'in_progress') {
        return {
            title: 'Nothing in progress',
            body: 'Drafted or started tests will show up in this filter.',
        };
    }

    return {
        title: "You're all caught up",
        body: 'There are no laboratory tests requiring your attention.',
    };
}

export default function AnalystIndex({
    tasks,
    consolidations = [],
    releasedPrints = [],
    counts,
    jobs,
    filters,
}: Props) {
    const { errors } = usePage().props as {
        errors?: Record<string, string>;
    };
    const [query, setQuery] = useState(filters.q ?? '');
    const [active, setActive] = useState<AnalystTask | null>(null);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [sheetJobId, setSheetJobId] = useState<number | null>(null);
    const [expandedIds, setExpandedIds] = useState<Set<number>>(new Set());
    const [confirmComplete, setConfirmComplete] = useState(false);
    const [discardConfirm, setDiscardConfirm] = useState(false);
    const [savedJustNow, setSavedJustNow] = useState(false);
    const [submitSignatoryJobId, setSubmitSignatoryJobId] = useState<number | null>(
        null,
    );
    const [submitSignatoryError, setSubmitSignatoryError] = useState<string | null>(
        null,
    );
    const [submitSignatorySaving, setSubmitSignatorySaving] = useState(false);
    const [editSignatoryJobId, setEditSignatoryJobId] = useState<number | null>(
        null,
    );
    const [editSignatoryError, setEditSignatoryError] = useState<string | null>(
        null,
    );
    const [editSignatorySaving, setEditSignatorySaving] = useState(false);
    const { isRefreshing } = useInertiaNavigation();
    const form = useForm({
        result_value: '',
        result_pass_fail: '',
        result_measurement: '',
        result_unit: '',
        result_remarks: '',
        result_method: '',
    });

    const groups = useMemo(() => {
        const map = new Map<
            number,
            { job: AnalystTask['job_order']; tasks: AnalystTask[] }
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

    const sheetGroup = useMemo(
        () => groups.find((g) => g.job.id === sheetJobId) ?? null,
        [groups, sheetJobId],
    );
    const sheetOpen = sheetJobId !== null && Boolean(sheetGroup);
    const editableJobStatuses = [
        'in_analysis',
        'pending_review',
        'ready_for_pickup',
    ];
    const jobAllowsEdit = editableJobStatuses.includes(
        active?.job_order.status ?? '',
    );
    const readOnly =
        !active || !canWorkTask(active) || !jobAllowsEdit;
    const isCompletedLine = active?.status === 'completed';
    const pauseQueueReload =
        Boolean(active) ||
        Boolean(previewUrl) ||
        sheetOpen ||
        confirmComplete ||
        discardConfirm;

    useEffect(() => {
        if (sheetJobId !== null && !sheetGroup) {
            setSheetJobId(null);
        }
    }, [sheetJobId, sheetGroup]);

    useEffect(() => {
        setQuery(filters.q ?? '');
    }, [filters.q]);

    useEffect(() => {
        const params = new URLSearchParams(window.location.search);
        const jobParam = params.get('job');
        if (!jobParam) {
            return;
        }

        const id = Number(jobParam);
        if (!Number.isFinite(id) || id <= 0) {
            return;
        }

        setSheetJobId(id);
    }, []);

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
    }, [query, filters.q, filters.status, filters.sort]);

    const consolidationsById = useMemo(() => {
        const map = new Map<number, Consolidation>();
        consolidations.forEach((item) => map.set(item.id, item));
        return map;
    }, [consolidations]);

    const releasedById = useMemo(() => {
        const map = new Map<number, ReleasedPrint>();
        releasedPrints.forEach((item) => map.set(item.id, item));
        return map;
    }, [releasedPrints]);

    function applyFilters(next: {
        q?: string;
        status?: string;
        sort?: string;
    }) {
        router.get(
            '/analyst',
            {
                q: next.q ?? query,
                status: next.status ?? filters.status,
                sort: next.sort ?? filters.sort ?? 'needs_action',
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

    function openTask(task: AnalystTask) {
        if (task.can_work === false) {
            return;
        }

        if (task.can_work === undefined && task.is_mine === false) {
            return;
        }

        setActive(task);
        setSavedJustNow(false);
        setConfirmComplete(false);
        setDiscardConfirm(false);
        form.clearErrors();
        form.setData({
            result_value: task.result_value ?? '',
            result_pass_fail: task.result_pass_fail ?? '',
            result_measurement: task.result_measurement ?? '',
            result_unit: task.result_unit ?? '',
            result_remarks: task.result_remarks ?? '',
            result_method:
                task.result_method ?? task.procedure_method ?? '',
        });
    }

    function toggleExpand(jobId: number) {
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

    function closeTaskModal() {
        setActive(null);
        setConfirmComplete(false);
        setDiscardConfirm(false);
        setSavedJustNow(false);
    }

    function requestCloseTaskModal() {
        if (readOnly || !form.isDirty) {
            closeTaskModal();
            return;
        }

        setDiscardConfirm(true);
    }

    function saveDraft() {
        if (!active || readOnly) {
            return;
        }

        form.post(`/analyst/tasks/${active.id}/draft`, {
            preserveScroll: true,
            onSuccess: () => {
                setSavedJustNow(true);
                form.setDefaults({
                    result_value: form.data.result_value,
                    result_pass_fail: form.data.result_pass_fail,
                    result_measurement: form.data.result_measurement,
                    result_unit: form.data.result_unit,
                    result_remarks: form.data.result_remarks,
                    result_method: form.data.result_method,
                });
            },
        });
    }

    function requestComplete(event: FormEvent) {
        event.preventDefault();

        if (!active || readOnly) {
            return;
        }

        setConfirmComplete(true);
    }

    function confirmCompleteTask() {
        if (!active || readOnly) {
            return;
        }

        form.post(`/analyst/tasks/${active.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmComplete(false);
                closeTaskModal();
            },
        });
    }

    function submitJob(jobId: number) {
        const consolidation = consolidationsById.get(jobId);
        if (consolidation?.signatory) {
            setSubmitSignatoryError(null);
            setSubmitSignatoryJobId(jobId);

            return;
        }

        router.post(
            `/analyst/job-orders/${jobId}/submit-for-review`,
            {},
            { preserveScroll: true },
        );
    }

    function confirmSubmitWithSignatories(signatories: ResultSignatoryRow[]) {
        if (!submitSignatoryJobId) {
            return;
        }

        setSubmitSignatorySaving(true);
        setSubmitSignatoryError(null);

        router.post(
            `/analyst/job-orders/${submitSignatoryJobId}/submit-for-review`,
            { signatories },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSubmitSignatoryJobId(null);
                    setSheetJobId(null);
                },
                onError: (formErrors) => {
                    const first =
                        formErrors.signatories ||
                        formErrors['signatories.0.name'] ||
                        formErrors['signatories.0.prc_id'] ||
                        formErrors.job_order ||
                        Object.values(formErrors)[0];
                    setSubmitSignatoryError(
                        typeof first === 'string'
                            ? first
                            : 'Could not send to Head. Check analyst name and PRC.',
                    );
                },
                onFinish: () => setSubmitSignatorySaving(false),
            },
        );
    }

    const submitSignatoryConfig: ResultSignatoryConfig | null = useMemo(() => {
        if (!submitSignatoryJobId) {
            return null;
        }

        return consolidationsById.get(submitSignatoryJobId)?.signatory ?? null;
    }, [submitSignatoryJobId, consolidationsById]);

    const editSignatoryConfig: ResultSignatoryConfig | null = useMemo(() => {
        if (!editSignatoryJobId) {
            return null;
        }

        return releasedById.get(editSignatoryJobId)?.signatory ?? null;
    }, [editSignatoryJobId, releasedById]);

    function openEditSignatories(jobId: number) {
        setEditSignatoryError(null);
        setEditSignatoryJobId(jobId);
    }

    async function confirmEditSignatories(signatories: ResultSignatoryRow[]) {
        const config = editSignatoryConfig;
        if (!editSignatoryJobId || !config?.save_url) {
            return;
        }

        setEditSignatorySaving(true);
        setEditSignatoryError(null);

        try {
            const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
            const token = match ? decodeURIComponent(match[1]) : null;
            const response = await fetch(config.save_url, {
                method: 'PUT',
                credentials: 'same-origin',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    ...(token ? { 'X-XSRF-TOKEN': token } : {}),
                },
                body: JSON.stringify({ signatories }),
            });

            const payload = (await response.json().catch(() => null)) as {
                message?: string;
                errors?: Record<string, string[]>;
            } | null;

            if (!response.ok) {
                const firstError = payload?.errors
                    ? Object.values(payload.errors)[0]?.[0]
                    : null;
                setEditSignatoryError(
                    firstError ||
                        payload?.message ||
                        'Could not save printed analyst details.',
                );
                return;
            }

            setEditSignatoryJobId(null);
            router.reload({ only: ['releasedPrints'], preserveScroll: true });
        } catch {
            setEditSignatoryError('Could not save printed analyst details.');
        } finally {
            setEditSignatorySaving(false);
        }
    }

    function openJobSheet(jobId: number) {
        setSheetJobId(jobId);
    }

    function saveStateLabel(): string {
        if (form.processing) {
            return 'Saving…';
        }

        if (form.isDirty) {
            return '● Unsaved changes';
        }

        if (savedJustNow) {
            return '✓ Saved just now';
        }

        return '✓ Saved';
    }

    const needsAction =
        counts.needs_action ?? counts.assigned + counts.returned;

    const statusChips = [
        { id: '', label: 'All', count: counts.all },
        { id: 'needs_action', label: 'Needs action', count: needsAction },
        {
            id: 'in_progress',
            label: 'In progress',
            count: counts.in_progress,
        },
        { id: 'returned', label: 'Returned', count: counts.returned },
        { id: 'completed', label: 'Completed', count: counts.completed },
    ] as const;

    const summaryCards = [
        {
            id: 'needs_action',
            label: 'Needs action',
            value: needsAction,
            unit: 'Tests',
            tone: 'default' as const,
        },
        {
            id: 'in_progress',
            label: 'In progress',
            value: counts.in_progress,
            unit: 'Tests',
            tone: 'info' as const,
        },
        {
            id: 'returned',
            label: 'Returned',
            value: counts.returned,
            unit: 'Tests',
            tone: 'warning' as const,
        },
        {
            id: 'completed',
            label: 'Completed',
            value: counts.completed,
            unit: 'Tests',
            tone: 'success' as const,
        },
    ];

    const empty = emptyMessage(filters);
    const sort = filters.sort || 'needs_action';

    return (
        <>
            <Head title="Analyst workspace" />
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
            <NavigationLoadingShell variant="queue">
                <LimsWorkspace className="gap-3">
                    <WorkspaceHeader
                        title="Analyst workspace"
                        description="Laboratory results and assigned tests. Updates live when the queue changes."
                        refreshing={isRefreshing}
                        refreshLabel="Updating workspace…"
                    />

                {errors?.job_order && (
                    <p className="rounded-lg border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {errors.job_order}
                    </p>
                )}

                <AnalystSummaryCards
                    cards={summaryCards}
                    activeId={filters.status || ''}
                    onSelect={(id) =>
                        applyFilters({ status: id, q: query.trim() })
                    }
                />

                <div className="flex flex-col gap-3">
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
                        placeholder="Search job order, customer, sample, or test…"
                    />

                    <div className="flex flex-wrap items-center gap-2 text-sm">
                        <span className="text-muted-foreground">Sort</span>
                        {(
                            [
                                ['needs_action', 'Needs action first'],
                                ['newest', 'Newest'],
                                ['oldest', 'Oldest'],
                            ] as const
                        ).map(([id, label]) => (
                            <button
                                key={id}
                                type="button"
                                onClick={() => applyFilters({ sort: id })}
                                className={cn(
                                    'rounded-full border px-3 py-1 text-xs font-medium transition',
                                    sort === id
                                        ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                )}
                            >
                                {label}
                            </button>
                        ))}
                    </div>
                </div>

                <QueueRangeNote
                    from={jobs.from}
                    to={jobs.to}
                    total={jobs.total}
                    suffix={`${tasks.length} test${tasks.length === 1 ? '' : 's'} on this page (${groups.length} job order${groups.length === 1 ? '' : 's'}).`}
                />

                <div className="space-y-3">
                    <h2 className="text-sm font-semibold tracking-wide text-[#1A3694] uppercase">
                        Job order queue
                    </h2>

                    <AnalystWorkQueueTable
                        groups={groups}
                        expandedIds={expandedIds}
                        empty={empty}
                        consolidationsById={consolidationsById}
                        onToggleExpand={toggleExpand}
                        onOpenJobDetails={openJobSheet}
                        onOpenTask={openTask}
                        onPreview={setPreviewUrl}
                        onSendToHead={submitJob}
                    />

                    <TablePagination
                        links={jobs.links}
                        from={jobs.from}
                        to={jobs.to}
                        total={jobs.total}
                        label="job orders"
                    />
                </div>
                </LimsWorkspace>
            </NavigationLoadingShell>

            <AnalystJobSheet
                open={sheetOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        setSheetJobId(null);
                        const url = new URL(window.location.href);
                        if (url.searchParams.has('job')) {
                            url.searchParams.delete('job');
                            window.history.replaceState({}, '', url);
                        }
                    }
                }}
                job={sheetGroup?.job ?? null}
                tasks={sheetGroup?.tasks ?? []}
                consolidation={
                    sheetJobId
                        ? consolidationsById.get(sheetJobId)
                        : undefined
                }
                releasedPrint={
                    sheetJobId ? releasedById.get(sheetJobId) : undefined
                }
                onOpenTask={openTask}
                onPreview={setPreviewUrl}
                onSubmit={submitJob}
                onEditSignatories={openEditSignatories}
            />

            <Dialog
                open={!!active}
                onOpenChange={(open) => {
                    if (!open) {
                        requestCloseTaskModal();
                    }
                }}
            >
                <DialogContent
                    className="flex max-h-[90vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-2xl"
                    onEscapeKeyDown={(event) => {
                        if (!readOnly && form.isDirty) {
                            event.preventDefault();
                            setDiscardConfirm(true);
                        }
                    }}
                    onPointerDownOutside={(event) => {
                        if (!readOnly && form.isDirty) {
                            event.preventDefault();
                            setDiscardConfirm(true);
                        }
                    }}
                >
                    <DialogHeader className="shrink-0 space-y-1 border-b border-slate-200 px-6 pt-6 pb-4 pr-12 text-left">
                        <DialogTitle>{active?.name}</DialogTitle>
                        <DialogDescription>
                            Job Order {active?.job_order.reference_no}
                            {active?.job_order.customer_name
                                ? ` · ${active.job_order.customer_name}`
                                : ''}
                            {active?.category_label
                                ? ` · ${active.category_label}`
                                : ''}
                        </DialogDescription>
                    </DialogHeader>

                    <form
                        className="flex min-h-0 flex-1 flex-col overflow-hidden"
                        onSubmit={requestComplete}
                    >
                        <div className="nppc-scroll-sidebar min-h-0 flex-1 overflow-y-auto overscroll-contain px-6 py-4 pb-6">
                            {active && (
                                <div className="space-y-3">
                                    {active.status === 'returned' &&
                                        active.job_order.review_notes && (
                                            <div className="rounded-lg border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-950">
                                                <p className="font-semibold">
                                                    ↩ Result returned
                                                </p>
                                                <p className="mt-1 text-xs whitespace-pre-wrap">
                                                    {
                                                        active.job_order
                                                            .review_notes
                                                    }
                                                </p>
                                            </div>
                                        )}

                                    <div className="grid gap-2 text-sm sm:grid-cols-2">
                                        <p>
                                            <span className="text-muted-foreground">
                                                Classification:{' '}
                                            </span>
                                            {active.job_order.classification ||
                                                '—'}
                                        </p>
                                        <p>
                                            <span className="text-muted-foreground">
                                                Storage temp:{' '}
                                            </span>
                                            {active.job_order
                                                .sample_storage_temp || '—'}
                                        </p>
                                        {active.job_order.field_data && (
                                            <p className="sm:col-span-2">
                                                <span className="text-muted-foreground">
                                                    Field data:{' '}
                                                </span>
                                                {active.job_order.field_data}
                                            </p>
                                        )}
                                        <div className="sm:col-span-2">
                                            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                                Samples
                                            </p>
                                            <ul className="mt-1 space-y-0.5 text-xs text-slate-700">
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
                                                {active.job_order.samples
                                                    .length === 0 && <li>—</li>}
                                            </ul>
                                        </div>
                                    </div>

                                    {!readOnly && !isCompletedLine && (
                                        <p className="rounded-md border border-slate-200 bg-[#f8fafc] px-3 py-2 text-xs text-slate-600">
                                            <span className="font-semibold text-[#365BB0]">
                                                Result entry ·{' '}
                                            </span>
                                            Save Draft if still encoding. Use
                                            Save &amp; Complete only when
                                            verified and ready for the next
                                            stage.
                                        </p>
                                    )}

                                    {!readOnly && isCompletedLine && (
                                        <p className="rounded-md border border-sky-200 bg-sky-50 px-3 py-2 text-xs text-sky-950">
                                            This result is completed. You can
                                            still correct values — changes update
                                            the printable result form.
                                        </p>
                                    )}

                                    {readOnly && (
                                        <p className="rounded-md border border-slate-200 bg-slate-50 px-3 py-2 text-xs text-slate-700">
                                            You can view this result, but you
                                            cannot edit it on this job.
                                        </p>
                                    )}
                                </div>
                            )}

                            <div className="mt-3 space-y-3">
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
                            <Label htmlFor="result_value">Result *</Label>
                            <Input
                                id="result_value"
                                className="mt-1"
                                required={!readOnly}
                                autoFocus={!readOnly}
                                readOnly={readOnly}
                                disabled={readOnly}
                                placeholder="e.g. &lt;1.8, 10.2, ND"
                                value={form.data.result_value}
                                onChange={(e) => {
                                    setSavedJustNow(false);
                                    form.setData(
                                        'result_value',
                                        e.target.value,
                                    );
                                }}
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
                                placeholder="e.g. mg/L, %, MPN/100ml"
                                readOnly={readOnly}
                                disabled={readOnly}
                                value={form.data.result_unit}
                                onChange={(e) => {
                                    setSavedJustNow(false);
                                    form.setData(
                                        'result_unit',
                                        e.target.value,
                                    );
                                }}
                            />
                        </div>
                        {active?.requires_pass_fail !== false && (
                            <div>
                                <Label>Pass / Fail *</Label>
                                <div className="mt-2 grid grid-cols-2 gap-3">
                                    {(
                                        [
                                            {
                                                value: 'Passed',
                                                icon: Check,
                                            },
                                            {
                                                value: 'Failed',
                                                icon: X,
                                            },
                                        ] as const
                                    ).map((option) => {
                                        const selected =
                                            form.data.result_pass_fail ===
                                            option.value;
                                        const Icon = option.icon;

                                        return (
                                            <button
                                                key={option.value}
                                                type="button"
                                                disabled={readOnly}
                                                aria-pressed={selected}
                                                className={cn(
                                                    'flex min-h-24 flex-col items-center justify-center gap-1 rounded-xl border-2 px-3 text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#1A3694]/50',
                                                    selected
                                                        ? 'border-[#1A3694] bg-[#1A3694]/5 text-[#1A3694]'
                                                        : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                                    readOnly &&
                                                        'cursor-default opacity-90',
                                                )}
                                                onClick={() => {
                                                    if (readOnly) {
                                                        return;
                                                    }
                                                    setSavedJustNow(false);
                                                    form.setData(
                                                        'result_pass_fail',
                                                        option.value,
                                                    );
                                                }}
                                            >
                                                <Icon className="size-6" />
                                                {option.value}
                                            </button>
                                        );
                                    })}
                                </div>
                                <InputError
                                    className="mt-1"
                                    message={form.errors.result_pass_fail}
                                />
                            </div>
                        )}
                        <div>
                            <Label htmlFor="result_method">Method</Label>
                            <Textarea
                                id="result_method"
                                className="mt-1"
                                rows={2}
                                readOnly={readOnly}
                                disabled={readOnly}
                                placeholder="Defaults from Procedures; edit if needed"
                                value={form.data.result_method}
                                onChange={(e) => {
                                    setSavedJustNow(false);
                                    form.setData(
                                        'result_method',
                                        e.target.value,
                                    );
                                }}
                            />
                            {active?.procedure_method &&
                                form.data.result_method.trim() !==
                                    (active.procedure_method ?? '').trim() && (
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Procedure default:{' '}
                                        {active.procedure_method}
                                    </p>
                                )}
                            <InputError
                                className="mt-1"
                                message={form.errors.result_method}
                            />
                        </div>
                        <div>
                            <Label htmlFor="result_remarks">Remarks</Label>
                            <Textarea
                                id="result_remarks"
                                className="mt-1"
                                placeholder="Detection limits, observations…"
                                readOnly={readOnly}
                                disabled={readOnly}
                                value={form.data.result_remarks}
                                onChange={(e) => {
                                    setSavedJustNow(false);
                                    form.setData(
                                        'result_remarks',
                                        e.target.value,
                                    );
                                }}
                            />
                        </div>
                            </div>
                        </div>

                        <DialogFooter className="shrink-0 gap-2 border-t border-slate-200 bg-background px-6 py-4 sm:justify-between">
                            <div className="text-xs text-muted-foreground">
                                {saveStateLabel()}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={requestCloseTaskModal}
                                    disabled={form.processing}
                                >
                                    {readOnly ? 'Close' : 'Cancel'}
                                </Button>
                                {!readOnly && (
                                    <>
                                        {!isCompletedLine && (
                                            <Button
                                                type="button"
                                                variant="outline"
                                                disabled={form.processing}
                                                title="Saves the current result without marking it complete."
                                                onClick={saveDraft}
                                            >
                                                Save draft
                                            </Button>
                                        )}
                                        {active && (
                                            <Button
                                                type="button"
                                                variant="ghost"
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
                                            type="submit"
                                            disabled={form.processing}
                                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                                            title={
                                                isCompletedLine
                                                    ? 'Updates the completed result used on the printable form.'
                                                    : 'Finalizes this result and marks it ready for the next workflow stage.'
                                            }
                                        >
                                            {form.processing
                                                ? 'Saving…'
                                                : isCompletedLine
                                                  ? 'Save changes'
                                                  : 'Save & complete'}
                                        </Button>
                                    </>
                                )}
                                {readOnly && active && (
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
                            </div>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={confirmComplete}
                onOpenChange={setConfirmComplete}
                title={
                    isCompletedLine
                        ? 'Save result changes?'
                        : 'Complete this result?'
                }
                description={
                    isCompletedLine
                        ? 'This will update the completed result on the printable form.'
                        : 'This will mark the result as complete.'
                }
                confirmLabel={
                    isCompletedLine ? 'Save changes' : 'Complete result'
                }
                processingLabel="Saving…"
                processing={form.processing}
                onConfirm={confirmCompleteTask}
            >
                {active && (
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-muted-foreground">Test</dt>
                            <dd className="font-medium">{active.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Result</dt>
                            <dd className="font-medium">
                                {encodedResultLabel({
                                    result_value: form.data.result_value,
                                    result_pass_fail:
                                        active.requires_pass_fail !== false
                                            ? form.data.result_pass_fail
                                            : null,
                                    result_unit: form.data.result_unit,
                                }) || '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Method</dt>
                            <dd className="font-medium whitespace-pre-wrap">
                                {form.data.result_method.trim() || '—'}
                            </dd>
                        </div>
                    </dl>
                )}
            </ConfirmDialog>

            <ConfirmDialog
                open={discardConfirm}
                onOpenChange={setDiscardConfirm}
                title="You have unsaved changes. Discard them?"
                description="Encoded values that were not saved as draft will be lost."
                confirmLabel="Discard changes"
                cancelLabel="Keep editing"
                variant="destructive"
                onConfirm={closeTaskModal}
            />

            <Dialog
                open={Boolean(submitSignatoryJobId && submitSignatoryConfig)}
                onOpenChange={(open) => {
                    if (!open && !submitSignatorySaving) {
                        setSubmitSignatoryJobId(null);
                        setSubmitSignatoryError(null);
                    }
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    {submitSignatoryConfig && (
                        <ResultSignatoryPanel
                            config={submitSignatoryConfig}
                            submitting={submitSignatorySaving}
                            error={submitSignatoryError}
                            confirmLabel="Send to Head"
                            onCancel={() => {
                                if (!submitSignatorySaving) {
                                    setSubmitSignatoryJobId(null);
                                    setSubmitSignatoryError(null);
                                }
                            }}
                            onConfirm={confirmSubmitWithSignatories}
                        />
                    )}
                </DialogContent>
            </Dialog>

            <Dialog
                open={Boolean(editSignatoryJobId && editSignatoryConfig)}
                onOpenChange={(open) => {
                    if (!open && !editSignatorySaving) {
                        setEditSignatoryJobId(null);
                        setEditSignatoryError(null);
                    }
                }}
            >
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-md">
                    {editSignatoryConfig && (
                        <ResultSignatoryPanel
                            config={editSignatoryConfig}
                            submitting={editSignatorySaving}
                            error={editSignatoryError}
                            confirmLabel="Save printed analyst"
                            onCancel={() => {
                                if (!editSignatorySaving) {
                                    setEditSignatoryJobId(null);
                                    setEditSignatoryError(null);
                                }
                            }}
                            onConfirm={confirmEditSignatories}
                        />
                    )}
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
