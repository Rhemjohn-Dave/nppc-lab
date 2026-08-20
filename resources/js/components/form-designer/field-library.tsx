import {
    CheckSquare,
    ChevronDown,
    ChevronRight,
    CircleDot,
    GripVertical,
    Hash,
    PenLine,
    Plus,
    Search,
    Table2,
    Text,
    Type,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { DataSource, DesignerField, FieldTypeOption } from '@/lib/controlled-forms';
import { DRAG_SOURCE_MIME } from '@/components/form-designer/utils';

const typeIcons: Record<string, typeof Type> = {
    text: Type,
    number: Hash,
    date: Hash,
    currency: Hash,
    multiline: Text,
    checkbox: CheckSquare,
    radio: CircleDot,
    signature: PenLine,
    table: Table2,
};

// Groups that start collapsed by default because they can be very long.
// Matched case-insensitively against the group label.
const HEAVY_GROUPS = new Set([
    'analysis selected',
    'classification checkboxes',
    'analyses / tests',
]);

type Props = {
    sources: DataSource[];
    fieldTypes: FieldTypeOption[];
    fields: DesignerField[];
    pendingSourceKey: string | null;
    canEdit: boolean;
    onSelectSource: (source: DataSource) => void;
    onAddFieldType: (type: FieldTypeOption) => void;
    onClearPending: () => void;
    packageMode?: boolean;
    showAllFields?: boolean;
    onShowAllFieldsChange?: (value: boolean) => void;
};

export default function FieldLibrary({
    sources,
    fieldTypes,
    fields,
    pendingSourceKey,
    canEdit,
    onSelectSource,
    onAddFieldType,
    onClearPending,
    packageMode = false,
    showAllFields = true,
    onShowAllFieldsChange,
}: Props) {
    const [query, setQuery] = useState('');
    const [showTypes, setShowTypes] = useState(false);

    // Track which groups are open; default heavy groups to closed
    const [openGroups, setOpenGroups] = useState<Record<string, boolean>>(() => {
        const initial: Record<string, boolean> = {};
        for (const source of sources) {
            const key = source.group.toLowerCase();
            if (!(key in initial)) {
                initial[key] = !HEAVY_GROUPS.has(key);
            }
        }
        return initial;
    });

    const mappedKeys = useMemo(
        () => new Set(fields.map((field) => field.data_source_key).filter(Boolean)),
        [fields],
    );

    const grouped = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const groups = new Map<string, DataSource[]>();

        for (const source of sources) {
            if (needle) {
                const haystack = `${source.label} ${source.key} ${source.group}`.toLowerCase();
                if (!haystack.includes(needle)) {
                    continue;
                }
            }

            const list = groups.get(source.group) ?? [];
            list.push(source);
            groups.set(source.group, list);
        }

        return [...groups.entries()];
    }, [sources, query]);

    const pendingLabel = sources.find((source) => source.key === pendingSourceKey)?.label;

    function toggleGroup(group: string) {
        const key = group.toLowerCase();
        setOpenGroups((prev) => ({ ...prev, [key]: !prev[key] }));
    }

    function isGroupOpen(group: string) {
        const key = group.toLowerCase();
        // When searching, always expand all groups
        if (query.trim()) return true;
        return openGroups[key] ?? true;
    }

    return (
        <aside className="flex h-full min-h-0 flex-col bg-white">
            {/* Fixed header */}
            <div className="shrink-0 border-b px-3 py-3">
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Field Library
                </h2>
                <div className="relative mt-2">
                    <Search className="absolute top-2.5 left-2.5 size-3.5 text-muted-foreground" />
                    <Input
                        className="h-8 pl-8 text-sm"
                        placeholder="Search fields…"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>
                {packageMode && onShowAllFieldsChange && (
                    <label className="mt-2 flex items-center gap-2 text-xs text-muted-foreground">
                        <input
                            type="checkbox"
                            checked={showAllFields}
                            onChange={(event) =>
                                onShowAllFieldsChange(event.target.checked)
                            }
                        />
                        Show all fields
                    </label>
                )}
            </div>

            {pendingSourceKey && (
                <div className="shrink-0 border-b bg-[#e8eef8] px-3 py-2 text-xs">
                    <p className="font-medium text-[#1A3694]">Placement mode</p>
                    <p className="mt-0.5 text-muted-foreground">
                        Click on the PDF to place{' '}
                        <span className="font-medium text-slate-800">{pendingLabel}</span>
                    </p>
                    <Button
                        variant="ghost"
                        size="sm"
                        className="mt-1 h-7 px-2 text-xs"
                        onClick={onClearPending}
                    >
                        Cancel
                    </Button>
                </div>
            )}

            {/* Scrollable field list */}
            <div className="designer-scroll min-h-0 flex-1 overflow-y-auto px-2 py-2">
                {grouped.length === 0 && (
                    <p className="px-2 py-4 text-sm text-muted-foreground">No fields match your search.</p>
                )}

                {grouped.map(([group, items]) => {
                    const open = isGroupOpen(group);
                    const mappedCount = items.filter((s) => mappedKeys.has(s.key)).length;

                    return (
                        <section key={group} className="mb-1">
                            <button
                                type="button"
                                className="flex w-full items-center gap-1 rounded px-2 py-1 text-left hover:bg-slate-50"
                                onClick={() => toggleGroup(group)}
                            >
                                {open ? (
                                    <ChevronDown className="size-3 shrink-0 text-muted-foreground" />
                                ) : (
                                    <ChevronRight className="size-3 shrink-0 text-muted-foreground" />
                                )}
                                <span className="flex-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                    {group}
                                </span>
                                {mappedCount > 0 && (
                                    <span className="shrink-0 text-[10px] text-emerald-600">
                                        {mappedCount}/{items.length}
                                    </span>
                                )}
                            </button>

                            {open && (
                                <ul className="mt-0.5 space-y-0.5 pl-1">
                                    {items.map((source) => {
                                        const Icon = typeIcons[source.type] ?? Type;
                                        const isMapped = mappedKeys.has(source.key);
                                        const isPending = pendingSourceKey === source.key;

                                        return (
                                            <li key={source.key}>
                                                <button
                                                    type="button"
                                                    draggable={canEdit}
                                                    disabled={!canEdit}
                                                    title={source.hint ?? source.key}
                                                    className={`flex w-full items-center gap-1.5 rounded-md px-2 py-1 text-left text-xs transition-colors ${
                                                        isPending
                                                            ? 'bg-[#e8eef8] text-[#1A3694]'
                                                            : 'text-slate-700 hover:bg-slate-50'
                                                    } ${!canEdit ? 'cursor-not-allowed opacity-60' : 'cursor-grab active:cursor-grabbing'}`}
                                                    onClick={() => onSelectSource(source)}
                                                    onDragStart={(event) => {
                                                        event.dataTransfer.setData(
                                                            DRAG_SOURCE_MIME,
                                                            JSON.stringify(source),
                                                        );
                                                        event.dataTransfer.effectAllowed = 'copy';
                                                    }}
                                                >
                                                    <GripVertical className="size-3 shrink-0 text-muted-foreground/50" />
                                                    <Icon className="size-3 shrink-0 text-[#1A3694]/70" />
                                                    <span className="min-w-0 flex-1 truncate">{source.label}</span>
                                                    {isMapped && (
                                                        <span className="shrink-0 text-[10px] font-medium text-emerald-600">✓</span>
                                                    )}
                                                </button>
                                            </li>
                                        );
                                    })}
                                </ul>
                            )}
                        </section>
                    );
                })}

                {/* Field types section */}
                <section className="mt-2 border-t pt-2">
                    <button
                        type="button"
                        className="flex w-full items-center gap-1 rounded px-2 py-1 text-left hover:bg-slate-50"
                        onClick={() => setShowTypes((value) => !value)}
                    >
                        {showTypes ? (
                            <ChevronDown className="size-3 shrink-0 text-muted-foreground" />
                        ) : (
                            <ChevronRight className="size-3 shrink-0 text-muted-foreground" />
                        )}
                        <span className="flex-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Field types
                        </span>
                        <Plus className="size-3 text-muted-foreground" />
                    </button>
                    {showTypes && (
                        <div className="mt-1 grid gap-0.5 pl-1">
                            {fieldTypes.map((type) => {
                                const Icon = typeIcons[type.value] ?? Plus;

                                return (
                                    <button
                                        key={type.value}
                                        type="button"
                                        disabled={!canEdit}
                                        className="flex items-center gap-2 rounded-md px-2 py-1 text-left text-xs text-slate-700 hover:bg-slate-50 disabled:opacity-60"
                                        onClick={() => onAddFieldType(type)}
                                    >
                                        <Icon className="size-3 text-[#1A3694]/70" />
                                        {type.label}
                                    </button>
                                );
                            })}
                        </div>
                    )}
                </section>
            </div>

            <div className="shrink-0 border-t px-3 py-2 text-[10px] leading-relaxed text-muted-foreground">
                Drag onto PDF or click to select then click to place.
            </div>
        </aside>
    );
}
