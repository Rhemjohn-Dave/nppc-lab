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
    /** Test id → name of the selected package that already includes it. */
    lockedTypes?: Map<number, string>;
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
    lockedTypes,
    estimatedPrice,
    onTogglePreset,
    onToggleMember,
}: Props) {
    const [detailsOpen, setDetailsOpen] = useState(false);

    const selectedMemberCount = preset.analysis_type_ids.filter(
        (id) => selectedTypes.includes(id) && !lockedTypes?.has(id),
    ).length;
    const lockedMemberCount = preset.analysis_type_ids.filter((id) =>
        lockedTypes?.has(id),
    ).length;

    const detailsId = `preset-details-${preset.key}`;

    return (
        <div
            className={cn(
                'rounded-lg border bg-white transition',
                selected
                    ? 'border-nppc-900 shadow-sm'
                    : 'border-slate-200/80 hover:border-nppc-500/60',
            )}
        >
            <div className="flex items-center gap-3 px-3 py-2.5">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm font-semibold text-nppc-900">
                        {preset.label}
                    </p>
                    <p className="truncate text-xs text-slate-600">
                        {preset.tests.length} test
                        {preset.tests.length === 1 ? '' : 's'} · ₱
                        {estimatedPrice.toFixed(2)}
                        {selected
                            ? ` · ${selectedMemberCount} included`
                            : ''}
                        {selected && lockedMemberCount > 0
                            ? ` · ${lockedMemberCount} in package`
                            : ''}
                    </p>
                </div>
                {preset.tests.length > 0 ? (
                    <button
                        type="button"
                        aria-expanded={detailsOpen}
                        aria-controls={detailsId}
                        onClick={() => setDetailsOpen((open) => !open)}
                        className="inline-flex shrink-0 items-center gap-0.5 text-xs font-medium text-nppc-700 hover:underline focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                    >
                        Details
                        <ChevronDown
                            className={cn(
                                'size-3.5 transition',
                                detailsOpen && 'rotate-180',
                            )}
                            aria-hidden
                        />
                    </button>
                ) : null}
                <button
                    type="button"
                    onClick={onTogglePreset}
                    aria-pressed={selected}
                    className={cn(
                        'flex h-9 shrink-0 items-center gap-1.5 rounded-lg px-3 text-sm font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                        selected
                            ? 'bg-nppc-900 text-white'
                            : 'border border-nppc-900/30 text-nppc-900 hover:bg-nppc-50',
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

            {detailsOpen && preset.tests.length > 0 ? (
                <div
                    id={detailsId}
                    className={cn(
                        'border-t px-3 py-2.5',
                        selected
                            ? 'border-[#d7e2f5] bg-nppc-50/60'
                            : 'border-slate-100',
                    )}
                >
                    {preset.description ? (
                        <p className="mb-2 text-xs text-slate-600">
                            {preset.description}
                        </p>
                    ) : null}

                    {selected ? (
                        <>
                            <p className="text-xs text-muted-foreground">
                                Uncheck tests you do not need. Selected tests
                                are billed individually.
                            </p>
                            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
                                {preset.tests.map((test) => {
                                    const lockedBy = lockedTypes?.get(test.id);

                                    return (
                                        <label
                                            key={test.id}
                                            className={cn(
                                                'flex items-center gap-2 text-sm text-slate-700',
                                                lockedBy && 'text-slate-500',
                                            )}
                                        >
                                            <Checkbox
                                                checked={
                                                    lockedBy !== undefined ||
                                                    selectedTypes.includes(
                                                        test.id,
                                                    )
                                                }
                                                disabled={lockedBy !== undefined}
                                                onCheckedChange={() =>
                                                    onToggleMember(test.id)
                                                }
                                            />
                                            <span className="min-w-0 truncate">
                                                {test.name}
                                                {lockedBy ? (
                                                    <span className="ml-1 text-[11px] text-muted-foreground">
                                                        In {lockedBy}
                                                    </span>
                                                ) : null}
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </>
                    ) : (
                        <ul className="grid gap-1 text-xs text-slate-600 sm:grid-cols-2">
                            {preset.tests.map((test) => (
                                <li key={test.id} className="truncate">
                                    {test.name}
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
