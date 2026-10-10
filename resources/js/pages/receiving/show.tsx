import { Head, Link, router, useForm } from '@inertiajs/react';
import { ChevronDown, ChevronRight } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import CustomerEmailLog from '@/components/email/customer-email-log';
import type { EmailLogSummary } from '@/components/email/customer-email-log';
import LimsWorkspace from '@/components/lims/lims-workspace';
import ReceivingPricingKpis from '@/components/receiving/receiving-pricing-kpis';
import ReceivingQuotationAudit from '@/components/receiving/receiving-quotation-audit';
import ReceivingSampleMatrix from '@/components/receiving/receiving-sample-matrix';
import type {
    PricingLine,
    SamplePackageGroup,
    SamplePricingGroup,
} from '@/components/receiving/types';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { hasPrintedThisSession } from '@/lib/receiving-workflow';

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
    emailLogs?: EmailLogSummary[];
};

function money(value: number) {
    return `₱${value.toFixed(2)}`;
}

function lineAmount(line: PricingLine | undefined): number {
    if (!line) {
        return 0;
    }

    return Number(line.quantity || 0) * Number(line.unit_price || 0);
}

function redistributePackagePrice(
    lines: PricingLine[],
    memberIds: number[],
    newPackagePrice: number,
): PricingLine[] {
    const members = lines.filter((line) => memberIds.includes(line.id));
    if (members.length === 0) {
        return lines;
    }

    const target = Math.max(0, Number(newPackagePrice) || 0);
    const weights = members.map((line) => {
        const amount = lineAmount(line);
        return amount > 0 ? amount : 1;
    });
    const weightSum = weights.reduce((sum, weight) => sum + weight, 0);
    const nextById = new Map<number, number>();

    let allocated = 0;
    members.forEach((line, index) => {
        const qty = Math.max(1, Number(line.quantity) || 1);
        if (index === members.length - 1) {
            const remainder = Math.round((target - allocated) * 100) / 100;
            nextById.set(line.id, Math.round((remainder / qty) * 100) / 100);
            return;
        }

        const share =
            Math.round(((target * weights[index]) / weightSum) * 100) / 100;
        allocated += share;
        nextById.set(line.id, Math.round((share / qty) * 100) / 100);
    });

    return lines.map((line) =>
        nextById.has(line.id)
            ? { ...line, unit_price: nextById.get(line.id)! }
            : line,
    );
}

