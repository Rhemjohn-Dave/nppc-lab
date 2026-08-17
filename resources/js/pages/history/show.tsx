import { Head, Link, usePage } from '@inertiajs/react';
import RequestForAnalysisForm from '@/components/request-for-analysis-form';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

type Props = {
    jobOrder: RequestForAnalysisData & {
        id: number;
        status?: string;
        is_signed?: boolean;
    };
    canSign: boolean;
};

export default function HistoryShow({ jobOrder, canSign }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const isSigned = Boolean(jobOrder.is_signed || jobOrder.reviewed_at);

    return (
        <>
            <Head title={`History ${jobOrder.reference_no}`} />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 mb-1 text-slate-600"
                        >
                            <Link href="/history">← Back to history</Link>
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
                                    : 'Ready for pickup'}
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
                    <div className="flex flex-wrap gap-2">
                        <Button asChild variant="outline">
                            <Link href={`/history/${jobOrder.id}/print`}>
                                Print form
                            </Link>
                        </Button>
                        <Button asChild variant="outline">
                            <a href={`/history/${jobOrder.id}/pdf`}>
                                Download PDF
                            </a>
                        </Button>
                        {canSign && !isSigned && (
                            <Button
                                asChild
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                            >
                                <Link href={`/head/${jobOrder.id}`}>
                                    Sign in Head Analysis
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="overflow-hidden rounded-xl border bg-white">
                    <div className="border-b bg-[#f8fafc] px-4 py-3">
                        <h2 className="font-semibold text-[#1A3694]">
                            Official Request for Analysis
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Read-only archive view with analyst results.
                        </p>
                    </div>
                    <div className="p-4">
                        <RequestForAnalysisForm
                            jobOrder={jobOrder}
                            showResults
                            showPrintButton={false}
                        />
                    </div>
                </div>
            </div>
        </>
    );
}

HistoryShow.layout = {
    breadcrumbs: [
        { title: 'History', href: '/history' },
        { title: 'Form', href: '#' },
    ],
};
