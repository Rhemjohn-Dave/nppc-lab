import { Head, router, usePage } from '@inertiajs/react';
import { useEffect, useMemo, useState } from 'react';
import TablePagination from '@/components/table-pagination';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { cn } from '@/lib/utils';

type Analyst = {
    id: number;
    name: string;
    email: string;
    analysis_type_ids: number[];
};

type Procedure = {
    id: number;
    code: string;
    name: string;
    category: string;
    category_label: string;
};

type Group = {
    category: string;
    label: string;
    items: Procedure[];
    type_ids: number[];
};

type Props = {
    analysts: Analyst[];
    groups: Group[];
};

const ANALYSTS_PAGE_SIZE = 10;
const CATEGORIES_PAGE_SIZE = 5;

export default function AdminAssignments({ analysts, groups }: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [selectedAnalyst, setSelectedAnalyst] = useState<Analyst | null>(
        analysts[0] ?? null,
    );
    const [selectedIds, setSelectedIds] = useState<number[]>(
        analysts[0]?.analysis_type_ids ?? [],
    );
    const [saving, setSaving] = useState(false);
    const [openCategories, setOpenCategories] = useState<string[]>(
        groups.map((group) => group.category),
    );
    const [analystPage, setAnalystPage] = useState(1);
    const [categoryPage, setCategoryPage] = useState(1);

    const totalProcedures = useMemo(
        () => groups.reduce((sum, group) => sum + group.items.length, 0),
        [groups],
    );

    const analystTotalPages = Math.max(
        1,
        Math.ceil(analysts.length / ANALYSTS_PAGE_SIZE),
    );
    const pagedAnalysts = useMemo(() => {
        const start = (analystPage - 1) * ANALYSTS_PAGE_SIZE;

        return analysts.slice(start, start + ANALYSTS_PAGE_SIZE);
    }, [analysts, analystPage]);

    const categoryTotalPages = Math.max(
        1,
        Math.ceil(groups.length / CATEGORIES_PAGE_SIZE),
    );
    const pagedGroups = useMemo(() => {
        const start = (categoryPage - 1) * CATEGORIES_PAGE_SIZE;

        return groups.slice(start, start + CATEGORIES_PAGE_SIZE);
    }, [groups, categoryPage]);

    useEffect(() => {
        if (analystPage > analystTotalPages) {
            setAnalystPage(analystTotalPages);
        }
    }, [analystPage, analystTotalPages]);

    useEffect(() => {
        if (categoryPage > categoryTotalPages) {
            setCategoryPage(categoryTotalPages);
        }
    }, [categoryPage, categoryTotalPages]);

    function chooseAnalyst(analyst: Analyst) {
        setSelectedAnalyst(analyst);
        setSelectedIds(analyst.analysis_type_ids);
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

    function save() {
        if (!selectedAnalyst) {
            return;
        }

        setSaving(true);
        router.put(
            `/admin/assignments/${selectedAnalyst.id}`,
            {
                analysis_type_ids: selectedIds,
            },
            {
                onFinish: () => setSaving(false),
                onSuccess: () => {
                    setSelectedAnalyst((current) =>
                        current
                            ? {
                                  ...current,
                                  analysis_type_ids: selectedIds,
                              }
                            : current,
                    );
                },
            },
        );
    }

    return (
        <>
            <Head title="Assignments" />
            <div className="flex flex-col gap-5 p-4">
                <div>
                    <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                        Analyst assignments
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Assign by category for fast setup (e.g. all
                        Microbiological procedures), then fine-tune individual
                        tests. Receiving uses this when auto-assigning job
                        lines.
                    </p>
                    {flash?.success && (
                        <p className="mt-2 text-sm text-emerald-700">
                            {flash.success}
                        </p>
                    )}
                </div>

                <div className="grid gap-4 lg:grid-cols-[260px_1fr]">
                    <aside className="rounded-xl border bg-white p-3">
                        <p className="mb-2 px-2 text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                            Analysts
                        </p>
                        {pagedAnalysts.map((analyst) => {
                            const active =
                                selectedAnalyst?.id === analyst.id;
                            const count = active
                                ? selectedIds.length
                                : analyst.analysis_type_ids.length;

                            return (
                                <button
                                    key={analyst.id}
                                    type="button"
                                    className={cn(
                                        'mb-1 w-full rounded-lg px-3 py-2.5 text-left text-sm transition',
                                        active
                                            ? 'bg-[#1A3694] text-white'
                                            : 'hover:bg-[#f8fafc]',
                                    )}
                                    onClick={() => chooseAnalyst(analyst)}
                                >
                                    <div className="font-medium">
                                        {analyst.name}
                                    </div>
                                    <div
                                        className={cn(
                                            'text-xs',
                                            active
                                                ? 'text-white/80'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {analyst.email}
                                    </div>
                                    <div
                                        className={cn(
                                            'mt-1 text-xs font-medium',
                                            active
                                                ? 'text-white/90'
                                                : 'text-[#1A3694]',
                                        )}
                                    >
                                        {count}/{totalProcedures} procedures
                                    </div>
                                </button>
                            );
                        })}
                        {analysts.length === 0 && (
                            <p className="px-2 py-4 text-sm text-muted-foreground">
                                No analyst accounts yet.
                            </p>
                        )}
                        <div className="mt-3 px-1">
                            <TablePagination
                                mode="client"
                                page={analystPage}
                                totalPages={analystTotalPages}
                                totalItems={analysts.length}
                                pageSize={ANALYSTS_PAGE_SIZE}
                                onPageChange={setAnalystPage}
                                label="analysts"
                            />
                        </div>
                    </aside>

                    <section className="rounded-xl border bg-white">
                        {selectedAnalyst ? (
                            <>
                                <div className="flex flex-wrap items-center justify-between gap-3 border-b bg-[#f8fafc] px-4 py-3">
                                    <div>
                                        <h2 className="font-semibold text-[#1A3694]">
                                            {selectedAnalyst.name}
                                        </h2>
                                        <p className="text-xs text-muted-foreground">
                                            {selectedIds.length} of{' '}
                                            {totalProcedures} procedures
                                            selected
                                        </p>
                                    </div>
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() =>
                                                setSelectedIds(
                                                    groups.flatMap(
                                                        (group) =>
                                                            group.type_ids,
                                                    ),
                                                )
                                            }
                                        >
                                            Select all
                                        </Button>
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            onClick={() => setSelectedIds([])}
                                        >
                                            Clear
                                        </Button>
                                        <Button
                                            size="sm"
                                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                                            disabled={saving}
                                            onClick={save}
                                        >
                                            {saving
                                                ? 'Saving…'
                                                : 'Save assignments'}
                                        </Button>
                                    </div>
                                </div>

                                <div className="space-y-3 p-4">
                                    {pagedGroups.map((group) => {
                                        const open = openCategories.includes(
                                            group.category,
                                        );
                                        const state = categoryState(
                                            group.type_ids,
                                        );
                                        const selectedInCategory =
                                            group.type_ids.filter((id) =>
                                                selectedIds.includes(id),
                                            ).length;

                                        return (
                                            <div
                                                key={group.category}
                                                className="overflow-hidden rounded-xl border"
                                            >
                                                <div className="flex flex-wrap items-center justify-between gap-3 bg-[#f8fafc] px-4 py-3">
                                                    <div className="flex min-w-0 items-start gap-3">
                                                        <Checkbox
                                                            checked={state}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) =>
                                                                setCategory(
                                                                    group.type_ids,
                                                                    checked ===
                                                                        true,
                                                                )
                                                            }
                                                            className="mt-0.5"
                                                            aria-label={`Assign all ${group.label}`}
                                                        />
                                                        <button
                                                            type="button"
                                                            className="min-w-0 text-left"
                                                            onClick={() =>
                                                                toggleCategoryOpen(
                                                                    group.category,
                                                                )
                                                            }
                                                        >
                                                            <p className="font-semibold text-[#1A3694]">
                                                                {group.label}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {selectedInCategory}
                                                                /
                                                                {
                                                                    group
                                                                        .items
                                                                        .length
                                                                }{' '}
                                                                assigned · click
                                                                to{' '}
                                                                {open
                                                                    ? 'collapse'
                                                                    : 'expand'}
                                                            </p>
                                                        </button>
                                                    </div>
                                                    <div className="flex gap-2">
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="outline"
                                                            onClick={() =>
                                                                setCategory(
                                                                    group.type_ids,
                                                                    true,
                                                                )
                                                            }
                                                        >
                                                            Assign category
                                                        </Button>
                                                        <Button
                                                            type="button"
                                                            size="sm"
                                                            variant="ghost"
                                                            onClick={() =>
                                                                setCategory(
                                                                    group.type_ids,
                                                                    false,
                                                                )
                                                            }
                                                        >
                                                            Clear
                                                        </Button>
                                                    </div>
                                                </div>

                                                {open && (
                                                    <div className="grid gap-2 border-t p-3 sm:grid-cols-2">
                                                        {group.items.map(
                                                            (type) => {
                                                                const checked =
                                                                    selectedIds.includes(
                                                                        type.id,
                                                                    );

                                                                return (
                                                                    <label
                                                                        key={
                                                                            type.id
                                                                        }
                                                                        className={cn(
                                                                            'flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border px-3 py-3 text-sm transition',
                                                                            checked
                                                                                ? 'border-[#1A3694] bg-[#eef3fb]'
                                                                                : 'border-slate-100 bg-white hover:border-[#5282D3]/40',
                                                                        )}
                                                                    >
                                                                        <Checkbox
                                                                            checked={
                                                                                checked
                                                                            }
                                                                            onCheckedChange={() =>
                                                                                toggle(
                                                                                    type.id,
                                                                                )
                                                                            }
                                                                            className="mt-0.5"
                                                                        />
                                                                        <span>
                                                                            <span className="font-medium">
                                                                                {
                                                                                    type.code
                                                                                }
                                                                            </span>{' '}
                                                                            {
                                                                                type.name
                                                                            }
                                                                        </span>
                                                                    </label>
                                                                );
                                                            },
                                                        )}
                                                    </div>
                                                )}
                                            </div>
                                        );
                                    })}

                                    <TablePagination
                                        mode="client"
                                        page={categoryPage}
                                        totalPages={categoryTotalPages}
                                        totalItems={groups.length}
                                        pageSize={CATEGORIES_PAGE_SIZE}
                                        onPageChange={setCategoryPage}
                                        label="categories"
                                    />
                                </div>
                            </>
                        ) : (
                            <p className="p-6 text-sm text-muted-foreground">
                                No analysts available.
                            </p>
                        )}
                    </section>
                </div>
            </div>
        </>
    );
}

AdminAssignments.layout = {
    breadcrumbs: [{ title: 'Assignments', href: '/admin/assignments' }],
};
