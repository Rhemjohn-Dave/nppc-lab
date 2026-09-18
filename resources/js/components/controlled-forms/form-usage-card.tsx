import type { AnalysisPackageOption } from '@/components/package-select';

type Props = {
    packages: AnalysisPackageOption[];
    analysisPackageId: number | null | undefined;
    analysisTypes: Array<{ id: number; code: string; name: string }>;
};

export default function FormUsageCard({
    packages,
    analysisPackageId,
    analysisTypes,
}: Props) {
    const boundPackage =
        analysisPackageId != null
            ? packages.find((item) => item.id === analysisPackageId) ?? null
            : null;
    const typeCount = analysisTypes.length;
    const hasUsage = boundPackage !== null || typeCount > 0;

    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <h2 className="mb-1.5 text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                Usage
            </h2>

            {!hasUsage ? (
                <p className="text-sm text-muted-foreground">
                    No packages are currently using this form.
                </p>
            ) : (
                <div className="space-y-2">
                    <div className="grid grid-cols-2 gap-2">
                        <div className="rounded-md border bg-slate-50/80 px-2.5 py-1.5">
                            <p className="text-[11px] text-muted-foreground uppercase">
                                Bound package
                            </p>
                            <p className="mt-0.5 text-base font-semibold text-slate-900">
                                {boundPackage ? 1 : 0}
                            </p>
                        </div>
                        <div className="rounded-md border bg-slate-50/80 px-2.5 py-1.5">
                            <p className="text-[11px] text-muted-foreground uppercase">
                                Analysis types
                            </p>
                            <p className="mt-0.5 text-base font-semibold text-slate-900">
                                {typeCount}
                            </p>
                        </div>
                    </div>

                    {boundPackage ? (
                        <div>
                            <p className="mb-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                Package using this form
                            </p>
                            <ul className="space-y-0.5 text-sm text-slate-900">
                                <li>
                                    <span className="text-muted-foreground">• </span>
                                    {boundPackage.name}
                                    <span className="ml-1 font-mono text-xs text-muted-foreground">
                                        ({boundPackage.code})
                                    </span>
                                </li>
                            </ul>
                        </div>
                    ) : null}

                    {typeCount > 0 ? (
                        <div>
                            <p className="mb-0.5 text-[11px] font-medium tracking-wide text-muted-foreground uppercase">
                                Bound analysis types
                            </p>
                            <ul className="max-h-36 space-y-0.5 overflow-y-auto text-sm text-slate-900">
                                {analysisTypes.map((type) => (
                                    <li key={type.id}>
                                        <span className="text-muted-foreground">• </span>
                                        {type.name}
                                        <span className="ml-1 font-mono text-xs text-muted-foreground">
                                            ({type.code})
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}
                </div>
            )}
        </section>
    );
}
