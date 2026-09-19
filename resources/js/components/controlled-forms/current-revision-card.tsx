import type { ReactNode } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    statusBadgeClass,
    type ControlledRevisionSummary,
} from '@/lib/controlled-forms';

type Props = {
    formCode: string;
    updatedAt: string | null;
    revision: ControlledRevisionSummary | null;
    onEditFormInfo?: () => void;
};

function MetaRow({ label, children }: { label: string; children: ReactNode }) {
    return (
        <div className="flex items-start justify-between gap-3 border-b border-slate-50 py-1.5 text-xs last:border-b-0">
            <dt className="shrink-0 text-muted-foreground">{label}</dt>
            <dd className="min-w-0 text-right break-words text-slate-900">{children}</dd>
        </div>
    );
}

function truncateHash(hash: string): string {
    if (hash.length <= 16) {
        return hash;
    }
    return `${hash.slice(0, 12)}…`;
}

export default function CurrentRevisionCard({
    formCode,
    updatedAt,
    revision,
    onEditFormInfo,
}: Props) {
    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <div className="mb-1.5 flex items-center justify-between gap-2 border-b pb-1.5">
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Revision specs
                </h2>
                {onEditFormInfo ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 px-2 text-xs"
                        onClick={onEditFormInfo}
                    >
                        Edit
                    </Button>
                ) : null}
            </div>
            {!revision ? (
                <p className="text-sm text-muted-foreground">No current revision.</p>
            ) : (
                <dl>
                    <MetaRow label="Form code">
                        <span className="font-mono text-[11px] font-semibold">
                            {formCode}
                        </span>
                    </MetaRow>
                    <MetaRow label="Current revision">
                        <span className="font-mono font-semibold">{revision.revision}</span>
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
                    {revision.page_count > 0 ? (
                        <MetaRow label="Pages">{revision.page_count}</MetaRow>
                    ) : null}
                    {revision.page_width_mm != null && revision.page_height_mm != null ? (
                        <MetaRow label="Page size">
                            {revision.page_width_mm} × {revision.page_height_mm} mm
                        </MetaRow>
                    ) : null}
                    {revision.fill_mode ? (
                        <MetaRow label="Fill mode">
                            <span className="font-mono text-[11px]">
                                {revision.fill_mode}
                            </span>
                        </MetaRow>
                    ) : null}
                    {revision.sha256 ? (
                        <MetaRow label="Security hash">
                            <span
                                className="font-mono text-[11px] text-muted-foreground"
                                title={revision.sha256}
                            >
                                {truncateHash(revision.sha256)}
                            </span>
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
