import { Head, Link, router, useForm } from '@inertiajs/react';
import LimsSection from '@/components/lims/lims-section';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { useEffect, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import ResultReportPreview, {
    loadResultReport,
    type ResultReportTab,
} from '@/components/result-report-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import LoadingState from '@/components/ui/loading-state';
import { Textarea } from '@/components/ui/textarea';
import { limsPanel } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';

type Props = {
    viewMode?: 'jo' | 'results';
    jobOrder: RequestForAnalysisData & {
        id: number;
        status?: string;
        is_signed?: boolean;
        needs_jo_approval?: boolean;
        jo_approved_at?: string | null;
        jo_approver_name?: string | null;
    };
};

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

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function HeadShow({ jobOrder, viewMode }: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [confirmSign, setConfirmSign] = useState(false);
    const [confirmApprove, setConfirmApprove] = useState(false);
    const [confirmReturn, setConfirmReturn] = useState(false);
    const [returning, setReturning] = useState(false);
    const [approving, setApproving] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const [formLoading, setFormLoading] = useState(true);
    const [formError, setFormError] = useState<string | null>(null);
    const [formObjectUrl, setFormObjectUrl] = useState<string | null>(null);
    const [resultFormTitle, setResultFormTitle] = useState<string | null>(null);
    const form = useForm({
        review_notes: '',
    });

    const needsJoApproval = Boolean(jobOrder.needs_jo_approval);
    const showJoForm = needsJoApproval || viewMode === 'jo';
    const isSigned = Boolean(jobOrder.is_signed || jobOrder.reviewed_at);
    const rfaPdfUrl = `/head/${jobOrder.id}/pdf?inline=1&results=0`;
    const resultReportUrl = `/head/${jobOrder.id}/result-report`;
    const [selectedResultUrl, setSelectedResultUrl] = useState(resultReportUrl);
    const [resultTabs, setResultTabs] = useState<ResultReportTab[]>([]);

    useEffect(() => {
        setSelectedResultUrl(resultReportUrl);
        setResultTabs([]);
    }, [resultReportUrl]);
    const resultsWithValues = jobOrder.analyses.filter(
        (line) =>
            line.result_value != null && String(line.result_value).trim() !== '',
    );

    useEffect(() => {
        let cancelled = false;
        let createdUrl: string | null = null;

        void Promise.resolve().then(async () => {
            setFormLoading(true);
            setFormError(null);
            setFormObjectUrl(null);
            setResultFormTitle(null);

            try {
                if (showJoForm) {
                    const response = await fetch(rfaPdfUrl, {
                        credentials: 'same-origin',
                        headers: { Accept: 'application/pdf' },
                    });

                    if (!response.ok) {
                        const message = await response.text();
                        throw new Error(
                            message.trim() ||
                                'Could not load the controlled Job Order PDF.',
                        );
                    }

                    const blob = await response.blob();

                    if (cancelled) {
                        return;
                    }

                    createdUrl = URL.createObjectURL(blob);
                } else {
                    const report = await loadResultReport(selectedResultUrl);

                    if (cancelled) {
                        return;
                    }

                    createdUrl = URL.createObjectURL(report.blob);
                    setResultFormTitle(report.title);
                    setResultTabs(
                        (report.reports?.length ?? 0) > 1
                            ? (report.reports ?? [])
                            : [],
                    );
                }

                if (cancelled) {
                    if (createdUrl) {
                        URL.revokeObjectURL(createdUrl);
                    }

                    return;
                }

                setFormObjectUrl(createdUrl);
            } catch (cause: unknown) {
                if (cancelled) {
                    return;
                }

                setFormError(
                    cause instanceof Error
                        ? cause.message
                        : showJoForm
                          ? 'Could not prepare the controlled Job Order PDF.'
                          : 'Could not prepare the analysis result form.',
                );
            } finally {
                if (!cancelled) {
                    setFormLoading(false);
                }
            }
        });

        return () => {
            cancelled = true;

            if (createdUrl) {
                URL.revokeObjectURL(createdUrl);
            }
        };
    }, [showJoForm, rfaPdfUrl, selectedResultUrl]);

    function toggle(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    }

    function approveJo() {
        setApproving(true);
        router.post(
            `/head/${jobOrder.id}/approve`,
            {},
            {
                onFinish: () => {
                    setApproving(false);
                    setConfirmApprove(false);
                },
            },
        );
    }

    function sign() {
        form.post(`/head/${jobOrder.id}/sign`, {
            onFinish: () => setConfirmSign(false),
        });
    }

    function returnSelected() {
        setReturning(true);
        router.post(
            `/head/${jobOrder.id}/return`,
            {
                analysis_ids: selectedIds,
                review_notes: form.data.review_notes,
            },
            {
                onFinish: () => {
                    setReturning(false);
                    setConfirmReturn(false);
                },
            },
        );
    }

    const stepLabels = showJoForm
        ? (['Review JO / RFA', 'Approve JO', 'Receiving prints'] as const)
        : ([
              'Review results',
              'Release results',
              isSigned ? 'Released' : 'Or return lines',
          ] as const);

    const stepActive = showJoForm
        ? needsJoApproval
            ? 1
            : 2
        : isSigned
          ? 2
          : 1;

    const detailsRail = (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-1.5">
                {stepLabels.map((label, index) => (
                    <span
                        key={label}
                        className={cn(
                            'rounded-md px-2 py-0.5 text-[11px] font-medium',
                            index === stepActive
                                ? 'bg-nppc-900 text-white'
                                : index < stepActive
                                  ? 'bg-emerald-100 text-emerald-800'
                                  : 'bg-slate-100 text-slate-600',
                        )}
                    >
                        {index + 1}. {label}
                    </span>
                ))}
            </div>

            <LimsSection title="Customer" className="min-w-0">
                <dl className="space-y-1.5 text-sm">
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Name</dt>
                        <dd className="text-right font-medium">
                            {jobOrder.customer_name}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Contact</dt>
                        <dd className="max-w-[60%] text-right text-xs">
                            {[
                                jobOrder.customer_contact,
                                jobOrder.customer_email,
                            ]
                                .filter(Boolean)
                                .join(' · ') || '—'}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Class</dt>
                        <dd className="text-right">
                            {jobOrder.classification || '—'}
                        </dd>
                    </div>
                </dl>
            </LimsSection>

            <LimsSection
                title={`Samples (${jobOrder.samples?.length ?? 0})`}
                className="min-w-0"
            >
                <ul className="divide-y divide-slate-100 text-sm">
                    {(jobOrder.samples ?? []).slice(0, 5).map((sample, index) => (
                        <li key={sample.id ?? index} className="py-1.5 first:pt-0 last:pb-0">
                            <p className="font-medium leading-snug">
                                {sample.sample_code
                                    ? `${sample.sample_code} — `
                                    : ''}
                                {sample.description || `Sample ${index + 1}`}
                            </p>
                            {sample.matrix ? (
                                <p className="text-xs text-muted-foreground">
                                    {sample.matrix}
                                </p>
                            ) : null}
                        </li>
                    ))}
                    {(jobOrder.samples?.length ?? 0) > 5 && (
                        <li className="py-1 text-xs text-muted-foreground">
                            +{(jobOrder.samples?.length ?? 0) - 5} more on form
                        </li>
                    )}
                </ul>
            </LimsSection>

            <LimsSection title="Summary" className="min-w-0">
                <dl className="space-y-1.5 text-sm">
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Tests</dt>
                        <dd className="font-semibold text-nppc-900">
                            {resultsWithValues.length}/{jobOrder.analyses.length}{' '}
                            with values
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Total</dt>
                        <dd className="font-semibold text-emerald-800">
                            {money(jobOrder.total_cost)}
                        </dd>
                    </div>
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Completed</dt>
                        <dd className="text-right text-xs">
                            {jobOrder.completed_at || '—'}
                        </dd>
                    </div>
                    {!showJoForm && (
                        <div className="flex justify-between gap-2">
                            <dt className="text-muted-foreground">Return sel.</dt>
                            <dd
                                className={cn(
                                    'font-semibold',
                                    selectedIds.length > 0
                                        ? 'text-amber-800'
                                        : 'text-slate-700',
                                )}
                            >
                                {selectedIds.length}
                            </dd>
                        </div>
                    )}
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Signature</dt>
                        <dd className="max-w-[55%] text-right text-xs">
                            {isSigned
                                ? `Signed${jobOrder.reviewed_at ? ` · ${jobOrder.reviewed_at}` : ''}`
                                : 'Awaiting'}
                        </dd>
                    </div>
                </dl>
                {!showJoForm && (
                    <ul className="mt-2 max-h-28 space-y-0.5 overflow-y-auto border-t border-slate-100 pt-2 text-xs">
                        {jobOrder.analyses.map((line) => (
                            <li
                                key={line.id}
                                className="flex justify-between gap-2"
                            >
                                <span className="truncate">{line.name}</span>
                                <span className="shrink-0 tabular-nums text-slate-600">
                                    {line.result_value
                                        ? encodedResultLabel(line)
                                        : '—'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </LimsSection>

            {!showJoForm && !isSigned && (
                <LimsSection title="Return selected" className="min-w-0">
                    {selectedIds.length > 0 && (
                        <p className="mb-2 text-xs text-amber-800">
                            {selectedIds.length} line
                            {selectedIds.length === 1 ? '' : 's'} selected —
                            notes go with return.
                        </p>
                    )}
                    <div className="flex max-h-40 flex-col gap-1.5 overflow-y-auto">
                        {jobOrder.analyses.map((line) => (
                            <label
                                key={line.id}
                                className={cn(
                                    'flex items-start gap-2 rounded-lg border px-2 py-1.5 text-xs',
                                    selectedIds.includes(line.id)
                                        ? 'border-amber-300 bg-amber-50'
                                        : 'border-slate-200 bg-white',
                                )}
                            >
                                <input
                                    type="checkbox"
                                    className="mt-0.5"
                                    checked={selectedIds.includes(line.id)}
                                    onChange={() => toggle(line.id)}
                                />
                                <span className="min-w-0">
                                    <span className="block font-medium">
                                        {line.name}
                                    </span>
                                    <span className="text-muted-foreground">
                                        {line.result_value
                                            ? encodedResultLabel(line)
                                            : '—'}
                                    </span>
                                </span>
                            </label>
                        ))}
                    </div>
                </LimsSection>
            )}

            {!showJoForm && isSigned && (
                <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                    Results released
                    {jobOrder.reviewer_name
                        ? ` by ${jobOrder.reviewer_name}`
                        : ''}
                    {jobOrder.reviewed_at ? ` on ${jobOrder.reviewed_at}` : ''}.
                    Return is locked.
                </p>
            )}

            {(needsJoApproval || !showJoForm) && (
                <LimsSection title="Notes" className="min-w-0">
                    <Label htmlFor="review_notes" className="sr-only">
                        Notes (optional)
                    </Label>
                    <Textarea
                        id="review_notes"
                        className="min-h-20 text-sm"
                        placeholder={
                            showJoForm
                                ? 'Optional notes for this JO review…'
                                : 'End-of-day notes or reason for return…'
                        }
                        value={form.data.review_notes}
                        onChange={(e) =>
                            form.setData('review_notes', e.target.value)
                        }
                    />
                </LimsSection>
            )}
        </div>
    );

    const pdfPane = (
        <div
            className={cn(
                'flex min-h-0 flex-1 flex-col overflow-hidden',
                limsPanel,
            )}
        >
            <div className="shrink-0 border-b border-slate-200 bg-nppc-page px-3 py-1.5">
                <h2 className="truncate text-xs font-semibold text-nppc-900">
                    {showJoForm
                        ? 'Official Job Order / RFA'
                        : resultFormTitle || 'Official analysis result form'}
                </h2>
                {!showJoForm && resultTabs.length > 1 ? (
                    <div
                        className="mt-1.5 flex flex-wrap gap-1"
                        role="tablist"
                        aria-label="Result reports"
                    >
                        {resultTabs.map((tab, index) => {
                            const active =
                                selectedResultUrl === resultReportUrl
                                    ? index === 0
                                    : tab.report_url === selectedResultUrl;

                            return (
                                <button
                                    key={tab.report_url}
                                    type="button"
                                    role="tab"
                                    aria-selected={active}
                                    disabled={formLoading}
                                    className={cn(
                                        'rounded-md px-2.5 py-1 text-xs transition',
                                        active
                                            ? 'bg-nppc-900 text-white'
                                            : 'bg-slate-100 text-slate-700 hover:bg-slate-200',
                                    )}
                                    onClick={() => {
                                        if (!active) {
                                            setSelectedResultUrl(tab.report_url);
                                        }
                                    }}
                                >
                                    {tab.label}
                                </button>
                            );
                        })}
                    </div>
                ) : null}
            </div>
            <div className="min-h-0 flex-1 bg-slate-100 p-1.5 max-lg:min-h-[50vh]">
                {formLoading && (
                    <LoadingState
                        title="Loading controlled form…"
                        description={
                            showJoForm
                                ? 'Generating the Job Order PDF overlay'
                                : 'Generating the analysis result PDF overlay'
                        }
                        size="lg"
                        showDocumentPreview
                        className="h-full min-h-[50vh] rounded-lg border border-slate-200 bg-white lg:min-h-0"
                    />
                )}
                {!formLoading && formError && (
                    <div className="flex h-full min-h-[50vh] items-center justify-center rounded-lg border border-slate-200 bg-white px-6 text-center text-sm text-red-700 lg:min-h-0">
                        {formError}
                    </div>
                )}
                {!formLoading && formObjectUrl && (
                    <iframe
                        title={
                            showJoForm
                                ? `Job Order ${jobOrder.reference_no}`
                                : `Result form ${jobOrder.reference_no}`
                        }
                        src={formObjectUrl}
                        className="h-full min-h-[50vh] w-full rounded-lg border border-slate-200 bg-white lg:min-h-0"
                    />
                )}
            </div>
        </div>
    );

    const pageHeader = (
        <div className="min-w-0 space-y-2">
            <div>
                <Button
                    asChild
                    variant="ghost"
                    size="sm"
                    className="-ml-2 mb-0.5 h-8 text-slate-600"
                >
                    <Link
                        href={showJoForm ? '/head/jo' : '/head/results'}
                    >
                        ← Back to{' '}
                        {showJoForm ? 'JO Approval' : 'Results'}
                    </Link>
                </Button>
                <div className="flex flex-wrap items-center gap-2">
                    <h1 className="font-heading text-xl font-semibold text-nppc-900 sm:text-2xl">
                        {jobOrder.reference_no}
                    </h1>
                    <Badge
                        variant="outline"
                        className={
                            needsJoApproval
                                ? 'border-amber-200 bg-amber-50 text-amber-900'
                                : showJoForm
                                  ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                  : isSigned
                                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                    : 'border-nppc-200 bg-nppc-50 text-nppc-900'
                        }
                    >
                        {needsJoApproval
                            ? 'Pending JO approval'
                            : showJoForm
                              ? 'JO approved'
                              : isSigned
                                ? 'Results released'
                                : 'Pending result release'}
                    </Badge>
                </div>
                <p className="mt-0.5 text-sm text-muted-foreground">
                    {jobOrder.customer_name}
                    {jobOrder.company_name
                        ? ` · ${jobOrder.company_name}`
                        : ''}
                </p>
            </div>
            {(needsJoApproval || !showJoForm) && (
                <div className="flex flex-wrap gap-2">
                    {needsJoApproval ? (
                        <Button
                            type="button"
                            size="sm"
                            className="h-9 bg-nppc-900 hover:bg-nppc-700"
                            onClick={() => setConfirmApprove(true)}
                        >
                            Approve Job Order
                        </Button>
                    ) : (
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="h-9 border-slate-200"
                            title={
                                isSigned
                                    ? 'Open the dated result form'
                                    : 'Open the analysis result form'
                            }
                            onClick={() => setPreviewOpen(true)}
                        >
                            Preview
                        </Button>
                    )}
                </div>
            )}
        </div>
    );

    return (
        <>
            <Head title={`Head Analysis ${jobOrder.reference_no}`} />
            <LimsWorkspace className="gap-3 pb-0 lg:h-full lg:min-h-0 lg:overflow-hidden">
                <div className="hidden min-h-0 flex-1 gap-4 overflow-hidden lg:grid lg:grid-cols-[minmax(260px,300px)_minmax(0,1fr)] lg:items-stretch">
                    <aside className="flex min-h-0 flex-col gap-3 overflow-y-auto pr-0.5">
                        {pageHeader}
                        {detailsRail}
                    </aside>
                    <div className="flex min-h-0 flex-col">{pdfPane}</div>
                </div>

                <div className="flex flex-col gap-3 lg:hidden">
                    {pdfPane}
                    {pageHeader}
                    <div className={cn('overflow-hidden', limsPanel)}>
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-2 px-4 py-2.5 text-left"
                            onClick={() => setDetailsOpen((open) => !open)}
                            aria-expanded={detailsOpen}
                        >
                            <span className="text-sm font-semibold text-nppc-900">
                                Job details
                            </span>
                            <ChevronDown
                                className={cn(
                                    'size-4 text-slate-500 transition',
                                    detailsOpen && 'rotate-180',
                                )}
                            />
                        </button>
                        {detailsOpen ? (
                            <div className="border-t border-slate-100 p-4">
                                {detailsRail}
                            </div>
                        ) : null}
                    </div>
                </div>

                <div className="z-20 shrink-0 border-t border-slate-200 bg-white/95 py-3 max-lg:sticky max-lg:bottom-0 max-lg:-mx-4 max-lg:px-4 max-lg:shadow-[0_-8px_30px_rgba(26,54,148,0.08)] max-lg:backdrop-blur sm:max-lg:-mx-6 sm:max-lg:px-6 lg:-mx-6 lg:px-6">
                    <div className="flex w-full flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {needsJoApproval
                                ? 'Approve costing so Receiving can print 3 JO copies and send to analysts.'
                                : showJoForm
                                  ? 'This Job Order is already approved. Receiving can print JO copies and hand off to analysts.'
                                  : isSigned
                                    ? 'This file’s results are already released.'
                                    : 'Check the result form, then release results — or return selected lines.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {!showJoForm && (
                                <Button
                                    variant="outline"
                                    className="h-10 border-slate-200"
                                    disabled={
                                        isSigned ||
                                        form.processing ||
                                        returning ||
                                        selectedIds.length === 0
                                    }
                                    onClick={() => setConfirmReturn(true)}
                                >
                                    Return selected ({selectedIds.length})
                                </Button>
                            )}
                            {needsJoApproval ? (
                                <Button
                                    className="h-10 bg-nppc-900 hover:bg-nppc-700"
                                    onClick={() => setConfirmApprove(true)}
                                    disabled={approving}
                                >
                                    Approve Job Order
                                </Button>
                            ) : (
                                !showJoForm &&
                                !isSigned && (
                                    <Button
                                        className="h-10 bg-nppc-900 hover:bg-nppc-700"
                                        onClick={() => setConfirmSign(true)}
                                        disabled={form.processing || returning}
                                    >
                                        Release results
                                    </Button>
                                )
                            )}
                        </div>
                    </div>
                </div>
            </LimsWorkspace>

            <ConfirmDialog
                open={confirmApprove}
                onOpenChange={setConfirmApprove}
                title={`Approve Job Order ${jobOrder.reference_no}?`}
                description="Unlocks Receiving to print 3 JO copies (customer, accounting, Head file) and send the job to analysts."
                confirmLabel="Approve Job Order"
                processingLabel="Approving…"
                processing={approving}
                onConfirm={approveJo}
            />

            <ConfirmDialog
                open={confirmSign}
                onOpenChange={setConfirmSign}
                title={`Release results for ${jobOrder.reference_no}?`}
                description="Marks results ready for pickup, emails the customer when email is on file, and unlocks dated result print."
                confirmLabel="Release results"
                processingLabel="Releasing…"
                processing={form.processing}
                onConfirm={sign}
            />
            <ConfirmDialog
                open={confirmReturn}
                onOpenChange={setConfirmReturn}
                title={`Return ${selectedIds.length} selected line${selectedIds.length === 1 ? '' : 's'}?`}
                description="Selected analyses go back to the assigned analyst for correction. The job leaves the ready-for-pickup queue until they are completed again."
                confirmLabel="Confirm return"
                processingLabel="Returning…"
                variant="destructive"
                processing={returning}
                onConfirm={returnSelected}
            />

            <ResultReportPreview
                open={previewOpen}
                onOpenChange={setPreviewOpen}
                reportUrl={`/head/${jobOrder.id}/result-report`}
            />
        </>
    );
}

HeadShow.layout = (props: Props) => ({
    breadcrumbs: [
        props.viewMode === 'jo' || props.jobOrder?.needs_jo_approval
            ? { title: 'JO Approval', href: '/head/jo' }
            : { title: 'Results', href: '/head/results' },
        {
            title: props.jobOrder?.reference_no ?? 'Review',
            href: '#',
        },
    ],
});
