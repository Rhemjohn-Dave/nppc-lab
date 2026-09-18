import { Link } from '@inertiajs/react';
import { FileText } from 'lucide-react';
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
            <h2 className="mb-1.5 text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                Source file &amp; layout
            </h2>

            <div className="space-y-2">
                <div>
                    <p className="mb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        Source document
                    </p>
                    {revision?.original_name || revision?.has_original ? (
                        <div className="flex flex-wrap items-start gap-2">
                            <div className="flex min-w-0 items-start gap-2">
                                <FileText
                                    className="mt-0.5 size-4 shrink-0 text-[#1A3694]"
                                    aria-hidden="true"
                                />
                                <div className="min-w-0">
                                    <p className="text-sm break-all text-slate-900">
                                        {revision.original_name ?? 'Uploaded source file'}
                                    </p>
                                    {revision.created_at ? (
                                        <p className="mt-0.5 text-xs text-muted-foreground">
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
                </div>

                <div className="border-t pt-2">
                    <p className="mb-1 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                        Layout mapping
                    </p>
                    {hasMapping ? (
                        <div className="space-y-0.5 text-sm">
                            <p className="font-medium text-emerald-800">
                                <span aria-hidden="true">✓ </span>
                                Mapping configured
                            </p>
                            <p className="text-muted-foreground">
                                {fieldCount} field{fieldCount === 1 ? '' : 's'} mapped
                                {hasBlueprint ? ' · Blueprint available' : ''}
                            </p>
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Layout mapping has not been configured.
                            {hasBlueprint
                                ? ' A blueprint is available to import in Form Designer.'
                                : ''}
                        </p>
                    )}
                    {designerHref ? (
                        <div className="mt-2">
                            <Button size="sm" variant="outline" asChild>
                                <Link href={designerHref}>Open Form Designer</Link>
                            </Button>
                        </div>
                    ) : null}
                </div>
            </div>
        </section>
    );
}
