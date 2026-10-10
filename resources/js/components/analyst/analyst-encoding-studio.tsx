import AnalystWorkflowStepper from '@/components/analyst/analyst-workflow-stepper';
import EncodingAssayForm from '@/components/analyst/encoding-assay-form';
import EncodingAssayTabs from '@/components/analyst/encoding-assay-tabs';
import EncodingContextRail from '@/components/analyst/encoding-context-rail';
import EncodingSampleTabs from '@/components/analyst/encoding-sample-tabs';
import EncodingShortcutStrip from '@/components/analyst/encoding-shortcut-strip';
import EncodingWorkbenchHeader from '@/components/analyst/encoding-workbench-header';
import type {
    AnalystTask,
    Consolidation,
    ReleasedPrint,
    SampleSummary,
} from '@/components/analyst/types';
import {
    groupTasksBySample,
    jobProgress,
} from '@/components/analyst/types';
import { Button } from '@/components/ui/button';
import { useSidebar } from '@/components/ui/sidebar';
import { cn } from '@/lib/utils';
import { ArrowRight, FileText, PanelRight, Send } from 'lucide-react';
import type { FormEvent } from 'react';
import { useEffect, useMemo, useState } from 'react';

type FormData = {
    performed_at: string;
    result_value: string;
    result_pass_fail: string;
    result_measurement: string;
    result_unit: string;
    result_remarks: string;
    result_method: string;
};

type FormErrors = Partial<Record<keyof FormData | 'analysis', string>>;

type Props = {
    job: AnalystTask['job_order'];
    tasks: AnalystTask[];
    active: AnalystTask | null;
    consolidation?: Consolidation;
    releasedPrint?: ReleasedPrint;
    formData: FormData;
    formErrors: FormErrors;
    formProcessing: boolean;
    draftSaving?: boolean;
    formDirty: boolean;
    readOnly: boolean;
    saveStateLabel: string;
    analysisError?: string | null;
    queuePendingCount?: number;
    focusPosition?: { index: number; total: number } | null;
    canGoPrev?: boolean;
    canGoNext?: boolean;
    onBackToQueue?: () => void;
    onPrevJob?: () => void;
    onNextJob?: () => void;
    onFocusTask: (task: AnalystTask) => void;
    onFieldChange: (key: keyof FormData, value: string) => void;
    onSaveDraft: () => void;
    onRequestComplete: (event: FormEvent) => void;
    onPreview: (url: string) => void;
    onSendToHead: (jobId: number) => void;
    onEditSignatories?: (jobId: number) => void;
};

function isTypingTarget(target: EventTarget | null): boolean {
    const el = target as HTMLElement | null;
    const tag = el?.tagName?.toLowerCase();
    return (
        tag === 'input' ||
        tag === 'textarea' ||
        tag === 'select' ||
        Boolean(el?.isContentEditable)
    );
}

