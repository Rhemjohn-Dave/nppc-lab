import { Button } from '@/components/ui/button';

type Props = {
    slots: number;
    requirePrc: boolean;
    onEdit?: () => void;
};

export default function SignatorySlotsCard({ slots, requirePrc, onEdit }: Props) {
    const slotLabels = [
        'Slot 1: Primary analyst',
        'Slot 2: Second analyst / verifier',
        'Slot 3: Additional signatory',
        'Slot 4: Additional signatory',
    ];

    return (
        <section className="rounded-lg border bg-white px-3 py-2">
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <div>
                    <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                        Analyst signatory slots
                    </h2>
                    <p className="mt-0.5 text-[11px] text-muted-foreground">
                        Signatory blocks printed on canonical generation
                    </p>
                </div>
                {onEdit ? (
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="h-7 px-2 text-xs text-[#1A3694]"
                        onClick={onEdit}
                    >
                        Edit
                    </Button>
                ) : null}
            </div>
            <div className="grid gap-1.5 sm:grid-cols-2">
                {slotLabels.slice(0, Math.max(1, Math.min(4, slots))).map((label, index) => (
                    <div
                        key={label}
                        className="rounded-md border px-2.5 py-2"
                    >
                        <div className="mb-1 flex items-center justify-between gap-2">
                            <p className="text-xs font-semibold text-slate-900">{label}</p>
                            <span className="rounded border border-amber-200 bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">
                                {index === 0 ? 'Mandatory' : 'Configured'}
                            </span>
                        </div>
                        <p className="text-[11px] text-muted-foreground">
                            {index === 0
                                ? 'Primary analyst signature on the result sheet.'
                                : 'Additional authorization line on the printed form.'}
                        </p>
                    </div>
                ))}
            </div>
            <p className="mt-2 text-[11px] text-muted-foreground">
                {requirePrc
                    ? 'PRC Chemist / Chem Tech license validation required at sign-off.'
                    : 'PRC license validation is not required for this form.'}
            </p>
        </section>
    );
}
