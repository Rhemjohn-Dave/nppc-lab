import { Link } from '@inertiajs/react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    statusBadgeClass,
    type ControlledRevisionSummary,
} from '@/lib/controlled-forms';
import { cn } from '@/lib/utils';

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
    const hasSuperseded = revisions.some((r) => r.status === 'superseded');

    return (
        <section className="overflow-hidden rounded-lg border bg-white">
            <div className="flex flex-wrap items-center justify-between gap-2 border-b px-3 py-2">
                <div>
                    <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                        Revision history ledger
                    </h2>
                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                        Historical versions retained for sample traceability
                    </p>
                </div>
                <span className="text-xs text-muted-foreground">
                    Total: {revisions.length} revision
                    {revisions.length === 1 ? '' : 's'}
                </span>
            </div>

            {hasSuperseded ? (
                <div className="flex items-start gap-2 border-b border-amber-200/80 bg-amber-50/70 px-3 py-2 text-[11px] text-amber-900">
                    <span className="font-semibold">Controlled document policy:</span>
                    <span>
                        Superseded revisions are read-only and preserved for
                        historical sample traceability.
                    </span>
                </div>
            ) : null}

            {revisions.length === 0 ? (
                <p className="px-3 py-4 text-sm text-muted-foreground">
                    No revisions yet.
                </p>
            ) : (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[40rem] text-left text-xs">
                        <thead className="border-b bg-slate-50/80 text-muted-foreground">
                            <tr>
                                <th className="px-3 py-2 font-medium">Revision</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">
                                    Effective / fields
                                </th>
                                <th className="px-3 py-2 font-medium">
                                    Approval &amp; remarks
                                </th>
                                <th className="px-3 py-2 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {revisions.map((revision) => {
                                const isActive = revision.status === 'active';
                                return (
                                    <tr
                                        key={revision.id}
                                        className={cn(
                                            isActive
                                                ? 'bg-emerald-50/30'
                                                : 'text-slate-600',
                                        )}
                                    >
                                        <td className="px-3 py-2.5 align-top">
                                            <div className="flex items-center gap-2 font-mono font-semibold text-slate-900">
                                                <span
                                                    aria-hidden="true"
                                                    className={cn(
                                                        'size-1.5 rounded-full',
                                                        isActive
                                                            ? 'bg-emerald-500'
                                                            : 'bg-slate-300',
                                                    )}
                                                />
                                                Rev {revision.revision}
                                            </div>
                                        </td>
                                        <td className="px-3 py-2.5 align-top">
                                            <Badge
                                                variant="outline"
                                                className={statusBadgeClass(
                                                    revision.status,
                                                )}
                                            >
                                                <span aria-hidden="true" className="mr-1">
                                                    ●
                                                </span>
                                                {revision.status_label}
                                            </Badge>
                                        </td>
                                        <td className="px-3 py-2.5 align-top text-slate-700">
                                            <div>
                                                {revision.field_count} field
                                                {revision.field_count === 1
                                                    ? ''
                                                    : 's'}{' '}
                                                mapped
                                            </div>
                                            <div className="text-[11px] text-muted-foreground">
                                                {revision.effective_date
                                                    ? `Effective: ${revision.effective_date}`
                                                    : 'No effective date'}
                                            </div>
                                            {!revision.has_canonical ? (
                                                <div className="mt-0.5 text-[11px] text-amber-800">
                                                    Upload official file before designing
                                                </div>
                                            ) : null}
                                        </td>
                                        <td className="max-w-xs px-3 py-2.5 align-top">
                                            {(revision.approved_by ||
                                                revision.created_by) && (
                                                <p className="font-medium text-slate-800">
                                                    {revision.approved_by ??
                                                        revision.created_by}
                                                </p>
                                            )}
                                            {revision.notes ? (
                                                <p className="truncate text-[11px] text-muted-foreground">
                                                    {revision.notes}
                                                </p>
                                            ) : (
                                                <p className="text-[11px] text-muted-foreground">
                                                    —
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-3 py-2.5 align-top">
                                            <div className="flex flex-wrap justify-end gap-1">
                                                <Button size="sm" variant="outline" asChild>
                                                    <Link
                                                        href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/designer`}
                                                    >
                                                        Designer
                                                    </Link>
                                                </Button>
                                                {revision.has_canonical ? (
                                                    <Button
                                                        size="sm"
                                                        variant="outline"
                                                        asChild
                                                    >
                                                        <a
                                                            href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/canonical`}
                                                        >
                                                            {revision.status ===
                                                            'superseded'
                                                                ? 'PDF archive'
                                                                : 'Canonical PDF'}
                                                        </a>
                                                    </Button>
                                                ) : null}
                                                {(
                                                    transitions[revision.status] ?? []
                                                ).map((action) => (
                                                    <Button
                                                        key={action.status}
                                                        size="sm"
                                                        variant={
                                                            action.status === 'active'
                                                                ? 'default'
                                                                : 'outline'
                                                        }
                                                        className={
                                                            action.status === 'active'
                                                                ? 'bg-[#1A3694] hover:bg-[#365BB0]'
                                                                : undefined
                                                        }
                                                        onClick={() =>
                                                            onTransition(
                                                                revision,
                                                                action,
                                                            )
                                                        }
                                                    >
                                                        {action.label}
                                                    </Button>
                                                ))}
                                            </div>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            )}
        </section>
    );
}
