import { useMemo, useState } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { IntakePackageRowPackage } from '@/components/intake/intake-package-row';
import { samplePackagePricing } from '@/lib/package-pricing';

type CatalogItem = {
    id: number;
    name: string;
    default_price: string;
};

export type TestsRequestSummarySample = {
    label: string;
    package_ids: number[];
    analysis_type_ids: number[];
    subtotal: number;
};

type Props = {
    packages: IntakePackageRowPackage[];
    samples: TestsRequestSummarySample[];
    catalogItems: CatalogItem[];
    otherTests: string;
    estimatedTotal: number;
    customerName?: string;
    className?: string;
    variant?: 'sidebar' | 'inline';
    /** Samples step: hide line-item breakdown until tests are chosen. */
    mode?: 'full' | 'samples';
};

function buildTypeMap(items: CatalogItem[]) {
    const map = new Map<number, { name: string; price: string }>();
    items.forEach((item) =>
        map.set(item.id, { name: item.name, price: item.default_price }),
    );
    return map;
}

function buildSampleGroups(
    sample: TestsRequestSummarySample,
    packages: IntakePackageRowPackage[],
    typeMap: Map<number, { name: string; price: string }>,
) {
    const { packageLines, coveredIds: coveredByPackage } = samplePackagePricing(
        sample.package_ids,
        sample.analysis_type_ids,
        packages,
        (id) => Number(typeMap.get(id)?.price || 0),
    );

    const packageGroups = packageLines.map((line) => {
        const pkg = packages.find((p) => p.id === line.id);

        return {
            id: line.id,
            name: line.name,
            price: line.price,
            members: line.memberIds.map((id) => ({
                id,
                name:
                    pkg?.tests.find((t) => t.id === id)?.name ??
                    typeMap.get(id)?.name ??
                    `Test #${id}`,
            })),
        };
    });

    const individualLines = sample.analysis_type_ids
        .filter((id) => !coveredByPackage.has(id))
        .map((id) => {
            const meta = typeMap.get(id);
            return {
                id,
                name: meta?.name ?? `Test #${id}`,
                price: meta?.price ?? '0',
            };
        });

    return { packageGroups, individualLines };
}

