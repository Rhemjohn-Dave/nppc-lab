import { Head, Link, router, useForm } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { useEffect, useState, type ReactNode } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import ResultReportPreview, {
    loadResultReport,
} from '@/components/result-report-preview';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import LoadingState from '@/components/ui/loading-state';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';
import { ChevronDown } from 'lucide-react';

type Props = {
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

function RailHeading({ children }: { children: ReactNode }) {
    return (
        <h2 className="text-[10px] font-semibold tracking-wide text-[#365BB0] uppercase">
            {children}
        </h2>
    );
}

export default function HeadShow({ jobOrder }: Props) {
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
    const isSigned = Boolean(jobOrder.is_signed || jobOrder.reviewed_at);
    const rfaPdfUrl = `/head/${jobOrder.id}/pdf?inline=1&results=0`;
    const resultReportUrl = `/head/${jobOrder.id}/result-report`;
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
                if (needsJoApproval) {
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
                    const report = await loadResultReport(resultReportUrl);

                    if (cancelled) {
                        return;
                    }

                    createdUrl = URL.createObjectURL(report.blob);
                    setResultFormTitle(report.title);
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
                        : needsJoApproval
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
    }, [needsJoApproval, rfaPdfUrl, resultReportUrl]);

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

    const stepLabels = needsJoApproval
        ? (['Review JO / RFA', 'Approve JO', 'Receiving prints'] as const)
        : ([
              'Review results',
              'Release results',
              isSigned ? 'Released' : 'Or return lines',
          ] as const);

    const stepActive = needsJoApproval ? 1 : isSigned ? 2 : 1;

    const detailsRail = (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap gap-1.5">
                {stepLabels.map((label, index) => (
                    <span
                        key={label}
                        className={cn(
                            'rounded-full px-2 py-0.5 text-[11px] font-medium',
                            index === stepActive
                                ? 'bg-[#1A3694] text-white'
                                : index < stepActive
                                  ? 'bg-emerald-100 text-emerald-800'
                                  : 'bg-slate-100 text-slate-600',
                        )}
                    >
                        {index + 1}. {label}
                    </span>
                ))}
            </div>

            <section className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                <RailHeading>Customer</RailHeading>
                <dl className="mt-2 space-y-1.5 text-sm">
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
            </section>

            <section className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                <RailHeading>
                    Samples ({jobOrder.samples?.length ?? 0})
                </RailHeading>
                <ul className="mt-2 divide-y divide-slate-100 text-sm">
                    {(jobOrder.samples ?? []).slice(0, 5).map((sample, index) => (
                        <li key={sample.id ?? index} className="py-1.5">
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
            </section>

            <section className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                <RailHeading>Summary</RailHeading>
                <dl className="mt-2 space-y-1.5 text-sm">
                    <div className="flex justify-between gap-2">
                        <dt className="text-muted-foreground">Tests</dt>
                        <dd className="font-semibold text-[#1A3694]">
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
                    {!needsJoApproval && (
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
                {!needsJoApproval && (
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
            </section>

            {!needsJoApproval && !isSigned && (
                <section className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                    <RailHeading>Return selected</RailHeading>
                    {selectedIds.length > 0 && (
                        <p className="mt-1.5 text-xs text-amber-800">
                            {selectedIds.length} line
                            {selectedIds.length === 1 ? '' : 's'} selected —
                            notes go with return.
                        </p>
                    )}
                    <div className="mt-2 flex max-h-40 flex-col gap-1.5 overflow-y-auto">
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
                </section>
            )}

            {!needsJoApproval && isSigned && (
                <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-xs text-emerald-900">
                    Results released
                    {jobOrder.reviewer_name
                        ? ` by ${jobOrder.reviewer_name}`
                        : ''}
                    {jobOrder.reviewed_at ? ` on ${jobOrder.reviewed_at}` : ''}.
                    Return is locked.
                </p>
            )}

            <section className="rounded-xl border border-slate-200/80 bg-white p-3 shadow-sm">
                <Label htmlFor="review_notes" className="text-xs">
                    Notes (optional)
                </Label>
                <Textarea
                    id="review_notes"
                    className="mt-1 min-h-20 text-sm"
                    placeholder="End-of-day notes or reason for return…"
                    value={form.data.review_notes}
                    onChange={(e) =>
                        form.setData('review_notes', e.target.value)
                    }
                />
            </section>
        </div>
    );

    const pdfPane = (
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
            <div className="shrink-0 border-b bg-[#f8fafc] px-3 py-1.5">
                <h2 className="truncate text-xs font-semibold text-[#1A3694]">
                    {needsJoApproval
                        ? 'Official Job Order / RFA'
                        : resultFormTitle || 'Official analysis result form'}
                </h2>
            </div>
            <div className="min-h-0 flex-1 bg-slate-100 p-1.5">
                {formLoading && (
                    <LoadingState
                        title="Loading controlled form…"
                        description={
                            needsJoApproval
                                ? 'Generating the Job Order PDF overlay'
                                : 'Generating the analysis result PDF overlay'
                        }
                        size="lg"
                        showDocumentPreview
                        className="min-h-[calc(100svh-13rem)] rounded-lg border bg-white lg:min-h-[calc(100svh-11rem)]"
                    />
                )}
                {!formLoading && formError && (
                    <div className="flex min-h-[40vh] items-center justify-center rounded-lg border bg-white px-6 text-center text-sm text-red-700 lg:min-h-[calc(100svh-11rem)]">
                        {formError}
                    </div>
                )}
                {!formLoading && formObjectUrl && (
                    <iframe
                        title={
                            needsJoApproval
                                ? `Job Order ${jobOrder.reference_no}`
                                : `Result form ${jobOrder.reference_no}`
                        }
                        src={formObjectUrl}
                        className="min-h-[calc(100svh-13rem)] w-full rounded-lg border bg-white lg:min-h-[calc(100svh-11rem)]"
                    />
                )}
            </div>
        </div>
    );

    return (
        <>
            <Head title={`Head Analysis ${jobOrder.reference_no}`} />
            <LimsWorkspace className="gap-2 pt-2 pb-0">
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                    <div className="flex min-w-0 flex-wrap items-center gap-x-2 gap-y-1">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 h-8 px-2 text-slate-600"
                        >
                            <Link
                                href={
                                    needsJoApproval ? '/head/jo' : '/head/results'
                                }
                            >
                                ← {needsJoApproval ? 'JO' : 'Results'}
                            </Link>
                        </Button>
                        <h1 className="font-heading text-lg font-semibold text-[#1A3694]">
                            {jobOrder.reference_no}
                        </h1>
                        <Badge
                            variant="outline"
                            className={
                                needsJoApproval
                                    ? 'border-amber-200 bg-amber-50 text-amber-900'
                                    : isSigned
                                      ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                      : 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]'
                            }
                        >
                            {needsJoApproval
                                ? 'Pending JO approval'
                                : isSigned
                                  ? 'Results released'
                                  : 'Pending result release'}
                        </Badge>
                        <p className="max-w-[18rem] truncate text-xs text-muted-foreground sm:max-w-md">
                            {jobOrder.customer_name}
                            {jobOrder.company_name
                                ? ` · ${jobOrder.company_name}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex shrink-0 flex-wrap gap-2">
                        {needsJoApproval ? (
                            <Button
                                type="button"
                                size="sm"
                                className="h-8 bg-[#1A3694] hover:bg-[#365BB0]"
                                onClick={() => setConfirmApprove(true)}
                            >
                                Approve Job Order
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="h-8"
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
                </div>

                <div className="hidden gap-3 lg:grid lg:grid-cols-[minmax(260px,300px)_minmax(0,1fr)] lg:items-start">
                    <aside className="max-h-[calc(100svh-8.5rem)] overflow-y-auto pr-0.5">
                        {detailsRail}
                    </aside>
                    {pdfPane}
                </div>

                {/* Mobile: PDF first, details collapsible */}
                <div className="flex flex-col gap-3 lg:hidden">
                    {pdfPane}
                    <div className="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm">
                        <button
                            type="button"
                            className="flex w-full items-center justify-between gap-2 px-3 py-2.5 text-left"
                            onClick={() => setDetailsOpen((open) => !open)}
                            aria-expanded={detailsOpen}
                        >
                            <span className="text-sm font-semibold text-[#1A3694]">
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
                            <div className="border-t border-slate-100 p-3">
                                {detailsRail}
                            </div>
                        ) : null}
                    </div>
                </div>

                <div className="sticky bottom-0 z-20 -mx-4 border-t border-slate-200 bg-white/95 px-4 py-3 shadow-[0_-8px_30px_rgba(15,42,120,0.08)] backdrop-blur">
                    <div className="flex w-full flex-wrap items-center justify-between gap-3">
                        <p className="text-sm text-muted-foreground">
                            {needsJoApproval
                                ? 'Approve costing so Receiving can print 3 JO copies and send to analysts.'
                                : isSigned
                                  ? 'This file’s results are already released.'
                                  : 'Check the result form, then release results — or return selected lines.'}
                        </p>
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="destructive"
                                className="h-10"
                                disabled={
                                    needsJoApproval ||
                                    isSigned ||
                                    form.processing ||
                                    returning ||
                                    selectedIds.length === 0
                                }
                                onClick={() => setConfirmReturn(true)}
                            >
                                Return selected ({selectedIds.length})
                            </Button>
                            {needsJoApproval ? (
                                <Button
                                    className="h-10 bg-[#1A3694] hover:bg-[#365BB0]"
                                    onClick={() => setConfirmApprove(true)}
                                    disabled={approving}
                                >
                                    Approve Job Order
                                </Button>
                            ) : (
                                !isSigned && (
                                    <Button
                                        className="h-10 bg-[#1A3694] hover:bg-[#365BB0]"
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

HeadShow.layout = {
    breadcrumbs: [
        { title: 'Head Analysis', href: '/head' },
        { title: 'Form', href: '#' },
    ],
};
