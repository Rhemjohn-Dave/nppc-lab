import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import LimsSection from '@/components/lims/lims-section';
import LimsWorkspace from '@/components/lims/lims-workspace';
import NextActionPanel from '@/components/lims/next-action-panel';
import WorkflowTimeline from '@/components/lims/workflow-timeline';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    hasPrintedThisSession,
    nextAction,
    receivingStatusBadgeClass,
    receivingStatusLabel,
    timelineSteps,
} from '@/lib/receiving-workflow';
import { cn } from '@/lib/utils';

type Props = {
    jobOrder: RequestForAnalysisData & {
        id: number;
        status: string;
        reviewed_at?: string | null;
        jo_approved_at?: string | null;
        jo_approver_name?: string | null;
        received_at?: string | null;
        can_print_rfa?: boolean;
        can_receive?: boolean;
        needs_jo_approval?: boolean;
        discount_amount?: string | number | null;
        discount_percent?: string | number | null;
        subtotal?: string | number | null;
    };
};

function money(value: number) {
    return `₱${value.toFixed(2)}`;
}

export default function ReceivingShow({ jobOrder }: Props) {
    const [confirmReceive, setConfirmReceive] = useState(false);
    const [receiving, setReceiving] = useState(false);
    const [printedThisSession, setPrintedThisSession] = useState(false);
    const isReviewed = Boolean(jobOrder.reviewed_at);
    const canPrintRfa = Boolean(jobOrder.can_print_rfa);
    const canReceive = Boolean(jobOrder.can_receive);
    const awaitingJoApproval =
        jobOrder.status === 'pending_jo_approval' ||
        jobOrder.status === 'priced';
    const [copies, setCopies] = useState(3);

    useEffect(() => {
        setPrintedThisSession(hasPrintedThisSession(jobOrder.id));
    }, [jobOrder.id]);

    const form = useForm({
        lines: jobOrder.analyses.map((line) => ({
            id: line.id,
            quantity: line.quantity,
            unit_price: Number(line.unit_price),
        })),
        discount_percent: Number(jobOrder.discount_percent ?? 0),
    });

    const subtotal = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) =>
                    sum +
                    Number(line.quantity || 0) * Number(line.unit_price || 0),
                0,
            ),
        [form.data.lines],
    );

    const discountPercent = Math.max(
        0,
        Math.min(100, Number(form.data.discount_percent || 0)),
    );
    const discountAmount = Math.round(subtotal * (discountPercent / 100) * 100) / 100;
    const estimatedTotal = Math.max(0, subtotal - discountAmount);

    const needsPricing = jobOrder.status === 'draft_submitted';
    const hasUnsavedPricing =
        JSON.stringify({
            lines: form.data.lines,
            discount_percent: Number(form.data.discount_percent || 0),
        }) !==
        JSON.stringify({
            lines: jobOrder.analyses.map((line) => ({
                id: line.id,
                quantity: line.quantity,
                unit_price: Number(line.unit_price),
            })),
            discount_percent: Number(jobOrder.discount_percent ?? 0),
        });

    const action = nextAction(jobOrder, { printedThisSession });
    const progress = timelineSteps(jobOrder, { printedThisSession });
    const statusLabel = receivingStatusLabel(jobOrder);

    const nextDescription = [
        action.steps[0] ?? null,
        printedThisSession && canPrintRfa
            ? 'JO print opened this session'
            : null,
        !canReceive
            ? 'Do not send to analysts until Head approves the Job Order.'
            : null,
    ]
        .filter(Boolean)
        .join(' · ');

    function updateLine(
        index: number,
        key: 'quantity' | 'unit_price',
        value: number,
    ) {
        const lines = [...form.data.lines];
        lines[index] = {
            ...lines[index],
            [key]: value,
        };
        form.setData('lines', lines);
    }

    function markReceived() {
        setReceiving(true);
        router.post(
            `/receiving/${jobOrder.id}/receive`,
            {},
            {
                onFinish: () => {
                    setReceiving(false);
                    setConfirmReceive(false);
                },
            },
        );
    }

    return (
        <>
            <Head title={`Receiving ${jobOrder.reference_no}`} />
            <LimsWorkspace>
                <div className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(320px,400px)] lg:items-start">
                    <div className="min-w-0 space-y-3">
                        <div>
                            <Button
                                asChild
                                variant="ghost"
                                size="sm"
                                className="-ml-2 mb-0.5 h-8 text-slate-600"
                            >
                                <Link href="/receiving">← Back to queue</Link>
                            </Button>
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="font-heading text-xl font-semibold text-[#1A3694] sm:text-2xl">
                                    {jobOrder.reference_no}
                                </h1>
                                <Badge
                                    variant="outline"
                                    className={receivingStatusBadgeClass(
                                        jobOrder.status,
                                        isReviewed,
                                    )}
                                >
                                    {statusLabel}
                                </Badge>
                            </div>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {jobOrder.customer_name}
                                {jobOrder.company_name
                                    ? ` · ${jobOrder.company_name}`
                                    : ''}
                            </p>
                        </div>

                        <WorkflowTimeline
                            title="Receiving progress"
                            steps={progress}
                        />

                        <LimsSection title="Customer" className="min-w-0">
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Name
                                    </dt>
                                    <dd className="text-right font-medium">
                                        {jobOrder.customer_name}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Contact
                                    </dt>
                                    <dd className="text-right text-xs sm:text-sm">
                                        {[
                                            jobOrder.customer_contact,
                                            jobOrder.customer_email,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Classification
                                    </dt>
                                    <dd className="text-right">
                                        {jobOrder.classification || '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Sampling site
                                    </dt>
                                    <dd className="max-w-[60%] text-right text-xs sm:text-sm">
                                        {jobOrder.sampling_site || '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Payment
                                    </dt>
                                    <dd className="text-right text-xs sm:text-sm">
                                        {[
                                            jobOrder.payment_mode_label,
                                            jobOrder.payment_terms_label,
                                        ]
                                            .filter(Boolean)
                                            .join(' · ') || '—'}
                                    </dd>
                                </div>
                            </dl>
                        </LimsSection>

                        <LimsSection
                            title={`Samples (${jobOrder.samples?.length ?? 0})`}
                            className="min-w-0"
                        >
                            <ul className="divide-y divide-slate-100 text-sm">
                                {(jobOrder.samples ?? []).map(
                                    (sample, index) => (
                                        <li
                                            key={sample.id ?? index}
                                            className="py-1.5 first:pt-0 last:pb-0"
                                        >
                                            <p className="font-medium">
                                                {sample.sample_code
                                                    ? `${sample.sample_code} — `
                                                    : ''}
                                                {sample.description ||
                                                    `Sample ${index + 1}`}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {[
                                                    sample.matrix,
                                                    sample.quantity != null
                                                        ? `${sample.quantity}${sample.unit ? ` ${sample.unit}` : ''}`
                                                        : null,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' · ') ||
                                                    'No matrix/qty'}
                                            </p>
                                        </li>
                                    ),
                                )}
                                {(jobOrder.samples?.length ?? 0) === 0 && (
                                    <li className="text-muted-foreground">
                                        No samples listed.
                                    </li>
                                )}
                            </ul>
                        </LimsSection>

                        <LimsSection title="Order" className="min-w-0">
                            <dl className="space-y-1.5 text-sm">
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Analyses
                                    </dt>
                                    <dd className="font-medium tabular-nums">
                                        {jobOrder.analyses.length}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Submitted
                                    </dt>
                                    <dd className="text-right text-xs sm:text-sm">
                                        {jobOrder.created_at ?? '—'}
                                    </dd>
                                </div>
                                <div className="flex justify-between gap-3">
                                    <dt className="text-muted-foreground">
                                        Head approved
                                    </dt>
                                    <dd className="max-w-[65%] text-right text-xs sm:text-sm">
                                        {jobOrder.jo_approved_at
                                            ? `${jobOrder.jo_approved_at}${
                                                  jobOrder.jo_approver_name
                                                      ? ` · ${jobOrder.jo_approver_name}`
                                                      : ''
                                              }`
                                            : '—'}
                                    </dd>
                                </div>
                            </dl>
                        </LimsSection>
                    </div>

                    <aside className="space-y-3 lg:sticky lg:top-3 lg:max-h-[calc(100svh-5.5rem)] lg:overflow-y-auto lg:overscroll-contain">
                        <NextActionPanel
                            className="sm:flex-col sm:items-stretch"
                            title={action.title}
                            description={nextDescription || null}
                            tone={
                                action.emphasize
                                    ? 'emphasis'
                                    : needsPricing || awaitingJoApproval
                                      ? 'warning'
                                      : 'neutral'
                            }
                            actions={
                                <div className="flex w-full flex-col gap-2">
                                    {canPrintRfa ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <label className="flex items-center gap-2 text-sm">
                                                Copies
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={20}
                                                    className="h-9 w-16"
                                                    value={copies}
                                                    onChange={(event) =>
                                                        setCopies(
                                                            Math.min(
                                                                20,
                                                                Math.max(
                                                                    1,
                                                                    Number(
                                                                        event
                                                                            .target
                                                                            .value,
                                                                    ) || 1,
                                                                ),
                                                            ),
                                                        )
                                                    }
                                                />
                                            </label>
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="h-9 flex-1"
                                            >
                                                <Link
                                                    href={`/receiving/${jobOrder.id}/print?copies=${copies}`}
                                                >
                                                    {printedThisSession ||
                                                    isReviewed
                                                        ? 'Reprint JO'
                                                        : 'Print JO'}
                                                </Link>
                                            </Button>
                                            <Button
                                                asChild
                                                variant="outline"
                                                className="h-9"
                                            >
                                                <a
                                                    href={`/receiving/${jobOrder.id}/pdf`}
                                                >
                                                    PDF
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {!isReviewed && (
                                        <Button
                                            className="h-9 w-full bg-[#1A3694] hover:bg-[#365BB0]"
                                            onClick={() =>
                                                setConfirmReceive(true)
                                            }
                                            disabled={!canReceive}
                                            title={
                                                !canReceive
                                                    ? awaitingJoApproval
                                                        ? 'Waiting for Head JO approval'
                                                        : 'Save pricing and wait for Head approval first'
                                                    : undefined
                                            }
                                        >
                                            Send to analysts
                                        </Button>
                                    )}
                                </div>
                            }
                        />

                        <form
                            className={cn(
                                'overflow-hidden rounded-xl border bg-white shadow-sm',
                                needsPricing
                                    ? 'border-amber-300/80 ring-1 ring-amber-200/60'
                                    : 'border-slate-200/80',
                            )}
                            onSubmit={(e) => {
                                e.preventDefault();
                                form.patch(
                                    `/receiving/${jobOrder.id}/pricing`,
                                );
                            }}
                        >
                            <div
                                className={cn(
                                    'border-b px-4 py-3',
                                    needsPricing
                                        ? 'bg-amber-50/90'
                                        : 'bg-[#eef3fb]',
                                )}
                            >
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <div>
                                        <h2 className="text-sm font-semibold text-[#1A3694]">
                                            Pricing
                                        </h2>
                                        <p className="mt-0.5 text-xs text-muted-foreground">
                                            {jobOrder.status ===
                                                'jo_approved' ||
                                            canPrintRfa ||
                                            canReceive
                                                ? 'Adjust quantities and unit prices if needed. Saving does not send the JO back to Head.'
                                                : awaitingJoApproval
                                                  ? 'Adjust prices if needed. Job is already with Head for JO approval.'
                                                  : 'Adjust quantities and unit prices, then save to send to Head for JO approval.'}
                                        </p>
                                    </div>
                                    <p
                                        className={cn(
                                            'rounded-md px-2 py-0.5 text-xs font-medium',
                                            hasUnsavedPricing
                                                ? 'bg-amber-100 text-amber-800'
                                                : 'bg-emerald-50 text-emerald-800',
                                        )}
                                    >
                                        {hasUnsavedPricing
                                            ? 'Unsaved changes'
                                            : 'Matches saved total'}
                                    </p>
                                </div>
                                <p className="font-heading mt-2 text-3xl font-semibold tabular-nums text-emerald-800">
                                    {money(estimatedTotal)}
                                </p>
                                <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                    <p className="flex justify-between gap-3">
                                        <span>Subtotal</span>
                                        <span className="tabular-nums">
                                            {money(subtotal)}
                                        </span>
                                    </p>
                                    {discountPercent > 0 ? (
                                        <p className="flex justify-between gap-3">
                                            <span>
                                                Discount ({discountPercent}%)
                                            </span>
                                            <span className="tabular-nums">
                                                −{money(discountAmount)}
                                            </span>
                                        </p>
                                    ) : null}
                                    <p>
                                        Saved on record:{' '}
                                        {money(
                                            Number(jobOrder.total_cost || 0),
                                        )}
                                    </p>
                                </div>
                            </div>

                            <div className="nppc-scroll divide-y divide-slate-100">
                                {form.data.lines.map((line, index) => {
                                    const meta = jobOrder.analyses[index];
                                    const lineTotal =
                                        Number(line.quantity || 0) *
                                        Number(line.unit_price || 0);

                                    return (
                                        <div
                                            key={line.id}
                                            className="space-y-2 px-4 py-3"
                                        >
                                            <div className="min-w-0">
                                                <p className="truncate text-sm font-medium text-slate-900">
                                                    {meta.name}
                                                </p>
                                                {meta.category_label ? (
                                                    <p className="text-xs text-muted-foreground">
                                                        {meta.category_label}
                                                    </p>
                                                ) : null}
                                            </div>
                                            <div className="grid grid-cols-[4.5rem_minmax(0,1fr)_auto] items-end gap-2">
                                                <div>
                                                    <label className="mb-0.5 block text-[11px] font-medium text-slate-500">
                                                        Qty
                                                    </label>
                                                    <Input
                                                        type="number"
                                                        min={1}
                                                        className="h-10 w-full"
                                                        value={line.quantity}
                                                        onChange={(e) =>
                                                            updateLine(
                                                                index,
                                                                'quantity',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div>
                                                    <label className="mb-0.5 block text-[11px] font-medium text-slate-500">
                                                        Unit price
                                                    </label>
                                                    <Input
                                                        type="number"
                                                        min={0}
                                                        step="0.01"
                                                        className="h-10 w-full"
                                                        value={line.unit_price}
                                                        onChange={(e) =>
                                                            updateLine(
                                                                index,
                                                                'unit_price',
                                                                Number(
                                                                    e.target
                                                                        .value,
                                                                ),
                                                            )
                                                        }
                                                    />
                                                </div>
                                                <div className="pb-2 text-right">
                                                    <p className="text-[11px] font-medium text-slate-500">
                                                        Line
                                                    </p>
                                                    <p className="text-sm font-semibold tabular-nums text-slate-800">
                                                        {money(lineTotal)}
                                                    </p>
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>

                            <div className="space-y-3 border-t bg-[#f8fafc] px-4 py-3">
                                <div>
                                    <label
                                        htmlFor="discount_percent"
                                        className="mb-0.5 block text-[11px] font-medium text-slate-500"
                                    >
                                        Discount (%)
                                    </label>
                                    <Input
                                        id="discount_percent"
                                        type="number"
                                        min={0}
                                        max={100}
                                        step="0.01"
                                        className="h-10 w-full"
                                        value={form.data.discount_percent}
                                        onChange={(e) =>
                                            form.setData(
                                                'discount_percent',
                                                Number(e.target.value),
                                            )
                                        }
                                    />
                                    {form.errors.discount_percent ? (
                                        <p className="mt-1 text-xs text-red-600">
                                            {form.errors.discount_percent}
                                        </p>
                                    ) : null}
                                </div>
                                <p
                                    className={cn(
                                        'text-xs',
                                        hasUnsavedPricing
                                            ? 'text-amber-700'
                                            : 'text-emerald-700',
                                    )}
                                >
                                    {hasUnsavedPricing
                                        ? 'Unsaved changes are visible only on this screen until you save pricing.'
                                        : 'All pricing changes are saved.'}
                                </p>
                                <Button
                                    type="submit"
                                    disabled={form.processing}
                                    className="h-10 w-full bg-[#1A3694] hover:bg-[#365BB0]"
                                >
                                    {form.processing
                                        ? 'Saving…'
                                        : 'Save pricing'}
                                </Button>
                            </div>
                        </form>
                    </aside>
                </div>
            </LimsWorkspace>

            <ConfirmDialog
                open={confirmReceive}
                onOpenChange={setConfirmReceive}
                title={`Send ${jobOrder.reference_no} to analysts?`}
                description="Samples are already on hand from intake. This assigns analyses to analysts and opens the job in their workspace. Print 3 JO copies for customer, accounting, and Head if you have not already."
                confirmLabel="Send to analysts"
                processingLabel="Sending…"
                processing={receiving}
                onConfirm={markReceived}
            >
                <div className="rounded-lg border bg-[#f8fafc] px-3 py-2 text-sm">
                    <p>
                        <span className="text-muted-foreground">
                            Customer:{' '}
                        </span>
                        {jobOrder.customer_name}
                    </p>
                    <p>
                        <span className="text-muted-foreground">
                            Estimated total:{' '}
                        </span>
                        {money(estimatedTotal)}
                    </p>
                    <p>
                        <span className="text-muted-foreground">Tests: </span>
                        {jobOrder.analyses.length}
                    </p>
                </div>
            </ConfirmDialog>
        </>
    );
}

ReceivingShow.layout = {
    breadcrumbs: [
        { title: 'Receiving', href: '/receiving' },
        { title: 'Job order', href: '#' },
    ],
};
