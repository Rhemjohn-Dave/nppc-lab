import { ChevronDown, Trash2 } from 'lucide-react';
import IntakeField from '@/components/intake/intake-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type IntakeSampleDraft = {
    sample_code: string;
    description: string;
    matrix: string;
    quantity: string;
    unit: string;
    remarks: string;
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
};

function summarize(sample: IntakeSampleDraft) {
    return (
        [
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

/** One sample in the intake list: summary header plus an expandable field grid. */
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
}: Props) {
    const fieldsId = `sample-fields-${index}`;
    const title = `Sample ${index + 1}${sample.sample_code.trim() ? ` · ${sample.sample_code.trim()}` : ''}`;

    return (
        <div
            id={`sample-card-${index}`}
            className={cn(
                'rounded-xl border bg-white transition',
                expanded
                    ? 'border-[#1A3694]/40 shadow-sm'
                    : 'border-slate-200/80 hover:border-[#5282D3]/60',
            )}
        >
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 p-3 sm:p-3.5">
                {collapsible ? (
                    <button
                        type="button"
                        aria-expanded={expanded}
                        aria-controls={fieldsId}
                        onClick={onToggleExpand}
                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md text-left focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none"
                    >
                        <ChevronDown
                            className={cn(
                                'size-4 shrink-0 text-[#365BB0] transition',
                                expanded && 'rotate-180',
                            )}
                            aria-hidden
                        />
                        <span className="min-w-0">
                            <span className="block truncate text-sm font-semibold text-[#1A3694]">
                                {title}
                            </span>
                            {!expanded ? (
                                <span className="block truncate text-sm text-slate-600">
                                    {summarize(sample)}
                                </span>
                            ) : null}
                        </span>
                    </button>
                ) : (
                    <p className="min-w-0 flex-1 truncate text-sm font-semibold text-[#1A3694]">
                        {title}
                    </p>
                )}

                <div className="flex shrink-0 items-center gap-2">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-9"
                        onClick={onDuplicate}
                    >
                        Duplicate
                    </Button>
                    {removable && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-9 text-red-600 hover:text-red-700"
                            onClick={onRemove}
                        >
                            <Trash2 className="size-4" />
                            Remove
                        </Button>
                    )}
                </div>
            </div>

            {expanded ? (
                <div
                    id={fieldsId}
                    className="grid grid-cols-1 gap-3 border-t border-slate-100 p-3 sm:grid-cols-2 sm:p-3.5 lg:grid-cols-4"
                >
                    <IntakeField
                        label="Sample code"
                        htmlFor={`sample_code_${index}`}
                    >
                        <Input
                            id={`sample_code_${index}`}
                            className="h-10 w-full bg-white"
                            value={sample.sample_code}
                            onChange={(e) =>
                                onChange('sample_code', e.target.value)
                            }
                        />
                    </IntakeField>
                    <IntakeField label="Description">
                        <Input
                            className="h-10 w-full bg-white"
                            placeholder="Optional — e.g. Drinking water from main faucet"
                            value={sample.description}
                            onChange={(e) =>
                                onChange('description', e.target.value)
                            }
                        />
                    </IntakeField>
                    <IntakeField label="Sample type">
                        <Input
                            className="h-10 w-full bg-white"
                            placeholder="Water, soil, food, prawn…"
                            value={sample.matrix}
                            onChange={(e) => onChange('matrix', e.target.value)}
                        />
                    </IntakeField>
                    <div className="grid grid-cols-2 gap-2">
                        <IntakeField label="Quantity">
                            <Input
                                className="h-10 w-full bg-white"
                                value={sample.quantity}
                                onChange={(e) =>
                                    onChange('quantity', e.target.value)
                                }
                            />
                        </IntakeField>
                        <IntakeField label="Unit">
                            <Input
                                className="h-10 w-full bg-white"
                                placeholder="mL, g…"
                                value={sample.unit}
                                onChange={(e) =>
                                    onChange('unit', e.target.value)
                                }
                            />
                        </IntakeField>
                    </div>
                    <IntakeField
                        label="Remarks"
                        className="sm:col-span-2 lg:col-span-4"
                    >
                        <Input
                            className="h-10 w-full bg-white"
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
