import { router } from '@inertiajs/react';
import { ChevronDown, ChevronRight, Search } from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { cn } from '@/lib/utils';

export type AssignmentAnalyst = {
    id: number;
    name: string;
    email: string;
    analysis_type_ids: number[];
    active_tasks: number;
};

export type AssignmentProcedure = {
    id: number;
    code: string;
    name: string;
    category: string;
    category_label: string;
};

export type AssignmentGroup = {
    category: string;
    label: string;
    items: AssignmentProcedure[];
    type_ids: number[];
};

type Props = {
    open: boolean;
    analyst: AssignmentAnalyst | null;
    groups: AssignmentGroup[];
    onOpenChange: (open: boolean) => void;
};

function sameIdSet(a: number[], b: number[]): boolean {
    if (a.length !== b.length) {
        return false;
    }
    const left = [...a].sort((x, y) => x - y);
    const right = [...b].sort((x, y) => x - y);
    return left.every((id, index) => id === right[index]);
}

function assignmentLabel(selected: number, total: number): string {
    if (total === 0) {
        return 'No procedures';
    }
    if (selected === 0) {
        return 'Unassigned';
    }
    if (selected === total) {
        return 'Fully assigned';
    }
    return 'Partially assigned';
}

export default function AssignmentModal({
    open,
    analyst,
    groups,
    onOpenChange,
}: Props) {
    const [selectedIds, setSelectedIds] = useState<number[]>([]);
    const [saving, setSaving] = useState(false);
    const [procedureQuery, setProcedureQuery] = useState('');
    const [categoryFilter, setCategoryFilter] = useState('all');
    const [openCategories, setOpenCategories] = useState<string[]>([]);
    const [discardOpen, setDiscardOpen] = useState(false);
    const [bulkConfirm, setBulkConfirm] = useState<{
        label: string;
        ids: number[];
        assign: boolean;
    } | null>(null);

    useEffect(() => {
        if (!open || !analyst) {
            return;
        }
        setSelectedIds(analyst.analysis_type_ids);
        setProcedureQuery('');
        setCategoryFilter('all');
        setOpenCategories([]);
        setDiscardOpen(false);
        setBulkConfirm(null);
        // Intentionally key off analyst id so Inertia prop refreshes don't wipe drafts.
        // eslint-disable-next-line react-hooks/exhaustive-deps -- seed only on open / analyst change
    }, [open, analyst?.id]);

    const totalProcedures = useMemo(
        () => groups.reduce((sum, group) => sum + group.items.length, 0),
        [groups],
    );

    const savedIds = analyst?.analysis_type_ids ?? [];
    const isDirty = Boolean(analyst && !sameIdSet(selectedIds, savedIds));

    const filteredGroups = useMemo(() => {
        const q = procedureQuery.trim().toLowerCase();

        return groups
            .filter(
                (group) =>
                    categoryFilter === 'all' ||
                    group.category === categoryFilter,
            )
            .map((group) => {
                const items =
                    q === ''
                        ? group.items
                        : group.items.filter(
                              (item) =>
                                  item.code.toLowerCase().includes(q) ||
                                  item.name.toLowerCase().includes(q),
                          );

                return {
                    ...group,
                    items,
                    type_ids: items.map((item) => item.id),
                };
            })
            .filter((group) => group.items.length > 0);
    }, [groups, procedureQuery, categoryFilter]);

    const visibleTypeIds = useMemo(
        () => filteredGroups.flatMap((group) => group.type_ids),
        [filteredGroups],
    );

    function requestClose() {
        if (saving) {
            return;
        }
        if (isDirty) {
            setDiscardOpen(true);
            return;
        }
        onOpenChange(false);
    }

    function discardAndClose() {
        setDiscardOpen(false);
        if (analyst) {
            setSelectedIds(analyst.analysis_type_ids);
        }
        onOpenChange(false);
    }

    function toggle(id: number) {
        setSelectedIds((current) =>
            current.includes(id)
                ? current.filter((value) => value !== id)
                : [...current, id],
        );
    }

    function setCategory(ids: number[], checked: boolean) {
        setSelectedIds((current) => {
            if (checked) {
                return [...new Set([...current, ...ids])];
            }
            return current.filter((id) => !ids.includes(id));
        });
    }

    function categoryState(ids: number[]) {
        const selectedCount = ids.filter((id) =>
            selectedIds.includes(id),
        ).length;

        if (selectedCount === 0) {
            return false as const;
        }
        if (selectedCount === ids.length) {
            return true as const;
        }
        return 'indeterminate' as const;
    }

    function toggleCategoryOpen(category: string) {
        setOpenCategories((current) =>
            current.includes(category)
                ? current.filter((value) => value !== category)
                : [...current, category],
        );
    }

    function selectVisible(checked: boolean) {
        setCategory(visibleTypeIds, checked);
    }

    function requestBulk(label: string, ids: number[], assign: boolean) {
        if (ids.length === 0) {
            return;
        }
        setBulkConfirm({ label, ids, assign });
    }

    function applyBulk() {
        if (!bulkConfirm) {
            return;
        }
        setCategory(bulkConfirm.ids, bulkConfirm.assign);
        setBulkConfirm(null);
    }

    function save() {
        if (!analyst || !isDirty) {
            return;
        }

        setSaving(true);
        router.put(
            `/admin/assignments/${analyst.id}`,
            {
                analysis_type_ids: selectedIds,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    onOpenChange(false);
                },
                onFinish: () => setSaving(false),
            },
        );
    }

    return (
        <>
            <Dialog
                open={open}
                onOpenChange={(next) => {
                    if (next) {
                        onOpenChange(true);
                        return;
                    }
                    requestClose();
                }}
            >
                <DialogContent
                    className={cn(
                        'flex max-h-[calc(100vh-48px)] w-full flex-col gap-0 overflow-hidden p-0',
                        'max-w-[calc(100vw-48px)] sm:max-w-[min(1100px,calc(100vw-48px))]',
                        'max-sm:fixed max-sm:inset-3 max-sm:top-3 max-sm:left-3 max-sm:h-[calc(100vh-24px)] max-sm:max-h-none max-sm:w-[calc(100vw-24px)] max-sm:max-w-none max-sm:translate-x-0 max-sm:translate-y-0',
                    )}
                    onPointerDownOutside={(event) => {
                        if (isDirty) {
                            event.preventDefault();
                            requestClose();
                        }
                    }}
                    onEscapeKeyDown={(event) => {
                        if (isDirty) {
                            event.preventDefault();
                            requestClose();
                        }
                    }}
                >
                    <DialogHeader className="shrink-0 space-y-2 border-b border-slate-200 px-5 py-4 pr-12 text-left">
                        <DialogTitle className="text-lg font-semibold text-nppc-900">
                            Assign Tests
                        </DialogTitle>
                        {analyst ? (
                            <div>
                                <p className="text-sm font-semibold text-slate-900">
                                    {analyst.name}
                                </p>
                                <p className="text-xs text-slate-500">
                                    {analyst.email}
                                </p>
                                <DialogDescription className="mt-2 text-xs text-slate-600">
                                    {selectedIds.length} / {totalProcedures}{' '}
                                    procedures assigned
                                    {isDirty ? (
                                        <span className="ml-2 inline-flex items-center gap-1.5 font-medium text-amber-700">
                                            <span className="size-1.5 rounded-full bg-amber-500" />
                                            Unsaved changes
                                        </span>
                                    ) : null}
                                </DialogDescription>
                            </div>
                        ) : null}
                    </DialogHeader>

                    <div className="shrink-0 space-y-3 border-b border-slate-100 px-5 py-3">
                        <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                            <div className="relative min-w-0 flex-1">
                                <Search className="pointer-events-none absolute top-1/2 left-2.5 size-3.5 -translate-y-1/2 text-slate-400" />
                                <Input
                                    value={procedureQuery}
                                    onChange={(event) =>
                                        setProcedureQuery(event.target.value)
                                    }
                                    placeholder="Search tests…"
                                    className="h-9 pl-8 text-sm"
                                />
                            </div>
                            <Select
                                value={categoryFilter}
                                onValueChange={setCategoryFilter}
                            >
                                <SelectTrigger className="h-9 w-full text-sm sm:w-[220px]">
                                    <SelectValue placeholder="Category" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="all">
                                        All categories
                                    </SelectItem>
                                    {groups.map((group) => (
                                        <SelectItem
                                            key={group.category}
                                            value={group.category}
                                        >
                                            {group.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>

                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div className="flex flex-wrap gap-2">
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 border-slate-200 text-xs shadow-xs"
                                    disabled={visibleTypeIds.length === 0}
                                    onClick={() => selectVisible(true)}
                                >
                                    Select all
                                </Button>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 border-slate-200 text-xs shadow-xs"
                                    disabled={visibleTypeIds.length === 0}
                                    onClick={() => selectVisible(false)}
                                >
                                    Clear
                                </Button>
                            </div>
                            {isDirty ? (
                                <Badge
                                    variant="outline"
                                    className="border-amber-200 bg-amber-50 text-[10px] font-medium text-amber-800"
                                >
                                    Unsaved changes
                                </Badge>
                            ) : null}
                        </div>
                    </div>

                    <div className="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-4">
                        {filteredGroups.map((group) => {
                            const open = openCategories.includes(
                                group.category,
                            );
                            // Counts against full category membership for status text,
                            // but checkboxes / assign-all operate on visible (filtered) ids.
                            const fullGroup =
                                groups.find(
                                    (g) => g.category === group.category,
                                ) ?? group;
                            const selectedInFull = fullGroup.type_ids.filter(
                                (id) => selectedIds.includes(id),
                            ).length;
                            const state = categoryState(group.type_ids);
                            const status = assignmentLabel(
                                selectedInFull,
                                fullGroup.items.length,
                            );

                            return (
                                <div
                                    key={group.category}
                                    className="overflow-hidden rounded-xl border border-slate-200"
                                >
                                    <div className="flex flex-wrap items-center justify-between gap-2 bg-slate-50/90 px-3 py-2.5">
                                        <div className="flex min-w-0 items-start gap-2.5">
                                            <Checkbox
                                                checked={state}
                                                onCheckedChange={(checked) => {
                                                    if (checked === true) {
                                                        requestBulk(
                                                            group.label,
                                                            group.type_ids,
                                                            true,
                                                        );
                                                    } else {
                                                        requestBulk(
                                                            group.label,
                                                            group.type_ids,
                                                            false,
                                                        );
                                                    }
                                                }}
                                                className="mt-0.5"
                                                aria-label={`Toggle ${group.label}`}
                                            />
                                            <button
                                                type="button"
                                                className="min-w-0 text-left"
                                                onClick={() =>
                                                    toggleCategoryOpen(
                                                        group.category,
                                                    )
                                                }
                                                aria-expanded={open}
                                            >
                                                <div className="flex flex-wrap items-center gap-2">
                                                    {open ? (
                                                        <ChevronDown className="size-3.5 shrink-0 text-slate-500" />
                                                    ) : (
                                                        <ChevronRight className="size-3.5 shrink-0 text-slate-500" />
                                                    )}
                                                    <p className="text-sm font-semibold text-nppc-900">
                                                        {group.label}
                                                    </p>
                                                    <Badge
                                                        variant="outline"
                                                        className={cn(
                                                            'text-[10px] font-medium',
                                                            status ===
                                                                'Fully assigned'
                                                                ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                                                                : status ===
                                                                    'Unassigned'
                                                                  ? 'border-slate-200 bg-slate-50 text-slate-600'
                                                                  : 'border-amber-200 bg-amber-50 text-amber-800',
                                                        )}
                                                    >
                                                        {status}
                                                    </Badge>
                                                </div>
                                                <p className="mt-0.5 pl-5 text-[11px] tabular-nums text-slate-500">
                                                    {selectedInFull} /{' '}
                                                    {fullGroup.items.length}{' '}
                                                    assigned
                                                    {procedureQuery.trim() ||
                                                    categoryFilter !== 'all'
                                                        ? ` · showing ${group.items.length}`
                                                        : ''}
                                                </p>
                                            </button>
                                        </div>
                                        <div className="flex flex-wrap gap-1.5">
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="outline"
                                                className="h-8 border-slate-200 text-xs shadow-xs"
                                                onClick={() =>
                                                    requestBulk(
                                                        group.label,
                                                        group.type_ids,
                                                        true,
                                                    )
                                                }
                                            >
                                                Assign all
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                className="h-8 text-xs"
                                                onClick={() =>
                                                    requestBulk(
                                                        group.label,
                                                        group.type_ids,
                                                        false,
                                                    )
                                                }
                                            >
                                                Clear
                                            </Button>
                                        </div>
                                    </div>

                                    {open ? (
                                        <div className="grid gap-1.5 border-t border-slate-100 p-2 sm:grid-cols-2">
                                            {group.items.map((type) => {
                                                const checked =
                                                    selectedIds.includes(
                                                        type.id,
                                                    );

                                                return (
                                                    <label
                                                        key={type.id}
                                                        className={cn(
                                                            'flex min-h-11 cursor-pointer items-start gap-2.5 rounded-lg border px-3 py-2 text-sm transition',
                                                            checked
                                                                ? 'border-nppc-900/40 bg-nppc-50'
                                                                : 'border-slate-100 bg-white hover:border-slate-200',
                                                        )}
                                                    >
                                                        <Checkbox
                                                            checked={checked}
                                                            onCheckedChange={() =>
                                                                toggle(type.id)
                                                            }
                                                            className="mt-0.5"
                                                        />
                                                        <span className="min-w-0 leading-snug">
                                                            <span className="font-medium text-slate-900">
                                                                {type.code}
                                                            </span>{' '}
                                                            <span className="text-slate-700">
                                                                {type.name}
                                                            </span>
                                                        </span>
                                                    </label>
                                                );
                                            })}
                                        </div>
                                    ) : null}
                                </div>
                            );
                        })}

                        {filteredGroups.length === 0 ? (
                            <p className="py-10 text-center text-sm text-slate-500">
                                {groups.length === 0
                                    ? 'No active procedures in the catalog.'
                                    : 'No procedures match your search or filter.'}
                            </p>
                        ) : null}
                    </div>

                    <DialogFooter className="shrink-0 gap-2 border-t border-slate-200 bg-white px-5 py-3 sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            className="h-9 border-slate-200 text-sm shadow-xs"
                            disabled={saving}
                            onClick={requestClose}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            className="h-9 bg-nppc-900 text-sm hover:bg-nppc-700"
                            disabled={saving || !isDirty}
                            onClick={save}
                        >
                            {saving ? 'Saving…' : 'Save Assignments'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={discardOpen}
                onOpenChange={setDiscardOpen}
                title="Discard unsaved changes?"
                description="Your assignment edits for this analyst will be lost."
                confirmLabel="Discard"
                cancelLabel="Keep editing"
                variant="destructive"
                onConfirm={discardAndClose}
            />

            <ConfirmDialog
                open={bulkConfirm !== null}
                onOpenChange={(next) => {
                    if (!next) {
                        setBulkConfirm(null);
                    }
                }}
                title={
                    bulkConfirm?.assign
                        ? `Assign all ${bulkConfirm.ids.length} ${bulkConfirm.label} tests?`
                        : `Clear ${bulkConfirm?.ids.length ?? 0} ${bulkConfirm?.label ?? ''} tests?`
                }
                description={
                    bulkConfirm?.assign
                        ? 'This will mark every visible test in this category as assigned for this analyst.'
                        : 'This will unassign every visible test in this category for this analyst.'
                }
                confirmLabel={bulkConfirm?.assign ? 'Assign all' : 'Clear'}
                cancelLabel="Cancel"
                onConfirm={applyBulk}
            />
        </>
    );
}
