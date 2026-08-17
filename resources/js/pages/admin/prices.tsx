import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Pencil, Plus, Search, Tags } from 'lucide-react';
import { useEffect, useMemo, useState, type FormEvent } from 'react';
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
};

function money(value: string | number) {
    return `₱${Number(value || 0).toFixed(2)}`;
}

export default function AdminPrices({ groups, categories }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
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
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Procedures & prices
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Searchable catalog of analyst procedures. Use
                            modals to add categories and add/edit procedures.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
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
                                {category.label}
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
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setEditing(item)}
                                        >
                                            <Pencil className="size-3.5" />
                                            Edit
                                        </Button>
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
            </div>

            <AddCategoryModal
                open={addCategoryOpen}
                onOpenChange={setAddCategoryOpen}
            />
            <ManageCategoriesModal
                open={manageCategoriesOpen}
                onOpenChange={setManageCategoriesOpen}
                categories={categories}
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
                mode="edit"
                procedure={editing}
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
    mode,
    procedure = null,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    categories: CategoryOption[];
    mode: 'create' | 'edit';
    procedure?: AnalysisType | null;
}) {
    const form = useForm({
        code: procedure?.code ?? '',
        name: procedure?.name ?? '',
        category_id: procedure?.category_id ?? categories[0]?.id ?? 0,
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
            category_id: procedure?.category_id ?? categories[0]?.id ?? 0,
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
                                placeholder="MB-11"
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
                                {categories.map((category) => (
                                    <option
                                        key={category.id}
                                        value={category.id}
                                    >
                                        {category.label}
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
