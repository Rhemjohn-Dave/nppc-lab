import { Head, Link, useForm } from '@inertiajs/react';
import {
    Check,
    Copy,
    LayoutGrid,
    List,
    MoreHorizontal,
    Plus,
    Search,
    X,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import AnalysisTypePicker from '@/components/analysis-type-picker';
import LimsWorkspace from '@/components/lims/lims-workspace';
import PackageSelect, {
    type AnalysisPackageOption,
} from '@/components/package-select';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    statusBadgeClass,
    type AnalysisGroup,
    type ControlledFormSummary,
} from '@/lib/controlled-forms';
import { cn } from '@/lib/utils';

type Props = {
    forms: ControlledFormSummary[];
    categories: Array<{ value: string; label: string }>;
    analysisGroups: AnalysisGroup[];
    packages?: AnalysisPackageOption[];
};

type ViewMode = 'table' | 'grid';

const WORKFLOW_DISMISS_KEY = 'nppc.cf.workflowBanner.dismissed';

const STATUS_FILTERS = [
    { value: 'all', label: 'All statuses' },
    { value: 'active', label: 'Active only' },
    { value: 'draft', label: 'Draft' },
    { value: 'for_review', label: 'For review' },
    { value: 'for_approval', label: 'For approval' },
    { value: 'approved', label: 'Approved' },
    { value: 'superseded', label: 'Superseded' },
    { value: 'archived', label: 'Archived' },
] as const;

const WORKFLOW_STEPS = [
    {
        step: '01',
        title: 'Upload Master PDF',
        description: 'Import the official QA/QC signed PDF or DOCX file.',
        highlight: false,
    },
    {
        step: '02',
        title: 'Map Canvas Fields',
        description: 'Place dynamic database coordinate bindings in Form Designer.',
        highlight: false,
    },
    {
        step: '03',
        title: 'Preview Mock Data',
        description: 'Verify alignment with sample analytical runs before go-live.',
        highlight: false,
    },
    {
        step: '04',
        title: 'Activate & Release',
        description: 'Promote the revision into operations and bind to receiving tests.',
        highlight: true,
    },
] as const;

async function copyFormCode(code: string): Promise<void> {
    try {
        await navigator.clipboard.writeText(code);
    } catch {
        // Clipboard may be unavailable in some browsers; ignore quietly.
    }
}

function FormRowActions({ item }: { item: ControlledFormSummary }) {
    return (
        <div className="flex items-center justify-end gap-1.5">
            <Button variant="outline" size="sm" asChild>
                <Link href={`/admin/controlled-forms/${item.id}`}>Open</Link>
            </Button>
            {item.current_revision ? (
                <Button
                    size="sm"
                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                    asChild
                >
                    <Link
                        href={`/admin/controlled-forms/${item.id}/revisions/${item.current_revision.id}/designer`}
                    >
                        Designer
                    </Link>
                </Button>
            ) : null}
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        className="size-8 px-0"
                        aria-label={`More actions for ${item.form_code}`}
                    >
                        <MoreHorizontal className="size-4" />
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    <DropdownMenuItem
                        onSelect={() => {
                            void copyFormCode(item.form_code);
                        }}
                    >
                        <Copy className="size-3.5" />
                        Copy form code
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </div>
    );
}

function FormCodeChip({ code }: { code: string }) {
    return (
        <div className="group/code flex items-center gap-1.5">
            <span className="rounded border bg-slate-50 px-1.5 py-0.5 font-mono text-[11px] font-medium text-slate-800">
                {code}
            </span>
            <button
                type="button"
                title="Copy form code"
                className="rounded p-0.5 text-muted-foreground opacity-0 transition-opacity group-hover/code:opacity-100 hover:text-slate-700"
                onClick={() => {
                    void copyFormCode(code);
                }}
            >
                <Copy className="size-3.5" />
            </button>
        </div>
    );
}