function SampleSummaryRow({
    label,
    subtotal,
    packageGroups,
    individualLines,
    defaultOpen,
}: {
    label: string;
    subtotal: number;
    packageGroups: Array<{ id: number; name: string; price: string | number }>;
    individualLines: Array<{ id: number; name: string; price: string }>;
    defaultOpen: boolean;
}) {
    const [open, setOpen] = useState(defaultOpen);
    const hasDetails = packageGroups.length > 0 || individualLines.length > 0;
    const detailId = `summary-${label.replace(/\s+/g, '-')}`;

    return (
        <div className="space-y-1">
            <button
                type="button"
                className="flex w-full items-center justify-between gap-2 text-left"
                aria-expanded={hasDetails ? open : undefined}
                aria-controls={hasDetails ? detailId : undefined}
                disabled={!hasDetails}
                onClick={() => hasDetails && setOpen((value) => !value)}
            >
                <span className="flex min-w-0 items-center gap-1">
                    <span className="min-w-0 truncate font-medium text-nppc-900">
                        {label}
                    </span>
                    {hasDetails ? (
                        <ChevronDown
                            className={cn(
                                'size-3.5 shrink-0 text-slate-400 transition',
                                open && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    ) : null}
                </span>
                <span className="shrink-0 font-heading font-semibold tabular-nums text-slate-800">
                    ₱{subtotal.toFixed(2)}
                </span>
            </button>

            {!hasDetails ? (
                <p className="text-xs text-slate-500">No analyses yet</p>
            ) : null}

            {open && hasDetails ? (
                <div
                    id={detailId}
                    className="space-y-1 pl-0.5 text-xs text-slate-600"
                >
                    {packageGroups.map((group) => (
                        <div
                            key={group.id}
                            className="flex justify-between gap-2"
                        >
                            <span className="min-w-0 truncate">{group.name}</span>
                            <span className="shrink-0 tabular-nums">
                                ₱{Number(group.price).toFixed(2)}
                            </span>
                        </div>
                    ))}
                    {individualLines.map((line) => (
                        <div
                            key={line.id}
                            className="flex justify-between gap-2"
                        >
                            <span className="min-w-0 truncate">{line.name}</span>
                            <span className="shrink-0 tabular-nums">
                                ₱{Number(line.price).toFixed(2)}
                            </span>
                        </div>
                    ))}
                </div>
            ) : null}
        </div>
    );
}

function StatRow({ label, value }: { label: string; value: string | number }) {
    return (
        <div className="flex items-center justify-between gap-2 text-xs">
            <span className="text-slate-600">{label}</span>
            <span className="font-semibold tabular-nums text-nppc-900">
                {value}
            </span>
        </div>
    );
}

/**
 * Sticky request / cost summary for Samples, Tests, and Review steps.
 */
export default function TestsRequestSummary({
    packages,
    samples,
    catalogItems,
    otherTests,
    estimatedTotal,
    customerName,
    className,
    variant = 'sidebar',
    mode = 'full',
}: Props) {
    const typeMap = useMemo(
        () => buildTypeMap(catalogItems),
        [catalogItems],
    );

    const sampleBlocks = useMemo(
        () =>
            samples.map((sample) => ({
                sample,
                ...buildSampleGroups(sample, packages, typeMap),
            })),
        [packages, samples, typeMap],
    );

    const totalAnalyses = samples.reduce(
        (sum, sample) => sum + sample.analysis_type_ids.length,
        0,
    );

    const totalPackages = samples.reduce(
        (sum, sample) => sum + sample.package_ids.length,
        0,
    );

    const hasContent =
        sampleBlocks.some(
            (block) =>
                block.packageGroups.length > 0 ||
                block.individualLines.length > 0,
        ) || otherTests.trim().length > 0;

    const showBreakdown = mode === 'full';

    return (
        <div
            className={cn(
                className,
                variant === 'sidebar' &&
                    'rounded-xl border border-slate-200 bg-white p-4 shadow-xs',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <p className="text-[10px] font-semibold tracking-wider text-nppc-700 uppercase">
                    Request summary
                </p>
                <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide text-slate-600 uppercase">
                    Estimated
                </span>
            </div>

            {customerName?.trim() ? (
                <p className="mt-2 truncate text-sm font-medium text-nppc-900">
                    {customerName.trim()}
                </p>
            ) : (
                <p className="mt-2 text-xs text-slate-500">
                    Customer details from step 1
                </p>
            )}

            <div className="mt-3 space-y-1.5 border-t border-slate-100 pt-3">
                <StatRow label="Samples" value={samples.length} />
                <StatRow label="Packages" value={totalPackages} />
                <StatRow label="Analyses" value={totalAnalyses} />
            </div>

            {showBreakdown ? (
                <div
                    className={cn(
                        'nppc-scroll mt-3 space-y-1.5 overflow-y-auto border-t border-slate-100 pt-3 text-xs',
                        variant === 'sidebar'
                            ? 'max-h-[min(40vh,calc(100vh-18rem))]'
                            : 'max-h-56',
                    )}
                >
                    {sampleBlocks.map(
                        (
                            { sample, packageGroups, individualLines },
                            index,
                        ) => (
                            <SampleSummaryRow
                                key={`sample-summary-${index}`}
                                label={sample.label}
                                subtotal={sample.subtotal}
                                packageGroups={packageGroups}
                                individualLines={individualLines}
                                defaultOpen={samples.length === 1}
                            />
                        ),
                    )}

                    {otherTests.trim() ? (
                        <p className="text-slate-700">
                            <span className="font-medium">Other: </span>
                            {otherTests.trim()}
                        </p>
                    ) : null}

                    {!hasContent ? (
                        <p className="text-slate-500">
                            No analyses selected yet.
                        </p>
                    ) : null}
                </div>
            ) : (
                <p className="mt-3 border-t border-slate-100 pt-3 text-xs text-slate-500">
                    Select packages and tests in the next step. Estimated total
                    updates as you choose analyses.
                </p>
            )}

            <div className="mt-3 flex items-baseline justify-between border-t border-slate-100 pt-3">
                <span className="text-xs font-medium text-slate-700">
                    Estimated total
                </span>
                <span className="font-heading text-2xl font-extrabold tabular-nums text-nppc-900">
                    ₱{estimatedTotal.toFixed(2)}
                </span>
            </div>
            <p className="mt-2 text-[10px] leading-snug text-slate-500">
                Receiving may adjust final pricing. Typical turnaround 5–7
                business days · ISO/IEC 17025.
            </p>
        </div>
    );
}
