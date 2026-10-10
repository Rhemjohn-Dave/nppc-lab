import { useEffect, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

export type ResultSignatoryRow = {
    name: string;
    prc_id: string | null;
};

export type ResultSignatoryConfig = {
    slots: number;
    slot_labels?: string[];
    require_prc: boolean;
    confirmed: boolean;
    can_edit?: boolean;
    signatories: ResultSignatoryRow[];
    save_url?: string | null;
};

type Props = {
    config: ResultSignatoryConfig;
    submitting?: boolean;
    error?: string | null;
    confirmLabel?: string;
    onCancel: () => void;
    onConfirm: (signatories: ResultSignatoryRow[]) => void;
};

function blankRows(slots: number, existing: ResultSignatoryRow[] = []): ResultSignatoryRow[] {
    const count = Math.max(1, Math.min(4, slots));

    return Array.from({ length: count }, (_, index) => ({
        name: existing[index]?.name ?? '',
        prc_id: existing[index]?.prc_id ?? '',
    }));
}

/**
 * In-dialog form (must stay inside DialogContent so Radix focus trap allows typing).
 */
export default function ResultSignatoryPanel({
    config,
    submitting = false,
    error = null,
    confirmLabel = 'Confirm & continue',
    onCancel,
    onConfirm,
}: Props) {
    const [rows, setRows] = useState<ResultSignatoryRow[]>(() =>
        blankRows(config.slots, config.signatories),
    );

    useEffect(() => {
        setRows(blankRows(config.slots, config.signatories));
    }, [config.slots, config.signatories]);

    function updateRow(index: number, patch: Partial<ResultSignatoryRow>) {
        setRows((current) =>
            current.map((row, i) => (i === index ? { ...row, ...patch } : row)),
        );
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        onConfirm(
            rows.map((row) => ({
                name: row.name.trim(),
                prc_id: row.prc_id?.trim() ? row.prc_id.trim() : null,
            })),
        );
    }

    return (
        <form
            onSubmit={submit}
            className="flex max-h-[min(85vh,36rem)] w-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm"
        >
            <div className="shrink-0 border-b px-5 pt-4 pb-3">
                <h2 className="font-heading text-base font-semibold text-nppc-900 sm:text-lg">
                    Confirm analyst signatories
                </h2>
                <p className="mt-1 text-xs text-muted-foreground sm:text-sm">
                    Names
                    {config.require_prc ? ' and PRC IDs' : ' (PRC optional)'} that
                    appear on the result form — required before Send to Head.
                </p>
            </div>

            <div className="min-h-0 flex-1 space-y-2.5 overflow-y-auto px-5 py-3">
                {rows.map((row, index) => (
                    <div
                        key={index}
                        className="space-y-1.5 rounded-md border border-slate-200 p-2.5"
                    >
                        <p className="text-[10px] font-semibold tracking-wide text-slate-500 uppercase">
                            {config.slot_labels?.[index] ?? `Analyst ${index + 1}`}
                        </p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label
                                    htmlFor={`signatory-name-${index}`}
                                    className="text-xs"
                                >
                                    Name
                                </Label>
                                <Input
                                    id={`signatory-name-${index}`}
                                    className="h-9"
                                    value={row.name}
                                    disabled={submitting}
                                    onChange={(event) =>
                                        updateRow(index, {
                                            name: event.target.value,
                                        })
                                    }
                                    placeholder="Full name"
                                    autoFocus={index === 0}
                                />
                            </div>
                            <div className="space-y-1">
                                <Label
                                    htmlFor={`signatory-prc-${index}`}
                                    className="text-xs"
                                >
                                    PRC ID
                                    {!config.require_prc ? ' (optional)' : ''}
                                </Label>
                                <Input
                                    id={`signatory-prc-${index}`}
                                    className="h-9"
                                    value={row.prc_id ?? ''}
                                    disabled={submitting}
                                    onChange={(event) =>
                                        updateRow(index, {
                                            prc_id: event.target.value,
                                        })
                                    }
                                    placeholder="PRC license number"
                                />
                            </div>
                        </div>
                    </div>
                ))}
                {error ? (
                    <p className="text-sm text-red-700" role="alert">
                        {error}
                    </p>
                ) : null}
            </div>

            <div className="flex shrink-0 flex-wrap justify-end gap-2 border-t bg-slate-50/80 px-5 py-3">
                <Button
                    type="button"
                    variant="outline"
                    disabled={submitting}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button
                    type="submit"
                    className="bg-nppc-900 text-white hover:bg-nppc-700"
                    disabled={submitting}
                >
                    {submitting ? 'Saving…' : confirmLabel}
                </Button>
            </div>
        </form>
    );
}
