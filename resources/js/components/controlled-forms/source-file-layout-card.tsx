import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { ControlledRevisionSummary } from '@/lib/controlled-forms';

type Props = {
    formId: number;
    hasBlueprint: boolean;
    revision: ControlledRevisionSummary | null;
};

export default function SourceFileLayoutCard({
    formId,
    hasBlueprint,
    revision,
}: Props) {
    const fieldCount = revision?.field_count ?? 0;
    const hasMapping = fieldCount > 0;
    const designerHref = revision
        ? `/admin/controlled-forms/${formId}/revisions/${revision.id}/designer`
        : null;

    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <div className="mb-2 flex items-center justify-between gap-2 border-b pb-1.5">
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Source document &amp; PDF blueprint
                </h2>
                {hasMapping ? (
                    <Badge
                        variant="outline"
                        className="border-emerald-200 bg-emerald-50 text-emerald-800"
                    >
                        <span aria-hidden="true" className="mr-1">
                            ✓
                        </span>
                        Mapping configured
                    </Badge>
                ) : (
                    <Badge variant="outline" className="text-muted-foreground">
                        Mapping pending
                    </Badge>
                )}
            </div>

            <div className="space-y-2">
                {revision?.original_name || revision?.has_original ? (
                    <div className="flex flex-col gap-2 rounded-md border bg-slate-50/70 px-2.5 py-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="flex min-w-0 items-start gap-2">
                            <div className="flex size-9 shrink-0 items-center justify-center rounded-md border border-[#1A3694]/20 bg-white text-[#1A3694]">
                                <FileText className="size-4" aria-hidden="true" />
                            </div>
                            <div className="min-w-0">
                                <p className="text-sm font-medium break-all text-slate-900">
                                    {revision.original_name ?? 'Uploaded source file'}
                                </p>
                                {revision.created_at ? (
                                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                                        Uploaded: {revision.created_at}
                                    </p>
                                ) : null}
                            </div>
                        </div>
                        <div className="flex flex-wrap gap-1.5">
                            {revision.has_original ? (
                                <Button size="sm" variant="outline" asChild>
                                    <a
                                        href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/original`}
                                    >
                                        View Source
                                    </a>
                                </Button>
                            ) : null}
                            {revision.has_canonical ? (
                                <Button size="sm" variant="outline" asChild>
                                    <a
                                        href={`/admin/controlled-forms/${formId}/revisions/${revision.id}/canonical`}
                                    >
                                        View Canonical PDF
                                    </a>
                                </Button>
                            ) : null}
                        </div>
                    </div>
                ) : (
                    <p className="text-sm text-muted-foreground">
                        No source file uploaded for the current revision. Create a
                        revision with a file, or upload in Form Designer.
                    </p>
                )}

                <div className="flex flex-wrap items-center justify-between gap-2 border-t pt-2">
                    <p className="text-xs text-muted-foreground">
                        {hasMapping ? (
                            <>
                                <span className="font-semibold text-slate-900">
                                    {fieldCount} field{fieldCount === 1 ? '' : 's'}{' '}
                                    mapped
                                </span>
                                {hasBlueprint ? ' · Blueprint available' : ''}
                            </>
                        ) : (
                            <>
                                Layout mapping has not been configured.
                                {hasBlueprint
                                    ? ' A blueprint is available to import in Form Designer.'
                                    : ''}
                            </>
                        )}
                    </p>
                    {designerHref ? (
                        <Button
                            size="sm"
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            asChild
                        >
                            <Link href={designerHref}>Open Form Designer</Link>
                        </Button>
                    ) : null}
                </div>
            </div>
        </section>
    );
}
