import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { statusBadgeClass } from '@/lib/controlled-forms';

type Props = {
    formCode: string;
    name: string;
    department: string | null;
    categoryLabel: string;
    status: string | null;
    statusLabel: string;
    onNewRevision: () => void;
};

export default function ControlledFormHeader({
    formCode,
    name,
    department,
    categoryLabel,
    status,
    statusLabel,
    onNewRevision,
}: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-2">
            <div className="min-w-0 space-y-0.5">
                <p className="font-mono text-xs tracking-wide text-muted-foreground">
                    {formCode}
                </p>
                <h1 className="font-heading text-2xl font-semibold break-words text-[#1A3694]">
                    {name}
                </h1>
                <p className="text-sm text-muted-foreground">
                    {department || 'Laboratory'} · {categoryLabel}
                </p>
                {statusLabel ? (
                    <div className="pt-0.5">
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
                    </div>
                ) : null}
            </div>
            <div className="flex flex-wrap gap-2">
                <Button variant="outline" asChild>
                    <Link href="/admin/controlled-forms">Back to catalog</Link>
                </Button>
                <Button
                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                    onClick={onNewRevision}
                >
                    + New Revision
                </Button>
            </div>
        </div>
    );
}
