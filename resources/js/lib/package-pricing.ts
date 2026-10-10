type PricedPackage = {
    id: number;
    name: string;
    default_price: string | number;
    analysis_type_ids: number[];
};

export type SamplePackageLine = {
    id: number;
    name: string;
    price: number;
    memberIds: number[];
};

/**
 * Mirrors intake billing: a fully selected package costs its package price;
 * with any member unchecked it costs the sum of the selected members' test prices.
 */
export function samplePackagePricing(
    packageIds: number[],
    analysisTypeIds: number[],
    packages: PricedPackage[],
    testPrice: (typeId: number) => number,
): {
    packageLines: SamplePackageLine[];
    coveredIds: Set<number>;
    subtotal: number;
} {
    const selected = new Set(analysisTypeIds);
    const coveredIds = new Set<number>();
    const packageLines: SamplePackageLine[] = [];

    packageIds.forEach((pkgId) => {
        const pkg = packages.find((p) => p.id === pkgId);
        if (!pkg) {
            return;
        }

        const memberIds = pkg.analysis_type_ids.filter(
            (id) => selected.has(id) && !coveredIds.has(id),
        );
        const full = pkg.analysis_type_ids.every((id) => selected.has(id));
        memberIds.forEach((id) => coveredIds.add(id));

        packageLines.push({
            id: pkg.id,
            name: pkg.name,
            price: full
                ? Number(pkg.default_price) || 0
                : memberIds.reduce((sum, id) => sum + testPrice(id), 0),
            memberIds,
        });
    });

    const individualTotal = analysisTypeIds
        .filter((id) => !coveredIds.has(id))
        .reduce((sum, id) => sum + testPrice(id), 0);

    return {
        packageLines,
        coveredIds,
        subtotal:
            packageLines.reduce((sum, line) => sum + line.price, 0) +
            individualTotal,
    };
}
