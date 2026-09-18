import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import {
    statusBadgeClass,
    type ControlledRevisionSummary,
} from '@/lib/controlled-forms';

type Props = {
    formCode: string;
    updatedAt: string | null;
    revision: ControlledRevisionSummary | null;
};

function MetaRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="grid grid-cols-[7rem_1fr] gap-x-2 gap-y-0 text-sm sm:grid-cols-[8rem_1fr]">
            <dt className="text-muted-foreground">{label}</dt>
            <dd className="min-w-0 break-words text-slate-900">{children}</dd>
        </div>
    );
}

export default function CurrentRevisionCard({
    formCode,
    updatedAt,
    revision,
}: Props) {
    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <h2 className="mb-1.5 text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                Current revision
            </h2>
            {!revision ? (
                <p className="text-sm text-muted-foreground">No current revision.</p>
            ) : (
                <dl className="space-y-1.5">
                    <MetaRow label="Form code">
                        <span className="font-mono text-xs">{formCode}</span>
                    </MetaRow>
                    <MetaRow label="Revision">
                        <span className="font-mono">{revision.revision}</span>
                    </MetaRow>
                    <MetaRow label="Status">
                        <Badge
                            variant="outline"
                            className={statusBadgeClass(revision.status)}
                        >
                            <span aria-hidden="true" className="mr-1.5">
                                ●
                            </span>
                            {revision.status_label}
                        </Badge>
                    </MetaRow>
                    {revision.effective_date ? (
                        <MetaRow label="Effective date">{revision.effective_date}</MetaRow>
                    ) : null}
                    {revision.created_at ? (
                        <MetaRow label="Created">{revision.created_at}</MetaRow>
                    ) : null}
                    {revision.created_by ? (
                        <MetaRow label="Created by">{revision.created_by}</MetaRow>
                    ) : null}
                    {revision.approved_by ? (
                        <MetaRow label="Approved by">
                            {revision.approved_by}
                            {revision.approved_at ? (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {revision.approved_at}
                                </span>
                            ) : null}
                        </MetaRow>
                    ) : null}
                    {updatedAt ? (
                        <MetaRow label="Last updated">
                            {new Date(updatedAt).toLocaleString()}
                        </MetaRow>
                    ) : null}
                </dl>
            )}
        </section>
    );
}