function FormTitleCell({ item }: { item: ControlledFormSummary }) {
    const fieldCount = item.current_revision?.field_count ?? 0;

    return (
        <div>
            <div className="flex flex-wrap items-center gap-1.5">
                <span className="font-medium text-slate-900">{item.name}</span>
                {fieldCount > 0 ? (
                    <span className="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] text-slate-600">
                        {fieldCount} field{fieldCount === 1 ? '' : 's'} mapped
                    </span>
                ) : null}
            </div>
            <p className="mt-0.5 flex flex-wrap items-center gap-1.5 text-[11px] text-muted-foreground">
                <span
                    aria-hidden="true"
                    className="size-1.5 rounded-full bg-[#365BB0]"
                />
                {item.category_label}
                {item.department ? ` · ${item.department}` : ''}
            </p>
        </div>
    );
}

export default function ControlledFormsIndex({
    forms,
    categories,
    analysisGroups = [],
    packages = [],
}: Props) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [statusFilter, setStatusFilter] = useState<string>('all');
    const [categoryFilter, setCategoryFilter] = useState<string>('all');
    const [viewMode, setViewMode] = useState<ViewMode>('table');
    const [pageSize, setPageSize] = useState(10);
    const [page, setPage] = useState(1);
    const [workflowDismissed, setWorkflowDismissed] = useState(false);

    const form = useForm({
        form_code: 'NPPC-LAB-FRM-001',
        name: 'Request for Analysis Form / Job Order',
        description: '',
        department: 'Laboratory',
        category: 'job_order',
        job_order_variant: 'general',
        revision: '11',
        effective_date: '',
        notes: '',
        file: null as File | null,
        activate: false as boolean,
        analysis_type_ids: [] as number[],
        analysis_package_id: '' as number | '',
    });

    useEffect(() => {
        try {
            setWorkflowDismissed(
                window.localStorage.getItem(WORKFLOW_DISMISS_KEY) === '1',
            );
        } catch {
            setWorkflowDismissed(false);
        }
    }, []);

    const statusCounts = useMemo(() => {
        let active = 0;
        let pendingQa = 0;
        for (const item of forms) {
            if (item.status === 'active') {
                active += 1;
            }
            if (item.status === 'for_review' || item.status === 'for_approval') {
                pendingQa += 1;
            }
        }
        return { active, pendingQa, total: forms.length };
    }, [forms]);

    const filtered = useMemo(() => {
        const q = query.trim().toLowerCase();
        return forms.filter((item) => {
            if (statusFilter !== 'all' && item.status !== statusFilter) {
                return false;
            }
            if (categoryFilter !== 'all' && item.category !== categoryFilter) {
                return false;
            }
            if (!q) {
                return true;
            }
            const haystack = [
                item.form_code,
                item.name,
                item.status_label,
                item.category_label,
                item.department ?? '',
            ]
                .join(' ')
                .toLowerCase();
            return haystack.includes(q);
        });
    }, [forms, query, statusFilter, categoryFilter]);

    useEffect(() => {
        setPage(1);
    }, [query, statusFilter, categoryFilter, pageSize]);

    const pageCount = Math.max(1, Math.ceil(filtered.length / pageSize));
    const currentPage = Math.min(page, pageCount);
    const pageStart = filtered.length === 0 ? 0 : (currentPage - 1) * pageSize + 1;
    const pageEnd = Math.min(currentPage * pageSize, filtered.length);
    const paged = filtered.slice(
        (currentPage - 1) * pageSize,
        currentPage * pageSize,
    );

    function dismissWorkflow() {
        setWorkflowDismissed(true);
        try {
            window.localStorage.setItem(WORKFLOW_DISMISS_KEY, '1');
        } catch {
            // Ignore storage failures.
        }
    }

    return (
        <>
            <Head title="Controlled Forms" />
            <LimsWorkspace className="gap-2.5">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div className="min-w-0 max-w-2xl">
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Controlled Forms &amp; Blueprints
                        </h1>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Manage approved ISO/IEC 17025 laboratory templates,
                            configure PDF field coordinate bindings, and track
                            document lifecycles without redrawing forms.
                        </p>
                    </div>
                    <Button
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                        onClick={() => setOpen(true)}
                    >
                        <Plus className="size-4" />
                        Upload controlled form
                    </Button>
                </div>

                {!workflowDismissed ? (
                    <section className="rounded-lg border bg-white px-3 py-2.5">
                        <div className="mb-2 flex items-start justify-between gap-2 border-b pb-2">
                            <div className="min-w-0">
                                <p className="text-xs font-semibold text-slate-900">
                                    Standard controlled form deployment workflow
                                </p>
                                <p className="mt-0.5 text-[11px] text-muted-foreground">
                                    Compliant with ISO 17025 Section 8.3 — control
                                    of management system documents
                                </p>
                            </div>
                            <div className="flex shrink-0 items-center gap-2">
                                <span className="rounded border border-emerald-200 bg-emerald-50 px-2 py-0.5 text-[11px] font-medium text-emerald-800">
                                    Production ready
                                </span>
                                <Button
                                    type="button"
                                    size="sm"
                                    variant="ghost"
                                    className="size-7 px-0"
                                    aria-label="Dismiss workflow banner"
                                    onClick={dismissWorkflow}
                                >
                                    <X className="size-3.5" />
                                </Button>
                            </div>
                        </div>
                        <div className="grid gap-1.5 sm:grid-cols-2 xl:grid-cols-4">
                            {WORKFLOW_STEPS.map((step) => (
                                <div
                                    key={step.step}
                                    className={cn(
                                        'rounded-md border px-2.5 py-2',
                                        step.highlight
                                            ? 'border-emerald-200 bg-emerald-50/40'
                                            : 'border-slate-100 bg-slate-50/60',
                                    )}
                                >
                                    <div className="mb-1 flex items-center justify-between gap-2">
                                        <span
                                            className={cn(
                                                'font-mono text-[10px] font-semibold tracking-wider uppercase',
                                                step.highlight
                                                    ? 'text-emerald-700'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            Step {step.step}
                                        </span>
                                        <span
                                            className={cn(
                                                'flex size-5 items-center justify-center rounded-full text-[10px] font-bold',
                                                step.highlight
                                                    ? 'bg-emerald-600 text-white'
                                                    : 'bg-slate-200 text-slate-700',
                                            )}
                                        >
                                            {step.highlight ? (
                                                <Check
                                                    className="size-3"
                                                    aria-hidden="true"
                                                />
                                            ) : (
                                                step.step.replace(/^0/, '')
                                            )}
                                        </span>
                                    </div>
                                    <p
                                        className={cn(
                                            'text-xs font-semibold',
                                            step.highlight
                                                ? 'text-emerald-950'
                                                : 'text-slate-900',
                                        )}
                                    >
                                        {step.title}
                                    </p>
                                    <p
                                        className={cn(
                                            'mt-0.5 text-[11px] leading-relaxed',
                                            step.highlight
                                                ? 'text-emerald-800/80'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {step.description}
                                    </p>
                                </div>
                            ))}
                        </div>
                    </section>
                ) : null}

                <div className="space-y-2">
                    <div className="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                        <div className="relative max-w-md flex-1">
                            <Search className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                            <Input
                                className="h-8 pl-8 text-xs"
                                placeholder="Search form code, title, test category…"
                                value={query}
                                onChange={(event) => setQuery(event.target.value)}
                            />
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <select
                                className="h-8 rounded-md border bg-white px-2 text-xs text-slate-700"
                                value={statusFilter}
                                onChange={(event) =>
                                    setStatusFilter(event.target.value)
                                }
                                aria-label="Filter by status"
                            >
                                {STATUS_FILTERS.map((option) => (
                                    <option key={option.value} value={option.value}>
                                        {option.label}
                                    </option>
                                ))}
                            </select>
                            <select
                                className="h-8 rounded-md border bg-white px-2 text-xs text-slate-700"
                                value={categoryFilter}
                                onChange={(event) =>
                                    setCategoryFilter(event.target.value)
                                }
                                aria-label="Filter by category"
                            >
                                <option value="all">All divisions</option>
                                {categories.map((category) => (
                                    <option
                                        key={category.value}
                                        value={category.value}
                                    >
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                            <div className="flex items-center rounded-md border bg-slate-50 p-0.5">
                                <button
                                    type="button"
                                    title="Table view"
                                    className={cn(
                                        'rounded p-1.5',
                                        viewMode === 'table'
                                            ? 'bg-white text-[#1A3694] shadow-sm'
                                            : 'text-muted-foreground hover:text-slate-800',
                                    )}
                                    aria-pressed={viewMode === 'table'}
                                    onClick={() => setViewMode('table')}
                                >
                                    <List className="size-3.5" />
                                </button>
                                <button
                                    type="button"
                                    title="Grid view"
                                    className={cn(
                                        'rounded p-1.5',
                                        viewMode === 'grid'
                                            ? 'bg-white text-[#1A3694] shadow-sm'
                                            : 'text-muted-foreground hover:text-slate-800',
                                    )}
                                    aria-pressed={viewMode === 'grid'}
                                    onClick={() => setViewMode('grid')}
                                >
                                    <LayoutGrid className="size-3.5" />
                                </button>
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center justify-between gap-2 px-0.5 text-xs text-muted-foreground">
                        <p>
                            Showing{' '}
                            <span className="font-medium text-slate-900">
                                {filtered.length}
                            </span>{' '}
                            of {statusCounts.total} controlled form
                            {statusCounts.total === 1 ? '' : 's'}
                        </p>
                        <div className="flex items-center gap-3">
                            <span className="inline-flex items-center gap-1.5">
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 rounded-full bg-emerald-500"
                                />
                                {statusCounts.active} Active
                            </span>
                            <span className="inline-flex items-center gap-1.5">
                                <span
                                    aria-hidden="true"
                                    className="size-1.5 rounded-full bg-amber-500"
                                />
                                {statusCounts.pendingQa} Pending QA
                            </span>
                        </div>
                    </div>
                </div>

                {viewMode === 'table' ? (
                    <div className="overflow-hidden rounded-lg border bg-white">
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[720px] text-left text-xs">
                                <thead className="border-b bg-slate-50/80 text-muted-foreground">
                                    <tr>
                                        <th className="px-3 py-2 font-medium">
                                            Form code
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Form title &amp; department
                                        </th>
                                        <th className="px-3 py-2 text-center font-medium">
                                            Rev
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Effective date
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Status
                                        </th>
                                        <th className="px-3 py-2 font-medium">
                                            Last updated
                                        </th>
                                        <th className="px-3 py-2 text-right font-medium">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y">
                                    {paged.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="hover:bg-slate-50/70"
                                        >
                                            <td className="px-3 py-2.5 align-top whitespace-nowrap">
                                                <FormCodeChip code={item.form_code} />
                                            </td>
                                            <td className="px-3 py-2.5 align-top">
                                                <FormTitleCell item={item} />
                                            </td>
                                            <td className="px-3 py-2.5 text-center align-top">
                                                <span className="rounded bg-slate-100 px-1.5 py-0.5 font-mono text-[11px] text-slate-700">
                                                    {item.current_revision
                                                        ?.revision ?? '—'}
                                                </span>
                                            </td>
                                            <td className="px-3 py-2.5 align-top whitespace-nowrap text-slate-600">
                                                {item.current_revision
                                                    ?.effective_date ?? '—'}
                                            </td>
                                            <td className="px-3 py-2.5 align-top">
                                                <Badge
                                                    variant="outline"
                                                    className={statusBadgeClass(
                                                        item.status,
                                                    )}
                                                >
                                                    <span
                                                        aria-hidden="true"
                                                        className="mr-1"
                                                    >
                                                        ●
                                                    </span>
                                                    {item.status_label}
                                                </Badge>
                                            </td>
                                            <td className="px-3 py-2.5 align-top whitespace-nowrap text-muted-foreground">
                                                <div>
                                                    {item.updated_at
                                                        ? new Date(
                                                              item.updated_at,
                                                          ).toLocaleString()
                                                        : '—'}
                                                </div>
                                                {item.current_revision
                                                    ?.created_by ? (
                                                    <div className="text-[11px]">
                                                        by{' '}
                                                        {
                                                            item.current_revision
                                                                .created_by
                                                        }
                                                    </div>
                                                ) : null}
                                            </td>
                                            <td className="px-3 py-2.5 align-top">
                                                <FormRowActions item={item} />
                                            </td>
                                        </tr>
                                    ))}
                                    {paged.length === 0 ? (
                                        <tr>
                                            <td
                                                className="px-3 py-8 text-center text-muted-foreground"
                                                colSpan={7}
                                            >
                                                No controlled forms match these
                                                filters. Upload an official PDF to
                                                start, or clear the search.
                                            </td>
                                        </tr>
                                    ) : null}
                                </tbody>
                            </table>
                        </div>
                        <CatalogPagination
                            pageStart={pageStart}
                            pageEnd={pageEnd}
                            total={filtered.length}
                            pageSize={pageSize}
                            currentPage={currentPage}
                            pageCount={pageCount}
                            onPageSizeChange={setPageSize}
                            onPageChange={setPage}
                        />
                    </div>
                ) : (
                    <div className="space-y-2">
                        <div className="grid gap-2 sm:grid-cols-2 xl:grid-cols-3">
                            {paged.map((item) => (
                                <article
                                    key={item.id}
                                    className="rounded-lg border bg-white px-3 py-2.5"
                                >
                                    <div className="mb-2 flex items-start justify-between gap-2">
                                        <FormCodeChip code={item.form_code} />
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(
                                                item.status,
                                            )}
                                        >
                                            <span
                                                aria-hidden="true"
                                                className="mr-1"
                                            >
                                                ●
                                            </span>
                                            {item.status_label}
                                        </Badge>
                                    </div>
                                    <FormTitleCell item={item} />
                                    <dl className="mt-2 grid grid-cols-2 gap-x-2 gap-y-1 text-[11px] text-muted-foreground">
                                        <div>
                                            <dt className="uppercase tracking-wide">
                                                Rev
                                            </dt>
                                            <dd className="font-mono text-slate-800">
                                                {item.current_revision?.revision ??
                                                    '—'}
                                            </dd>
                                        </div>
                                        <div>
                                            <dt className="uppercase tracking-wide">
                                                Effective
                                            </dt>
                                            <dd className="text-slate-800">
                                                {item.current_revision
                                                    ?.effective_date ?? '—'}
                                            </dd>
                                        </div>
                                    </dl>
                                    <div className="mt-2 border-t pt-2">
                                        <FormRowActions item={item} />
                                    </div>
                                </article>
                            ))}
                        </div>
                        {paged.length === 0 ? (
                            <p className="rounded-lg border bg-white px-3 py-8 text-center text-sm text-muted-foreground">
                                No controlled forms match these filters.
                            </p>
                        ) : null}
                        <div className="overflow-hidden rounded-lg border bg-white">
                            <CatalogPagination
                                pageStart={pageStart}
                                pageEnd={pageEnd}
                                total={filtered.length}
                                pageSize={pageSize}
                                currentPage={currentPage}
                                pageCount={pageCount}
                                onPageSizeChange={setPageSize}
                                onPageChange={setPage}
                            />
                        </div>
                    </div>
                )}
            </LimsWorkspace>

            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent className="flex max-h-[90vh] flex-col overflow-hidden sm:max-w-2xl">
                    <DialogHeader>
                        <DialogTitle>Upload Controlled Form</DialogTitle>
                        <DialogDescription>
                            Choose the form type first. For analyst result
                            sheets, tick the exact tests this PDF is for, then
                            upload the file.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid min-h-0 flex-1 gap-3 overflow-y-auto pr-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            form.post('/admin/controlled-forms', {
                                forceFormData: true,
                                onSuccess: () => setOpen(false),
                            });
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="category">Category</Label>
                            <select
                                id="category"
                                className="h-9 rounded-md border px-3 text-sm"
                                value={form.data.category}
                                onChange={(event) => {
                                    const category = event.target.value;
                                    form.setData({
                                        ...form.data,
                                        category,
                                        job_order_variant:
                                            category === 'job_order'
                                                ? form.data.job_order_variant ||
                                                  'general'
                                                : '',
                                        analysis_type_ids:
                                            category === 'analysis_result'
                                                ? form.data.analysis_type_ids
                                                : [],
                                        analysis_package_id:
                                            category === 'analysis_result'
                                                ? form.data.analysis_package_id
                                                : '',
                                    });
                                }}
                            >
                                {categories.map((category) => (
                                    <option key={category.value} value={category.value}>
                                        {category.label}
                                    </option>
                                ))}
                            </select>
                            <p className="text-xs text-muted-foreground">
                                Job Order is the Request for Analysis. Use
                                General (FO1) or Aqua (FO4). Analysis Result is
                                the combined analyst PDF. Choose a package
                                (recommended) or tick individual tests.
                            </p>
                        </div>
                        {form.data.category === 'job_order' && (
                            <div className="grid gap-2">
                                <Label htmlFor="job_order_variant">
                                    Job Order variant
                                </Label>
                                <select
                                    id="job_order_variant"
                                    className="h-9 rounded-md border px-3 text-sm"
                                    value={form.data.job_order_variant}
                                    onChange={(event) => {
                                        const variant = event.target.value;
                                        form.setData({
                                            ...form.data,
                                            job_order_variant: variant,
                                            form_code:
                                                variant === 'aqua'
                                                    ? 'NPPC-LAB-FRM-AQUA'
                                                    : 'NPPC-LAB-FRM-001',
                                            name:
                                                variant === 'aqua'
                                                    ? 'Request for Analysis Form / Job Order (Aqua)'
                                                    : 'Request for Analysis Form / Job Order',
                                            revision:
                                                variant === 'aqua' ? '03' : '11',
                                        });
                                    }}
                                >
                                    <option value="general">
                                        General (LSP 7.1 FO1)
                                    </option>
                                    <option value="aqua">
                                        Aqua (LSP 7.1 FO4)
                                    </option>
                                </select>
                            </div>
                        )}
                        {form.data.category === 'analysis_result' && (
                            <>
                                <PackageSelect
                                    packages={packages}
                                    value={form.data.analysis_package_id}
                                    onChange={(packageId, typeIds) => {
                                        form.setData({
                                            ...form.data,
                                            analysis_package_id: packageId ?? '',
                                            analysis_type_ids: packageId
                                                ? typeIds
                                                : form.data.analysis_type_ids,
                                        });
                                    }}
                                />
                                <AnalysisTypePicker
                                    groups={analysisGroups}
                                    selectedIds={form.data.analysis_type_ids}
                                    onChange={(ids) =>
                                        form.setData('analysis_type_ids', ids)
                                    }
                                    error={form.errors.analysis_type_ids}
                                    className={
                                        form.data.analysis_package_id
                                            ? 'pointer-events-none opacity-60'
                                            : undefined
                                    }
                                />
                            </>
                        )}
                        <div className="rounded-xl border bg-[#f8fafc] p-4">
                            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                Document info
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Upload the approved source file first. Supported:
                                PDF, DOC, DOCX.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="file">Choose file</Label>
                            <Input
                                id="file"
                                type="file"
                                accept=".pdf,.doc,.docx,application/pdf"
                                onChange={(event) =>
                                    form.setData('file', event.target.files?.[0] ?? null)
                                }
                            />
                            {form.data.file && (
                                <p className="text-xs text-muted-foreground">
                                    Selected file: {form.data.file.name}
                                </p>
                            )}
                            {form.errors.file && (
                                <p className="text-sm text-red-600">{form.errors.file}</p>
                            )}
                        </div>
                        <div className="rounded-xl border bg-[#f8fafc] p-4">
                            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                Revision info
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Use this to identify which revision is being
                                designed and approved.
                            </p>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="form_code">Form Code</Label>
                            <Input
                                id="form_code"
                                value={form.data.form_code}
                                onChange={(event) =>
                                    form.setData('form_code', event.target.value)
                                }
                            />
                            {form.errors.form_code && (
                                <p className="text-sm text-red-600">
                                    {form.errors.form_code}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Form Name</Label>
                            <Input
                                id="name"
                                value={form.data.name}
                                onChange={(event) => form.setData('name', event.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="revision">Revision</Label>
                            <Input
                                id="revision"
                                value={form.data.revision}
                                onChange={(event) =>
                                    form.setData('revision', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="effective_date">Effective Date</Label>
                            <Input
                                id="effective_date"
                                type="date"
                                value={form.data.effective_date}
                                onChange={(event) =>
                                    form.setData('effective_date', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="department">Department</Label>
                            <Input
                                id="department"
                                value={form.data.department}
                                onChange={(event) =>
                                    form.setData('department', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="description">Description</Label>
                            <Textarea
                                id="description"
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData('description', event.target.value)
                                }
                            />
                        </div>
                        <div className="rounded-xl border bg-[#f8fafc] p-4">
                            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                Activation
                            </p>
                            <label className="mt-2 flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    checked={form.data.activate}
                                    onChange={(event) =>
                                        form.setData('activate', event.target.checked)
                                    }
                                />
                                Activate this revision after upload
                            </label>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Recommended flow: save draft, map fields in the
                                designer, preview, then activate.
                            </p>
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save as Draft'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

function CatalogPagination({
    pageStart,
    pageEnd,
    total,
    pageSize,
    currentPage,
    pageCount,
    onPageSizeChange,
    onPageChange,
}: {
    pageStart: number;
    pageEnd: number;
    total: number;
    pageSize: number;
    currentPage: number;
    pageCount: number;
    onPageSizeChange: (size: number) => void;
    onPageChange: (page: number) => void;
}) {
    return (
        <div className="flex flex-col gap-2 border-t bg-slate-50/50 px-3 py-2 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between">
            <div className="flex flex-wrap items-center gap-3">
                <span>
                    Showing{' '}
                    <span className="font-medium text-slate-800">{pageStart}</span> to{' '}
                    <span className="font-medium text-slate-800">{pageEnd}</span> of{' '}
                    <span className="font-medium text-slate-800">{total}</span> forms
                </span>
                <label className="inline-flex items-center gap-1.5">
                    Rows per page:
                    <select
                        className="h-7 rounded border bg-white px-1.5 text-xs text-slate-700"
                        value={pageSize}
                        onChange={(event) =>
                            onPageSizeChange(Number(event.target.value))
                        }
                    >
                        <option value={10}>10</option>
                        <option value={25}>25</option>
                        <option value={50}>50</option>
                    </select>
                </label>
            </div>
            <div className="flex items-center gap-1">
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={currentPage <= 1}
                    onClick={() => onPageChange(currentPage - 1)}
                >
                    Previous
                </Button>
                <span className="min-w-8 rounded bg-[#1A3694] px-2 py-1 text-center font-medium text-white">
                    {currentPage}
                </span>
                <Button
                    type="button"
                    size="sm"
                    variant="outline"
                    disabled={currentPage >= pageCount}
                    onClick={() => onPageChange(currentPage + 1)}
                >
                    Next
                </Button>
            </div>
        </div>
    );
}

ControlledFormsIndex.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Controlled Forms', href: '/admin/controlled-forms' },
    ],
};
