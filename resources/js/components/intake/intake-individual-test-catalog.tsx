import { useMemo } from 'react';
import { Search } from 'lucide-react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type IntakeCatalogCategory = {
    category: string;
    label: string;
    items: Array<{
        id: number;
        code: string;
        name: string;
        default_price: string;
    }>;
};

type Props = {
    categories: IntakeCatalogCategory[];
    query: string;
    onQueryChange: (value: string) => void;
    activeCategory: string | null;
    onActiveCategoryChange: (category: string | null) => void;
    selectedTypeIds: Set<number>;
    onToggleType: (id: number) => void;
};

/**
 * Individual analyses browser: search + compact category chips + dense test rows.
 * Selection stays request-level — this component only reports toggles upward.
 */
export default function IntakeIndividualTestCatalog({
    categories,
    query,
    onQueryChange,
    activeCategory,
    onActiveCategoryChange,
    selectedTypeIds,
    onToggleType,
}: Props) {
    const matched = useMemo(() => {
        const needle = query.trim().toLowerCase();
        if (!needle) {
            return categories;
        }

        return categories
            .map((group) => ({
                ...group,
                items: group.items.filter(
                    (item) =>
                        item.name.toLowerCase().includes(needle) ||
                        item.code.toLowerCase().includes(needle),
                ),
            }))
            .filter((group) => group.items.length > 0);
    }, [categories, query]);

    const visible = useMemo(
        () =>
            activeCategory
                ? matched.filter((group) => group.category === activeCategory)
                : matched,
        [matched, activeCategory],
    );

    const totalSelected = useMemo(
        () =>
            categories.reduce(
                (sum, group) =>
                    sum +
                    group.items.filter((item) => selectedTypeIds.has(item.id))
                        .length,
                0,
            ),
        [categories, selectedTypeIds],
    );

    const shown = visible.length > 0 ? visible : [];

    return (
        <div className="space-y-2.5">
            <div className="relative">
                <Search
                    className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400"
                    aria-hidden
                />
                <Input
                    className="h-10 bg-white pl-9"
                    placeholder="Search tests by name or code…"
                    value={query}
                    onChange={(e) => onQueryChange(e.target.value)}
                />
            </div>

            <div
                className="flex flex-wrap gap-1.5"
                role="group"
                aria-label="Test categories"
            >
                <CategoryChip
                    active={activeCategory === null}
                    count={totalSelected}
                    onClick={() => onActiveCategoryChange(null)}
                >
                    All
                </CategoryChip>
                {matched.map((group) => {
                    const count = group.items.filter((item) =>
                        selectedTypeIds.has(item.id),
                    ).length;

                    return (
                        <CategoryChip
                            key={group.category}
                            active={activeCategory === group.category}
                            count={count}
                            onClick={() =>
                                onActiveCategoryChange(
                                    activeCategory === group.category
                                        ? null
                                        : group.category,
                                )
                            }
                        >
                            {group.label}
                        </CategoryChip>
                    );
                })}
            </div>

            {shown.length === 0 ? (
                <div className="rounded-xl border border-dashed border-slate-200 p-4 text-center">
                    <p className="font-medium text-slate-800">
                        No tests found.
                    </p>
                    <p className="mt-0.5 text-sm text-slate-600">
                        Try another test name or code.
                    </p>
                </div>
            ) : (
                <div className="nppc-scroll max-h-[26rem] space-y-3 overflow-y-auto overscroll-contain pr-1">
                    {shown.map((group) => (
                        <div key={group.category}>
                            {activeCategory === null ? (
                                <p className="mb-1.5 text-xs font-semibold tracking-wide text-slate-500 uppercase">
                                    {group.label}
                                </p>
                            ) : null}
                            <div className="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-3">
                                {group.items.map((item) => {
                                    const checked = selectedTypeIds.has(
                                        item.id,
                                    );

                                    return (
                                        <label
                                            key={item.id}
                                            className={cn(
                                                'flex min-h-10 cursor-pointer items-center gap-2.5 rounded-lg border px-2.5 py-1.5 text-sm transition',
                                                checked
                                                    ? 'border-[#1A3694] bg-[#eef3fb]'
                                                    : 'border-slate-200/80 bg-white hover:border-[#5282D3]/40',
                                            )}
                                        >
                                            <Checkbox
                                                checked={checked}
                                                onCheckedChange={() =>
                                                    onToggleType(item.id)
                                                }
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block truncate font-medium text-slate-800">
                                                    {item.name}
                                                </span>
                                                <span className="block font-mono text-xs text-slate-500">
                                                    {item.code} · ₱
                                                    {Number(
                                                        item.default_price,
                                                    ).toFixed(2)}
                                                </span>
                                            </span>
                                        </label>
                                    );
                                })}
                            </div>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

function CategoryChip({
    active,
    count,
    onClick,
    children,
}: {
    active: boolean;
    count: number;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            aria-pressed={active}
            onClick={onClick}
            className={cn(
                'rounded-lg border px-2.5 py-1.5 text-sm font-medium transition focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none',
                active
                    ? 'border-[#1A3694] bg-[#1A3694] text-white'
                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
            )}
        >
            {children}
            {count > 0 ? (
                <span
                    className={cn(
                        'ml-1.5 rounded-md px-1.5 py-0.5 text-xs font-semibold',
                        active
                            ? 'bg-white/20 text-white'
                            : 'bg-[#e8eef8] text-[#1A3694]',
                    )}
                >
                    {count}
                </span>
            ) : null}
        </button>
    );
}
