import type { ReactNode } from 'react';
import { ArrowRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type SampleRollup = {
    label: string;
    amount: number;
};

type Props = {
    estimatedTotal: number;
    packagesSubtotal: number;
    individualsSubtotal: number;
    subtotal: number;
    discountPercent: number;
    discountAmount: number;
    savedTotal: number;
    hasUnsavedPricing: boolean;
    discountPercentValue: number;
    discountPercentError?: string;
    onDiscountChange: (value: number) => void;
    processing: boolean;
    money: (value: number) => string;
    sampleRollups: SampleRollup[];
    secondaryActions: ReactNode;
};

export default function ReceivingQuotationAudit({
    estimatedTotal,
    packagesSubtotal,
    individualsSubtotal,
    subtotal,
    discountPercent,
    discountAmount,
    savedTotal,
    hasUnsavedPricing,
    discountPercentValue,
    discountPercentError,
    onDiscountChange,
    processing,
    money,
    sampleRollups,
    secondaryActions,
}: Props) {
    return (
        <div className="space-y-3">
            <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div className="border-b border-slate-200 bg-slate-50/90 px-4 py-2.5">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <h2 className="text-sm font-semibold text-nppc-900">
                            Quotation audit
                        </h2>
                        <Badge
                            variant="outline"
                            className={cn(
                                'text-[10px] font-medium',
                                hasUnsavedPricing
                                    ? 'border-amber-200 bg-amber-50 text-amber-800'
                                    : 'border-emerald-200 bg-emerald-50 text-emerald-800',
                            )}
                        >
                            {hasUnsavedPricing
                                ? 'Unsaved changes'
                                : 'Matches saved'}
                        </Badge>
                    </div>
                    <p className="mt-1.5 text-[11px] font-medium tracking-wide text-slate-500 uppercase">
                        Job order total
                    </p>
                    <p className="font-heading text-2xl font-semibold tabular-nums text-emerald-800">
                        {money(estimatedTotal)}
                    </p>
                </div>

                <div className="space-y-3 border-b border-slate-200 px-4 py-3">
                    {sampleRollups.length > 0 ? (
                        <div>
                            <p className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                                By sample
                            </p>
                            <ul className="mt-1.5 space-y-1">
                                {sampleRollups.map((row) => (
                                    <li
                                        key={row.label}
                                        className="flex justify-between gap-3 text-xs"
                                    >
                                        <span className="truncate text-slate-600">
                                            {row.label}
                                        </span>
                                        <span className="shrink-0 font-semibold tabular-nums text-slate-900">
                                            {money(row.amount)}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    ) : null}

                    <div className="space-y-0.5 text-xs text-slate-500">
                        {packagesSubtotal > 0 || individualsSubtotal > 0 ? (
                            <>
                                <p className="flex justify-between gap-3">
                                    <span>Packages</span>
                                    <span className="tabular-nums text-slate-700">
                                        {money(packagesSubtotal)}
                                    </span>
                                </p>
                                <p className="flex justify-between gap-3">
                                    <span>Individual tests</span>
                                    <span className="tabular-nums text-slate-700">
                                        {money(individualsSubtotal)}
                                    </span>
                                </p>
                            </>
                        ) : null}
                        <p className="flex justify-between gap-3">
                            <span>Subtotal</span>
                            <span className="tabular-nums text-slate-700">
                                {money(subtotal)}
                            </span>
                        </p>
                        {discountPercent > 0 ? (
                            <p className="flex justify-between gap-3">
                                <span>Discount ({discountPercent}%)</span>
                                <span className="tabular-nums text-slate-700">
                                    −{money(discountAmount)}
                                </span>
                            </p>
                        ) : null}
                        <p className="pt-1 text-[11px] text-slate-500">
                            Saved on record: {money(savedTotal)}
                        </p>
                    </div>
                </div>

                <div className="space-y-2.5 px-4 py-3">
                    <div>
                        <label
                            htmlFor="discount_percent"
                            className="mb-0.5 block text-[11px] font-medium text-slate-500"
                        >
                            Discount (%)
                        </label>
                        <Input
                            id="discount_percent"
                            type="number"
                            min={0}
                            max={100}
                            step="0.01"
                            className="h-8 w-full"
                            value={discountPercentValue}
                            onChange={(e) =>
                                onDiscountChange(Number(e.target.value))
                            }
                        />
                        {discountPercentError ? (
                            <p className="mt-1 text-xs text-red-600">
                                {discountPercentError}
                            </p>
                        ) : null}
                    </div>
                    <p
                        className={cn(
                            'text-[11px]',
                            hasUnsavedPricing
                                ? 'text-amber-700'
                                : 'text-emerald-700',
                        )}
                    >
                        {hasUnsavedPricing
                            ? 'Unsaved changes are visible only on this screen until you save.'
                            : 'All pricing changes are saved.'}
                    </p>
                    <Button
                        type="submit"
                        disabled={processing}
                        className="h-9 w-full gap-1.5 bg-nppc-900 text-xs hover:bg-nppc-700"
                    >
                        {processing ? 'Saving…' : 'Save pricing'}
                        {!processing ? (
                            <ArrowRight className="size-3.5" />
                        ) : null}
                    </Button>
                </div>
            </div>

            {secondaryActions ? (
                <div className="rounded-xl border border-slate-200 bg-white px-3 py-2.5 shadow-xs">
                    <p className="mb-2 text-[10px] font-semibold tracking-wider text-slate-500 uppercase">
                        Workflow actions
                    </p>
                    <div className="flex flex-col gap-2">{secondaryActions}</div>
                </div>
            ) : null}
        </div>
    );
}
