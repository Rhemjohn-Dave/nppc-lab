import { useState } from 'react';
import { Check, ChevronDown } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export type IntakeTypePreset = {
    key: string;
    label: string;
    description: string | null;
    form_code: string | null;
    classifications: string[];
    analysis_type_ids: number[];
    tests: Array<{
        id: number;
        code: string;
        name: string;
        default_price?: string | number;
    }>;
};

type Props = {
    preset: IntakeTypePreset;
    selected: boolean;
    selectedTypes: number[];
    estimatedPrice: number;
    onTogglePreset: () => void;
    onToggleMember: (typeId: number) => void;
};

/**
 * Package-like row for non-package type panels (e.g. FO2 wastewater physico).
 * Only toggles analysis_type_ids — never package_ids.
 */
export default function IntakeTypePresetRow({
    preset,
    selected,
    selectedTypes,
    estimatedPrice,
    onTogglePreset,
    onToggleMember,
}: Props) {
    const [detailsOpen, setDetailsOpen] = useState(false);

    const selectedMemberCount = preset.analysis_type_ids.filter((id) =>
        selectedTypes.includes(id),
    ).length;

    const detailsId = `preset-details-${preset.key}`;

    return (
        <div
            className={cn(
                'rounded-xl border bg-white transition',
                selected
                    ? 'border-[#1A3694] shadow-sm'
                    : 'border-slate-200/80 hover:border-[#5282D3]/60',
            )}
        >
            <div className="p-3 sm:p-3.5">
                <div className="flex flex-wrap items-center justify-between gap-x-3 gap-y-1.5">
                    <div className="min-w-0 flex-1">
                        <div className="flex min-w-0 flex-wrap items-center gap-2">
                            <p className="min-w-0 truncate font-semibold text-[#1A3694]">
                                {preset.label}
                            </p>
                            <span className="rounded-md bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-800">
                                Individual pay
                            </span>
                        </div>
                        <p className="mt-0.5 text-sm text-slate-600">
                            {preset.tests.length} test
                            {preset.tests.length === 1 ? '' : 's'} · ₱
                            {estimatedPrice.toFixed(2)}
                            {selected
                                ? ` · ${selectedMemberCount} included`
                                : ''}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onTogglePreset}
                        aria-pressed={selected}
                        className={cn(
                            'flex h-10 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none',
                            selected
                                ? 'bg-[#1A3694] text-white'
                                : 'border border-[#1A3694]/30 text-[#1A3694] hover:bg-[#eef3fb]',
                        )}
                    >
                        {selected ? (
                            <>
                                <Check className="size-4" aria-hidden />
                                Selected
                            </>
                        ) : (
                            'Select'
                        )}
                    </button>
                </div>

                {preset.description ? (
                    <p className="mt-1 line-clamp-1 text-sm text-slate-600">
                        {preset.description}
                    </p>
                ) : null}

                {preset.tests.length > 0 ? (
                    <button
                        type="button"
                        aria-expanded={detailsOpen}
                        aria-controls={detailsId}
                        onClick={() => setDetailsOpen((open) => !open)}
                        className="mt-1.5 flex items-center gap-1 text-xs font-medium text-[#365BB0] hover:underline focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none"
                    >
                        {selected ? 'Adjust tests' : 'View tests'}
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition',
                                detailsOpen && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>
                ) : null}
            </div>

            {detailsOpen && preset.tests.length > 0 ? (
                <div
                    id={detailsId}
                    className={cn(
                        'border-t px-3 py-2.5 sm:px-3.5',
                        selected
                            ? 'border-[#d7e2f5] bg-[#eef3fb]/60'
                            : 'border-slate-100',
                    )}
                >
                    {selected ? (
                        <>
                            <p className="text-xs text-muted-foreground">
                                Uncheck tests you do not need. Selected tests
                                are billed individually and print on the FO2
                                result sheet.
                            </p>
                            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
                                {preset.tests.map((test) => (
                                    <label
                                        key={test.id}
                                        className="flex items-center gap-2 text-sm text-slate-700"
                                    >
                                        <Checkbox
                                            checked={selectedTypes.includes(
                                                test.id,
                                            )}
                                            onCheckedChange={() =>
                                                onToggleMember(test.id)
                                            }
                                        />
                                        <span className="min-w-0 truncate">
                                            {test.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </>
                    ) : (
                        <ul className="grid gap-1 text-xs text-slate-600 sm:grid-cols-2">
                            {preset.tests.map((test) => (
                                <li key={test.id} className="truncate">
                                    {test.name}
                                    <span className="ml-1 font-mono text-muted-foreground">
                                        ({test.code})
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}

                    {preset.form_code ? (
                        <p className="mt-2 font-mono text-[11px] text-muted-foreground">
                            {preset.form_code}
                        </p>
                    ) : null}
                </div>
            ) : null}
        </div>
    );
}
