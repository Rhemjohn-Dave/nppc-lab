import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import RequestForAnalysisForm from '@/components/request-for-analysis-form';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import ResultReportPreview from '@/components/result-report-preview';
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
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

type Props = {
    jobOrder: RequestForAnalysisData & {
        id: number;
        status?: string;
        is_signed?: boolean;
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

export default function HeadShow({ jobOrder }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [confirmSign, setConfirmSign] = useState(false);
    const [confirmReturn, setConfirmReturn] = useState(false);
    const [returning, setReturning] = useState(false);
    const [previewOpen, setPreviewOpen] = useState(false);
    const form = useForm({
        review_notes: '',
    });

    const isSigned = Boolean(jobOrder.is_signed || jobOrder.reviewed_at);
    const resultsWithValues = jobOrder.analyses.filter(
        (line) => line.result_value != null && String(line.result_value).trim() !== '',
    );

    function toggle(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
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

    return (
        <>
            <Head title={`Head Analysis ${jobOrder.reference_no}`} />
            <div className="flex flex-col gap-5 p-4 pb-28">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 mb-1 text-slate-600"
                        >
                            <Link href="/head">← Back to signing queue</Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                                {jobOrder.reference_no}
                            </h1>
                            <Badge
                                variant="outline"
                                className={
                                    isSigned
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        : 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]'
                                }
                            >
                                {isSigned
                                    ? 'Signed'
                                    : 'Pending review · awaiting Head signature'}
                            </Badge>
                        </div>
                        <p className="mt-1 text-sm text-muted-foreground">
                            {jobOrder.customer_name}
                            {jobOrder.company_name
                                ? ` · ${jobOrder.company_name}`
                                : ''}
                            {' · '}
                            Official form with analyst results
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={!isSigned}
                            title={
                                isSigned
                                    ? 'Open the dated result form'
                                    : 'Available after you release the results'
                            }
                            onClick={() => setPreviewOpen(true)}
                        >
                            Preview result report
                        </Button>
                    </div>
                </div>

                <div className="grid gap-2 rounded-xl border bg-[#f8fafc] p-3 text-sm sm:grid-cols-3">
                    <div className="rounded-lg bg-white px-3 py-2 text-slate-700">
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            Step 1
                        </p>
                        <p className="font-medium">Review results on form</p>
                    </div>
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            !isSigned
                                ? 'bg-[#1A3694] text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 2
                        </p>
                        <p className="font-medium">Sign finished file</p>
                    </div>
                    <div
                        className={cn(
                            'rounded-lg px-3 py-2',
                            isSigned
                                ? 'bg-emerald-700 text-white'
                                : 'bg-white text-slate-700',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide uppercase opacity-80">
                            Step 3
                        </p>
                        <p className="font-medium">
                            {isSigned ? 'Signed for the day' : 'Or return lines'}
                        </p>
                    </div>
                </div>

                <div className="grid gap-4 lg:grid-cols-3">
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
                            {(jobOrder.samples ?? []).slice(0, 4).map(
                                (sample, index) => (
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
                                        {sample.matrix && (
                                            <p className="text-xs text-muted-foreground">
                                                {sample.matrix}
                                            </p>
                                        )}
                                    </li>
                                ),
                            )}
                            {(jobOrder.samples?.length ?? 0) > 4 && (
                                <li className="text-xs text-muted-foreground">
                                    +{(jobOrder.samples?.length ?? 0) - 4} more
                                    on the form
                                </li>
                            )}
                        </ul>
                    </section>

                    <section className="rounded-xl border bg-white p-4">
                        <h2 className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Results summary
                        </h2>
                        <p className="mt-3 text-sm">
                            <span className="font-heading text-2xl font-semibold text-[#1A3694]">
                                {resultsWithValues.length}
                            </span>
                            <span className="text-muted-foreground">
                                {' '}
                                / {jobOrder.analyses.length} with values
                            </span>
                        </p>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Total {money(jobOrder.total_cost)} · Customer
                            already notified if email is on file
                        </p>
                        <ul className="mt-3 max-h-36 space-y-1 overflow-y-auto text-xs">
                            {jobOrder.analyses.map((line) => (
                                <li
                                    key={line.id}
                                    className="flex justify-between gap-2 border-t border-slate-100 py-1"
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
                    </section>
                </div>

                <div className="grid gap-4 xl:grid-cols-4">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Completed
                        </p>
                        <p className="mt-1 font-medium text-slate-900">
                            {jobOrder.completed_at || '—'}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Result lines
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {jobOrder.analyses.length}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-amber-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Selected for return
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-amber-800">
                            {selectedIds.length}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">
                            Signature state
                        </p>
                        <p className="mt-1 font-medium text-slate-900">
                            {isSigned
                                ? `Signed${jobOrder.reviewed_at ? ` on ${jobOrder.reviewed_at}` : ''}`
                                : 'Awaiting signature'}
                        </p>
                    </div>
                </div>

                {selectedIds.length > 0 && (
                    <div className="rounded-xl border border-amber-200 bg-amber-50 px-4 py-3">
                        <p className="text-sm font-medium text-amber-900">
                            {selectedIds.length} analysis line
                            {selectedIds.length === 1 ? '' : 's'} selected for
                            correction
                        </p>
                        <p className="mt-1 text-xs text-amber-800">
                            Review notes will be sent with the return action.
                            Returned lines leave the ready-for-pickup queue
                            until analysts complete them again.
                        </p>
                    </div>
                )}

                <div className="overflow-hidden rounded-xl border bg-white">
                    <div className="border-b bg-[#f8fafc] px-4 py-3">
                        <h2 className="font-semibold text-[#1A3694]">
                            Official Request for Analysis
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Select result lines below if you need to return them
                            to an analyst for correction.
                        </p>
                    </div>
                    <div className="p-4">
                        <RequestForAnalysisForm
                            jobOrder={jobOrder}
                            showResults
                            selectable
                            selectedIds={selectedIds}
                            onToggle={toggle}
                            showPrintButton={false}
                        />
                    </div>
                </div>

                <div className="space-y-3 rounded-xl border bg-white p-4">
                    <div>
                        <Label htmlFor="review_notes">Notes (optional)</Label>
                        <Textarea
                            id="review_notes"
                            className="mt-1"
                            placeholder="End-of-day notes or reason for return…"
                            value={form.data.review_notes}
                            onChange={(e) =>
                                form.setData('review_notes', e.target.value)
                            }
                        />
                    </div>
                    {isSigned && (
                        <p className="rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-2 text-sm text-emerald-900">
                            Signed
                            {jobOrder.reviewer_name
                                ? ` by ${jobOrder.reviewer_name}`
                                : ''}
                            {jobOrder.reviewed_at
                                ? ` on ${jobOrder.reviewed_at}`
                                : ''}
                            . You can still return selected lines if a
                            correction is needed.
                        </p>
                    )}
                    {selectedIds.length > 0 && (
                        <p className="text-sm text-[#1A3694]">
                            {selectedIds.length} analysis line
                            {selectedIds.length === 1 ? '' : 's'} selected for
                            return
                        </p>
                    )}
                </div>
            </div>

            <div className="fixed inset-x-0 bottom-0 z-20 border-t bg-white/95 px-4 py-3 shadow-[0_-8px_30px_rgba(15,42,120,0.08)] backdrop-blur">
                <div className="mx-auto flex max-w-6xl flex-wrap items-center justify-between gap-3">
                    <p className="text-sm text-muted-foreground">
                        {isSigned
                            ? 'This file is already signed for the day.'
                            : 'Check the form, then sign — or return selected lines.'}
                    </p>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="destructive"
                            disabled={
                                form.processing ||
                                returning ||
                                selectedIds.length === 0
                            }
                            onClick={() => setConfirmReturn(true)}
                        >
                            Return selected ({selectedIds.length})
                        </Button>
                        {!isSigned && (
                            <Button
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                                onClick={() => setConfirmSign(true)}
                                disabled={form.processing || returning}
                            >
                                Sign finished file
                            </Button>
                        )}
                    </div>
                </div>
            </div>

            <Dialog open={confirmSign} onOpenChange={setConfirmSign}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Sign {jobOrder.reference_no}?
                        </DialogTitle>
                        <DialogDescription>
                            Records your review signature on this finished
                            Request for Analysis. Results are already released
                            to the customer if an email is on file.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmSign(false)}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={sign}
                            disabled={form.processing}
                        >
                            {form.processing ? 'Signing…' : 'Confirm signature'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={confirmReturn} onOpenChange={setConfirmReturn}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>
                            Return {selectedIds.length} selected line
                            {selectedIds.length === 1 ? '' : 's'}?
                        </DialogTitle>
                        <DialogDescription>
                            Selected analyses go back to the assigned analyst
                            for correction. The job leaves the ready-for-pickup
                            queue until they are completed again.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setConfirmReturn(false)}
                            disabled={returning}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={returnSelected}
                            disabled={returning}
                        >
                            {returning ? 'Returning…' : 'Confirm return'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

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
