import { useState } from 'react';
import { Check, ChevronDown, Plus } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

export type IntakePackageRowPackage = {
    id: number;
    name: string;
    description: string | null;
    default_price: string | number;
    form_code: string | null;
    report_layout: 'controlled_form' | 'dynamic_matrix';
    analysis_type_ids: number[];
    tests: Array<{
        id: number;
        code: string;
        name: string;
    }>;
};

type Props = {
    pkg: IntakePackageRowPackage;
    selected: boolean;
    selectedTypes: number[];
    onTogglePackage: () => void;
    onToggleMember: (typeId: number) => void;
};

export default function IntakePackageRow({
    pkg,
    selected,
    selectedTypes,
    onTogglePackage,
    onToggleMember,
}: Props) {
    const [detailsOpen, setDetailsOpen] = useState(false);

    const selectedMemberCount = pkg.analysis_type_ids.filter((id) =>
        selectedTypes.includes(id),
    ).length;

    const detailsId = `package-details-${pkg.id}`;
    const previewTests = pkg.tests.slice(0, 4);

    return (
        <div
            className={cn(
                'overflow-hidden rounded-xl border bg-white shadow-xs transition',
                selected
                    ? 'border-nppc-900 ring-1 ring-nppc-900/15'
                    : 'border-slate-200 hover:border-nppc-500/50',
            )}
        >
            <div className="flex flex-col gap-3 p-4 sm:flex-row sm:items-start sm:justify-between sm:gap-5">
                <div className="min-w-0 flex-1 space-y-2">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <p className="font-heading text-base font-semibold text-nppc-900">
                            {pkg.name}
                        </p>
                        {pkg.form_code ? (
                            <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[10px] font-medium text-slate-600">
                                {pkg.form_code}
                            </span>
                        ) : null}
                        <span className="rounded bg-nppc-50 px-1.5 py-0.5 text-[10px] font-medium text-nppc-800">
                            Standard suite
                        </span>
                    </div>
                    {pkg.description ? (
                        <p className="line-clamp-2 text-sm leading-snug text-slate-600">
                            {pkg.description}
                        </p>
                    ) : null}
                    <ul className="space-y-1 text-sm text-slate-700">
                        {previewTests.map((test) => (
                            <li
                                key={test.id}
                                className="flex items-center gap-2"
                            >
                                <span
                                    className={cn(
                                        'size-1.5 shrink-0 rounded-full',
                                        selected
                                            ? 'bg-emerald-500'
                                            : 'bg-slate-300',
                                    )}
                                />
                                <span className="min-w-0 truncate">
                                    {test.name}
                                </span>
                            </li>
                        ))}
                        {pkg.tests.length > 4 ? (
                            <li className="pl-3.5 text-xs text-slate-500">
                                +{pkg.tests.length - 4} more
                            </li>
                        ) : null}
                    </ul>
                    {pkg.tests.length > 0 ? (
                        <button
                            type="button"
                            aria-expanded={detailsOpen}
                            aria-controls={detailsId}
                            onClick={() => setDetailsOpen((open) => !open)}
                            className="inline-flex items-center gap-0.5 text-xs font-medium text-nppc-700 hover:underline focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none"
                        >
                            {selected
                                ? 'Adjust included tests'
                                : 'View all tests'}
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

                <div className="flex shrink-0 flex-row items-center gap-3 sm:flex-col sm:items-end">
                    <div className="text-right">
                        <p className="text-[9px] font-semibold tracking-wider text-slate-500 uppercase">
                            Fee per sample
                        </p>
                        <p className="font-heading text-xl font-extrabold tabular-nums text-nppc-900">
                            ₱{Number(pkg.default_price).toFixed(2)}
                        </p>
                    </div>
                    <button
                        type="button"
                        onClick={onTogglePackage}
                        aria-pressed={selected}
                        className={cn(
                            'inline-flex h-9 shrink-0 items-center justify-center gap-1.5 rounded-md px-3.5 text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                            selected
                                ? 'bg-nppc-900 text-white'
                                : 'border border-nppc-900/30 bg-white text-nppc-900 hover:bg-nppc-50',
                        )}
                    >
                        {selected ? (
                            <>
                                <Check className="size-3.5" aria-hidden />
                                Added
                            </>
                        ) : (
                            <>
                                <Plus className="size-3.5" aria-hidden />
                                Select package
                            </>
                        )}
                    </button>
                </div>
            </div>

            {detailsOpen && pkg.tests.length > 0 ? (
                <div
                    id={detailsId}
                    className={cn(
                        'border-t px-4 py-3',
                        selected
                            ? 'border-nppc-100 bg-nppc-50/40'
                            : 'border-slate-100 bg-slate-50/40',
                    )}
                >
                    {selected ? (
                        <>
                            <p className="text-[11px] text-slate-500">
                                Uncheck tests you do not need
                                {selectedMemberCount > 0
                                    ? ` · ${selectedMemberCount} included`
                                    : ''}
                                .
                            </p>
                            <div className="mt-2 grid gap-1.5 sm:grid-cols-2">
                                {pkg.tests.map((test) => (
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
                        <ul className="grid gap-1 text-sm text-slate-600 sm:grid-cols-2">
                            {pkg.tests.map((test) => (
                                <li key={test.id} className="truncate">
                                    {test.name}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : null}
        </div>
    );
}