export default function AnalystEncodingStudio({
    job,
    tasks,
    active,
    consolidation,
    releasedPrint,
    formData,
    formErrors,
    formProcessing,
    draftSaving = false,
    formDirty,
    readOnly,
    saveStateLabel,
    analysisError,
    queuePendingCount = 0,
    focusPosition = null,
    canGoPrev = false,
    canGoNext = false,
    onBackToQueue,
    onPrevJob,
    onNextJob,
    onFocusTask,
    onFieldChange,
    onSaveDraft,
    onRequestComplete,
    onPreview,
    onSendToHead,
    onEditSignatories,
}: Props) {
    const { setOpen: setSidebarOpen, open: sidebarOpen } = useSidebar();
    const [distractionFree, setDistractionFree] = useState(false);
    const [tabletRailOpen, setTabletRailOpen] = useState(false);
    const [sidebarBeforeFocus, setSidebarBeforeFocus] = useState<
        boolean | null
    >(null);

    const progress = jobProgress(tasks);
    const hasReturned = tasks.some((t) => t.status === 'returned');
    const isCompletedLine = active?.status === 'completed';
    const reviewNotes = (
        job.review_notes ||
        consolidation?.review_notes ||
        ''
    ).trim();

    const previewUrl =
        consolidation?.preview_url ||
        releasedPrint?.preview_url ||
        (tasks[0]
            ? `/analyst/job-orders/${job.id}/result-report`
            : null);
    const canPreview = Boolean(
        consolidation?.can_preview ||
            releasedPrint?.can_preview ||
            tasks.some((t) => t.report?.can_preview),
    );
    const canSend = Boolean(consolidation?.can_submit);

    const sampleGroups = useMemo(() => groupTasksBySample(tasks), [tasks]);

    const activeSampleGroup = useMemo(() => {
        if (sampleGroups.length === 0) {
            return null;
        }
        if (!active) {
            return sampleGroups[0];
        }
        const sampleId = active.sample_id ?? null;
        return (
            sampleGroups.find((g) =>
                sampleId == null
                    ? g.sample_id == null
                    : g.sample_id === sampleId,
            ) ?? sampleGroups[0]
        );
    }, [sampleGroups, active]);

    const checklist = activeSampleGroup?.tasks ?? tasks;

    const activeSample: SampleSummary | null = useMemo(() => {
        const id = active?.sample_id ?? activeSampleGroup?.sample_id ?? null;
        if (id == null) {
            return job.samples?.[0] ?? null;
        }
        return (
            job.samples?.find((s) => Number(s.id) === Number(id)) ?? null
        );
    }, [active, activeSampleGroup, job.samples]);

    const sampleLabel =
        active?.sample_label?.trim() ||
        activeSampleGroup?.label ||
        activeSample?.description ||
        null;

    const activeIndex = checklist.findIndex((t) => t.id === active?.id);
    const nextAssay =
        activeIndex >= 0 && activeIndex < checklist.length - 1
            ? checklist[activeIndex + 1]
            : null;

    function applyDistractionFree(next: boolean) {
        setDistractionFree(next);
        if (next) {
            setTabletRailOpen(false);
            setSidebarBeforeFocus(sidebarOpen);
            setSidebarOpen(false);
        } else if (sidebarBeforeFocus !== null) {
            setSidebarOpen(sidebarBeforeFocus);
            setSidebarBeforeFocus(null);
        } else {
            setSidebarOpen(true);
        }
    }

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (document.querySelector('[data-slot="dialog-content"]')) {
                return;
            }

            if (event.altKey && event.key.toLowerCase() === 'f') {
                event.preventDefault();
                setDistractionFree((current) => {
                    const next = !current;
                    if (next) {
                        setTabletRailOpen(false);
                        setSidebarBeforeFocus(sidebarOpen);
                        setSidebarOpen(false);
                    } else {
                        setSidebarOpen(true);
                        setSidebarBeforeFocus(null);
                    }
                    return next;
                });
                return;
            }

            if (isTypingTarget(event.target)) {
                return;
            }

            if (
                (event.ctrlKey || event.metaKey) &&
                event.key.toLowerCase() === 's'
            ) {
                event.preventDefault();
                if (!readOnly && !isCompletedLine) {
                    onSaveDraft();
                }
                return;
            }

            if ((event.ctrlKey || event.metaKey) && event.key === 'Enter') {
                event.preventDefault();
                if (!readOnly && active) {
                    const formEl = document.getElementById(
                        'analyst-encoding-form',
                    ) as HTMLFormElement | null;
                    formEl?.requestSubmit();
                }
                return;
            }

            if (
                !readOnly &&
                active?.requires_pass_fail === true &&
                formData.performed_at.trim()
            ) {
                if (event.key.toLowerCase() === 'p') {
                    event.preventDefault();
                    onFieldChange('result_pass_fail', 'Passed');
                    return;
                }
                if (event.key.toLowerCase() === 'f') {
                    event.preventDefault();
                    onFieldChange('result_pass_fail', 'Failed');
                    return;
                }
            }

            if (event.altKey && /^[1-9]$/.test(event.key)) {
                const index = Number(event.key) - 1;
                const task = checklist[index];
                if (task) {
                    event.preventDefault();
                    onFocusTask(task);
                }
            }
        }

        window.addEventListener('keydown', onKeyDown);
        return () => window.removeEventListener('keydown', onKeyDown);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [
        readOnly,
        isCompletedLine,
        active,
        formData.performed_at,
        checklist,
        sidebarOpen,
        onSaveDraft,
        onFieldChange,
        onFocusTask,
    ]);

    return (
        <main className="flex h-full min-h-0 min-w-0 w-full flex-1 flex-col overflow-hidden bg-nppc-page">
            <EncodingWorkbenchHeader
                referenceNo={job.reference_no}
                classification={job.classification}
                sampleLabel={sampleLabel}
                customerName={job.customer_name}
                queuePendingCount={queuePendingCount}
                saveStateLabel={saveStateLabel}
                focusPosition={focusPosition}
                canGoPrev={canGoPrev}
                canGoNext={canGoNext}
                distractionFree={distractionFree}
                hasReturned={hasReturned}
                onBackToQueue={onBackToQueue}
                onPrevJob={onPrevJob}
                onNextJob={onNextJob}
                onToggleDistractionFree={() =>
                    applyDistractionFree(!distractionFree)
                }
            />

            <div className="flex h-9 shrink-0 items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/90 px-3 sm:px-4">
                <div className="min-w-0 flex-1 overflow-hidden">
                    <AnalystWorkflowStepper job={job} compact />
                </div>
                {(job.sample_storage_temp || job.received_at) && (
                    <div className="hidden shrink-0 items-center gap-2 text-[11px] text-slate-500 xl:flex">
                        {job.sample_storage_temp ? (
                            <span>
                                Storage:{' '}
                                <strong className="font-semibold text-slate-700">
                                    {job.sample_storage_temp}
                                </strong>
                            </span>
                        ) : null}
                        {job.received_at ? (
                            <>
                                <span>·</span>
                                <span className="max-w-[12rem] truncate">
                                    Received {job.received_at}
                                </span>
                            </>
                        ) : null}
                    </div>
                )}
            </div>

            <EncodingSampleTabs
                groups={sampleGroups}
                activeSampleId={
                    active?.sample_id ?? activeSampleGroup?.sample_id ?? null
                }
                onFocusTask={onFocusTask}
            />

            <EncodingAssayTabs
                tasks={checklist}
                activeId={active?.id ?? null}
                onFocusTask={onFocusTask}
            />

            {hasReturned && reviewNotes ? (
                <div className="shrink-0 border-b border-rose-100 bg-rose-50 px-3 py-1.5 text-xs text-rose-950 sm:px-4">
                    <span className="font-bold">QA return · </span>
                    <span className="whitespace-pre-wrap">{reviewNotes}</span>
                </div>
            ) : null}

            <form
                id="analyst-encoding-form"
                className="flex min-h-0 flex-1 flex-col overflow-hidden"
                onSubmit={onRequestComplete}
            >
                <div className="relative flex min-h-0 flex-1 overflow-hidden">
                    {/* Main assay column — sole primary scroller */}
                    <div className="nppc-scroll-sidebar min-h-0 min-w-0 flex-1 overflow-y-auto overscroll-contain px-3 py-3 sm:px-4 sm:py-3.5">
                        <div className="flex min-w-0 flex-col gap-3">
                            {active ? (
                                <section className="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-xs">
                                    <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-50/80 px-3.5 py-2">
                                        <div className="flex min-w-0 items-center gap-2">
                                            <span className="flex size-5 shrink-0 items-center justify-center rounded-md bg-nppc-900 text-[11px] font-bold text-white">
                                                {Math.max(activeIndex, 0) + 1}
                                            </span>
                                            <div className="min-w-0">
                                                <div className="flex flex-wrap items-center gap-1.5">
                                                    <h2 className="truncate text-sm font-bold tracking-tight text-slate-900">
                                                        {active.name}
                                                    </h2>
                                                    {active.category_label ? (
                                                        <span className="rounded border border-indigo-200 bg-indigo-50 px-1.5 py-px text-[10px] font-medium text-indigo-800">
                                                            {
                                                                active.category_label
                                                            }
                                                        </span>
                                                    ) : null}
                                                </div>
                                                {sampleLabel ||
                                                job.classification ? (
                                                    <p className="mt-0.5 truncate text-[11px] text-slate-500">
                                                        {[
                                                            sampleLabel,
                                                            job.classification
                                                                ? `Matrix: ${job.classification}`
                                                                : null,
                                                        ]
                                                            .filter(Boolean)
                                                            .join(' · ')}
                                                    </p>
                                                ) : null}
                                            </div>
                                        </div>
                                        <div className="flex shrink-0 items-center gap-1.5">
                                            {!distractionFree ? (
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    size="sm"
                                                    className={cn(
                                                        'h-7 gap-1 border-slate-200 px-2 text-[11px] shadow-xs xl:hidden',
                                                        tabletRailOpen &&
                                                            'border-nppc-200 bg-nppc-50 text-nppc-900',
                                                    )}
                                                    onClick={() =>
                                                        setTabletRailOpen(
                                                            (open) => !open,
                                                        )
                                                    }
                                                >
                                                    <PanelRight className="size-3.5" />
                                                    Sample
                                                </Button>
                                            ) : null}
                                            <span
                                                className={cn(
                                                    'inline-flex items-center gap-1.5 rounded border px-2 py-0.5 text-[11px] font-medium',
                                                    isCompletedLine
                                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                        : hasReturned
                                                          ? 'border-rose-200 bg-rose-50 text-rose-800'
                                                          : 'border-amber-200 bg-amber-50 text-amber-900',
                                                )}
                                            >
                                                <span
                                                    className={cn(
                                                        'size-1.5 rounded-full',
                                                        isCompletedLine
                                                            ? 'bg-emerald-500'
                                                            : 'bg-amber-500',
                                                    )}
                                                />
                                                {isCompletedLine
                                                    ? 'Completed'
                                                    : readOnly
                                                      ? 'View only'
                                                      : 'Editing'}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="p-3.5 sm:p-4">
                                        {active.status === 'returned' &&
                                        reviewNotes ? (
                                            <div className="mb-2.5 rounded-md border border-amber-200 bg-amber-50 px-2.5 py-1.5 text-xs text-amber-950">
                                                <p className="font-semibold">
                                                    Result returned
                                                </p>
                                                <p className="mt-0.5 whitespace-pre-wrap">
                                                    {reviewNotes}
                                                </p>
                                            </div>
                                        ) : null}
                                        <EncodingAssayForm
                                            task={active}
                                            formData={formData}
                                            formErrors={formErrors}
                                            readOnly={readOnly}
                                            analysisError={analysisError}
                                            onFieldChange={onFieldChange}
                                        />
                                    </div>
                                </section>
                            ) : (
                                <div className="rounded-lg border border-slate-200 bg-white p-6 text-center text-sm text-slate-500 shadow-xs">
                                    Select a test to begin encoding.
                                </div>
                            )}

                            {/* Mobile: sample + checklist in the same scroller */}
                            {!distractionFree ? (
                                <div className="md:hidden">
                                    <EncodingContextRail
                                        job={job}
                                        active={active}
                                        sample={activeSample}
                                        sampleLabel={sampleLabel}
                                        checklist={checklist}
                                        onFocusTask={onFocusTask}
                                        className="w-full border-l-0 border-t"
                                    />
                                </div>
                            ) : null}
                        </div>
                    </div>

                    {/* Desktop rail (xl+) */}
                    {!distractionFree ? (
                        <EncodingContextRail
                            job={job}
                            active={active}
                            sample={activeSample}
                            sampleLabel={sampleLabel}
                            checklist={checklist}
                            onFocusTask={onFocusTask}
                            className="hidden xl:flex"
                        />
                    ) : null}

                    {/* Tablet collapsible rail (md–xl) */}
                    {!distractionFree && tabletRailOpen ? (
                        <>
                            <button
                                type="button"
                                aria-label="Close sample panel"
                                className="absolute inset-0 z-10 bg-slate-900/20 md:block xl:hidden"
                                onClick={() => setTabletRailOpen(false)}
                            />
                            <EncodingContextRail
                                job={job}
                                active={active}
                                sample={activeSample}
                                sampleLabel={sampleLabel}
                                checklist={checklist}
                                onFocusTask={onFocusTask}
                                className="absolute inset-y-0 right-0 z-20 flex shadow-lg md:flex xl:hidden"
                            />
                        </>
                    ) : null}
                </div>

                <EncodingShortcutStrip />

                <footer className="relative z-30 flex h-16 shrink-0 items-center justify-between gap-3 border-t border-slate-200 bg-white px-3 shadow-[0_-2px_8px_rgba(0,0,0,0.06)] sm:h-[4.25rem] sm:px-4">
                    <div className="flex min-w-0 items-center gap-2.5">
                        <div
                            className={cn(
                                'flex size-7 shrink-0 items-center justify-center rounded-full border text-[11px] font-bold',
                                progress.percent === 100
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-700'
                                    : 'border-slate-200 bg-slate-50 text-slate-600',
                            )}
                        >
                            {progress.done}/{progress.total}
                        </div>
                        <div className="min-w-0">
                            <p className="truncate text-xs font-semibold text-slate-900">
                                {progress.done} / {progress.total} Tests
                                Validated
                            </p>
                            <p className="truncate text-[10px] text-emerald-700">
                                {formDirty
                                    ? '● Unsaved changes'
                                    : draftSaving
                                      ? '● Saving draft…'
                                      : `● ${saveStateLabel}`}
                            </p>
                        </div>
                    </div>

                    <div className="hidden min-w-0 items-center gap-2 text-[11px] text-slate-500 md:flex">
                        {active ? (
                            <span className="truncate rounded bg-slate-100 px-2 py-0.5 font-medium text-slate-700">
                                Current: {active.name}
                            </span>
                        ) : null}
                        {nextAssay ? (
                            <>
                                <ArrowRight className="size-3 shrink-0 text-slate-400" />
                                <span className="truncate text-slate-400">
                                    {nextAssay.name}
                                </span>
                            </>
                        ) : null}
                    </div>

                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                        {canPreview && previewUrl ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9 gap-1 border-slate-200 text-xs shadow-xs"
                                onClick={() => onPreview(previewUrl)}
                            >
                                <FileText className="size-3.5" />
                                <span className="hidden sm:inline">Preview</span>
                            </Button>
                        ) : null}
                        {active && !readOnly && !isCompletedLine ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-9 border-slate-200 text-xs font-medium shadow-xs"
                                disabled={formProcessing || draftSaving}
                                onClick={onSaveDraft}
                            >
                                {draftSaving ? 'Saving…' : 'Save Draft'}
                            </Button>
                        ) : null}
                        {releasedPrint?.signatory?.can_edit &&
                        onEditSignatories ? (
                            <Button
                                type="button"
                                variant="ghost"
                                size="sm"
                                className="h-9 text-xs"
                                onClick={() => onEditSignatories(job.id)}
                            >
                                Signatories
                            </Button>
                        ) : null}
                        {active && !readOnly ? (
                            <Button
                                type="submit"
                                size="sm"
                                disabled={formProcessing}
                                className="h-9 gap-1 bg-nppc-900 px-3 text-xs font-semibold shadow-sm hover:bg-nppc-700"
                            >
                                {formProcessing
                                    ? 'Saving…'
                                    : isCompletedLine
                                      ? 'Save changes'
                                      : 'Save & Complete'}
                                {!formProcessing && !isCompletedLine ? (
                                    <ArrowRight className="size-3.5" />
                                ) : null}
                            </Button>
                        ) : null}
                        {canSend ? (
                            <Button
                                type="button"
                                size="sm"
                                className="h-9 gap-1 bg-nppc-900 text-xs font-semibold hover:bg-nppc-700"
                                onClick={() => onSendToHead(job.id)}
                            >
                                {hasReturned ? 'Resubmit' : 'Send to Head'}
                                <Send className="size-3.5" />
                            </Button>
                        ) : null}
                    </div>
                </footer>
            </form>
        </main>
    );
}
