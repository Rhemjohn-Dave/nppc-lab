import { useEffect, useState, type FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';

export type ResultFooterConfig = {
    lab_conditions: string;
    references: string;
    notes: string;
    save_url?: string | null;
    can_edit?: boolean;
};

type Props = {
    config: ResultFooterConfig;
    submitting?: boolean;
    error?: string | null;
    confirmLabel?: string;
    onCancel: () => void;
    onConfirm: (footer: {
        lab_conditions: string;
        references: string;
        notes: string;
    }) => void;
    onReset?: () => void;
};

/**
 * Edit FO2 / FO2A flowing footer text (lab conditions, references, notes).
 */
export default function ResultFooterPanel({
    config,
    submitting = false,
    error = null,
    confirmLabel = 'Save footer notes',
    onCancel,
    onConfirm,
    onReset,
}: Props) {
    const [labConditions, setLabConditions] = useState(config.lab_conditions);
    const [references, setReferences] = useState(config.references);
    const [notes, setNotes] = useState(config.notes);

    useEffect(() => {
        setLabConditions(config.lab_conditions);
        setReferences(config.references);
        setNotes(config.notes);
    }, [config.lab_conditions, config.references, config.notes]);

    function submit(event: FormEvent) {
        event.preventDefault();
        onConfirm({
            lab_conditions: labConditions,
            references,
            notes,
        });
    }

    return (
        <form
            onSubmit={submit}
            className="flex max-h-[min(85vh,40rem)] w-full flex-col overflow-hidden rounded-lg border bg-white shadow-sm"
        >
            <div className="shrink-0 border-b px-5 pt-4 pb-3">
                <h2 className="font-heading text-base font-semibold text-nppc-900 sm:text-lg">
                    Result footer notes
                </h2>
                <p className="mt-1 text-xs text-slate-600">
                    Lab conditions, references, and notes print under the FO2 /
                    FO2A test matrix. Leave blank sections to keep the default
                    text for this sample kind.
                </p>
            </div>

            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4">
                {error ? (
                    <p
                        className="border-l-2 border-rose-300 py-1 pl-2 text-xs font-medium text-rose-700"
                        role="alert"
                    >
                        {error}
                    </p>
                ) : null}

                <div className="space-y-1.5">
                    <Label htmlFor="fo2_lab_conditions">Lab conditions</Label>
                    <Textarea
                        id="fo2_lab_conditions"
                        rows={3}
                        className="text-sm"
                        value={labConditions}
                        onChange={(e) => setLabConditions(e.target.value)}
                        disabled={submitting}
                    />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="fo2_references">References</Label>
                    <Textarea
                        id="fo2_references"
                        rows={3}
                        className="text-sm"
                        value={references}
                        onChange={(e) => setReferences(e.target.value)}
                        disabled={submitting}
                    />
                </div>

                <div className="space-y-1.5">
                    <Label htmlFor="fo2_notes">Notes</Label>
                    <Textarea
                        id="fo2_notes"
                        rows={8}
                        className="font-mono text-xs leading-relaxed"
                        value={notes}
                        onChange={(e) => setNotes(e.target.value)}
                        disabled={submitting}
                    />
                </div>
            </div>

            <div className="flex shrink-0 flex-wrap items-center justify-end gap-2 border-t bg-slate-50/80 px-5 py-3">
                {onReset ? (
                    <Button
                        type="button"
                        variant="ghost"
                        className="mr-auto text-xs"
                        disabled={submitting}
                        onClick={onReset}
                    >
                        Reset to default
                    </Button>
                ) : null}
                <Button
                    type="button"
                    variant="outline"
                    disabled={submitting}
                    onClick={onCancel}
                >
                    Cancel
                </Button>
                <Button type="submit" disabled={submitting}>
                    {submitting ? 'Saving…' : confirmLabel}
                </Button>
            </div>
        </form>
    );
}
