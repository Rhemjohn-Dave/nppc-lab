import {
    Barcode,
    ChevronDown,
    Copy,
    FileText,
    FlaskConical,
    Layers,
    MessageSquareText,
    Trash2,
} from 'lucide-react';
import IntakeAlert from '@/components/intake/intake-alert';
import IntakeField from '@/components/intake/intake-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import { cn } from '@/lib/utils';

export type IntakeSampleDraft = {
    sample_code: string;
    description: string;
    matrix: string;
    quantity: string;
    unit: string;
    remarks: string;
    classification: string;
    classification_other: string;
    classification_other_specify: string;
};

type Props = {
    sample: IntakeSampleDraft;
    index: number;
    expanded: boolean;
    collapsible: boolean;
    removable: boolean;
    onToggleExpand: () => void;
    onChange: (key: keyof IntakeSampleDraft, value: string) => void;
    onDuplicate: () => void;
    onRemove: () => void;
    /** Wastewater Sample description kinds (FO2 vs FO2A); replaces free-text Description. */
    wastewaterKinds?: string[];
    showWastewaterKind?: boolean;
    wastewaterKindError?: boolean;
    classifications?: string[];
    otherClassificationOptions?: string[];
    classificationError?: boolean;
    onClassificationChange?: (value: string) => void;
    onClassificationOtherChange?: (value: string) => void;
    onClassificationOtherSpecifyChange?: (value: string) => void;
};

const softInput =
    'h-9 w-full rounded-lg border-slate-200 bg-white text-sm';

function summarize(sample: IntakeSampleDraft) {
    return (
        [
            sample.classification.trim() || null,
            sample.matrix.trim() || null,
            sample.quantity.trim()
                ? `${sample.quantity.trim()}${sample.unit.trim() ? ` ${sample.unit.trim()}` : ''}`
                : null,
            sample.description.trim() || null,
        ]
            .filter(Boolean)
            .join(' · ') || 'No details yet'
    );
}