export default function ReceivingShow({ jobOrder, emailLogs = [] }: Props) {
    const [confirmReceive, setConfirmReceive] = useState(false);
    const [receiving, setReceiving] = useState(false);
    const [printedThisSession, setPrintedThisSession] = useState(false);
    const [detailsOpen, setDetailsOpen] = useState(false);
    const isReviewed = Boolean(jobOrder.reviewed_at);
    const canPrintRfa = Boolean(jobOrder.can_print_rfa);
    const canReceive = Boolean(jobOrder.can_receive);
    const awaitingJoApproval =
        jobOrder.status === 'pending_jo_approval' ||
        jobOrder.status === 'priced';
    const [copies, setCopies] = useState(3);
    const samples = jobOrder.samples ?? [];
    const packages = jobOrder.packages ?? [];
    const [expandedSampleId, setExpandedSampleId] = useState<number | null>(
        samples[0]?.id ?? null,
    );

    useEffect(() => {
        setPrintedThisSession(hasPrintedThisSession(jobOrder.id));
        setExpandedSampleId(jobOrder.samples?.[0]?.id ?? null);
    }, [jobOrder.id]);

    useEffect(() => {
        if (
            expandedSampleId != null &&
            !samples.some((sample) => sample.id === expandedSampleId)
        ) {
            setExpandedSampleId(samples[0]?.id ?? null);
        }
    }, [samples, expandedSampleId]);

    const form = useForm({
        lines: jobOrder.analyses.map((line) => ({
            id: line.id,
            quantity: line.quantity,
            unit_price: Number(line.unit_price),
        })),
        discount_percent: Number(jobOrder.discount_percent ?? 0),
    });

    const linesById = useMemo(() => {
        const map = new Map<number, PricingLine>();
        for (const line of form.data.lines) {
            map.set(line.id, line);
        }
        return map;
    }, [form.data.lines]);

    const sampleGroups = useMemo((): SamplePricingGroup[] => {
        const analyses = jobOrder.analyses;

        return samples.map((sample, sampleIndex) => {
            const sampleAnalyses = analyses.filter(
                (line) => Number(line.sample_id) === Number(sample.id),
            );
            const samplePackages = packages.filter(
                (pkg) => Number(pkg.sample_id) === Number(sample.id),
            );

            const coveredTypeIds = new Set<number>();
            const packageGroups: SamplePackageGroup[] = samplePackages.map(
                (pkg) => {
                    const selected = new Set(
                        (pkg.selected_type_ids ?? []).map(Number),
                    );
                    const members = sampleAnalyses.filter((line) => {
                        const typeId = Number(line.analysis_type_id);
                        if (!selected.has(typeId)) {
                            return false;
                        }
                        coveredTypeIds.add(typeId);
                        return true;
                    });

                    return {
                        package: pkg,
                        members,
                        memberLineIds: members.map((member) => member.id),
                    };
                },
            );

            const individuals = sampleAnalyses.filter((line) => {
                const typeId = line.analysis_type_id;
                if (typeId == null) {
                    return true;
                }
                return !coveredTypeIds.has(Number(typeId));
            });

            return {
                sample,
                sampleIndex,
                controlNumber:
                    sample.control_number ??
                    sampleAnalyses[0]?.control_number ??
                    null,
                packages: packageGroups,
                individuals,
            };
        });
    }, [jobOrder.analyses, packages, samples]);

    const packagesCount = useMemo(
        () =>
            sampleGroups.reduce((sum, group) => sum + group.packages.length, 0),
        [sampleGroups],
    );
    const individualsCount = useMemo(
        () =>
            sampleGroups.reduce(
                (sum, group) => sum + group.individuals.length,
                0,
            ),
        [sampleGroups],
    );

    const subtotal = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) => sum + lineAmount(line),
                0,
            ),
        [form.data.lines],
    );

    const { packagesSubtotal, individualsSubtotal } = useMemo(() => {
        let packageTotal = 0;
        let individualTotal = 0;

        for (const group of sampleGroups) {
            for (const pkg of group.packages) {
                for (const memberId of pkg.memberLineIds) {
                    packageTotal += lineAmount(linesById.get(memberId));
                }
            }
            for (const line of group.individuals) {
                individualTotal += lineAmount(linesById.get(line.id));
            }
        }

        return {
            packagesSubtotal: packageTotal,
            individualsSubtotal: individualTotal,
        };
    }, [linesById, sampleGroups]);

    const discountPercent = Math.max(
        0,
        Math.min(100, Number(form.data.discount_percent || 0)),
    );
    const discountAmount =
        Math.round(subtotal * (discountPercent / 100) * 100) / 100;
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

    const contactLine =
        [jobOrder.customer_contact, jobOrder.customer_email]
            .filter(Boolean)
            .join(' · ') || null;

    const sampleRollups = useMemo(
        () =>
            sampleGroups.map((group) => {
                const ids = [
                    ...group.packages.flatMap((pkg) => pkg.memberLineIds),
                    ...group.individuals.map((line) => line.id),
                ];
                const amount = ids.reduce(
                    (sum, id) => sum + lineAmount(linesById.get(id)),
                    0,
                );
                return {
                    label: `Sample ${group.sampleIndex + 1}`,
                    amount,
                };
            }),
        [linesById, sampleGroups],
    );

    function updateLine(
        lineId: number,
        key: 'quantity' | 'unit_price',
        value: number,
    ) {
        form.setData(
            'lines',
            form.data.lines.map((line) =>
                line.id === lineId ? { ...line, [key]: value } : line,
            ),
        );
    }

    function updatePackagePrice(memberLineIds: number[], newPrice: number) {
        form.setData(
            'lines',
            redistributePackagePrice(
                form.data.lines,
                memberLineIds,
                newPrice,
            ),
        );
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
            <LimsWorkspace className="gap-4">
                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <button
                        type="button"
                        className="flex w-full items-center justify-between gap-3 px-4 py-2.5 text-left hover:bg-slate-50"
                        onClick={() => setDetailsOpen((open) => !open)}
                        aria-expanded={detailsOpen}
                    >
                        <p className="text-[11px] text-slate-500">
                            {detailsOpen
                                ? 'Customer, order, and sample details'
                                : 'Details collapsed — expand to review'}
                        </p>
                        <span className="inline-flex items-center gap-1 text-xs text-slate-600">
                            {detailsOpen ? 'Hide' : 'Show'}
                            {detailsOpen ? (
                                <ChevronDown className="size-3.5" />
                            ) : (
                                <ChevronRight className="size-3.5" />
                            )}
                        </span>
                    </button>
                    {detailsOpen ? (
                        <div className="border-t border-slate-100 px-4 py-3">
                            <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
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
                                            {contactLine || '—'}
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

                                <ul className="divide-y divide-slate-100 text-sm sm:col-span-2 lg:col-span-1">
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
                                                <p className="text-[11px] text-slate-500">
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
                            </div>
                        </div>
                    ) : null}
                </div>

                <ReceivingPricingKpis
                    samplesCount={samples.length}
                    analysesCount={jobOrder.analyses.length}
                    packagesCount={packagesCount}
                    individualsCount={individualsCount}
                />

                <form
                    className="grid gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(280px,340px)] lg:items-start"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/receiving/${jobOrder.id}/pricing`);
                    }}
                >
                    <ReceivingSampleMatrix
                        sampleGroups={sampleGroups}
                        expandedSampleId={expandedSampleId}
                        onToggleSample={setExpandedSampleId}
                        linesById={linesById}
                        money={money}
                        lineAmount={lineAmount}
                        updateLine={updateLine}
                        updatePackagePrice={updatePackagePrice}
                        needsPricing={needsPricing}
                    />

                    <aside className="lg:sticky lg:top-3">
                        <ReceivingQuotationAudit
                            estimatedTotal={estimatedTotal}
                            packagesSubtotal={packagesSubtotal}
                            individualsSubtotal={individualsSubtotal}
                            subtotal={subtotal}
                            discountPercent={discountPercent}
                            discountAmount={discountAmount}
                            savedTotal={Number(jobOrder.total_cost || 0)}
                            hasUnsavedPricing={hasUnsavedPricing}
                            discountPercentValue={form.data.discount_percent}
                            discountPercentError={
                                form.errors.discount_percent
                            }
                            onDiscountChange={(value) =>
                                form.setData('discount_percent', value)
                            }
                            processing={form.processing}
                            money={money}
                            sampleRollups={sampleRollups}
                            secondaryActions={
                                <>
                                    {canPrintRfa ? (
                                        <div className="flex flex-wrap items-center gap-2">
                                            <label className="flex items-center gap-2 text-xs text-slate-600">
                                                Copies
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    max={20}
                                                    className="h-8 w-14"
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
                                                size="sm"
                                                variant="outline"
                                                className="h-8 flex-1 border-slate-200 text-xs shadow-xs"
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
                                                size="sm"
                                                variant="outline"
                                                className="h-8 border-slate-200 text-xs shadow-xs"
                                            >
                                                <a
                                                    href={`/receiving/${jobOrder.id}/pdf`}
                                                >
                                                    PDF
                                                </a>
                                            </Button>
                                        </div>
                                    ) : null}
                                    {!isReviewed ? (
                                        <Button
                                            type="button"
                                            size="sm"
                                            variant="outline"
                                            className="h-8 w-full border-slate-200 text-xs shadow-xs"
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
                                    ) : null}
                                </>
                            }
                        />
                        <CustomerEmailLog
                            logs={emailLogs}
                            canResend
                            className="mt-3"
                        />
                    </aside>
                </form>
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
                <div className="rounded-lg border bg-nppc-page px-3 py-2 text-sm">
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
