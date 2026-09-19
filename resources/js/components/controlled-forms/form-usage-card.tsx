import type { AnalysisPackageOption } from '@/components/package-select';
import { Button } from '@/components/ui/button';

type Props = {
    packages: AnalysisPackageOption[];
    analysisPackageId: number | null | undefined;
    analysisTypes: Array<{ id: number; code: string; name: string }>;
    onEdit?: () => void;
    showEdit?: boolean;
};

export default function FormUsageCard({
    packages,
    analysisPackageId,
    analysisTypes,
    onEdit,
    showEdit = false,
}: Props) {
    const boundPackage =
        analysisPackageId != null
            ? packages.find((item) => item.id === analysisPackageId) ?? null
            : null;
    const typeCount = analysisTypes.length;
    const hasUsage = boundPackage !== null || typeCount > 0;

    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <div className="mb-1.5 flex items-center justify-between gap-2 border-b pb-1.5">
                <div>
                    <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                        Bound analysis parameters
                    </h2>
                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                        Parameters connected to results entry for this sheet
                    </p>
                </div>
                {showEdit && onEdit ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 px-2 text-xs text-[#1A3694]"
                        onClick={onEdit}
                    >
                        Edit parameters
                    </Button>
                ) : null}
            </div>

            {!hasUsage ? (
                <p className="text-sm text-muted-foreground">
                    No packages or analysis types are bound to this form.
                </p>
            ) : (
                <div className="divide-y">
                    {boundPackage ? (
                        <div className="flex items-start gap-2 py-2">
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#1A3694]"
                            />
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-slate-900">
                                    Package: {boundPackage.name}
                                    <span className="ml-1.5 font-mono text-[10px] font-normal text-muted-foreground">
                                        {boundPackage.code}
                                    </span>
                                </p>
                            </div>
                        </div>
                    ) : null}
                    {analysisTypes.map((type) => (
                        <div key={type.id} className="flex items-start gap-2 py-2">
                            <span
                                aria-hidden="true"
                                className="mt-1.5 size-1.5 shrink-0 rounded-full bg-[#1A3694]"
                            />
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-slate-900">
                                    {type.name}
                                    <span className="ml-1.5 font-mono text-[10px] font-normal text-muted-foreground">
                                        {type.code}
                                    </span>
                                </p>
                            </div>
                        </div>
                    ))}
                </div>
            )}

            {showEdit && onEdit ? (
                <div className="mt-2 flex flex-wrap items-center justify-between gap-2 rounded-md border border-dashed bg-slate-50/80 px-2.5 py-2">
                    <p className="text-[11px] text-muted-foreground">
                        Bind a package panel or individual analysis types.
                    </p>
                    <Button type="button" size="sm" variant="outline" onClick={onEdit}>
                        Configure binding
                    </Button>
                </div>
            ) : null}
        </section>
    );
}
