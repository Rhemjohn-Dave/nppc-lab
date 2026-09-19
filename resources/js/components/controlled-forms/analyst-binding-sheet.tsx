import { ChevronRight } from 'lucide-react';
import AnalysisTypePicker from '@/components/analysis-type-picker';
import PackageSelect, {
    type AnalysisPackageOption,
} from '@/components/package-select';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import type { AnalysisGroup } from '@/lib/controlled-forms';

export type AnalystBindingData = {
    analysis_type_ids: number[];
    analysis_package_id: number | '';
    analyst_signatory_slots: number;
    analyst_require_prc: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    packages: AnalysisPackageOption[];
    analysisGroups: AnalysisGroup[];
    data: AnalystBindingData;
    errors: {
        analysis_type_ids?: string;
    };
    processing: boolean;
    summaryLabel: string;
    hideTrigger?: boolean;
    onChange: (patch: Partial<AnalystBindingData>) => void;
    onSave: () => void;
};

export default function AnalystBindingSheet({
    open,
    onOpenChange,
    packages,
    analysisGroups,
    data,
    errors,
    processing,
    summaryLabel,
    hideTrigger = false,
    onChange,
    onSave,
}: Props) {
    return (
        <>
            {!hideTrigger ? (
                <button
                    type="button"
                    onClick={() => onOpenChange(true)}
                    className="flex w-full items-center justify-between gap-3 rounded-lg border bg-white px-3 py-2 text-left transition-colors hover:bg-slate-50/80 focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none"
                    aria-haspopup="dialog"
                    aria-expanded={open}
                >
                    <div className="min-w-0">
                        <p className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                            Analyst result binding
                        </p>
                        <p className="mt-0.5 truncate text-sm text-slate-700">
                            {summaryLabel}
                        </p>
                    </div>
                    <ChevronRight
                        className="size-4 shrink-0 text-muted-foreground"
                        aria-hidden="true"
                    />
                </button>
            ) : null}

            <Sheet open={open} onOpenChange={onOpenChange}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col gap-0 overflow-hidden p-0 sm:max-w-lg md:max-w-xl"
                >
                    <SheetHeader className="space-y-1 border-b px-4 pt-4 pb-3 pr-12 text-left">
                        <SheetTitle className="text-base text-[#1A3694]">
                            Analyst result binding
                        </SheetTitle>
                        <SheetDescription className="text-xs leading-relaxed">
                            Bind a paid package when the customer buys the whole
                            panel, or bind analysis types only for individual
                            pay.
                        </SheetDescription>
                    </SheetHeader>

                    <form
                        className="flex min-h-0 flex-1 flex-col"
                        onSubmit={(event) => {
                            event.preventDefault();
                            onSave();
                        }}
                    >
                        <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-4 py-3">
                            <PackageSelect
                                packages={packages}
                                value={data.analysis_package_id}
                                onChange={(packageId, typeIds) => {
                                    onChange({
                                        analysis_package_id: packageId ?? '',
                                        analysis_type_ids: packageId
                                            ? typeIds
                                            : data.analysis_type_ids,
                                    });
                                }}
                            />
                            <AnalysisTypePicker
                                groups={analysisGroups}
                                selectedIds={data.analysis_type_ids}
                                onChange={(ids) => {
                                    onChange({
                                        analysis_type_ids: ids,
                                        analysis_package_id: '',
                                    });
                                }}
                                error={errors.analysis_type_ids}
                                className={
                                    data.analysis_package_id
                                        ? 'pointer-events-none opacity-60'
                                        : undefined
                                }
                            />
                            <div className="grid gap-2 rounded-md border border-slate-200 bg-slate-50/70 p-2.5 sm:grid-cols-2">
                                <div>
                                    <Label htmlFor="analyst-slots">
                                        Analyst signatory slots
                                    </Label>
                                    <select
                                        id="analyst-slots"
                                        className="mt-1 flex h-9 w-full rounded-md border border-input bg-white px-3 text-sm"
                                        value={data.analyst_signatory_slots}
                                        onChange={(event) =>
                                            onChange({
                                                analyst_signatory_slots: Number(
                                                    event.target.value,
                                                ),
                                            })
                                        }
                                    >
                                        <option value={1}>1 analyst</option>
                                        <option value={2}>2 analysts</option>
                                        <option value={3}>3 signatories</option>
                                        <option value={4}>
                                            4 signatories (FO2)
                                        </option>
                                    </select>
                                    <p className="mt-1 text-xs text-muted-foreground">
                                        Prompted before Print / Download of the
                                        result PDF.
                                    </p>
                                </div>
                                <div className="flex items-start gap-2 pt-5">
                                    <input
                                        id="analyst-require-prc"
                                        type="checkbox"
                                        className="mt-1 size-4 rounded border"
                                        checked={data.analyst_require_prc}
                                        onChange={(event) =>
                                            onChange({
                                                analyst_require_prc:
                                                    event.target.checked,
                                            })
                                        }
                                    />
                                    <div>
                                        <Label htmlFor="analyst-require-prc">
                                            Require PRC ID
                                        </Label>
                                        <p className="text-xs text-muted-foreground">
                                            When off, PRC is not collected or
                                            printed for this form.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div className="flex shrink-0 flex-wrap gap-2 border-t px-4 py-3">
                            <Button
                                type="button"
                                variant="outline"
                                disabled={processing}
                                onClick={() => onOpenChange(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing}
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                            >
                                {processing ? 'Saving…' : 'Save bound tests'}
                            </Button>
                        </div>
                    </form>
                </SheetContent>
            </Sheet>
        </>
    );
}
