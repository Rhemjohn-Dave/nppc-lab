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
            className="flex w-full max-w-md flex-col overflow-hidden rounded-lg border bg-white shadow-sm"
        >
            <div className="shrink-0 border-b px-5 pt-5 pb-3">
                <h2 className="text-lg font-semibold text-slate-900">
                    Confirm analyst signatories
                </h2>
                <p className="mt-1 text-sm text-muted-foreground">
                    Enter the analyst name{config.slots > 1 ? 's' : ''} that will appear on
                    the result form
                    {config.require_prc ? ', including PRC ID' : ' (PRC ID optional)'}. Required
                    before sending to Head.
                </p>
            </div>

            <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-5 py-4">
                {rows.map((row, index) => (
                    <div key={index} className="space-y-2 rounded-lg border p-3">
                        <p className="text-xs font-medium uppercase tracking-wide text-slate-500">
                            {config.slot_labels?.[index] ?? `Analyst ${index + 1}`}
                        </p>
                        <div>
                            <Label htmlFor={`signatory-name-${index}`}>Name</Label>
                            <Input
                                id={`signatory-name-${index}`}
                                value={row.name}
                                disabled={submitting}
                                onChange={(event) =>
                                    updateRow(index, { name: event.target.value })
                                }
                                placeholder="Full name"
                                autoFocus={index === 0}
                            />
                        </div>
                        <div>
                            <Label htmlFor={`signatory-prc-${index}`}>
                                PRC ID
                                {!config.require_prc ? ' (optional)' : ''}
                            </Label>
                            <Input
                                id={`signatory-prc-${index}`}
                                value={row.prc_id ?? ''}
                                disabled={submitting}
                                onChange={(event) =>
                                    updateRow(index, { prc_id: event.target.value })
                                }
                                placeholder="PRC license number"
                            />
                        </div>
                    </div>
                ))}
                {error && <p className="text-sm text-red-700">{error}</p>}
            </div>

            <div className="flex shrink-0 flex-wrap justify-end gap-2 border-t bg-white px-5 py-3">
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
                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                    disabled={submitting}
                >
                    {submitting ? 'Saving…' : confirmLabel}
                </Button>
            </div>
        </form>
    );
}