/** One sample in the intake list: SAMPLE N tab plus expandable field grid. */
export default function IntakeSampleRow({
    sample,
    index,
    expanded,
    collapsible,
    removable,
    onToggleExpand,
    onChange,
    onDuplicate,
    onRemove,
    wastewaterKinds = [],
    showWastewaterKind = false,
    wastewaterKindError = false,
    classifications = [],
    otherClassificationOptions = [],
    classificationError = false,
    onClassificationChange,
    onClassificationOtherChange,
    onClassificationOtherSpecifyChange,
}: Props) {
    const fieldsId = `sample-fields-${index}`;
    const code = sample.sample_code.trim();
    const tabLabel = `Sample ${index + 1}`;
    const kindSet = new Set(wastewaterKinds);

    return (
        <div
            id={`sample-card-${index}`}
            className={cn(
                'overflow-hidden rounded-xl border bg-white shadow-xs transition',
                expanded
                    ? 'border-nppc-900/25'
                    : 'border-slate-200 hover:border-nppc-500/50',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-2 border-b border-slate-100 bg-slate-50/70 pr-2.5">
                {collapsible ? (
                    <button
                        type="button"
                        aria-expanded={expanded}
                        aria-controls={fieldsId}
                        onClick={onToggleExpand}
                        className="flex min-w-0 items-center gap-1.5 rounded-none focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                    >
                        <span className="inline-flex items-center gap-1.5 rounded-br-lg bg-nppc-900 px-3 py-2 text-[10px] font-bold tracking-wider text-white uppercase">
                            <ChevronDown
                                className={cn(
                                    'size-3.5 shrink-0 transition',
                                    expanded && 'rotate-180',
                                )}
                                aria-hidden
                            />
                            {tabLabel}
                            {code ? (
                                <span className="font-semibold normal-case tracking-normal opacity-80">
                                    · #{code}
                                </span>
                            ) : null}
                        </span>
                        {!expanded ? (
                            <span className="truncate px-2 text-xs text-slate-600">
                                {summarize(sample)}
                            </span>
                        ) : null}
                    </button>
                ) : (
                    <span className="inline-flex items-center rounded-br-lg bg-nppc-900 px-3 py-2 text-[10px] font-bold tracking-wider text-white uppercase">
                        {tabLabel}
                    </span>
                )}

                <div className="flex shrink-0 items-center gap-1 py-1.5">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-7 gap-1.5 border-slate-200 px-2.5 text-xs"
                        onClick={onDuplicate}
                    >
                        <Copy className="size-3.5" />
                        Duplicate
                    </Button>
                    {removable ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-7 px-2 text-red-600 hover:text-red-700"
                            onClick={onRemove}
                        >
                            <Trash2 className="size-3.5" />
                            Remove
                        </Button>
                    ) : null}
                </div>
            </div>

            {expanded ? (
                <div
                    id={fieldsId}
                    className="grid grid-cols-1 gap-2 p-3 sm:grid-cols-2 sm:gap-x-3 sm:gap-y-2 sm:p-3.5"
                >
                    {classifications.length > 0 ? (
                        <div className="sm:col-span-2">
                            <p className="mb-1.5 text-[11px] font-semibold text-slate-700">
                                Classification{' '}
                                <span className="text-rose-600">*</span>
                            </p>
                            <div
                                className="flex flex-wrap gap-1.5"
                                role="group"
                                aria-label={`Sample ${index + 1} classification`}
                            >
                                {classifications.map((item) => {
                                    const active =
                                        sample.classification === item;

                                    return (
                                        <button
                                            key={item}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() =>
                                                onClassificationChange?.(item)
                                            }
                                            className={cn(
                                                'rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition',
                                                active
                                                    ? 'border-nppc-900 bg-nppc-900 text-white'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500/60',
                                            )}
                                        >
                                            {item}
                                        </button>
                                    );
                                })}
                            </div>
                            {classificationError &&
                            !sample.classification.trim() ? (
                                <p
                                    className="mt-1.5 border-l-2 border-rose-300 py-1 pl-2 text-xs font-medium text-rose-700"
                                    role="alert"
                                    data-intake-error=""
                                >
                                    Choose a classification for this sample.
                                </p>
                            ) : null}
                            {sample.classification === 'Others' ? (
                                <div className="mt-2 grid grid-cols-1 gap-2 md:grid-cols-2">
                                    <IntakeField
                                        label="Parameter category"
                                        htmlFor={`classification_other_${index}`}
                                        required
                                    >
                                        <Select
                                            value={
                                                sample.classification_other ||
                                                undefined
                                            }
                                            onValueChange={(value) =>
                                                onClassificationOtherChange?.(
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger
                                                id={`classification_other_${index}`}
                                                className="h-9 w-full rounded-lg border-slate-200 bg-white text-sm shadow-none"
                                            >
                                                <SelectValue placeholder="Select category…" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                {otherClassificationOptions.map(
                                                    (option) => (
                                                        <SelectItem
                                                            key={option}
                                                            value={option}
                                                        >
                                                            {option}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </IntakeField>
                                    {sample.classification_other
                                        .trim()
                                        .toLowerCase() === 'other' ? (
                                        <IntakeField
                                            label="Specify"
                                            htmlFor={`classification_other_specify_${index}`}
                                            required
                                        >
                                            <Input
                                                id={`classification_other_specify_${index}`}
                                                className={softInput}
                                                value={
                                                    sample.classification_other_specify
                                                }
                                                onChange={(e) =>
                                                    onClassificationOtherSpecifyChange?.(
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </IntakeField>
                                    ) : null}
                                </div>
                            ) : null}
                        </div>
                    ) : null}
                    <IntakeField
                        label="Sample Code / Label"
                        htmlFor={`sample_code_${index}`}
                        required
                        icon={Barcode}
                    >
                        <Input
                            id={`sample_code_${index}`}
                            className={softInput}
                            placeholder="Enter sample code or label"
                            value={sample.sample_code}
                            onChange={(e) =>
                                onChange('sample_code', e.target.value)
                            }
                        />
                    </IntakeField>
                    {showWastewaterKind ? (
                        <IntakeField
                            label="Sample description"
                            required
                            icon={FileText}
                            className="sm:col-span-2"
                        >
                            <p className="mb-1.5 text-[11px] leading-snug text-slate-500">
                                Printed on the result sheet. Wastewater and
                                River water default to the full physico panel;
                                Abstracted water defaults to pH, DO, BOD, and
                                TSS. Each sample can differ (FO2 vs FO2A).
                            </p>
                            <div
                                className="flex flex-wrap gap-1.5"
                                role="group"
                                aria-label={`Sample ${index + 1} description`}
                            >
                                {wastewaterKinds.map((kind) => {
                                    const active = sample.description === kind;

                                    return (
                                        <button
                                            key={kind}
                                            type="button"
                                            aria-pressed={active}
                                            onClick={() =>
                                                onChange(
                                                    'description',
                                                    active ? '' : kind,
                                                )
                                            }
                                            className={cn(
                                                'rounded-lg border px-2.5 py-1.5 text-xs font-semibold transition',
                                                active
                                                    ? 'border-nppc-900 bg-nppc-900 text-white'
                                                    : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500/60',
                                            )}
                                        >
                                            {kind}
                                        </button>
                                    );
                                })}
                            </div>
                            {wastewaterKindError &&
                            !kindSet.has(sample.description.trim()) ? (
                                <p
                                    className="mt-1.5 border-l-2 border-rose-300 py-1 pl-2 text-xs font-medium text-rose-700"
                                    role="alert"
                                    data-intake-error=""
                                >
                                    Choose Wastewater, River water, or
                                    Abstracted water for this sample.
                                </p>
                            ) : null}
                        </IntakeField>
                    ) : (
                        <IntakeField
                            label="Description"
                            hint="(optional)"
                            icon={FileText}
                        >
                            <Input
                                className={softInput}
                                placeholder="e.g. Drinking water from main kitchen faucet"
                                value={sample.description}
                                onChange={(e) =>
                                    onChange('description', e.target.value)
                                }
                            />
                        </IntakeField>
                    )}
                    <IntakeField
                        label="Sample Type"
                        required
                        icon={FlaskConical}
                    >
                        <Input
                            className={softInput}
                            placeholder="Water, soil, food, prawn, etc."
                            value={sample.matrix}
                            onChange={(e) => onChange('matrix', e.target.value)}
                        />
                    </IntakeField>
                    <IntakeField label="Volume / Qty" required>
                        <div className="relative flex overflow-hidden rounded-lg border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-nppc-900/20">
                            <Layers
                                className="pointer-events-none absolute top-2.5 left-2.5 size-3.5 text-slate-400"
                                aria-hidden
                            />
                            <Input
                                className="h-9 flex-1 rounded-none border-0 bg-transparent pl-9 text-sm shadow-none focus-visible:ring-0"
                                value={sample.quantity}
                                onChange={(e) =>
                                    onChange('quantity', e.target.value)
                                }
                            />
                            <Input
                                className="h-9 w-16 shrink-0 rounded-none border-0 border-l border-slate-200 bg-slate-50 text-center text-sm shadow-none focus-visible:ring-0"
                                placeholder="mL"
                                value={sample.unit}
                                onChange={(e) =>
                                    onChange('unit', e.target.value)
                                }
                                aria-label="Unit"
                            />
                        </div>
                    </IntakeField>
                    <IntakeField
                        label="Remarks & Collection Details"
                        hint="(optional)"
                        icon={MessageSquareText}
                        className="sm:col-span-2"
                    >
                        <Textarea
                            rows={2}
                            className="min-h-[2.75rem] w-full resize-y rounded-lg border-slate-200 bg-white py-1.5 text-sm"
                            placeholder="Optional notes on arrival condition or collection"
                            value={sample.remarks}
                            onChange={(e) =>
                                onChange('remarks', e.target.value)
                            }
                        />
                    </IntakeField>
                </div>
            ) : null}
        </div>
    );
}

export function IntakeSamplesTip() {
    return (
        <IntakeAlert variant="info" className="px-2.5 py-2 text-xs">
            Microbiological and physico-chemical pairs should be listed as
            separate sample items when they need distinct containers or
            preservation.
        </IntakeAlert>
    );
}
