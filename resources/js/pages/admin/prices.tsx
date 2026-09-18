import { Head, router, useForm } from '@inertiajs/react';
import LimsWorkspace from '@/components/lims/lims-workspace';
import { Pencil, Plus, Search, Tags, Trash2 } from 'lucide-react';
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
import { cn } from '@/lib/utils';

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

    function goToPage(next: number) {
        setPage(Math.min(totalPages, Math.max(1, next)));
    }

    return (
        <>
            <Head title="Procedures & prices" />
            <LimsWorkspace>
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Procedures & prices
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Searchable catalog of analyst procedures. Use
                            modals to add categories and add/edit procedures.
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            onClick={() => setManageCategoriesOpen(true)}
                        >
                            <Tags className="size-4" />
                            Categories
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setAddCategoryOpen(true)}
                        >
                            <Plus className="size-4" />
                            Add category
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={() => setAddProcedureOpen(true)}
                            disabled={categories.length === 0}
                        >
                            <Plus className="size-4" />
                            Add procedure
                        </Button>
                    </div>
                </div>

                <div className="grid gap-3 sm:grid-cols-3">
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Categories
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {categories.length}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-[#e8eef8]/60 p-4">
                        <p className="text-sm text-muted-foreground">
                            Procedures
                        </p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-[#1A3694]">
                            {procedures.length}
                        </p>
                    </div>
                    <div className="rounded-xl border bg-gradient-to-br from-white to-emerald-50/70 p-4">
                        <p className="text-sm text-muted-foreground">Active</p>
                        <p className="mt-1 font-heading text-3xl font-semibold text-emerald-800">
                            {activeCount}
                        </p>
                    </div>
                </div>

                <div className="flex flex-col gap-3 rounded-xl border bg-white p-3 lg:flex-row lg:items-center lg:justify-between">
                    <div className="flex flex-wrap gap-2">
                        <button
                            type="button"
                            onClick={() => setCategoryFilter('all')}
                            className={cn(
                                'inline-flex min-h-9 items-center rounded-full border px-3 text-sm font-medium transition',
                                categoryFilter === 'all'
                                    ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                    : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                            )}
                        >
                            All categories
                        </button>
                        {categories.map((category) => (
                            <button
                                key={category.id}
                                type="button"
                                onClick={() =>
                                    setCategoryFilter(String(category.id))
                                }
                                className={cn(
                                    'inline-flex min-h-9 items-center gap-2 rounded-full border px-3 text-sm font-medium transition',
                                    categoryFilter === String(category.id)
                                        ? 'border-[#1A3694] bg-[#1A3694] text-white'
                                        : 'border-slate-200 bg-white text-slate-700 hover:border-[#5282D3]',
                                )}
                            >
                                {categoryChipLabel(category, categories)}
                                <span
                                    className={cn(
                                        'rounded-full px-1.5 py-0.5 text-xs tabular-nums',
                                        categoryFilter === String(category.id)
                                            ? 'bg-white/20'
                                            : 'bg-slate-100 text-slate-600',
                                    )}
                                >
                                    {category.procedures_count}
                                </span>
                            </button>
                        ))}
                    </div>

                    <div className="flex w-full flex-col gap-2 sm:flex-row sm:items-center lg:w-auto">
                        <select
                            className="flex h-10 rounded-md border border-input bg-transparent px-3 text-sm outline-none"
                            value={statusFilter}
                            onChange={(e) =>
                                setStatusFilter(
                                    e.target.value as
                                        | 'all'
                                        | 'active'
                                        | 'inactive',
                                )
                            }
                        >
                            <option value="all">All status</option>
                            <option value="active">Active only</option>
                            <option value="inactive">Inactive only</option>
                        </select>
                        <div className="relative min-w-64 flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-slate-400" />
                            <Input
                                value={query}
                                onChange={(e) => setQuery(e.target.value)}
                                placeholder="Search code, name, category…"
                                className="h-10 pl-9"
                            />
                        </div>
                    </div>
                </div>

                <div className="overflow-x-auto rounded-xl border bg-white">
                    <table className="w-full text-sm">
                        <thead className="bg-[#f8fafc] text-left">
                            <tr>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Code
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Procedure
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Category
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Default price
                                </th>
                                <th className="px-4 py-3 font-medium text-slate-600">
                                    Status
                                </th>
                                <th className="px-4 py-3" />
                            </tr>
                        </thead>
                        <tbody>
                            {pageItems.map((item) => (
                                <tr
                                    key={item.id}
                                    className="border-t transition hover:bg-[#f8fafc]"
                                >
                                    <td className="px-4 py-3 font-semibold text-[#1A3694]">
                                        {item.code}
                                    </td>
                                    <td className="px-4 py-3 font-medium">
                                        {item.name}
                                    </td>
                                    <td className="px-4 py-3 text-slate-600">
                                        {item.category_label ?? '—'}
                                    </td>
                                    <td className="px-4 py-3 tabular-nums">
                                        {money(item.default_price)}
                                    </td>
                                    <td className="px-4 py-3">
                                        {item.is_active ? (
                                            <Badge
                                                variant="outline"
                                                className="border-emerald-200 bg-emerald-50 text-emerald-800"
                                            >
                                                Active
                                            </Badge>
                                        ) : (
                                            <Badge
                                                variant="outline"
                                                className="border-slate-200 bg-slate-50 text-slate-600"
                                            >
                                                Inactive
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex justify-end gap-2">
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                onClick={() => setEditing(item)}
                                            >
                                                <Pencil className="size-3.5" />
                                                Edit
                                            </Button>
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="text-red-700 hover:bg-red-50 hover:text-red-800"
                                                onClick={() => setDeleting(item)}
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
                                        colSpan={6}
                                        className="px-4 py-12 text-center text-muted-foreground"
                                    >
                                        {procedures.length === 0
                                            ? 'No procedures yet. Add a category, then add a procedure.'
                                            : 'No procedures match your filters.'}
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

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
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
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
                    className="bg-[#1A3694] hover:bg-[#365BB0]"
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
                        <div className="flex items-end pb-2">
                            <label className="flex items-center gap-2 text-sm">
                                <Checkbox
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData('is_active', !!checked)
                                    }
                                />
                                Active in intake & assignments
                            </label>
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
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
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
