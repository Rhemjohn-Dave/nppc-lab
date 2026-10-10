import { Head, Link } from '@inertiajs/react';
import CustomerEmailLog from '@/components/email/customer-email-log';
import type { EmailLogSummary } from '@/components/email/customer-email-log';
import LimsSection from '@/components/lims/lims-section';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueKpi from '@/components/lims/queue-kpi';
import type { RequestForAnalysisData } from '@/components/request-for-analysis-form';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { limsPanel } from '@/lib/lims-page-shell';
import { cn } from '@/lib/utils';

type Props = {
    jobOrder: RequestForAnalysisData & {
        id: number;
        status?: string;
        is_signed?: boolean;
    };
    canSign: boolean;
    emailLogs?: EmailLogSummary[];
    canResendEmail?: boolean;
};

export default function HistoryShow({
    jobOrder,
    canSign,
    emailLogs = [],
    canResendEmail = false,
}: Props) {
    const isSigned = Boolean(jobOrder.is_signed || jobOrder.reviewed_at);

    return (
        <>
            <Head title={`History ${jobOrder.reference_no}`} />
            <LimsWorkspace>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0">
                        <Button
                            asChild
                            variant="ghost"
                            size="sm"
                            className="-ml-2 mb-0.5 h-8 text-slate-600"
                        >
                            <Link href="/history">← Back to history</Link>
                        </Button>
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-heading text-xl font-semibold text-nppc-900 sm:text-2xl">
                                {jobOrder.reference_no}
                            </h1>
                            <Badge
                                variant="outline"
                                className={
                                    isSigned
                                        ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                        : 'border-nppc-200 bg-nppc-50 text-nppc-900'
                                }
                            >
                                {isSigned ? 'Signed' : 'Ready for pickup'}
                            </Badge>
                        </div>
                        <p className="mt-0.5 text-sm text-muted-foreground">
                            {jobOrder.customer_name}
                            {jobOrder.company_name
                                ? ` · ${jobOrder.company_name}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2 pt-1">
                        <Button asChild variant="outline" className="border-slate-200">
                            <Link href={`/history/${jobOrder.id}/print`}>
                                Print form
                            </Link>
                        </Button>
                        <Button asChild variant="outline" className="border-slate-200">
                            <a href={`/history/${jobOrder.id}/pdf`}>
                                Download PDF
                            </a>
                        </Button>
                        {canSign && !isSigned && (
                            <Button
                                asChild
                                className="bg-nppc-900 hover:bg-nppc-700"
                            >
                                <Link href={`/head/${jobOrder.id}`}>
                                    Sign in Head Analysis
                                </Link>
                            </Button>
                        )}
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <QueueKpi
                        label="Samples"
                        value={jobOrder.samples?.length ?? 0}
                        hint="on file"
                    />
                    <QueueKpi
                        label="Analyses"
                        value={jobOrder.analyses.length}
                        hint="test lines"
                    />
                    <QueueKpi
                        label="Status"
                        value={isSigned ? 'Signed' : 'Open'}
                        hint={
                            isSigned
                                ? `Reviewed ${jobOrder.reviewed_at || '—'}`
                                : `Completed ${jobOrder.completed_at || '—'}`
                        }
                        tone={isSigned ? 'success' : 'info'}
                    />
                </div>

                <div className="grid gap-4 lg:grid-cols-2">
                    <LimsSection title="Customer" className="min-w-0">
                        <dl className="space-y-1.5 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Name</dt>
                                <dd className="text-right font-medium">
                                    {jobOrder.customer_name}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Company
                                </dt>
                                <dd className="text-right">
                                    {jobOrder.company_name || '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Contact
                                </dt>
                                <dd className="max-w-[60%] text-right text-xs">
                                    {[
                                        jobOrder.customer_contact,
                                        jobOrder.customer_email,
                                    ]
                                        .filter(Boolean)
                                        .join(' · ') || '—'}
                                </dd>
                            </div>
                        </dl>
                        <CustomerEmailLog
                            logs={emailLogs}
                            canResend={canResendEmail}
                            className="mt-3 border-t border-slate-100 pt-3"
                        />
                    </LimsSection>

                    <LimsSection title="Archive" className="min-w-0">
                        <dl className="space-y-1.5 text-sm">
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">Record</dt>
                                <dd className="text-right font-medium">
                                    {isSigned
                                        ? 'Signed archive record'
                                        : 'Unsigned archive record'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Completed
                                </dt>
                                <dd className="text-right text-xs">
                                    {jobOrder.completed_at || '—'}
                                </dd>
                            </div>
                            <div className="flex justify-between gap-3">
                                <dt className="text-muted-foreground">
                                    Reviewed
                                </dt>
                                <dd className="text-right text-xs">
                                    {jobOrder.reviewed_at || '—'}
                                </dd>
                            </div>
                        </dl>
                    </LimsSection>
                </div>

                <div className={cn('overflow-hidden', limsPanel)}>
                    <div className="border-b border-slate-200 bg-nppc-page px-4 py-3">
                        <h2 className="text-sm font-semibold text-nppc-900">
                            Official Request for Analysis
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Controlled-form PDF with analyst results and
                            preserved provenance.
                        </p>
                    </div>
                    <iframe
                        title={`RFA ${jobOrder.reference_no}`}
                        src={`/history/${jobOrder.id}/pdf?inline=1`}
                        className="h-[min(80vh,1100px)] w-full bg-white"
                    />
                </div>
            </LimsWorkspace>
        </>
    );
}

HistoryShow.layout = (props: Props) => ({
    breadcrumbs: [
        { title: 'History', href: '/history' },
        {
            title: props.jobOrder?.reference_no ?? 'Record',
            href: '#',
        },
    ],
});
