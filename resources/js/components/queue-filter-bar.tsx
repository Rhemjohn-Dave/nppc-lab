import { Search, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type QueueChip = {
    id: string;
    label: string;
    count: number;
};

export type QueueSortOption = {
    value: string;
    label: string;
};

type Props = {
    chips: readonly QueueChip[];
    activeId: string;
    onChip: (id: string) => void;
    query: string;
    onQueryChange: (value: string) => void;
    onSearch: () => void;
    onClear: () => void;
    placeholder?: string;
    sortValue?: string;
    onSortChange?: (value: string) => void;
    sortOptions?: readonly QueueSortOption[];
};

export default function QueueFilterBar({
    chips,
    activeId,
    onChip,
    query,
    onQueryChange,
    onSearch,
    onClear,
    placeholder = 'Search reference, customer…',
    sortValue,
    onSortChange,
    sortOptions,
}: Props) {
    function submit(event: FormEvent) {
        event.preventDefault();
        onSearch();
    }

    const showSort =
        Boolean(sortOptions?.length) &&
        typeof sortValue === 'string' &&
        typeof onSortChange === 'function';

    return (
        <div className="flex flex-col gap-3 rounded-xl border border-slate-200 bg-white p-3 shadow-xs md:flex-row md:items-center md:justify-between">
            <div
                className="flex items-center gap-1 overflow-x-auto pb-1 text-xs md:pb-0"
                role="tablist"
            >
                {chips.map((chip) => {
                    const active = (activeId || '') === chip.id;

                    return (
                        <button
                            key={chip.id || 'all'}
                            type="button"
                            role="tab"
                            aria-selected={active}
                            onClick={() => onChip(chip.id)}
                            className={cn(
                                'whitespace-nowrap rounded-lg px-3 py-1.5 font-medium transition',
                                active
                                    ? 'border border-nppc-200/70 bg-nppc-50 font-semibold text-nppc-900 shadow-xs'
                                    : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900',
                            )}
                        >
                            {chip.label}{' '}
                            <span
                                className={cn(
                                    'ml-1 tabular-nums',
                                    active
                                        ? 'rounded-full bg-nppc-900 px-1.5 py-0.5 text-[10px] font-bold text-white'
                                        : 'text-[11px] font-semibold text-slate-400',
                                )}
                            >
                                {chip.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            <div className="flex flex-wrap items-center gap-3">
                <form
                    onSubmit={submit}
                    className="relative min-w-[200px] flex-1 md:w-72 md:flex-none"
                >
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-3.5 -translate-y-1/2 text-slate-400" />
                    <input
                        value={query}
                        onChange={(event) => onQueryChange(event.target.value)}
                        placeholder={placeholder}
                        className="w-full rounded-lg border border-slate-200 bg-slate-50 py-1.5 pr-9 pl-9 text-xs placeholder-slate-400 transition focus:border-nppc-500 focus:bg-white focus:ring-2 focus:ring-nppc-500/30 focus:outline-none"
                    />
                    {query ? (
                        <Button
                            type="button"
                            variant="ghost"
                            size="icon"
                            className="absolute top-1/2 right-0.5 size-7 -translate-y-1/2 text-slate-400 hover:text-slate-700"
                            onClick={onClear}
                            aria-label="Clear search"
                        >
                            <X className="size-3.5" />
                        </Button>
                    ) : null}
                </form>

                {showSort ? (
                    <div className="flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 py-1.5 text-xs text-slate-600 shadow-xs">
                        <span className="text-slate-400">Sort:</span>
                        <select
                            className="cursor-pointer border-0 bg-transparent p-0 text-xs font-medium text-slate-800 focus:ring-0"
                            value={sortValue}
                            onChange={(event) =>
                                onSortChange?.(event.target.value)
                            }
                        >
                            {sortOptions!.map((option) => (
                                <option key={option.value} value={option.value}>
                                    {option.label}
                                </option>
                            ))}
                        </select>
                    </div>
                ) : null}
            </div>
        </div>
    );
}
