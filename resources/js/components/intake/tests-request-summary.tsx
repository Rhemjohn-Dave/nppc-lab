import {
    ChevronDown,
    ClipboardList,
    FlaskConical,
    Package,
    TestTube2,
    UserRound,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import type { IntakePackageRowPackage } from '@/components/intake/intake-package-row';
import { samplePackagePricing } from '@/lib/package-pricing';
import { cn } from '@/lib/utils';

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
                <p className="text-xs text-slate-500">
                    No analyses yet. No packages selected yet.
                </p>
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

function StatRow({
    label,
    value,
    icon: Icon,
}: {
    label: string;
    value: string | number;
    icon: typeof FlaskConical;
}) {
    return (
        <div className="flex items-center justify-between gap-2 text-xs">
            <span className="inline-flex items-center gap-1.5 text-slate-600">
                <Icon className="size-3.5 text-nppc-700" aria-hidden />
                {label}
            </span>
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
                'overflow-hidden rounded-xl border border-nppc-900/20 bg-white shadow-xs',
                className,
            )}
        >
            <div className="flex items-center gap-2 bg-nppc-900 px-3 py-2 text-white sm:px-3.5">
                <ClipboardList className="size-4 shrink-0 text-white/90" aria-hidden />
                <p className="text-sm font-semibold">Request Summary</p>
                <span className="ml-auto rounded bg-white/15 px-1.5 py-0.5 text-[9px] font-semibold tracking-wide uppercase">
                    Estimated
                </span>
            </div>

            <div className="space-y-2.5 p-3 sm:p-3.5">
                {customerName?.trim() ? (
                    <p className="flex min-w-0 items-center gap-1.5 text-sm font-medium text-nppc-900">
                        <UserRound
                            className="size-3.5 shrink-0 text-nppc-700"
                            aria-hidden
                        />
                        <span className="truncate">{customerName.trim()}</span>
                    </p>
                ) : (
                    <p className="text-xs text-slate-500">
                        Customer details from step 1
                    </p>
                )}

                <div className="space-y-1.5 border-t border-slate-100 pt-2.5">
                    <StatRow
                        label="Samples"
                        value={samples.length}
                        icon={TestTube2}
                    />
                    <StatRow
                        label="Packages"
                        value={totalPackages}
                        icon={Package}
                    />
                    <StatRow
                        label="Analyses"
                        value={totalAnalyses}
                        icon={FlaskConical}
                    />
                </div>

                {showBreakdown ? (
                    <div
                        className={cn(
                            'nppc-scroll space-y-1.5 overflow-y-auto border-t border-slate-100 pt-2.5 text-xs',
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
                    <p className="rounded-lg border border-sky-100 bg-sky-50/80 px-2.5 py-2 text-[11px] leading-snug text-nppc-800">
                        Packages and tests are selected in the next step.
                        Estimated total updates as you choose analyses.
                    </p>
                )}

                <div className="rounded-lg border border-sky-100 bg-sky-50/90 px-3 py-2.5">
                    <div className="flex items-baseline justify-between gap-2">
                        <span className="text-xs font-medium text-slate-700">
                            Estimated Total
                        </span>
                        <span className="font-heading text-2xl font-extrabold tabular-nums text-nppc-900">
                            ₱{estimatedTotal.toFixed(2)}
                        </span>
                    </div>
                </div>
                <p className="text-[10px] leading-snug text-slate-500">
                    Receiving may adjust final pricing. Typical turnaround 5–7
                    business days · ISO/IEC 17025.
                </p>
            </div>
        </div>
    );
}
