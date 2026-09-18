import { useMemo } from 'react';
import { cn } from '@/lib/utils';
import type { IntakePackageRowPackage } from '@/components/intake/intake-package-row';

type CatalogItem = {
    id: number;
    name: string;
    default_price: string;
};

type Props = {
    packages: IntakePackageRowPackage[];
    selectedPackageIds: number[];
    selectedTypes: number[];
    catalogItems: CatalogItem[];
    sampleCount: number;
    otherTests: string;
    estimatedTotal: number;
    className?: string;
    variant?: 'sidebar' | 'inline';
};

function buildTypeMap(items: CatalogItem[]) {
    const map = new Map<number, { name: string; price: string }>();
    items.forEach((item) =>
        map.set(item.id, { name: item.name, price: item.default_price }),
    );
    return map;
}

export default function TestsRequestSummary({
    packages,
    selectedPackageIds,
    selectedTypes,
    catalogItems,
    sampleCount,
    otherTests,
    estimatedTotal,
    className,
    variant = 'sidebar',
}: Props) {
    const typeMap = useMemo(
        () => buildTypeMap(catalogItems),
        [catalogItems],
    );

    const { packageGroups, individualLines } = useMemo(() => {
        const packageGroups: Array<{
            id: number;
            name: string;
            price: string | number;
            members: Array<{ id: number; name: string; code?: string }>;
        }> = [];

        const coveredByPackage = new Set<number>();

        selectedPackageIds.forEach((pkgId) => {
            const pkg = packages.find((p) => p.id === pkgId);
            if (!pkg) {
                return;
            }

            const members = pkg.analysis_type_ids
                .filter((id) => selectedTypes.includes(id))
                .map((id) => {
                    coveredByPackage.add(id);
                    const fromPkg = pkg.tests.find((t) => t.id === id);
                    const fromMap = typeMap.get(id);
                    return {
                        id,
                        name: fromPkg?.name ?? fromMap?.name ?? `Test #${id}`,
                        code: fromPkg?.code,
                    };
                });

            packageGroups.push({
                id: pkg.id,
                name: pkg.name,
                price: pkg.default_price,
                members,
            });
        });

        const individualLines = selectedTypes
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
    }, [packages, selectedPackageIds, selectedTypes, typeMap]);

    const hasContent =
        packageGroups.length > 0 ||
        individualLines.length > 0 ||
        otherTests.trim().length > 0;

    const packageCount = selectedPackageIds.length;

    return (
        <div className={cn(className)}>
            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                Your selection
            </p>
            <p className="mt-1 text-sm text-slate-600">
                {sampleCount} sample{sampleCount === 1 ? '' : 's'} ·{' '}
                {selectedTypes.length} analyses
                {packageCount > 0
                    ? ` · ${packageCount} package${packageCount === 1 ? '' : 's'}`
                    : ''}
            </p>

            <div
                className={cn(
                    'nppc-scroll mt-2 space-y-2.5 overflow-y-auto border-t border-slate-100 pt-2.5 text-sm',
                    variant === 'sidebar'
                        ? 'max-h-[min(56vh,calc(100vh-18rem))]'
                        : 'max-h-80',
                )}
            >
                {packageGroups.map((group) => (
                    <div key={group.id}>
                        <div className="flex justify-between gap-3 font-medium text-slate-900">
                            <span className="min-w-0 truncate">
                                {group.name}
                            </span>
                            <span className="shrink-0 text-slate-700">
                                ₱{Number(group.price).toFixed(2)}
                            </span>
                        </div>
                        {group.members.length > 0 ? (
                            <ul className="mt-1 space-y-0.5 border-l-2 border-slate-100 pl-2.5 text-xs text-slate-600">
                                {group.members.map((member) => (
                                    <li key={member.id} className="truncate">
                                        {member.name}
                                    </li>
                                ))}
                            </ul>
                        ) : null}
                    </div>
                ))}

                {individualLines.length > 0 ? (
                    <ul className="space-y-1">
                        {individualLines.map((line) => (
                            <li
                                key={line.id}
                                className="flex justify-between gap-3"
                            >
                                <span className="min-w-0 truncate">
                                    {line.name}
                                </span>
                                <span className="shrink-0 text-slate-500">
                                    ₱{Number(line.price).toFixed(2)}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : null}

                {otherTests.trim() ? (
                    <p className="text-slate-700">
                        <span className="font-medium">Other: </span>
                        {otherTests.trim()}
                    </p>
                ) : null}

                {!hasContent ? (
                    <p className="text-muted-foreground">
                        No analyses selected yet.
                    </p>
                ) : null}
            </div>

            <div className="mt-2.5 flex items-center justify-between border-t border-slate-100 pt-2.5">
                <span className="text-sm font-medium">Estimated total</span>
                <span className="font-semibold text-[#1A3694]">
                    ₱{estimatedTotal.toFixed(2)}
                </span>
            </div>
            <p className="mt-1 text-xs text-muted-foreground">
                Receiving may adjust final pricing.
            </p>
        </div>
    );
}
