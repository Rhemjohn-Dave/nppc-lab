import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    statusBadgeClass,
    type ControlledRevisionSummary,
} from '@/lib/controlled-forms';

export type RevisionTransitionAction = {
    status: string;
    label: string;
};

type Props = {
    formId: number;
    revisions: ControlledRevisionSummary[];
    transitions: Record<string, RevisionTransitionAction[]>;
    onTransition: (
        revision: ControlledRevisionSummary,
        action: RevisionTransitionAction,
    ) => void;
};

export default function RevisionHistoryList({
    formId,
    revisions,
    transitions,
    onTransition,
}: Props) {
    return (
        <section className="rounded-lg border bg-white">
            <div className="flex items-center justify-between gap-2 border-b px-3 py-1.5">
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Revision history
                </h2>
                <span className="text-xs text-muted-foreground">
                    {revisions.length} revision{revisions.length === 1 ? '' : 's'}
                </span>
            </div>

            {revisions.length === 0 ? (
                <p className="px-3 py-4 text-sm text-muted-foreground">
                    No revisions yet.
                </p>
            ) : (
                <ul className="divide-y">
                    {revisions.map((revision) => (
                        <li key={revision.id} className="px-3 py-1.5">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div className="min-w-0 space-y-0.5">
                                    <div className="flex flex-wrap items-center gap-2">
                                        <span className="font-mono text-sm font-medium text-slate-900">
                                            {revision.revision}
                                        </span>
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(revision.status)}
                                        >
                                            <span aria-hidden="true" className="mr-1">
                                                ●
                                            </span>
                                            {revision.status_label}
                                        </Badge>
                                    </div>
                                    <p className="text-xs text-muted-foreground">
                                        {revision.effective_date
                                            ? `Effective ${revision.effective_date}`
                                            : 'No effective date'}
                                        {' · '}
                                        {revision.field_count} field
                                        {revision.field_count === 1 ? '' : 's'}
                                        {revision.created_by
                                            ? ` · ${revision.created_by}`
                                            : ''}
                                    </p>
                                    {revision.notes ? (
                                        <p className="max-w-2xl text-xs text-slate-600">
                                            {revision.notes}
                                        </p>
                                    ) : null}
                                    {revision.status === 'superseded' ? (
                                        <p className="max-w-xl text-xs text-amber-800">
                                            CONTROLLED DOCUMENT WARNING: this revision is no
                                            longer active. Historical documents remain
                                            accessible.
                                        </p>
                                    ) : null}
                                    {!revision.has_canonical ? (
                                        <p className="text-xs text-muted-foreground">
                                            Upload the official file before designing this
                                            revision.
                                        </p>
                                    ) : null}
                                </div>
                                <div className="flex flex-wrap gap-1">
                                    <Button size="sm" variant="outline" asChild>
                                        <Link
                                            href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/designer`}
                                        >
                                            Designer
                                        </Link>
                                    </Button>
                                    {revision.has_canonical ? (
                                        <Button size="sm" variant="outline" asChild>
                                            <a
                                                href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/canonical`}
                                            >
                                                Canonical PDF
                                            </a>
                                        </Button>
                                    ) : null}
                                    {(transitions[revision.status] ?? []).map((action) => (
                                        <Button
                                            key={action.status}
                                            size="sm"
                                            variant={
                                                action.status === 'active'
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            onClick={() => onTransition(revision, action)}
                                        >
                                            {action.label}
                                        </Button>
                                    ))}
                                </div>
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
