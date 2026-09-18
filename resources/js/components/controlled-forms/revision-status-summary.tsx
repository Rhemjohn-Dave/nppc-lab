import { Badge } from '@/components/ui/badge';
import { statusBadgeClass } from '@/lib/controlled-forms';

type Props = {
    revision: string | null;
    status: string | null;
    statusLabel: string | null;
    effectiveDate: string | null;
};

export default function RevisionStatusSummary({
    revision,
    status,
    statusLabel,
    effectiveDate,
}: Props) {
    if (!revision && !statusLabel && !effectiveDate) {
        return (
            <p className="text-sm text-muted-foreground">
                No revision has been created for this form yet.
            </p>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-x-3 gap-y-0.5 text-sm">
            {revision ? (
                <span>
                    <span className="text-muted-foreground">Revision </span>
                    <span className="font-mono font-medium text-slate-900">
                        {revision}
                    </span>
                </span>
            ) : null}
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
                </Badge>
            ) : null}
            {effectiveDate ? (
                <span className="text-muted-foreground">
                    Effective{' '}
                    <span className="font-medium text-slate-800">{effectiveDate}</span>
                </span>
            ) : null}
        </div>
    );
}
