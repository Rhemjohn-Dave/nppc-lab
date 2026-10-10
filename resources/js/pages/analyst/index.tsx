import AnalystEncodingStudio from '@/components/analyst/analyst-encoding-studio';
import AnalystQueuePanel from '@/components/analyst/analyst-queue-panel';
import CompleteJobOrderDialog from '@/components/analyst/complete-job-order-dialog';
import type {
    AnalystTask,
    Consolidation,
    ReleasedPrint,
} from '@/components/analyst/types';
import {
    actionableTasks,
    canWorkTask,
    encodedResultLabel,
    groupTasksBySample,
} from '@/components/analyst/types';
import ConfirmDialog from '@/components/confirm-dialog';
import LimsWorkspace from '@/components/lims/lims-workspace';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import ResultReportPreview from '@/components/result-report-preview';
import ResultSignatoryPanel, {
    type ResultSignatoryConfig,
    type ResultSignatoryRow,
} from '@/components/result-signatory-dialog';
import WorkspaceHeader from '@/components/workspace-header';
import { LabQueueRealtime } from '@/hooks/use-lab-queue-realtime';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';
import { formatScientificResult } from '@/lib/scientific-result';
import { Head, router, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useRef, useState } from 'react';
import {
    Dialog,
    DialogContent,
} from '@/components/ui/dialog';

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

function firstFocusTask(tasks: AnalystTask[]): AnalystTask | null {
    const open = actionableTasks(tasks);
    return (
        open.find((t) => t.status === 'returned') ??
        open[0] ??
        tasks.find((t) => canWorkTask(t)) ??
        tasks[0] ??
        null
    );
}

