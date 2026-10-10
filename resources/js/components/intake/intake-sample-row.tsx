import { ChevronDown, Copy, FlaskConical, Trash2 } from 'lucide-react';
import IntakeField from '@/components/intake/intake-field';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
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

const softInput = 'h-11 w-full border-slate-200 bg-white';

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
    const code = sample.sample_code.trim();
    const badgeLabel = `Sample ${index + 1}${code ? ` · #${code}` : ''}`;
    const descriptionSnippet = sample.description.trim();

    const badge = (
        <span className="inline-flex items-center rounded-md bg-sky-50 px-2 py-0.5 text-[10px] font-semibold tracking-wide text-nppc-900 uppercase ring-1 ring-sky-200/80">
            {badgeLabel}
        </span>
    );

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
            <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5 border-b border-slate-100 bg-white px-4 py-3">
                {collapsible ? (
                    <button
                        type="button"
                        aria-expanded={expanded}
                        aria-controls={fieldsId}
                        onClick={onToggleExpand}
                        className="flex min-w-0 flex-1 items-center gap-2 rounded-md text-left focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                    >
                        <ChevronDown
                            className={cn(
                                'size-3.5 shrink-0 text-nppc-700 transition',
                                expanded && 'rotate-180',
                            )}
                            aria-hidden
                        />
                        <span className="flex min-w-0 flex-wrap items-center gap-2">
                            {badge}
                            {expanded && descriptionSnippet ? (
                                <span className="truncate text-sm text-slate-600">
                                    {descriptionSnippet}
                                </span>
                            ) : null}
                            {!expanded ? (
                                <span className="truncate text-xs text-slate-600">
                                    {summarize(sample)}
                                </span>
                            ) : null}
                        </span>
                    </button>
                ) : (
                    <span className="flex min-w-0 flex-wrap items-center gap-2">
                        {badge}
                        {descriptionSnippet ? (
                            <span className="truncate text-sm text-slate-600">
                                {descriptionSnippet}
                            </span>
                        ) : null}
                    </span>
                )}

                <div className="flex shrink-0 items-center gap-1">
                    <Button
                        type="button"
                        variant="outline"
                        size="sm"
                        className="h-8 gap-1.5 border-slate-200 px-2.5 text-xs"
                        onClick={onDuplicate}
                    >
                        <Copy className="size-3.5" />
                        Duplicate
                    </Button>
                    {removable && (
                        <Button
                            type="button"
                            variant="ghost"
                            size="sm"
                            className="h-8 px-2 text-red-600 hover:text-red-700"
                            onClick={onRemove}
                        >
                            <Trash2 className="size-3.5" />
                            Remove
                        </Button>
                    )}
                </div>
            </div>

            {expanded ? (
                <div
                    id={fieldsId}
                    className="grid grid-cols-1 gap-4 p-4 sm:grid-cols-2 lg:grid-cols-4"
                >
                    <IntakeField
                        label="Sample code / Label"
                        htmlFor={`sample_code_${index}`}
                        required
                    >
                        <Input
                            id={`sample_code_${index}`}
                            className={softInput}
                            value={sample.sample_code}
                            onChange={(e) =>
                                onChange('sample_code', e.target.value)
                            }
                        />
                    </IntakeField>
                    <IntakeField label="Description (Optional)">
                        <Input
                            className={softInput}
                            placeholder="e.g. Drinking water from main kitchen faucet"
                            value={sample.description}
                            onChange={(e) =>
                                onChange('description', e.target.value)
                            }
                        />
                    </IntakeField>
                    <IntakeField label="Sample type" required>
                        <Input
                            className={softInput}
                            placeholder="Water, soil, food, prawn…"
                            value={sample.matrix}
                            onChange={(e) => onChange('matrix', e.target.value)}
                        />
                    </IntakeField>
                    <IntakeField label="Volume / Qty" required>
                        <div className="flex overflow-hidden rounded-md border border-slate-200 bg-white focus-within:ring-2 focus-within:ring-nppc-900/20">
                            <Input
                                className="h-11 flex-1 rounded-none border-0 bg-transparent shadow-none focus-visible:ring-0"
                                value={sample.quantity}
                                onChange={(e) =>
                                    onChange('quantity', e.target.value)
                                }
                            />
                            <Input
                                className="h-11 w-16 shrink-0 rounded-none border-0 border-l border-slate-200 bg-slate-50 text-center text-sm shadow-none focus-visible:ring-0"
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
                        label="Remarks & collection details"
                        className="sm:col-span-2 lg:col-span-4"
                    >
                        <Textarea
                            className="min-h-20 w-full resize-y border-slate-200 bg-white"
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
        <div className="flex items-start gap-2.5 rounded-xl border border-sky-200/80 bg-sky-50/80 px-4 py-3 text-sm text-nppc-900">
            <FlaskConical className="mt-0.5 size-4 shrink-0 text-nppc-700" />
            <p className="leading-snug">
                Microbiological and physico-chemical pairs should be listed as
                separate sample items when they need distinct containers or
                labels. Assign specific tests in the next step.
            </p>
        </div>
    );
}
