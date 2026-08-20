import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
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
import { cn } from '@/lib/utils';

type Props = {
    jobOrder: RequestForAnalysisData & {
        id: number;
        status: string;
        reviewed_at?: string | null;
    };
};

function money(value: number) {
    return `₱${value.toFixed(2)}`;
}

export default function ReceivingShow({ jobOrder }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [confirmReceive, setConfirmReceive] = useState(false);
    const [receiving, setReceiving] = useState(false);
    const isReviewed = Boolean(jobOrder.reviewed_at);
    const [copies, setCopies] = useState(isReviewed ? 3 : 2);

    const form = useForm({
        lines: jobOrder.analyses.map((line) => ({
            id: line.id,
            quantity: line.quantity,
            unit_price: Number(line.unit_price),
        })),
    });

    const estimatedTotal = useMemo(
        () =>
            form.data.lines.reduce(
                (sum, line) =>
                    sum + Number(line.quantity || 0) * Number(line.unit_price || 0),
                0,
            ),
        [form.data.lines],
    );

    const needsPricing = jobOrder.status === 'draft_submitted';
    const isPriced = jobOrder.status === 'priced';
    const hasUnsavedPricing =
        JSON.stringify(form.data.lines) !==
        JSON.stringify(
            jobOrder.analyses.map((line) => ({
                id: line.id,
                quantity: line.quantity,
                unit_price: Number(line.unit_price),
            })),
        );

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
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 mb-1 text-slate-600"
                        >
                            <Link href="/receiving">← Back to queue</Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                {jobOrder.reference_no}
                            </h1>
                            <Badge
                                variant="outline"
                                className={cn(
                                    isPriced
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        : 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]',
                                )}
                            >
                                {needsPricing
                                    ? 'Needs pricing'
                                    : jobOrder.status_label}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {jobOrder.customer_name}
                            {jobOrder.company_name
                                ? ` · ${jobOrder.company_name}`
                                : ''}
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        {isReviewed ? (
                            <>
                                <label className="flex items-center gap-2 text-sm">
                                    Copies
                                    <Input
                                        type="number"
                                        min={1}
                                        max={20}
                                        className="w-20"
                                        value={copies}
                                        onChange={(event) =>
                                            setCopies(
                                                Math.min(
                                                    20,
                                                    Math.max(
                                                        1,
                                                        Number(event.target.value) ||
                                                            1,
                                                    ),
                                                ),
                                            )
                                        }
                                    />
                                </label>
                                <Button asChild variant="outline">
                                    <Link
                                        href={`/receiving/${jobOrder.id}/print?copies=${copies}`}
                                    >
                                        Print reviewed RFA
                                    </Link>
                                </Button>
                                <Button asChild variant="outline">
                                    <a href={`/receiving/${jobOrder.id}/pdf`}>
                                        Download PDF
                                    </a>
                                </Button>
                            </>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                RFA printing is available after Head releases
                                the results.
                            </p>
                        )}
                        {!isReviewed && (
                            <Button
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                                onClick={() => setConfirmReceive(true)}
                                disabled={!isPriced}
                                title={
                                    !isPriced
                                        ? 'Save pricing first'
                                        : undefined
                                }
                            >
                                Mark received
                            </Button>
                        )}
                    </div>
                </div>

                {!isPriced && (
                    <p className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                        Save pricing first (Step 1). Analysts only see this job
                        after you finalize pricing and mark it received.
                    </p>
                )}

                <div className="grid gap-2 rounded-xl border bg-[#f8fafc] p-3 text-sm sm:grid-cols-3">
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            needsPricing
                                ? 'bg-[#1A3694] text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 1
                        </p>
                        <p className="font-medium">Set & save pricing</p>
                    </div>
                    <div className="rounded-lg bg-white px-3 py-2 text-slate-700">
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            Step 2
                        </p>
                        <p className="font-medium">Print Request for Analysis</p>
                    </div>
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            isPriced
                                ? 'bg-emerald-700 text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 3
                        </p>
                        <p className="font-medium">Mark samples received</p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <section className="rounded-xl border bg-white p-4">
                        <h2 className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Customer
                        </h2>
                        <dl className="mt-3 space-y-2 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Name</dt>
                                <dd className="text-right font-medium">
                                    {jobOrder.customer_name}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Contact
                                </dt>
                                <dd className="text-right">
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
                                    Address
                                </dt>
                                <dd className="max-w-[60%] text-right">
                                    {jobOrder.customer_address || '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Ownership
                                </dt>
                                <dd className="text-right">
                                    {jobOrder.ownership_type || '—'}
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
                        </dl>
                    </section>

                    <section className="rounded-xl border bg-white p-4">
                        <h2 className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Samples ({jobOrder.samples?.length ?? 0})
                        </h2>
                        <ul className="mt-3 space-y-2 text-sm">
                            {(jobOrder.samples ?? []).map((sample, index) => (
                                <li
                                    key={sample.id ?? index}
                                    className="rounded-lg border border-slate-100 bg-[#f8fafc] px-3 py-2"
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
                                            .join(' · ') || 'No matrix/qty'}
                                    </p>
                                </li>
                            ))}
                            {(jobOrder.samples?.length ?? 0) === 0 && (
                                <li className="text-muted-foreground">
                                    No samples listed.
                                </li>
                            )}
                        </ul>
                    </section>
                </div>

                <div className="grid gap-4 xl:grid-cols-4">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Submitted
                        </p>
                        <p className="mt-1 font-medium text-slate-900">
                            {jobOrder.created_at ?? '—'}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Samples</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {jobOrder.samples?.length ?? 0}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">Tests</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {jobOrder.analyses.length}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Current total
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {money(estimatedTotal)}
                        </p>
                    </div>
                </div>

                <form
                    className="overflow-hidden rounded-xl border bg-white"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.patch(`/receiving/${jobOrder.id}/pricing`);
                    }}
                >
                    <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-[#f8fafc] px-4 py-3">
                        <div>
                            <h2 className="font-semibold text-[#1A3694]">
                                Pricing
                            </h2>
                            <p className="text-xs text-muted-foreground">
                                Adjust quantities and unit prices, then save
                                before marking received.
                            </p>
                        </div>
                        <div className="text-right">
                            <p className="text-sm font-semibold text-[#1A3694]">
                                Estimated total: {money(estimatedTotal)}
                            </p>
                            <p className="text-xs text-muted-foreground">
                                {hasUnsavedPricing
                                    ? 'You have unsaved pricing changes.'
                                    : 'Pricing matches the saved job order total.'}
                            </p>
                        </div>
                    </div>

                    <div className="overflow-x-auto">
                        <table className="w-full text-sm">
                            <thead className="bg-white text-left">
                                <tr className="border-b">
                                    <th className="px-4 py-3 font-medium text-slate-600">
                                        Analysis
                                    </th>
                                    <th className="px-4 py-3 font-medium text-slate-600">
                                        Qty
                                    </th>
                                    <th className="px-4 py-3 font-medium text-slate-600">
                                        Unit price
                                    </th>
                                    <th className="px-4 py-3 text-right font-medium text-slate-600">
                                        Line total
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {form.data.lines.map((line, index) => {
                                    const meta = jobOrder.analyses[index];
                                    const lineTotal =
                                        Number(line.quantity || 0) *
                                        Number(line.unit_price || 0);

                                    return (
                                        <tr
                                            key={line.id}
                                            className="border-t"
                                        >
                                            <td className="px-4 py-3">
                                                <p className="font-medium">
                                                    {meta.name}
                                                </p>
                                                {meta.category_label && (
                                                    <p className="text-xs text-muted-foreground">
                                                        {meta.category_label}
                                                    </p>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    min={1}
                                                    className="h-10 w-20"
                                                    value={line.quantity}
                                                    onChange={(e) =>
                                                        updateLine(
                                                            index,
                                                            'quantity',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <Input
                                                    type="number"
                                                    min={0}
                                                    step="0.01"
                                                    className="h-10 w-28"
                                                    value={line.unit_price}
                                                    onChange={(e) =>
                                                        updateLine(
                                                            index,
                                                            'unit_price',
                                                            Number(
                                                                e.target.value,
                                                            ),
                                                        )
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-right tabular-nums">
                                                {money(lineTotal)}
                                            </td>
                                        </tr>
                                    );
                                })}
                            </tbody>
                        </table>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-3 border-t bg-[#f8fafc] px-4 py-3">
                        <div className="space-y-1 text-sm">
                            <p className="text-muted-foreground">
                                Saved total on record:{' '}
                                <span className="font-medium text-slate-800">
                                    {money(Number(jobOrder.total_cost || 0))}
                                </span>
                            </p>
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
                        </div>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                        >
                            {form.processing
                                ? 'Saving…'
                                : 'Save pricing'}
                        </Button>
                    </div>
                </form>
            </div>

            <Dialog open={confirmReceive} onOpenChange={setConfirmReceive}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Mark {jobOrder.reference_no} received?
                        </DialogTitle>
                        <DialogDescription>
                            Pricing is finalized. This assigns analyses to
                            analysts and opens the job in their workspace.
                            Print the Request for Analysis form if needed.
                        </DialogDescription>
                    </DialogHeader>
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
                            <span className="text-muted-foreground">
                                Tests:{' '}
                            </span>
                            {jobOrder.analyses.length}
                        </p>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmReceive(false)}
                            disabled={receiving}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={markReceived}
                            disabled={receiving}
                        >
                            {receiving ? 'Receiving…' : 'Confirm received'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

ReceivingShow.layout = {
    breadcrumbs: [
        { title: 'Receiving', href: '/receiving' },
        { title: 'Job order', href: '#' },
    ],
};
