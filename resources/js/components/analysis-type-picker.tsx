import { useMemo, useState } from 'react';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import type { AnalysisGroup } from '@/lib/controlled-forms';
import { cn } from '@/lib/utils';

type Props = {
    groups: AnalysisGroup[];
    selectedIds: number[];
    onChange: (ids: number[]) => void;
    error?: string;
    className?: string;
    heading?: string;
    hint?: string;
};

export default function AnalysisTypePicker({
    groups,
    selectedIds,
    onChange,
    error,
    className,
    heading = 'Bound tests',
    hint,
}: Props) {
    const hintText =
        hint ??
        `${selectedIds.length} selected · combined PDF matches this exact set`;
    const [query, setQuery] = useState('');
    const [openLabels, setOpenLabels] = useState<string[]>(() =>
        groups.map((group) => group.label),
    );

    const filtered = useMemo(() => {
        const needle = query.trim().toLowerCase();

        return groups
            .map((group) => ({
                ...group,
                items: needle
                    ? group.items.filter((item) =>
                          `${item.code} ${item.name} ${group.label}`
                              .toLowerCase()
                              .includes(needle),
                      )
                    : group.items,
            }))
            .filter((group) => group.items.length > 0);
    }, [groups, query]);

    function toggle(id: number, checked: boolean) {
        if (checked) {
            onChange([...new Set([...selectedIds, id])]);
            return;
        }

        onChange(selectedIds.filter((value) => value !== id));
    }

    function toggleGroup(ids: number[], checked: boolean) {
        if (checked) {
            onChange([...new Set([...selectedIds, ...ids])]);
            return;
        }

        onChange(selectedIds.filter((id) => !ids.includes(id)));
    }

    return (
        <div className={cn('grid gap-2', className)}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-sm font-medium">{heading}</p>
                <p className="text-xs text-muted-foreground">{hintText}</p>
            </div>
            <Input
                placeholder="Search tests or category"
                value={query}
                onChange={(event) => setQuery(event.target.value)}
            />
            <div className="max-h-72 space-y-2 overflow-y-auto rounded-md border p-2">
                {filtered.map((group) => {
                    const groupIds = group.items.map((item) => item.id);
                    const selectedInGroup = groupIds.filter((id) =>
                        selectedIds.includes(id),
                    ).length;
                    const allSelected =
                        groupIds.length > 0 &&
                        selectedInGroup === groupIds.length;
                    const open =
                        query.trim() !== '' ||
                        openLabels.includes(group.label);

                    return (
                        <div
                            key={group.label}
                            className="overflow-hidden rounded-lg border"
                        >
                            <div className="flex items-center gap-2 bg-[#f8fafc] px-3 py-2">
                                <Checkbox
                                    checked={allSelected}
                                    onCheckedChange={(checked) =>
                                        toggleGroup(groupIds, checked === true)
                                    }
                                    aria-label={`Select all ${group.label}`}
                                />
                                <button
                                    type="button"
                                    className="min-w-0 flex-1 text-left"
                                    onClick={() =>
                                        setOpenLabels((current) =>
                                            current.includes(group.label)
                                                ? current.filter(
                                                      (label) =>
                                                          label !== group.label,
                                                  )
                                                : [...current, group.label],
                                        )
                                    }
                                >
                                    <p className="text-sm font-medium text-[#1A3694]">
                                        {group.label}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {selectedInGroup}/{group.items.length}{' '}
                                        selected
                                    </p>
                                </button>
                            </div>
                            {open && (
                                <div className="grid gap-1 border-t p-2 sm:grid-cols-2">
                                    {group.items.map((item) => (
                                        <label
                                            key={item.id}
                                            className="flex items-start gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-slate-50"
                                        >
                                            <Checkbox
                                                className="mt-0.5"
                                                checked={selectedIds.includes(
                                                    item.id,
                                                )}
                                                onCheckedChange={(checked) =>
                                                    toggle(
                                                        item.id,
                                                        checked === true,
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="block font-medium">
                                                    {item.name}
                                                </span>
                                                <span className="font-mono text-[11px] text-muted-foreground">
                                                    {item.code}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            )}
                        </div>
                    );
                })}
                {filtered.length === 0 && (
                    <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                        {groups.length === 0
                            ? 'No analysis tests are in the catalog yet. Add them under Admin → Prices first.'
                            : 'No tests match that search.'}
                    </p>
                )}
            </div>
            {error && <p className="text-sm text-red-600">{error}</p>}
        </div>
    );
}
