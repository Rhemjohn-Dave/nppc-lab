import { Head, router, useForm } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import QueueSummaryCards from '@/components/lims/queue-summary-cards';
import QueueFilterBar from '@/components/queue-filter-bar';
import WorkspaceHeader from '@/components/workspace-header';
import { ArrowRight, Pencil, Plus, Tags, Trash2 } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import TablePagination from '@/components/table-pagination';
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
import { Label } from '@/components/ui/label';

type AnalysisType = {
    id: number;
    code: string;
    name: string;
    method: string | null;
    acceptable_values?: string | null;
    category_id: number;
    category: string | null;
    category_label: string | null;
    default_price: string | number;
    is_active: boolean;
    requires_pass_fail: boolean;
    sort_order: number;
};

type CategoryOption = {
    id: number;
    value: string;
    slug: string;
    label: string;
    is_active: boolean;
    procedures_count: number;
};

type Group = {
    id: number;
    category: string;
    label: string;
    is_active: boolean;
    count: number;
    items: AnalysisType[];
};

type Props = {
    groups: Group[];
    categories: CategoryOption[];
    all_categories?: CategoryOption[];
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

function categoryChipLabel(
    category: CategoryOption,
    siblings: CategoryOption[],
): string {
    const duplicateName = siblings.some(
        (other) =>
            other.id !== category.id &&
            other.label.toLowerCase() === category.label.toLowerCase(),
    );

    return duplicateName ? `${category.label} (${category.slug})` : category.label;
}

export default function AdminPrices({
    groups,
    categories,
    all_categories,
}: Props) {
    const manageCategories = all_categories ?? categories;
    const [query, setQuery] = useState('');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [statusFilter, setStatusFilter] = useState<'all' | 'active' | 'inactive'>(
        'all',
    );
    const [page, setPage] = useState(1);
    const pageSize = 15;

    const [addCategoryOpen, setAddCategoryOpen] = useState(false);
    const [manageCategoriesOpen, setManageCategoriesOpen] = useState(false);
    const [addProcedureOpen, setAddProcedureOpen] = useState(false);
    const [editing, setEditing] = useState<AnalysisType | null>(null);
    const [deleting, setDeleting] = useState<AnalysisType | null>(null);
    const [deletingBusy, setDeletingBusy] = useState(false);

    const procedures = useMemo(
        () => groups.flatMap((group) => group.items),
        [groups],
    );

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();

        return procedures.filter((item) => {
            if (
                categoryFilter !== 'all' &&
                String(item.category_id) !== categoryFilter
            ) {
                return false;
            }

            if (statusFilter === 'active' && !item.is_active) {
                return false;
            }

            if (statusFilter === 'inactive' && item.is_active) {
                return false;
            }

            if (!q) {
                return true;
            }

            return (
                item.code.toLowerCase().includes(q) ||
                item.name.toLowerCase().includes(q) ||
                (item.category_label ?? '').toLowerCase().includes(q)
            );
        });
    }, [procedures, query, categoryFilter, statusFilter]);

    const totalPages = Math.max(1, Math.ceil(filtered.length / pageSize));

    useEffect(() => {
        setPage(1);
    }, [query, categoryFilter, statusFilter]);

    useEffect(() => {
        if (page > totalPages) {
            setPage(totalPages);
        }
    }, [page, totalPages]);

    const pageItems = useMemo(() => {
        const start = (page - 1) * pageSize;

        return filtered.slice(start, start + pageSize);
    }, [filtered, page, pageSize]);

    const activeCount = procedures.filter((item) => item.is_active).length;
    const inactiveCount = procedures.length - activeCount;
    const kpiTotal = Math.max(procedures.length, 1);

    const summaryCards = [
        {
            id: 'categories',
            label: 'Categories',
            value: categories.length,
            unit: 'Procedure groups',
            tone: 'info' as const,
            share: Math.min(
                100,
                Math.round((categories.length / Math.max(kpiTotal, 1)) * 100),
            ),
        },
        {
            id: 'all',
            label: 'Procedures',
            value: procedures.length,
            unit: 'Catalog SOPs',
            tone: 'default' as const,
            share: 100,
        },
        {
            id: 'active',
            label: 'Active',
            value: activeCount,
            unit: 'Available for intake',
            tone: 'success' as const,
            share: Math.round((activeCount / kpiTotal) * 100),
        },
        {
            id: 'inactive',
            label: 'Inactive',
            value: inactiveCount,
            unit: 'Hidden from intake',
            tone: 'warning' as const,
            share: Math.round((inactiveCount / kpiTotal) * 100),
        },
    ];

    const categoryChips = [
        {
            id: 'all',
            label: 'All categories',
            count: procedures.length,
        },
        ...categories.map((category) => ({
            id: String(category.id),
            label: categoryChipLabel(category, categories),
            count: category.procedures_count,
        })),
    ];

    function goToPage(next: number) {
        setPage(Math.min(totalPages, Math.max(1, next)));
    }

    function onSummarySelect(id: string) {
        if (id === 'categories') {
            setCategoryFilter('all');
            return;
        }

        setStatusFilter(id as 'all' | 'active' | 'inactive');
    }

    return (
        <>
            <Head title="Procedures & prices" />
            <LimsWorkspace className="gap-4">
                <WorkspaceHeader
                    title="Procedures & prices"
                    description="Searchable catalog of analyst procedures. Use modals to add categories and add/edit procedures."
                    actions={
                        <div className="flex flex-wrap gap-2">
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 border-slate-200 text-xs shadow-xs"
                                onClick={() => setManageCategoriesOpen(true)}
                            >
                                <Tags className="size-3.5" />
                                Categories
                            </Button>
                            <Button
                                variant="outline"
                                size="sm"
                                className="h-8 border-slate-200 text-xs shadow-xs"
                                onClick={() => setAddCategoryOpen(true)}
                            >
                                <Plus className="size-3.5" />
                                Add category
                            </Button>
                            <Button
                                size="sm"
                                className="h-8 gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                onClick={() => setAddProcedureOpen(true)}
                                disabled={categories.length === 0}
                            >
                                <Plus className="size-3.5" />
                                Add procedure
                            </Button>
                        </div>
                    }
                />

                <QueueSummaryCards
                    cards={summaryCards}
                    activeId={statusFilter}
                    onSelect={onSummarySelect}
                    className="sm:grid-cols-2 lg:grid-cols-4"
                />

                <QueueFilterBar
                    chips={categoryChips}
                    activeId={categoryFilter}
                    onChip={setCategoryFilter}
                    query={query}
                    onQueryChange={setQuery}
                    onSearch={() => undefined}
                    onClear={() => setQuery('')}
                    placeholder="Search code, name, category…"
                    sortValue={statusFilter}
                    onSortChange={(value) =>
                        setStatusFilter(
                            value as 'all' | 'active' | 'inactive',
                        )
                    }
                    sortOptions={[
                        { value: 'all', label: 'All Status' },
                        { value: 'active', label: 'Active Only' },
                        { value: 'inactive', label: 'Inactive Only' },
                    ]}
                />

                <div className="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                    <div className="overflow-x-auto">
                        <table className="w-full border-collapse text-left text-xs">
                            <thead className="sticky top-0 z-10 border-b border-slate-200 bg-slate-50/90">
                                <tr className="select-none text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    <th className="px-4 py-3">Code</th>
                                    <th className="px-4 py-3">Procedure</th>
                                    <th className="px-4 py-3">Category</th>
                                    <th className="px-4 py-3">Default price</th>
                                    <th className="px-4 py-3">Pass / Fail</th>
                                    <th className="px-4 py-3">Status</th>
                                    <th className="min-w-[12rem] px-4 py-3 text-right">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-200/80">
                                {pageItems.map((item) => (
                                    <tr
                                        key={item.id}
                                        className="transition-colors hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-4 align-top font-semibold text-slate-900">
                                            {item.code}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="font-semibold text-slate-900">
                                                {item.name}
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top text-[11px] text-slate-500">
                                            {item.category_label ?? '—'}
                                        </td>
                                        <td className="px-4 py-4 align-top text-xs font-bold tabular-nums text-slate-900">
                                            {money(item.default_price)}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            {item.requires_pass_fail ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-amber-200 bg-amber-50 text-[10px] text-amber-900"
                                                >
                                                    Required
                                                </Badge>
                                            ) : (
                                                <span className="text-[11px] text-muted-foreground">
                                                    Off
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            {item.is_active ? (
                                                <Badge
                                                    variant="outline"
                                                    className="border-emerald-200 bg-emerald-50 text-[10px] text-emerald-800"
                                                >
                                                    Active
                                                </Badge>
                                            ) : (
                                                <Badge
                                                    variant="outline"
                                                    className="border-slate-200 bg-slate-50 text-[10px] text-slate-600"
                                                >
                                                    Inactive
                                                </Badge>
                                            )}
                                        </td>
                                        <td className="px-4 py-4 text-right align-top">
                                            <div className="inline-flex flex-wrap justify-end gap-1.5">
                                                <Button
                                                    size="sm"
                                                    className="h-8 gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                                    onClick={() =>
                                                        setEditing(item)
                                                    }
                                                >
                                                    <Pencil className="size-3.5" />
                                                    Edit
                                                    <ArrowRight className="size-3" />
                                                </Button>
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    className="h-8 border-slate-200 text-xs text-red-700 shadow-xs hover:bg-red-50 hover:text-red-800"
                                                    onClick={() =>
                                                        setDeleting(item)
                                                    }
                                                >
                                                    <Trash2 className="size-3.5" />
                                                    Delete
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                                {filtered.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-12 text-center"
                                        >
                                            <p className="text-sm font-medium text-nppc-900">
                                                {procedures.length === 0
                                                    ? 'No procedures yet'
                                                    : 'No matching procedures'}
                                            </p>
                                            <p className="mt-1 text-xs text-muted-foreground">
                                                {procedures.length === 0
                                                    ? 'Add a category, then add a procedure.'
                                                    : 'No procedures match your filters.'}
                                            </p>
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    <div className="border-t border-slate-200 bg-slate-50/50 px-4 py-3">
                        <TablePagination
                            mode="client"
                            page={page}
                            totalPages={totalPages}
                            totalItems={filtered.length}
                            pageSize={pageSize}
                            onPageChange={goToPage}
                            label="procedures"
                            filteredTotal={procedures.length}
                        />
                    </div>
                </div>
            </LimsWorkspace>

            <AddCategoryModal
                open={addCategoryOpen}
                onOpenChange={setAddCategoryOpen}
            />
            <ManageCategoriesModal
                open={manageCategoriesOpen}
                onOpenChange={setManageCategoriesOpen}
                categories={manageCategories}
            />
            <ProcedureModal
                open={addProcedureOpen}
                onOpenChange={setAddProcedureOpen}
                categories={categories}
                mode="create"
            />
            <ProcedureModal
                open={!!editing}
                onOpenChange={(open) => !open && setEditing(null)}
                categories={categories}
                allCategories={manageCategories}
                mode="edit"
                procedure={editing}
            />
            <ConfirmDialog
                open={deleting !== null}
                onOpenChange={(open) => {
                    if (!open && !deletingBusy) {
                        setDeleting(null);
                    }
                }}
                title="Delete procedure?"
                description={
                    deleting ? (
                        <>
                            Delete <span className="font-medium">{deleting.code}</span>{' '}
                            ({deleting.name})? This cannot be undone. Procedures
                            used on packages or job orders cannot be deleted —
                            deactivate them instead.
                        </>
                    ) : null
                }
                confirmLabel="Delete"
                processingLabel="Deleting…"
                variant="destructive"
                processing={deletingBusy}
                onConfirm={() => {
                    if (!deleting) {
                        return;
                    }

                    setDeletingBusy(true);
                    router.delete(`/admin/prices/${deleting.id}`, {
                        preserveScroll: true,
                        onFinish: () => {
                            setDeletingBusy(false);
                            setDeleting(null);
                        },
                    });
                }}
            />
        </>
    );
}

function AddCategoryModal({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    const form = useForm({ name: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/admin/prices/categories', {
            onSuccess: () => {
                form.reset();
                onOpenChange(false);
            },
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add category</DialogTitle>
                    <DialogDescription>
                        Categories group procedures for intake and analyst
                        assignment (e.g. Microbiological).
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    <div>
                        <Label htmlFor="new_category_name">Name *</Label>
                        <Input
                            id="new_category_name"
                            className="mt-1"
                            placeholder="e.g. Soil Analysis"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                            autoFocus
                        />
                        {form.errors.name && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.name}
                            </p>
                        )}
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="bg-nppc-900 hover:bg-nppc-700"
                        >
                            {form.processing ? 'Adding…' : 'Add category'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

function ManageCategoriesModal({
    open,
    onOpenChange,
    categories,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: CategoryOption[];
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Manage categories</DialogTitle>
                    <DialogDescription>
                        Rename or deactivate categories. Empty categories can be
                        removed.
                    </DialogDescription>
                </DialogHeader>
                <div className="space-y-3">
                    {categories.map((category) => (
                        <CategoryEditorRow
                            key={category.id}
                            category={category}
                        />
                    ))}
                    {categories.length === 0 && (
                        <p className="text-sm text-muted-foreground">
                            No categories yet.
                        </p>
                    )}
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => onOpenChange(false)}>
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}

function CategoryEditorRow({ category }: { category: CategoryOption }) {
    const form = useForm({
        name: category.label,
        is_active: category.is_active,
    });

    return (
        <div className="rounded-xl border p-3">
            <Input
                className="h-9"
                value={form.data.name}
                onChange={(e) => form.setData('name', e.target.value)}
            />
            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                <label className="flex items-center gap-2 text-xs text-slate-600">
                    <Checkbox
                        checked={form.data.is_active}
                        onCheckedChange={(checked) =>
                            form.setData('is_active', !!checked)
                        }
                    />
                    Active
                </label>
                <span className="text-xs text-muted-foreground">
                    {category.procedures_count} procedure
                    {category.procedures_count === 1 ? '' : 's'}
                </span>
            </div>
            <div className="mt-3 flex flex-wrap gap-2">
                <Button
                    size="sm"
                    className="bg-nppc-900 hover:bg-nppc-700"
                    disabled={form.processing}
                    onClick={() =>
                        form.patch(`/admin/prices/categories/${category.id}`)
                    }
                >
                    Save
                </Button>
                <Button
                    size="sm"
                    variant="outline"
                    disabled={
                        form.processing || category.procedures_count > 0
                    }
                    onClick={() =>
                        router.delete(
                            `/admin/prices/categories/${category.id}`,
                        )
                    }
                >
                    Remove
                </Button>
            </div>
        </div>
    );
}

function ProcedureModal({
    open,
    onOpenChange,
    categories,
    allCategories,
    mode,
    procedure = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: CategoryOption[];
    allCategories?: CategoryOption[];
    mode: 'create' | 'edit';
    procedure?: AnalysisType | null;
}) {
    const categoryChoices = useMemo(() => {
        const pool = allCategories ?? categories;
        const byId = new Map(categories.map((category) => [category.id, category]));

        if (
            procedure?.category_id &&
            !byId.has(procedure.category_id)
        ) {
            const orphan = pool.find(
                (category) => category.id === procedure.category_id,
            );
            if (orphan) {
                return [...categories, orphan];
            }
        }

        return categories;
    }, [allCategories, categories, procedure?.category_id]);

    const form = useForm({
        code: procedure?.code ?? '',
        name: procedure?.name ?? '',
        method: procedure?.method ?? '',
        acceptable_values: procedure?.acceptable_values ?? '',
        category_id: procedure?.category_id ?? categoryChoices[0]?.id ?? 0,
        default_price: Number(procedure?.default_price ?? 0),
        is_active: procedure?.is_active ?? true,
        requires_pass_fail: procedure?.requires_pass_fail ?? false,
    });

    useEffect(() => {
        if (!open) {
            return;
        }

        form.setData({
            code: procedure?.code ?? '',
            name: procedure?.name ?? '',
            method: procedure?.method ?? '',
            acceptable_values: procedure?.acceptable_values ?? '',
            category_id: procedure?.category_id ?? categoryChoices[0]?.id ?? 0,
            default_price: Number(procedure?.default_price ?? 0),
            is_active: procedure?.is_active ?? true,
            requires_pass_fail: procedure?.requires_pass_fail ?? false,
        });
        form.clearErrors();
        // Only re-sync when the dialog opens or the edited procedure changes.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, procedure?.id]);

    function submit(event: FormEvent) {
        event.preventDefault();

        if (mode === 'create') {
            form.post('/admin/prices', {
                onSuccess: () => {
                    form.reset();
                    form.setData({
                        code: '',
                        name: '',
                        method: '',
                        acceptable_values: '',
                        category_id: categories[0]?.id ?? 0,
                        default_price: 0,
                        is_active: true,
                        requires_pass_fail: false,
                    });
                    onOpenChange(false);
                },
            });
            return;
        }

        if (!procedure) {
            return;
        }

        form.patch(`/admin/prices/${procedure.id}`, {
            onSuccess: () => onOpenChange(false),
        });
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>
                        {mode === 'create'
                            ? 'Add procedure'
                            : `Edit ${procedure?.code}`}
                    </DialogTitle>
                    <DialogDescription>
                        Default prices are used when intake creates job order
                        lines.
                    </DialogDescription>
                </DialogHeader>
                <form className="space-y-4" onSubmit={submit}>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="proc_code">Code *</Label>
                            <Input
                                id="proc_code"
                                className="mt-1"
                                placeholder="AQ-W-01"
                                value={form.data.code}
                                onChange={(e) =>
                                    form.setData('code', e.target.value)
                                }
                                required
                            />
                            {form.errors.code && (
                                <p className="mt-1 text-xs text-red-600">
                                    {form.errors.code}
                                </p>
                            )}
                        </div>
                        <div>
                            <Label htmlFor="proc_category">Category *</Label>
                            <select
                                id="proc_category"
                                className="mt-1 flex h-9 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none"
                                value={form.data.category_id}
                                onChange={(e) =>
                                    form.setData(
                                        'category_id',
                                        Number(e.target.value),
                                    )
                                }
                                required
                            >
                                {categoryChoices.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {categoryChipLabel(
                                            category,
                                            categoryChoices,
                                        )}
                                        {!category.is_active ? ' (inactive)' : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="proc_name">Procedure name *</Label>
                        <Input
                            id="proc_name"
                            className="mt-1"
                            placeholder="Aerobic Plate Count (HPC)"
                            value={form.data.name}
                            onChange={(e) =>
                                form.setData('name', e.target.value)
                            }
                            required
                        />
                    </div>
                    <div>
                        <Label htmlFor="proc_method">Method</Label>
                        <Input
                            id="proc_method"
                            className="mt-1"
                            placeholder="Shown under the test name on dynamic matrix PDFs"
                            value={form.data.method}
                            onChange={(e) =>
                                form.setData('method', e.target.value)
                            }
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Default for analyst encoding and Dynamic test matrix
                            PDFs. Analysts can override method when encoding.
                        </p>
                        {form.errors.method && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.method}
                            </p>
                        )}
                    </div>
                    <div>
                        <Label htmlFor="proc_acceptable">Acceptable values</Label>
                        <textarea
                            id="proc_acceptable"
                            className="mt-1 min-h-[72px] w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            placeholder="e.g. 6.5-8.5 / 5-7 for RO or distilled product water"
                            value={form.data.acceptable_values}
                            onChange={(e) =>
                                form.setData('acceptable_values', e.target.value)
                            }
                        />
                        <p className="mt-1 text-xs text-muted-foreground">
                            Optional. Printed in the Acceptable Values column on
                            Drinking Water Physico-Chemical style sheets.
                        </p>
                        {form.errors.acceptable_values && (
                            <p className="mt-1 text-xs text-red-600">
                                {form.errors.acceptable_values}
                            </p>
                        )}
                    </div>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <div>
                            <Label htmlFor="proc_price">Default price</Label>
                            <Input
                                id="proc_price"
                                type="number"
                                min={0}
                                step="0.01"
                                className="mt-1"
                                value={form.data.default_price}
                                onChange={(e) =>
                                    form.setData(
                                        'default_price',
                                        Number(e.target.value),
                                    )
                                }
                                required
                            />
                        </div>
                        <div className="flex flex-col justify-end gap-2 pb-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', !!checked)
                                    }
                                />
                                Active in intake & assignments
                            </label>
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.requires_pass_fail}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'requires_pass_fail',
                                            !!checked,
                                        )
                                    }
                                />
                                Require Pass / Fail
                            </label>
                            <p className="text-xs text-muted-foreground">
                                Off by default. When enabled, analysts must
                                select Passed or Failed to complete this
                                procedure.
                            </p>
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || categories.length === 0}
                            className="bg-nppc-900 hover:bg-nppc-700"
                        >
                            {form.processing
                                ? 'Saving…'
                                : mode === 'create'
                                  ? 'Add procedure'
                                  : 'Save changes'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    );
}

AdminPrices.layout = {
    breadcrumbs: [{ title: 'Procedures', href: '/admin/prices' }],
};
