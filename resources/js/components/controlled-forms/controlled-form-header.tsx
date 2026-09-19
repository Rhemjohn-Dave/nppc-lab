import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { statusBadgeClass } from '@/lib/controlled-forms';
import RevisionWorkflow from '@/components/controlled-forms/revision-workflow';

type Props = {
    formCode: string;
    name: string;
    department: string | null;
    categoryLabel: string;
    status: string | null;
    statusLabel: string;
    revision: string | null;
    effectiveDate: string | null;
    workflowStatus: string | null | undefined;
    onNewRevision: () => void;
};

export default function ControlledFormHeader({
    formCode,
    name,
    department,
    categoryLabel,
    status,
    statusLabel,
    revision,
    effectiveDate,
    workflowStatus,
    onNewRevision,
}: Props) {
    return (
        <section className="space-y-3 rounded-lg border bg-white px-3 py-2.5">
            <div className="flex flex-wrap items-start justify-between gap-2">
                <div className="min-w-0 space-y-1">
                    <div className="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <span className="font-mono text-xs tracking-wide text-muted-foreground">
                            {formCode}
                        </span>
                        {statusLabel ? (
                            <Badge
                                variant="outline"
                                className={statusBadgeClass(status)}
                                aria-label={`Status: ${statusLabel}`}
                            >
                                <span aria-hidden="true" className="mr-1.5">
                                    ●
                                </span>
                                {statusLabel}
                                {revision ? ` (v${revision})` : ''}
                            </Badge>
                        ) : null}
                        {effectiveDate ? (
                            <>
                                <span className="text-xs text-muted-foreground">·</span>
                                <span className="text-xs text-muted-foreground">
                                    Effective:{' '}
                                    <span className="font-medium text-slate-800">
                                        {effectiveDate}
                                    </span>
                                </span>
                            </>
                        ) : null}
                    </div>
                    <h1 className="font-heading text-2xl font-semibold break-words text-[#1A3694]">
                        {name}
                    </h1>
                    <p className="text-xs text-muted-foreground">
                        {department || 'Laboratory'} · {categoryLabel}
                    </p>
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href="/admin/controlled-forms">Back to catalog</Link>
                    </Button>
                    <Button
                        size="sm"
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                        onClick={onNewRevision}
                    >
                        + New Revision Draft
                    </Button>
                </div>
            </div>

            <div className="border-t pt-2.5">
                <RevisionWorkflow
                    currentStatus={workflowStatus}
                    currentRevision={revision}
                    embedded
                />
            </div>
        </section>
    );
}
