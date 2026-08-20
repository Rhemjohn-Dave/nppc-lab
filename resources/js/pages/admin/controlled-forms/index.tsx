import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { Plus, Search } from 'lucide-react';
import { useState } from 'react';
import AnalysisTypePicker from '@/components/analysis-type-picker';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    statusBadgeClass,
    type AnalysisGroup,
    type ControlledFormSummary,
} from '@/lib/controlled-forms';

type Props = {
    forms: ControlledFormSummary[];
    categories: Array<{ value: string; label: string }>;
    analysisGroups: AnalysisGroup[];
    packages?: AnalysisPackageOption[];
};

export default function ControlledFormsIndex({
    forms,
    categories,
    analysisGroups = [],
    packages = [],
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');

    const form = useForm({
        form_code: 'NPPC-LAB-FRM-001',
        name: 'Request for Analysis Form / Job Order',
        description: '',
        department: 'Laboratory',
        category: 'job_order',
        revision: '03',
        effective_date: '',
        notes: '',
        file: null as File | null,
        activate: false as boolean,
        analysis_type_ids: [] as number[],
        analysis_package_id: '' as number | '',
    });

    const filtered = forms.filter((item) => {
        const haystack = `${item.form_code} ${item.name} ${item.status_label}`.toLowerCase();

        return haystack.includes(query.toLowerCase());
    });

    return (
        <>
            <Head title="Controlled Forms" />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Document Control
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Upload approved PDFs, map database fields, and
                            generate official laboratory documents without
                            redrawing the form.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                    <Button
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                        onClick={() => setOpen(true)}
                    >
                        <Plus className="size-4" />
                        Upload controlled form
                    </Button>
                </div>

                <div className="grid gap-3 lg:grid-cols-4">
                    {[
                        ['1', 'Upload', 'Add the approved PDF or Word file.'],
                        ['2', 'Map', 'Place fields in the designer on top of the PDF.'],
                        ['3', 'Preview', 'Check sample data before going live.'],
                        ['4', 'Activate', 'Use the approved revision in operations.'],
                    ].map(([step, title, description]) => (
                        <div
                            key={step}
                            className="rounded-xl border bg-white p-4"
                        >
                            <p className="text-xs font-semibold tracking-wide text-[#365BB0] uppercase">
                                Step {step}
                            </p>
                            <p className="mt-2 font-medium text-slate-900">
                                {title}
                            </p>
                            <p className="mt-1 text-sm text-muted-foreground">
                                {description}
                            </p>
                        </div>
                    ))}
                </div>

                <div className="relative max-w-md">
                    <Search className="absolute top-2.5 left-2.5 size-4 text-muted-foreground" />
                    <Input
                        className="pl-8"
                        placeholder="Search form code, name, or status"
                        value={query}
                        onChange={(event) => setQuery(event.target.value)}
                    />
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">Form Code</th>
                                <th className="px-3 py-2 font-medium">Form Name</th>
                                <th className="px-3 py-2 font-medium">Revision</th>
                                <th className="px-3 py-2 font-medium">Effective</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Updated</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {filtered.map((item) => (
                                <tr key={item.id} className="border-t">
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {item.form_code}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="font-medium">{item.name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {item.category_label}
                                            {item.department ? ` · ${item.department}` : ''}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.current_revision?.revision ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        {item.current_revision?.effective_date ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(item.status)}
                                        >
                                            {item.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2 text-xs text-muted-foreground">
                                        {item.updated_at
                                            ? new Date(item.updated_at).toLocaleString()
                                            : '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-2">
                                            <Button variant="outline" size="sm" asChild>
                                                <Link
                                                    href={`/admin/controlled-forms/${item.id}`}
                                                >
                                                    Open
                                                </Link>
                                            </Button>
                                            {item.current_revision && (
                                                <Button variant="outline" size="sm" asChild>
                                                    <Link
                                                        href={`/admin/controlled-forms/${item.id}/revisions/${item.current_revision.id}/designer`}
                                                    >
                                                        Designer
                                                    </Link>
                                                </Button>
                                            )}
                                        </div>
                                    </td>
                                </tr>
                            ))}
                            {filtered.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={7}
                                    >
                                        No controlled forms yet. Upload the official
                                        Request for Analysis PDF to start.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

            </div>

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
                                Job Order is the Request for Analysis. Analysis
                                Result is the combined analyst PDF. Choose a
                                package (recommended) or tick individual tests.
                            </p>
                        </div>
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

ControlledFormsIndex.layout = {
    breadcrumbs: [
        { title: 'Document Control', href: '/admin/controlled-forms' },
        { title: 'Controlled Forms', href: '/admin/controlled-forms' },
    ],
};
