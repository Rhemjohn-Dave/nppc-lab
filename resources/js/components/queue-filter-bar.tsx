import { Search, X } from 'lucide-react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';

export type QueueChip = {
    id: string;
    label: string;
    count: number;
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
}: Props) {
    function submit(event: FormEvent) {
        event.preventDefault();
        onSearch();
    }

    return (
        <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap gap-2">
                {chips.map((chip) => {
                    const active = (activeId || '') === chip.id;

                    return (
                        <button
                            key={chip.id || 'all'}
                            type="button"
                            onClick={() => onChip(chip.id)}
                            className={cn(
                                'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                active
                                    ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                            )}
                        >
                            {chip.label}
                            <span
                                className={cn(
                                    'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                    active
                                        ? 'bg-white/20'
                                        : 'bg-slate-100 text-slate-600',
                                )}
                            >
                                {chip.count}
                            </span>
                        </button>
                    );
                })}
            </div>

            <form
                onSubmit={submit}
                className="flex w-full max-w-md items-center gap-2"
            >
                <div className="relative flex-1">
                    <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                    <Input
                        value={query}
                        onChange={(event) => onQueryChange(event.target.value)}
                        placeholder={placeholder}
                        className="h-10 pl-9"
                    />
                </div>
                <Button type="submit" variant="outline" className="h-10">
                    Search
                </Button>
                {query && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="icon"
                        className="h-10 w-10 shrink-0"
                        onClick={onClear}
                    >
                        <X className="size-4" />
                    </Button>
                )}
            </form>
        </div>
    );
}
