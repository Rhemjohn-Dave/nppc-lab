import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus } from 'lucide-react';
import { useState } from 'react';
import AnalysisTypePicker from '@/components/analysis-type-picker';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import type { AnalysisGroup } from '@/lib/controlled-forms';

type PackageRow = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    category_id: number | null;
    category_label: string | null;
    default_price: string | number;
    classifications: string[];
    form_code: string | null;
    result_form: {
        id: number;
        form_code: string;
        name: string;
    } | null;
    signatory_user_id: number | null;
    signatory_name: string | null;
    is_active: boolean;
    analysis_type_ids: number[];
    tests: Array<{ id: number; code: string; name: string }>;
};

type Props = {
    packages: PackageRow[];
    categories: Array<{ id: number; label: string }>;
    classifications: string[];
    analysisGroups: AnalysisGroup[];
    analysts?: Array<{ id: number; name: string; email: string }>;
};

const emptyForm = {
    code: '',
    name: '',
    description: '',
    category_id: '' as number | '',
    default_price: '0',
    classifications: [] as string[],
    signatory_user_id: '' as number | '',
    is_active: true,
    analysis_type_ids: [] as number[],
};

export default function AdminPackages({
    packages,
    categories,
    classifications,
    analysisGroups,
    analysts = [],
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };
    const [open, setOpen] = useState(false);
    const [editing, setEditing] = useState<PackageRow | null>(null);
    const form = useForm(emptyForm);

    function openCreate() {
        setEditing(null);
        form.reset();
        form.setData(emptyForm);
        setOpen(true);
    }

    function openEdit(row: PackageRow) {
        setEditing(row);
        form.setData({
            code: row.code,
            name: row.name,
            description: row.description ?? '',
            category_id: row.category_id ?? '',
            default_price: String(row.default_price),
            classifications: row.classifications,
            signatory_user_id: row.signatory_user_id ?? '',
            is_active: row.is_active,
            analysis_type_ids: row.analysis_type_ids,
        });
        setOpen(true);
    }

    function submit() {
        form.setData(
            'category_id',
            form.data.category_id === '' ? '' : form.data.category_id,
        );

        const options = {
            onSuccess: () => setOpen(false),
        };

        if (editing) {
            form.put(`/admin/packages/${editing.id}`, options);
            return;
        }

        form.post('/admin/packages', options);
    }

    function toggleClassification(tag: string) {
        form.setData(
            'classifications',
            form.data.classifications.includes(tag)
                ? form.data.classifications.filter((item) => item !== tag)
                : [...form.data.classifications, tag],
        );
    }

    return (
        <>
            <Head title="Analysis packages" />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            Analysis packages
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Packages appear on the kiosk as one tap and expand
                            into individual tests for analysts.
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">
                                {flash.success}
                            </p>
                        )}
                    </div>
                    <Button
                        className="bg-[#1A3694] hover:bg-[#365BB0]"
                        onClick={openCreate}
                    >
                        <Plus className="size-4" />
                        New package
                    </Button>
                </div>

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[720px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">Code</th>
                                <th className="px-3 py-2 font-medium">Name</th>
                                <th className="px-3 py-2 font-medium">Tests</th>
                                <th className="px-3 py-2 font-medium">Price</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {packages.map((row) => (
                                <tr key={row.id} className="border-t">
                                    <td className="px-3 py-2 font-mono text-xs">
                                        {row.code}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="font-medium">{row.name}</div>
                                        <div className="text-xs text-muted-foreground">
                                            {(row.classifications ?? []).join(', ') ||
                                                'All classifications'}
                                            {row.result_form
                                                ? ` · ${row.result_form.form_code}`
                                                : ' · Result form not linked'}
                                            {row.signatory_name
                                                ? ` · Signatory ${row.signatory_name}`
                                                : ''}
                                        </div>
                                    </td>
                                    <td className="px-3 py-2 text-xs">
                                        {row.tests.map((test) => test.name).join(', ')}
                                    </td>
                                    <td className="px-3 py-2">
                                        ₱{Number(row.default_price).toFixed(2)}
                                    </td>
                                    <td className="px-3 py-2">
                                        <Badge variant="outline">
                                            {row.is_active ? 'Active' : 'Inactive'}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => openEdit(row)}
                                        >
                                            Edit
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {packages.length === 0 && (
                                <tr>
                                    <td
                                        className="px-3 py-8 text-center text-muted-foreground"
                                        colSpan={6}
                                    >
                                        No packages yet. Create one to offer a
                                        bundle on the kiosk.
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
                        <DialogTitle>
                            {editing ? 'Edit package' : 'New package'}
                        </DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid min-h-0 flex-1 gap-3 overflow-y-auto pr-1"
                        onSubmit={(event) => {
                            event.preventDefault();
                            submit();
                        }}
                    >
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Code</Label>
                                <Input
                                    value={form.data.code}
                                    onChange={(event) =>
                                        form.setData('code', event.target.value)
                                    }
                                />
                                {form.errors.code && (
                                    <p className="text-sm text-red-600">{form.errors.code}</p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Price</Label>
                                <Input
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    value={form.data.default_price}
                                    onChange={(event) =>
                                        form.setData('default_price', event.target.value)
                                    }
                                />
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Name</Label>
                            <Input
                                value={form.data.name}
                                onChange={(event) =>
                                    form.setData('name', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Description</Label>
                            <Textarea
                                value={form.data.description}
                                onChange={(event) =>
                                    form.setData('description', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="grid gap-2">
                                <Label>Category</Label>
                                <select
                                    className="h-9 rounded-md border px-3 text-sm"
                                    value={form.data.category_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'category_id',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                >
                                    <option value="">None</option>
                                    {categories.map((category) => (
                                        <option key={category.id} value={category.id}>
                                            {category.label}
                                        </option>
                                    ))}
                                </select>
                            </div>
                            <div className="grid gap-2">
                                <Label>Result controlled form</Label>
                                <p className="rounded-md border bg-muted/40 px-3 py-2 text-sm text-muted-foreground">
                                    {editing?.result_form
                                        ? `${editing.result_form.form_code} — ${editing.result_form.name}`
                                        : 'Not linked. Bind this package on the Analysis Result controlled form in Document Control.'}
                                </p>
                            </div>
                            <div className="grid gap-2">
                                <Label>Designated analyst (signatory)</Label>
                                <select
                                    className="h-9 rounded-md border px-3 text-sm"
                                    value={form.data.signatory_user_id}
                                    onChange={(event) =>
                                        form.setData(
                                            'signatory_user_id',
                                            event.target.value === ''
                                                ? ''
                                                : Number(event.target.value),
                                        )
                                    }
                                >
                                    <option value="">None</option>
                                    {analysts.map((analyst) => (
                                        <option key={analyst.id} value={analyst.id}>
                                            {analyst.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.signatory_user_id && (
                                    <p className="text-sm text-red-600">
                                        {form.errors.signatory_user_id}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label>Suggest for classifications</Label>
                            <div className="flex flex-wrap gap-3">
                                {classifications.map((tag) => (
                                    <label
                                        key={tag}
                                        className="flex items-center gap-2 text-sm"
                                    >
                                        <Checkbox
                                            checked={form.data.classifications.includes(
                                                tag,
                                            )}
                                            onCheckedChange={() =>
                                                toggleClassification(tag)
                                            }
                                        />
                                        {tag}
                                    </label>
                                ))}
                            </div>
                        </div>
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                checked={form.data.is_active}
                                onCheckedChange={(checked) =>
                                    form.setData('is_active', checked === true)
                                }
                            />
                            Active on kiosk
                        </label>
                        <AnalysisTypePicker
                            groups={analysisGroups}
                            selectedIds={form.data.analysis_type_ids}
                            onChange={(ids) =>
                                form.setData('analysis_type_ids', ids)
                            }
                            error={form.errors.analysis_type_ids}
                            heading="Member tests"
                            hint={`${form.data.analysis_type_ids.length} selected · kiosk expands this package into these lines`}
                        />
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {form.processing ? 'Saving…' : 'Save package'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminPackages.layout = {
    breadcrumbs: [
        { title: 'Admin', href: '/admin/prices' },
        { title: 'Packages', href: '/admin/packages' },
    ],
};
