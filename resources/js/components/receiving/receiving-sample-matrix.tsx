import { router } from '@inertiajs/react';
import { Check, ChevronDown, ChevronRight, Trash2 } from 'lucide-react';
import { Fragment } from 'react';
import ReceivingAddAnalyses, {
    type AnalysisCatalogPackage,
    type AnalysisCatalogType,
} from '@/components/receiving/receiving-add-analyses';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import type { PricingLine, SamplePricingGroup } from './types';

type Props = {
    jobOrderId: number;
    jobClassification?: string | null;
    isBulk?: boolean;
    canEditAnalyses?: boolean;
    analysisCatalog?: {
        packages: AnalysisCatalogPackage[];
        analysis_types: AnalysisCatalogType[];
    };
    sampleGroups: SamplePricingGroup[];
    expandedSampleId: number | null;
    onToggleSample: (sampleId: number | null) => void;
    linesById: Map<number, PricingLine>;
    money: (value: number) => string;
    lineAmount: (line: PricingLine | undefined) => number;
    lineGross: (line: PricingLine | undefined) => number;
    updateLine: (
        lineId: number,
        key: 'quantity' | 'unit_price' | 'discount_percent',
        value: number,
    ) => void;
    updatePackagePrice: (memberLineIds: number[], newPrice: number) => void;
    updatePackageDiscount: (
        memberLineIds: number[],
        discountPercent: number,
    ) => void;
    needsPricing: boolean;
};

function packageDiscountPercent(
    memberLineIds: number[],
    linesById: Map<number, PricingLine>,
): number {
    const percents = memberLineIds.map((id) =>
        Number(linesById.get(id)?.discount_percent || 0),
    );
    if (percents.length === 0) {
        return 0;
    }
    const first = percents[0];
    return percents.every((pct) => Math.abs(pct - first) < 0.001) ? first : first;
}

function sampleSubtotal(
    group: SamplePricingGroup,
    linesById: Map<number, PricingLine>,
    lineAmount: (line: PricingLine | undefined) => number,
): number {
    const ids = [
        ...group.packages.flatMap((pkg) => pkg.memberLineIds),
        ...group.individuals.map((line) => line.id),
    ];
    return ids.reduce((sum, id) => sum + lineAmount(linesById.get(id)), 0);
}

function chargeableCount(group: SamplePricingGroup): number {
    return group.packages.length + group.individuals.length;
}

const thClass =
    'px-3 py-2 text-left text-[11px] font-semibold tracking-wider text-slate-500 uppercase';