function setJobQueryParam(jobId: number | null) {
    const url = new URL(window.location.href);
    if (jobId == null) {
        url.searchParams.delete('job');
    } else {
        url.searchParams.set('job', String(jobId));
    }
    window.history.replaceState({}, '', url);
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
    const [selectedJobId, setSelectedJobId] = useState<number | null>(null);
    const [active, setActive] = useState<AnalystTask | null>(null);
    /** When true, discard confirm continues by leaving focus for the bench queue. */
    const [pendingBackToQueue, setPendingBackToQueue] = useState(false);
    const [previewUrl, setPreviewUrl] = useState<string | null>(null);
    const [confirmComplete, setConfirmComplete] = useState(false);
    const [sampleCompletePending, setSampleCompletePending] = useState<{
        jobId: number;
    } | null>(null);
    const [sampleCompleteProcessing, setSampleCompleteProcessing] =
        useState(false);
    const [sampleCompleteFallback, setSampleCompleteFallback] = useState(false);
    const [sampleCompleteError, setSampleCompleteError] = useState<
        string | null
    >(null);
    const [discardConfirm, setDiscardConfirm] = useState(false);
    const [pendingTask, setPendingTask] = useState<AnalystTask | null>(null);
    const [pendingJobId, setPendingJobId] = useState<number | null>(null);
    const [savedJustNow, setSavedJustNow] = useState(false);
    const [lastAutosavedAt, setLastAutosavedAt] = useState<Date | null>(null);
    const [draftSaving, setDraftSaving] = useState(false);
    const draftInFlight = useRef(false);
    const pendingAfterDraft = useRef<(() => void) | null>(null);
    const draftTaskIdRef = useRef<number | null>(null);
    const [submitSignatoryJobId, setSubmitSignatoryJobId] = useState<
        number | null
    >(null);
    const [submitSignatoryError, setSubmitSignatoryError] = useState<
        string | null
    >(null);
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
        performed_at: '',
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

    const selectedGroup = useMemo(
        () => groups.find((g) => g.job.id === selectedJobId) ?? null,
        [groups, selectedJobId],
    );

    const editableJobStatuses = [
        'in_analysis',
        'pending_review',
        'ready_for_pickup',
    ];
    const jobAllowsEdit = editableJobStatuses.includes(
        active?.job_order.status ?? '',
    );
    const readOnly = !active || !canWorkTask(active) || !jobAllowsEdit;
    const isCompletedLine = active?.status === 'completed';
    const pauseQueueReload =
        form.isDirty ||
        draftSaving ||
        Boolean(previewUrl) ||
        confirmComplete ||
        sampleCompleteFallback ||
        Boolean(sampleCompletePending) ||
        discardConfirm ||
        Boolean(submitSignatoryJobId) ||
        Boolean(editSignatoryJobId);

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
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [query, filters.q, filters.status, filters.sort]);

    // Deep-link ?job= opens focus; otherwise stay on the bench queue (no auto-open).
    useEffect(() => {
        if (groups.length === 0) {
            setSelectedJobId(null);
            setActive(null);
            return;
        }

        const params = new URLSearchParams(window.location.search);
        const jobParam = Number(params.get('job'));
        const fromQuery =
            Number.isFinite(jobParam) && jobParam > 0
                ? groups.find((g) => g.job.id === jobParam)
                : null;

        if (fromQuery) {
            setSelectedJobId(fromQuery.job.id);
            return;
        }

        setSelectedJobId((current) => {
            if (current && groups.some((g) => g.job.id === current)) {
                return current;
            }

            if (current) {
                setJobQueryParam(null);
            }

            return null;
        });
    }, [groups]);

    useEffect(() => {
        if (!selectedGroup) {
            setActive(null);
            return;
        }

        if (form.isDirty) {
            return;
        }

        const refreshed =
            active != null
                ? selectedGroup.tasks.find((t) => t.id === active.id)
                : null;

        if (refreshed) {
            if (
                refreshed.performed_at !== active?.performed_at ||
                refreshed.result_value !== active?.result_value ||
                refreshed.status !== active?.status
            ) {
                loadTaskIntoForm(refreshed);
            } else {
                setActive(refreshed);
            }
            return;
        }

        const focus = firstFocusTask(selectedGroup.tasks);
        if (focus) {
            loadTaskIntoForm(focus);
        } else {
            setActive(null);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedGroup, tasks]);

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

    function loadTaskIntoForm(task: AnalystTask) {
        setActive(task);
        setSavedJustNow(false);
        setConfirmComplete(false);
        setDiscardConfirm(false);
        form.clearErrors();
        form.setData({
            performed_at: task.performed_at ?? '',
            result_value: task.result_value ?? '',
            result_pass_fail: task.result_pass_fail ?? '',
            result_measurement: task.result_measurement ?? '',
            result_unit: task.result_unit ?? '',
            result_remarks: task.result_remarks ?? '',
            result_method: task.result_method ?? task.procedure_method ?? '',
        });
        form.setDefaults({
            performed_at: task.performed_at ?? '',
            result_value: task.result_value ?? '',
            result_pass_fail: task.result_pass_fail ?? '',
            result_measurement: task.result_measurement ?? '',
            result_unit: task.result_unit ?? '',
            result_remarks: task.result_remarks ?? '',
            result_method: task.result_method ?? task.procedure_method ?? '',
        });
    }

    function openTask(task: AnalystTask) {
        if (task.can_work === false) {
            return;
        }

        if (active?.id === task.id) {
            return;
        }

        if (!readOnly && form.isDirty) {
            if (isCompletedLine) {
                setPendingTask(task);
                setPendingJobId(null);
                setDiscardConfirm(true);
                return;
            }

            flushDraft(() => loadTaskIntoForm(task));
            return;
        }

        loadTaskIntoForm(task);
    }

    function selectJob(jobId: number) {
        if (jobId === selectedJobId) {
            return;
        }

        if (!readOnly && form.isDirty) {
            if (isCompletedLine) {
                setPendingJobId(jobId);
                setPendingTask(null);
                setPendingBackToQueue(false);
                setDiscardConfirm(true);
                return;
            }

            flushDraft(() => applyJobSelection(jobId));
            return;
        }

        applyJobSelection(jobId);
    }

    function applyJobSelection(jobId: number) {
        const group = groups.find((g) => g.job.id === jobId);
        setSelectedJobId(jobId);
        setJobQueryParam(jobId);
        setPendingBackToQueue(false);
        if (!group) {
            setActive(null);
            return;
        }

        const focus = firstFocusTask(group.tasks);
        if (focus) {
            loadTaskIntoForm(focus);
        } else {
            setActive(null);
        }
    }

    function applyBackToQueue() {
        setSelectedJobId(null);
        setActive(null);
        setJobQueryParam(null);
        setPendingBackToQueue(false);
        setPendingJobId(null);
        setPendingTask(null);
    }

    function backToQueue() {
        if (!readOnly && form.isDirty) {
            if (isCompletedLine) {
                setPendingBackToQueue(true);
                setPendingJobId(null);
                setPendingTask(null);
                setDiscardConfirm(true);
                return;
            }

            flushDraft(() => applyBackToQueue());
            return;
        }

        applyBackToQueue();
    }

    const focusIndex =
        selectedJobId == null
            ? -1
            : groups.findIndex((g) => g.job.id === selectedJobId);
    const focusPosition =
        focusIndex >= 0
            ? { index: focusIndex + 1, total: groups.length }
            : null;
    const prevJobId =
        focusIndex > 0 ? (groups[focusIndex - 1]?.job.id ?? null) : null;
    const nextJobId =
        focusIndex >= 0 && focusIndex < groups.length - 1
            ? (groups[focusIndex + 1]?.job.id ?? null)
            : null;

    function goAdjacentJob(direction: 'prev' | 'next') {
        const targetId = direction === 'prev' ? prevJobId : nextJobId;
        if (targetId == null) {
            return;
        }

        selectJob(targetId);
    }

    // Focus mode: Alt+← / Alt+→ switches Job Orders when not typing in a field.
    useEffect(() => {
        if (selectedJobId == null) {
            return;
        }

        function onKeyDown(event: KeyboardEvent) {
            if (!event.altKey) {
                return;
            }

            if (event.key !== 'ArrowLeft' && event.key !== 'ArrowRight') {
                return;
            }

            if (document.querySelector('[data-slot="dialog-content"]')) {
                return;
            }

            const target = event.target as HTMLElement | null;
            const tag = target?.tagName?.toLowerCase();
            if (
                tag === 'input' ||
                tag === 'textarea' ||
                tag === 'select' ||
                target?.isContentEditable
            ) {
                return;
            }

            event.preventDefault();
            if (event.key === 'ArrowLeft') {
                goAdjacentJob('prev');
            } else {
                goAdjacentJob('next');
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [selectedJobId, prevJobId, nextJobId, form.isDirty, readOnly, isCompletedLine]);

    function discardAndContinue() {
        setDiscardConfirm(false);
        form.clearErrors();
        form.reset();
        setSavedJustNow(false);

        if (pendingBackToQueue) {
            applyBackToQueue();
            return;
        }

        if (pendingJobId != null) {
            const jobId = pendingJobId;
            setPendingJobId(null);
            setPendingTask(null);
            applyJobSelection(jobId);
            return;
        }

        if (pendingTask) {
            const task = pendingTask;
            setPendingTask(null);
            loadTaskIntoForm(task);
        }
    }

    function flushDraft(onDone?: () => void) {
        if (!active || readOnly || isCompletedLine) {
            onDone?.();
            return;
        }

        if (!form.isDirty) {
            onDone?.();
            return;
        }

        // Another draft is saving — queue the follow-up (e.g. switch assay) instead of
        // running it immediately and wiping form data mid-request.
        if (draftInFlight.current) {
            if (onDone) {
                const previous = pendingAfterDraft.current;
                pendingAfterDraft.current = () => {
                    previous?.();
                    onDone();
                };
            }
            return;
        }

        const taskId = active.id;
        const passFail =
            active.requires_pass_fail === true
                ? form.data.result_pass_fail
                : '';
        const formattedValue = formatScientificResult(form.data.result_value);
        if (formattedValue !== form.data.result_value) {
            form.setData('result_value', formattedValue);
        }
        const snapshot = {
            performed_at: form.data.performed_at,
            result_value: formattedValue,
            result_pass_fail: passFail,
            result_measurement: form.data.result_measurement,
            result_unit: form.data.result_unit,
            result_remarks: form.data.result_remarks,
            result_method: form.data.result_method,
        };

        draftInFlight.current = true;
        draftTaskIdRef.current = taskId;
        setDraftSaving(true);
        if (onDone) {
            pendingAfterDraft.current = onDone;
        }

        router.post(`/analyst/tasks/${taskId}/draft`, snapshot, {
            preserveScroll: true,
            preserveState: true,
            // Partial + silent: keep encoding focus; no progress bar / nav chrome / toast.
            only: [
                'tasks',
                'jobs',
                'counts',
                'consolidations',
                'releasedPrints',
            ],
            showProgress: false,
            onSuccess: () => {
                setSavedJustNow(true);
                setLastAutosavedAt(new Date());
                // Align defaults to what was saved so isDirty clears when the form
                // still matches. Do NOT setData — that can wipe keystrokes typed
                // after this draft request started.
                if (draftTaskIdRef.current === taskId) {
                    form.setDefaults(snapshot);
                }
            },
            onError: () => {
                // Keep editing if draft fails — do not switch assays.
                pendingAfterDraft.current = null;
            },
            onFinish: () => {
                draftInFlight.current = false;
                draftTaskIdRef.current = null;
                setDraftSaving(false);
                const next = pendingAfterDraft.current;
                pendingAfterDraft.current = null;
                next?.();
            },
        });
    }

    function saveDraft() {
        if (!active || readOnly || isCompletedLine) {
            return;
        }

        flushDraft();
    }

    function jobWorkableTasks(): AnalystTask[] {
        if (!selectedGroup) {
            return [];
        }

        return selectedGroup.tasks.filter((t) => canWorkTask(t));
    }

    function missingJobAssays(jobTasks: AnalystTask[]): string[] {
        const sampleRows = groupTasksBySample(selectedGroup?.tasks ?? jobTasks);
        const multiSample = sampleRows.length > 1;

        return jobTasks
            .filter((task) => {
                if (active?.id === task.id) {
                    return (
                        !form.data.performed_at.trim() ||
                        !form.data.result_value.trim()
                    );
                }

                return (
                    !(task.performed_at ?? '').trim() ||
                    !(task.result_value ?? '').trim()
                );
            })
            .map((task) => {
                if (!multiSample) {
                    return task.name;
                }
                const label =
                    task.sample_label?.trim() ||
                    sampleRows.find((g) =>
                        task.sample_id == null
                            ? g.sample_id == null
                            : g.sample_id === task.sample_id,
                    )?.label ||
                    'Sample';
                return `${label} / ${task.name}`;
            });
    }

    function openSampleCompletePreview() {
        if (!active || !selectedGroup) {
            return;
        }

        const jobTasks = jobWorkableTasks();
        const missing = missingJobAssays(jobTasks);
        if (missing.length > 0) {
            setSampleCompleteError(
                `Encode all results before completing this job order: ${missing.join(', ')}.`,
            );
            return;
        }

        setSampleCompleteError(null);
        setSampleCompletePending({
            jobId: selectedGroup.job.id,
        });

        const consolidation = consolidationsById.get(selectedGroup.job.id);
        const canPreview = Boolean(
            consolidation?.can_preview ||
                jobTasks.some((t) => t.report?.can_preview),
        );

        if (!canPreview) {
            setSampleCompleteFallback(true);
            return;
        }

        setPreviewUrl(
            `/analyst/job-orders/${selectedGroup.job.id}/result-report`,
        );
    }

    function requestComplete(event: FormEvent) {
        event.preventDefault();

        if (!active || readOnly) {
            return;
        }

        if (!form.data.performed_at.trim()) {
            form.setError(
                'performed_at',
                'Enter the date and time this test was performed.',
            );
            return;
        }

        if (isCompletedLine) {
            form.clearErrors();
            setConfirmComplete(true);
            return;
        }

        flushDraft(() => openSampleCompletePreview());
    }

    function confirmCompleteTask() {
        if (!active || readOnly) {
            return;
        }

        const passFail =
            active.requires_pass_fail === true
                ? form.data.result_pass_fail
                : '';

        form.transform((data) => ({
            ...data,
            result_pass_fail: passFail,
        }));

        form.post(`/analyst/tasks/${active.id}/complete`, {
            preserveScroll: true,
            onSuccess: () => {
                setConfirmComplete(false);
                setSavedJustNow(true);
                form.setDefaults({
                    performed_at: form.data.performed_at,
                    result_value: form.data.result_value,
                    result_pass_fail: passFail,
                    result_measurement: form.data.result_measurement,
                    result_unit: form.data.result_unit,
                    result_remarks: form.data.result_remarks,
                    result_method: form.data.result_method,
                });
            },
            onError: () => {
                // Keep confirm open so the analyst can see the error and retry.
            },
        });
    }

    function confirmCompleteSample() {
        if (!sampleCompletePending) {
            return;
        }

        const jobId = sampleCompletePending.jobId;

        setSampleCompleteProcessing(true);
        setSampleCompleteError(null);

        router.post(`/analyst/job-orders/${jobId}/complete-sample`, {}, {
            preserveScroll: true,
            onSuccess: () => {
                setPreviewUrl(null);
                setSampleCompletePending(null);
                setSampleCompleteFallback(false);
                setSavedJustNow(true);
            },
            onError: (errs) => {
                const first =
                    errs.assays ||
                    errs.sample_id ||
                    errs.job_order ||
                    Object.values(errs)[0];
                setSampleCompleteError(
                    typeof first === 'string'
                        ? first
                        : 'Could not complete this job order.',
                );
                setSampleCompleteFallback(false);
                setPreviewUrl(null);
            },
            onFinish: () => setSampleCompleteProcessing(false),
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

    function saveStateLabel(): string {
        if (form.processing) {
            return 'Saving…';
        }

        if (draftSaving) {
            return 'Saving draft…';
        }

        if (form.isDirty) {
            return '● Unsaved changes';
        }

        if (savedJustNow && lastAutosavedAt) {
            const time = lastAutosavedAt.toLocaleTimeString(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: false,
            });
            return `✓ Saved ${time}`;
        }

        if (savedJustNow) {
            return '✓ Saved just now';
        }

        return '✓ Saved';
    }

    const needsAction =
        counts.needs_action ?? counts.assigned + counts.returned;
    const kpiTotal = Math.max(
        counts.all ||
            needsAction +
                counts.in_progress +
                counts.returned +
                counts.completed,
        1,
    );

    const kpis = [
        {
            id: 'needs_action',
            label: 'Needs Action',
            value: needsAction,
            unit: 'Jobs pending bench prep',
            tone: 'info' as const,
            share: Math.round((needsAction / kpiTotal) * 100),
        },
        {
            id: 'in_progress',
            label: 'In Progress',
            value: counts.in_progress,
            unit: 'Active test encoding',
            tone: 'warning' as const,
            share: Math.round((counts.in_progress / kpiTotal) * 100),
        },
        {
            id: 'returned',
            label: 'Returned',
            value: counts.returned,
            unit: 'Requires supervisor re-check',
            tone: 'danger' as const,
            share: Math.round((counts.returned / kpiTotal) * 100),
            badge: counts.returned > 0 ? 'QA Bounceback' : null,
        },
        {
            id: 'completed',
            label: 'Completed',
            value: counts.completed,
            unit: 'Sent to Section Head',
            tone: 'success' as const,
            share: Math.round((counts.completed / kpiTotal) * 100),
        },
    ];

    const empty = emptyMessage(filters);
    const sort = filters.sort || 'needs_action';
    const inFocus = selectedGroup != null;

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
                {inFocus ? (
                    <div
                        data-analyst-workbench
                        className="flex h-full min-h-0 min-w-0 flex-1 flex-col overflow-hidden"
                    >
                        {errors?.job_order && (
                            <p className="shrink-0 border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                                {errors.job_order}
                            </p>
                        )}
                        <AnalystEncodingStudio
                            job={selectedGroup.job}
                            tasks={selectedGroup.tasks}
                            active={active}
                            consolidation={consolidationsById.get(
                                selectedGroup.job.id,
                            )}
                            releasedPrint={releasedById.get(
                                selectedGroup.job.id,
                            )}
                            formData={form.data}
                            formErrors={form.errors}
                            formProcessing={form.processing}
                            draftSaving={draftSaving}
                            formDirty={form.isDirty}
                            readOnly={readOnly}
                            saveStateLabel={saveStateLabel()}
                            analysisError={
                                errors?.analysis ||
                                (
                                    form.errors as typeof form.errors & {
                                        analysis?: string;
                                    }
                                ).analysis
                            }
                            queuePendingCount={needsAction}
                            focusPosition={focusPosition}
                            canGoPrev={prevJobId != null}
                            canGoNext={nextJobId != null}
                            onBackToQueue={backToQueue}
                            onPrevJob={() => goAdjacentJob('prev')}
                            onNextJob={() => goAdjacentJob('next')}
                            onFocusTask={openTask}
                            onFieldChange={(key, value) => {
                                setSavedJustNow(false);
                                form.setData(key, value);
                            }}
                            onSaveDraft={saveDraft}
                            onRequestComplete={requestComplete}
                            onPreview={(url) => {
                                setSampleCompletePending(null);
                                setPreviewUrl(url);
                            }}
                            onSendToHead={submitJob}
                            onEditSignatories={openEditSignatories}
                        />
                    </div>
                ) : (
                    <LimsWorkspace className="gap-5">
                        <WorkspaceHeader
                            title="Analyst Workspace"
                            description="Assigned bench queue"
                            refreshing={isRefreshing}
                            refreshLabel="Updating queue…"
                        />

                        {errors?.job_order && (
                            <p className="rounded-lg border border-red-200 bg-red-50 px-4 py-2 text-sm text-red-800">
                                {errors.job_order}
                            </p>
                        )}

                        <AnalystQueuePanel
                            groups={groups}
                            selectedJobId={selectedJobId}
                            empty={empty}
                            consolidationsById={consolidationsById}
                            kpis={kpis}
                            allCount={counts.all}
                            activeStatus={filters.status || ''}
                            onSelectStatus={(id) =>
                                applyFilters({
                                    status: id,
                                    q: query.trim(),
                                })
                            }
                            query={query}
                            onQueryChange={setQuery}
                            sort={sort}
                            onSortChange={(next) =>
                                applyFilters({ sort: next })
                            }
                            onSelectJob={selectJob}
                            pagination={jobs}
                        />
                    </LimsWorkspace>
                )}
            </NavigationLoadingShell>

            <ConfirmDialog
                open={confirmComplete}
                onOpenChange={setConfirmComplete}
                title="Save result changes?"
                description="This will update the completed result on the printable form."
                confirmLabel="Save changes"
                processingLabel="Saving…"
                processing={form.processing}
                error={
                    (
                        form.errors as typeof form.errors & {
                            analysis?: string;
                        }
                    ).analysis ||
                    form.errors.performed_at ||
                    form.errors.result_value ||
                    form.errors.result_pass_fail ||
                    form.errors.result_method ||
                    form.errors.result_remarks ||
                    Object.values(form.errors).find(
                        (value) => typeof value === 'string' && value.trim(),
                    ) ||
                    null
                }
                onConfirm={confirmCompleteTask}
            >
                {active && (
                    <dl className="space-y-2 text-sm">
                        <div>
                            <dt className="text-muted-foreground">Test</dt>
                            <dd className="font-medium">{active.name}</dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Date & time performed
                            </dt>
                            <dd className="font-medium">
                                {form.data.performed_at.trim() || '—'}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">Result</dt>
                            <dd className="font-medium">
                                {encodedResultLabel({
                                    result_value: form.data.result_value,
                                    result_pass_fail:
                                        active.requires_pass_fail === true
                                            ? form.data.result_pass_fail
                                            : null,
                                    result_unit: form.data.result_unit,
                                    requires_pass_fail:
                                        active.requires_pass_fail,
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
                open={Boolean(sampleCompleteError)}
                onOpenChange={(open) => {
                    if (!open) {
                        setSampleCompleteError(null);
                    }
                }}
                title="Job order not ready"
                description={sampleCompleteError ?? ''}
                confirmLabel="OK"
                onConfirm={() => setSampleCompleteError(null)}
            />

            <CompleteJobOrderDialog
                open={sampleCompleteFallback}
                onOpenChange={(open) => {
                    if (!open && !sampleCompleteProcessing) {
                        setSampleCompleteFallback(false);
                        setSampleCompletePending(null);
                    }
                }}
                processing={sampleCompleteProcessing}
                onConfirm={confirmCompleteSample}
                jobReference={selectedGroup?.job.reference_no}
                samples={groupTasksBySample(selectedGroup?.tasks ?? [])}
                activeTaskId={active?.id ?? null}
                formResultValue={form.data.result_value}
            />

            <ConfirmDialog
                open={discardConfirm}
                onOpenChange={(open) => {
                    setDiscardConfirm(open);
                    if (!open) {
                        setPendingTask(null);
                        setPendingJobId(null);
                        setPendingBackToQueue(false);
                    }
                }}
                title="You have unsaved changes. Discard them?"
                description="Encoded values that were not saved as draft will be lost."
                confirmLabel="Discard changes"
                cancelLabel="Keep editing"
                variant="destructive"
                onConfirm={discardAndContinue}
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
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
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
                <DialogContent className="gap-0 overflow-hidden p-0 sm:max-w-xl">
                    {editSignatoryConfig && (
                        <ResultSignatoryPanel
                            config={editSignatoryConfig}
                            submitting={editSignatorySaving}
                            error={editSignatoryError}
                            confirmLabel="Save printed names"
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
                        if (!sampleCompleteProcessing) {
                            setSampleCompletePending(null);
                        }
                    }
                }}
                reportUrl={previewUrl}
                confirmCompleteLabel={
                    sampleCompletePending
                        ? 'Confirm & complete job order'
                        : undefined
                }
                confirmCompleteProcessing={sampleCompleteProcessing}
                onConfirmComplete={
                    sampleCompletePending ? confirmCompleteSample : undefined
                }
            />
        </>
    );
}

AnalystIndex.layout = {
    breadcrumbs: [{ title: 'Analyst', href: '/analyst' }],
};

