import type { AnalysisPackageOption } from '@/components/package-select';

type Props = {
    packages: AnalysisPackageOption[];
    analysisPackageId: number | null | undefined;
    analysisTypeCount: number;
};

export default function LabIntegrationCard({
    packages,
    analysisPackageId,
    analysisTypeCount,
}: Props) {
    const boundPackage =
        analysisPackageId != null
            ? packages.find((item) => item.id === analysisPackageId) ?? null
            : null;

    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <h2 className="mb-1.5 border-b pb-1.5 text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                Lab integration
            </h2>
            <div className="grid grid-cols-2 gap-2">
                <div className="rounded-md border bg-slate-50/80 px-2.5 py-2">
                    <p className="text-[11px] text-muted-foreground">Bound packages</p>
                    <p className="mt-0.5 text-xl font-bold text-slate-900">
                        {boundPackage ? 1 : 0}
                    </p>
                    <p className="mt-0.5 text-[10px] text-muted-foreground">
                        {boundPackage ? boundPackage.code : 'Standalone'}
                    </p>
                </div>
                <div className="rounded-md border bg-slate-50/80 px-2.5 py-2">
                    <p className="text-[11px] text-muted-foreground">Bound analysis</p>
                    <p className="mt-0.5 text-xl font-bold text-slate-900">
                        {analysisTypeCount}
                    </p>
                    <p className="mt-0.5 text-[10px] text-muted-foreground">
                        {analysisTypeCount > 0 ? 'Direct assignment' : 'None bound'}
                    </p>
                </div>
            </div>
        </section>
    );
}