export default function ReceivingSampleMatrix({
    jobOrderId,
    jobClassification,
    isBulk = false,
    canEditAnalyses = false,
    analysisCatalog,
    sampleGroups,
    expandedSampleId,
    onToggleSample,
    linesById,
    money,
    lineAmount,
    lineGross,
    updateLine,
    updatePackagePrice,
    updatePackageDiscount,
    needsPricing,
}: Props) {
    function removePackage(packageLinkId: number) {
        router.delete(
            `/receiving/${jobOrderId}/analyses/packages/${packageLinkId}`,
            { preserveScroll: true },
        );
    }

    function removeAnalysis(analysisId: number) {
        router.delete(`/receiving/${jobOrderId}/analyses/${analysisId}`, {
            preserveScroll: true,
        });
    }

    return (
        <div
            className={cn(
                'rounded-xl border border-slate-200 bg-white shadow-xs',
                needsPricing && 'border-amber-300/80 ring-1 ring-amber-200/70',
            )}
        >
            <div
                className={cn(
                    'border-b border-slate-200 px-4 py-2.5',
                    needsPricing ? 'bg-amber-50/80' : 'bg-slate-50/90',
                )}
            >
                <h2 className="text-sm font-semibold text-nppc-900">
                    Sample pricing matrix
                </h2>
                <p className="mt-0.5 text-[11px] text-slate-500">
                    Expand a sample to edit package totals and individual line
                    rates
                    {canEditAnalyses
                        ? '. Before JO approval you can also add a forgotten package or test.'
                        : ''}
                </p>
            </div>

            <div className="divide-y divide-slate-200/80">
                {sampleGroups.length === 0 ? (
                    <div className="px-4 py-8 text-center text-sm text-slate-500">
                        No samples on this job order.
                    </div>
                ) : (
                    sampleGroups.map((group) => {
                        const sampleId =
                            group.sample.id ?? group.sampleIndex;
                        const expanded =
                            expandedSampleId === group.sample.id;
                        const groupTotal = sampleSubtotal(
                            group,
                            linesById,
                            lineAmount,
                        );
                        const items = chargeableCount(group);
                        const sampleLabel = group.sample.sample_code
                            ? `${group.sample.sample_code} — ${group.sample.description || `Sample ${group.sampleIndex + 1}`}`
                            : group.sample.description ||
                              `Sample ${group.sampleIndex + 1}`;

                        return (
                            <div key={sampleId}>
                                <button
                                    type="button"
                                    className="flex w-full items-start gap-2 px-4 py-2.5 text-left hover:bg-slate-50"
                                    onClick={() =>
                                        onToggleSample(
                                            expanded
                                                ? null
                                                : (group.sample.id ?? null),
                                        )
                                    }
                                    aria-expanded={expanded}
                                >
                                    <span className="mt-0.5 text-slate-500">
                                        {expanded ? (
                                            <ChevronDown className="size-4" />
                                        ) : (
                                            <ChevronRight className="size-4" />
                                        )}
                                    </span>
                                    <div className="min-w-0 flex-1">
                                        <div className="flex items-start justify-between gap-3">
                                            <p className="text-sm font-semibold text-slate-900">
                                                Sample {group.sampleIndex + 1}
                                            </p>
                                            <p className="shrink-0 text-sm font-semibold tabular-nums text-emerald-800">
                                                {money(groupTotal)}
                                            </p>
                                        </div>
                                        <p className="mt-0.5 truncate text-[11px] text-slate-500">
                                            {sampleLabel}
                                            {group.sample.matrix
                                                ? ` · ${group.sample.matrix}`
                                                : ''}
                                        </p>
                                        <p className="mt-0.5 text-[11px] text-slate-500">
                                            {group.controlNumber
                                                ? `Control no. ${group.controlNumber} · `
                                                : ''}
                                            {items} chargeable{' '}
                                            {items === 1 ? 'item' : 'items'}
                                        </p>
                                    </div>
                                </button>

                                {expanded ? (
                                    <div className="overflow-visible border-t border-slate-100 bg-slate-50/30">
                                        <div className="overflow-x-auto">
                                            <table className="w-full min-w-[640px] border-collapse text-left text-xs">
                                                <thead className="border-b border-slate-200 bg-slate-50/90">
                                                    <tr>
                                                        <th className={thClass}>
                                                            Code
                                                        </th>
                                                        <th className={thClass}>
                                                            Parameter
                                                        </th>
                                                        <th className={`${thClass} w-16`}>
                                                            Qty
                                                        </th>
                                                        <th className={`${thClass} w-24`}>
                                                            Rate
                                                        </th>
                                                        <th className={`${thClass} w-20`}>
                                                            Disc %
                                                        </th>
                                                        <th className={`${thClass} w-24 text-right`}>
                                                            Net
                                                        </th>
                                                        {canEditAnalyses ? (
                                                            <th
                                                                className={`${thClass} w-12 text-right`}
                                                            >
                                                                {' '}
                                                            </th>
                                                        ) : null}
                                                    </tr>
                                                </thead>
                                                <tbody className="divide-y divide-slate-200/80 bg-white">
                                                    {group.packages.map(
                                                        (pkgGroup) => {
                                                            const pkgGross =
                                                                pkgGroup.memberLineIds.reduce(
                                                                    (sum, id) =>
                                                                        sum +
                                                                        lineGross(
                                                                            linesById.get(
                                                                                id,
                                                                            ),
                                                                        ),
                                                                    0,
                                                                );
                                                            const pkgNet =
                                                                pkgGroup.memberLineIds.reduce(
                                                                    (sum, id) =>
                                                                        sum +
                                                                        lineAmount(
                                                                            linesById.get(
                                                                                id,
                                                                            ),
                                                                        ),
                                                                    0,
                                                                );
                                                            const pkgDisc =
                                                                packageDiscountPercent(
                                                                    pkgGroup.memberLineIds,
                                                                    linesById,
                                                                );

                                                            return (
                                                                <Fragment
                                                                    key={`pkg-${pkgGroup.package.id}`}
                                                                >
                                                                    <tr>
                                                                        <td className="px-3 py-2 align-top font-mono text-[11px] font-semibold text-sky-800">
                                                                            PKG
                                                                        </td>
                                                                        <td className="px-3 py-2 align-top">
                                                                            <p className="font-semibold text-slate-900">
                                                                                {
                                                                                    pkgGroup
                                                                                        .package
                                                                                        .name
                                                                                }
                                                                            </p>
                                                                            <p className="mt-0.5 text-[11px] text-slate-500">
                                                                                Package
                                                                                ·{' '}
                                                                                {
                                                                                    pkgGroup
                                                                                        .members
                                                                                        .length
                                                                                }{' '}
                                                                                tests
                                                                            </p>
                                                                        </td>
                                                                        <td className="px-3 py-2 align-top">
                                                                            <Input
                                                                                type="number"
                                                                                min={
                                                                                    1
                                                                                }
                                                                                className="h-8 w-14"
                                                                                value={
                                                                                    1
                                                                                }
                                                                                disabled
                                                                                readOnly
                                                                            />
                                                                        </td>
                                                                        <td className="px-3 py-2 align-top">
                                                                            <Input
                                                                                type="number"
                                                                                min={
                                                                                    0
                                                                                }
                                                                                step="0.01"
                                                                                className="h-8 w-24"
                                                                                value={
                                                                                    pkgGross
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updatePackagePrice(
                                                                                        pkgGroup.memberLineIds,
                                                                                        Number(
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        ),
                                                                                    )
                                                                                }
                                                                            />
                                                                        </td>
                                                                        <td className="px-3 py-2 align-top">
                                                                            <Input
                                                                                type="number"
                                                                                min={
                                                                                    0
                                                                                }
                                                                                max={
                                                                                    100
                                                                                }
                                                                                step="0.01"
                                                                                className="h-8 w-16"
                                                                                value={
                                                                                    pkgDisc
                                                                                }
                                                                                onChange={(
                                                                                    e,
                                                                                ) =>
                                                                                    updatePackageDiscount(
                                                                                        pkgGroup.memberLineIds,
                                                                                        Number(
                                                                                            e
                                                                                                .target
                                                                                                .value,
                                                                                        ),
                                                                                    )
                                                                                }
                                                                            />
                                                                        </td>
                                                                        <td className="px-3 py-2 text-right align-top text-xs font-bold tabular-nums text-slate-900">
                                                                            {money(
                                                                                pkgNet,
                                                                            )}
                                                                        </td>
                                                                        {canEditAnalyses ? (
                                                                            <td className="px-3 py-2 text-right align-top">
                                                                                <Button
                                                                                    type="button"
                                                                                    variant="ghost"
                                                                                    size="sm"
                                                                                    className="h-8 w-8 p-0 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                                                                    aria-label={`Remove package ${pkgGroup.package.name}`}
                                                                                    onClick={() =>
                                                                                        removePackage(
                                                                                            pkgGroup
                                                                                                .package
                                                                                                .id,
                                                                                        )
                                                                                    }
                                                                                >
                                                                                    <Trash2 className="size-3.5" />
                                                                                </Button>
                                                                            </td>
                                                                        ) : null}
                                                                    </tr>
                                                                    {pkgGroup.members.map(
                                                                        (
                                                                            member,
                                                                        ) => (
                                                                            <tr
                                                                                key={`m-${member.id}`}
                                                                                className="bg-slate-50/50"
                                                                            >
                                                                                <td className="px-3 py-1.5 align-top font-mono text-[10px] text-slate-400">
                                                                                    —
                                                                                </td>
                                                                                <td
                                                                                    className="px-3 py-1.5 align-top text-[11px] text-slate-600"
                                                                                    colSpan={4}
                                                                                >
                                                                                    <span className="inline-flex items-center gap-1.5">
                                                                                        <Check className="size-3 text-emerald-600" />
                                                                                        {
                                                                                            member.name
                                                                                        }
                                                                                    </span>
                                                                                </td>
                                                                                <td className="px-3 py-1.5 text-right align-top text-[10px] text-slate-400">
                                                                                    Included
                                                                                </td>
                                                                                {canEditAnalyses ? (
                                                                                    <td className="px-3 py-1.5" />
                                                                                ) : null}
                                                                            </tr>
                                                                        ),
                                                                    )}
                                                                </Fragment>
                                                            );
                                                        },
                                                    )}

                                                    {group.individuals.map(
                                                        (meta) => {
                                                            const line =
                                                                linesById.get(
                                                                    meta.id,
                                                                );
                                                            if (!line) {
                                                                return null;
                                                            }
                                                            const lineTotal =
                                                                lineAmount(
                                                                    line,
                                                                );

                                                            return (
                                                                <tr
                                                                    key={
                                                                        meta.id
                                                                    }
                                                                >
                                                                    <td className="px-3 py-2 align-top font-mono text-[11px] font-semibold text-nppc-800">
                                                                        {meta.control_number ??
                                                                            '—'}
                                                                    </td>
                                                                    <td className="px-3 py-2 align-top">
                                                                        <p className="font-semibold text-slate-900">
                                                                            {
                                                                                meta.name
                                                                            }
                                                                        </p>
                                                                        {meta.category_label ? (
                                                                            <p className="mt-0.5 text-[11px] text-slate-500">
                                                                                {
                                                                                    meta.category_label
                                                                                }
                                                                            </p>
                                                                        ) : null}
                                                                    </td>
                                                                    <td className="px-3 py-2 align-top">
                                                                        <Input
                                                                            type="number"
                                                                            min={
                                                                                1
                                                                            }
                                                                            className="h-8 w-16"
                                                                            value={
                                                                                line.quantity
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateLine(
                                                                                    meta.id,
                                                                                    'quantity',
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td className="px-3 py-2 align-top">
                                                                        <Input
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            step="0.01"
                                                                            className="h-8 w-24"
                                                                            value={
                                                                                line.unit_price
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateLine(
                                                                                    meta.id,
                                                                                    'unit_price',
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td className="px-3 py-2 align-top">
                                                                        <Input
                                                                            type="number"
                                                                            min={
                                                                                0
                                                                            }
                                                                            max={
                                                                                100
                                                                            }
                                                                            step="0.01"
                                                                            className="h-8 w-16"
                                                                            value={
                                                                                line.discount_percent
                                                                            }
                                                                            onChange={(
                                                                                e,
                                                                            ) =>
                                                                                updateLine(
                                                                                    meta.id,
                                                                                    'discount_percent',
                                                                                    Number(
                                                                                        e
                                                                                            .target
                                                                                            .value,
                                                                                    ),
                                                                                )
                                                                            }
                                                                        />
                                                                    </td>
                                                                    <td className="px-3 py-2 text-right align-top text-xs font-bold tabular-nums text-slate-900">
                                                                        {money(
                                                                            lineTotal,
                                                                        )}
                                                                    </td>
                                                                    {canEditAnalyses ? (
                                                                        <td className="px-3 py-2 text-right align-top">
                                                                            <Button
                                                                                type="button"
                                                                                variant="ghost"
                                                                                size="sm"
                                                                                className="h-8 w-8 p-0 text-rose-700 hover:bg-rose-50 hover:text-rose-800"
                                                                                aria-label={`Remove test ${meta.name}`}
                                                                                onClick={() =>
                                                                                    removeAnalysis(
                                                                                        meta.id,
                                                                                    )
                                                                                }
                                                                            >
                                                                                <Trash2 className="size-3.5" />
                                                                            </Button>
                                                                        </td>
                                                                    ) : null}
                                                                </tr>
                                                            );
                                                        },
                                                    )}

                                                    {group.packages.length ===
                                                        0 &&
                                                    group.individuals
                                                        .length === 0 ? (
                                                        <tr>
                                                            <td
                                                                colSpan={
                                                                    canEditAnalyses
                                                                        ? 7
                                                                        : 6
                                                                }
                                                                className="px-3 py-6 text-center text-sm text-slate-500"
                                                            >
                                                                No chargeable
                                                                items for this
                                                                sample.
                                                            </td>
                                                        </tr>
                                                    ) : null}
                                                </tbody>
                                            </table>
                                        </div>

                                        {canEditAnalyses &&
                                        analysisCatalog &&
                                        group.sample.id != null ? (
                                            <ReceivingAddAnalyses
                                                jobOrderId={jobOrderId}
                                                sampleId={group.sample.id}
                                                sampleClassification={
                                                    group.sample.classification
                                                }
                                                jobClassification={
                                                    jobClassification
                                                }
                                                isBulk={isBulk}
                                                existingPackageIds={(isBulk
                                                    ? sampleGroups
                                                    : [group]
                                                ).flatMap((g) =>
                                                    g.packages.map(
                                                        (pkgGroup) =>
                                                            pkgGroup.package
                                                                .analysis_package_id,
                                                    ),
                                                )}
                                                existingTypeIds={(isBulk
                                                    ? sampleGroups
                                                    : [group]
                                                ).flatMap((g) =>
                                                    g.individuals
                                                        .map(
                                                            (line) =>
                                                                line.analysis_type_id,
                                                        )
                                                        .filter(
                                                            (
                                                                id,
                                                            ): id is number =>
                                                                id != null,
                                                        )
                                                        .concat(
                                                            g.packages.flatMap(
                                                                (pkgGroup) =>
                                                                    pkgGroup
                                                                        .package
                                                                        .selected_type_ids ??
                                                                    [],
                                                            ),
                                                        ),
                                                )}
                                                packages={
                                                    analysisCatalog.packages
                                                }
                                                analysisTypes={
                                                    analysisCatalog.analysis_types
                                                }
                                            />
                                        ) : null}

                                        <div className="flex items-center justify-between gap-3 border-t border-slate-200 bg-emerald-50/40 px-4 py-2.5 text-sm">
                                            <span className="font-semibold text-slate-800">
                                                Sample {group.sampleIndex + 1}{' '}
                                                subtotal
                                            </span>
                                            <span className="font-bold tabular-nums text-emerald-800">
                                                {money(groupTotal)}
                                            </span>
                                        </div>
                                    </div>
                                ) : null}
                            </div>
                        );
                    })
                )}
            </div>
        </div>
    );
}
