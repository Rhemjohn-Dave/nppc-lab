import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
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
    form: ControlledFormSummary;
    analysisGroups: AnalysisGroup[];
    packages?: AnalysisPackageOption[];
};

const transitions: Record<string, Array<{ status: string; label: string }>> = {
    draft: [
        { status: 'for_review', label: 'Submit for review' },
        { status: 'active', label: 'Activate' },
        { status: 'archived', label: 'Archive' },
    ],
    for_review: [
        { status: 'for_approval', label: 'Send for approval' },
        { status: 'draft', label: 'Return to draft' },
    ],
    for_approval: [
        { status: 'approved', label: 'Approve' },
        { status: 'for_review', label: 'Return to review' },
    ],
    approved: [{ status: 'active', label: 'Activate' }],
    active: [{ status: 'archived', label: 'Archive' }],
    superseded: [{ status: 'archived', label: 'Archive' }],
};

export default function ControlledFormShow({
    form,
    analysisGroups,
    packages = [],
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string; error?: string } };
    const [revisionOpen, setRevisionOpen] = useState(false);
    const revisionForm = useForm({
        revision: '',
        effective_date: '',
        notes: '',
        file: null as File | null,
        activate: false,
    });
    const bindForm = useForm({
        name: form.name,
        description: form.description ?? '',
        department: form.department ?? '',
        analysis_type_ids: form.analysis_type_ids,
        analysis_package_id: form.analysis_package_id ?? ('' as number | ''),
    });

    return (
        <>
            <Head title={form.form_code} />
            <div className="flex flex-col gap-5 p-4">
                <div className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="font-mono text-xs text-muted-foreground">
                            {form.form_code}
                        </p>
                        <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                            {form.name}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {form.department ?? 'Laboratory'} · {form.category_label}
                        </p>
                        {flash?.success && (
                            <p className="mt-2 text-sm text-emerald-700">{flash.success}</p>
                        )}
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <Link href="/admin/controlled-forms">Back to catalog</Link>
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            onClick={() => setRevisionOpen(true)}
                        >
                            New revision
                        </Button>
                    </div>
                </div>

                {form.description && (
                    <p className="max-w-3xl text-sm text-muted-foreground">{form.description}</p>
                )}

                <div className="grid gap-3 lg:grid-cols-5">
                    {[
                        ['Draft', 'Upload or replace the source file and start mapping fields.'],
                        ['For review', 'Internal review before formal approval.'],
                        ['For approval', 'Final review before activation.'],
                        ['Approved', 'Ready to activate when the layout is confirmed.'],
                        ['Active', 'Live revision used in operations.'],
                    ].map(([title, description]) => {
                        const active = form.current_revision?.status_label
                            ?.toLowerCase()
                            .includes(title.toLowerCase());

                        return (
                            <div
                                key={title}
                                className={
                                    active
                                        ? 'rounded-xl border border-[#1A3694]/30 bg-[#eef3fb] p-4'
                                        : 'rounded-xl border bg-white p-4'
                                }
                            >
                                <p className="text-sm font-medium text-slate-900">
                                    {title}
                                </p>
                                <p className="mt-1 text-xs text-muted-foreground">
                                    {description}
                                </p>
                            </div>
                        );
                    })}
                </div>

                {form.category === 'analysis_result' && (
                    <div className="rounded-xl border bg-white p-4">
                        <h2 className="font-heading text-lg font-semibold text-[#1A3694]">
                            Analyst result binding
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Combined PDF preview is offered only when a job’s
                            selected tests match this exact set. Pick a package
                            so the designer shows named result boxes, then map
                            fields and activate the revision.
                        </p>
                        <form
                            className="mt-4 grid gap-3"
                            onSubmit={(event) => {
                                event.preventDefault();
                                bindForm.put(
                                    `/admin/controlled-forms/${form.id}`,
                                );
                            }}
                        >
                            <PackageSelect
                                packages={packages}
                                value={bindForm.data.analysis_package_id}
                                onChange={(packageId, typeIds) => {
                                    bindForm.setData({
                                        ...bindForm.data,
                                        analysis_package_id: packageId ?? '',
                                        analysis_type_ids: packageId
                                            ? typeIds
                                            : bindForm.data.analysis_type_ids,
                                    });
                                }}
                            />
                            <AnalysisTypePicker
                                groups={analysisGroups}
                                selectedIds={bindForm.data.analysis_type_ids}
                                onChange={(ids) => {
                                    bindForm.setData({
                                        ...bindForm.data,
                                        analysis_type_ids: ids,
                                        analysis_package_id: '',
                                    });
                                }}
                                error={bindForm.errors.analysis_type_ids}
                                className={
                                    bindForm.data.analysis_package_id
                                        ? 'pointer-events-none opacity-60'
                                        : undefined
                                }
                            />
                            <div>
                                <Button
                                    type="submit"
                                    disabled={bindForm.processing}
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                >
                                    {bindForm.processing
                                        ? 'Saving…'
                                        : 'Save bound tests'}
                                </Button>
                            </div>
                        </form>
                    </div>
                )}

                <div className="overflow-x-auto rounded-xl border">
                    <table className="w-full min-w-[800px] text-sm">
                        <thead className="bg-[#e8eef8] text-left text-[#1A3694]">
                            <tr>
                                <th className="px-3 py-2 font-medium">Revision</th>
                                <th className="px-3 py-2 font-medium">Status</th>
                                <th className="px-3 py-2 font-medium">Effective</th>
                                <th className="px-3 py-2 font-medium">Fields</th>
                                <th className="px-3 py-2 font-medium">SHA-256</th>
                                <th className="px-3 py-2 font-medium">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {(form.revisions ?? []).map((revision) => (
                                <tr key={revision.id} className="border-t align-top">
                                    <td className="px-3 py-2 font-mono">{revision.revision}</td>
                                    <td className="px-3 py-2">
                                        <Badge
                                            variant="outline"
                                            className={statusBadgeClass(revision.status)}
                                        >
                                            {revision.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-3 py-2">{revision.effective_date ?? '—'}</td>
                                    <td className="px-3 py-2">{revision.field_count}</td>
                                    <td className="px-3 py-2 font-mono text-[10px] break-all">
                                        {revision.sha256 ?? '—'}
                                    </td>
                                    <td className="px-3 py-2">
                                        <div className="flex flex-wrap gap-1">
                                            <Button size="sm" variant="outline" asChild>
                                                <Link
                                                    href={`/admin/controlled-forms/${form.id}/revisions/${revision.id}/designer`}
                                                >
                                                    Designer
                                                </Link>
                                            </Button>
                                            {revision.has_canonical && (
                                                <Button size="sm" variant="outline" asChild>
                                                    <a
                                                        href={`/admin/controlled-forms/${form.id}/revisions/${revision.id}/canonical`}
                                                    >
                                                        Canonical PDF
                                                    </a>
                                                </Button>
                                            )}
                                            {(transitions[revision.status] ?? []).map((action) => (
                                                <Button
                                                    key={action.status}
                                                    size="sm"
                                                    variant={
                                                        action.status === 'active'
                                                            ? 'default'
                                                            : 'outline'
                                                    }
                                                    onClick={() => {
                                                        const needsConfirm =
                                                            action.status ===
                                                                'active' ||
                                                            action.status ===
                                                                'archived';
                                                        if (
                                                            needsConfirm &&
                                                            !window.confirm(
                                                                action.status ===
                                                                    'active'
                                                                    ? `Activate revision ${revision.revision}? This becomes the live document used in operations.`
                                                                    : `Archive revision ${revision.revision}? It will no longer be used for new documents.`,
                                                            )
                                                        ) {
                                                            return;
                                                        }

                                                        router.post(
                                                            `/admin/controlled-forms/${form.id}/revisions/${revision.id}/transition`,
                                                            { status: action.status },
                                                        );
                                                    }}
                                                >
                                                    {action.label}
                                                </Button>
                                            ))}
                                        </div>
                                        <p className="mt-2 max-w-sm text-xs text-muted-foreground">
                                            {revision.has_canonical
                                                ? 'PDF attached and ready for designer/preview use.'
                                                : 'Upload the official file before designing this revision.'}
                                        </p>
                                        {revision.status === 'superseded' && (
                                            <p className="mt-2 max-w-sm text-xs text-amber-800">
                                                CONTROLLED DOCUMENT WARNING: this revision is no
                                                longer active. Historical documents remain
                                                accessible.
                                            </p>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog open={revisionOpen} onOpenChange={setRevisionOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Create revision</DialogTitle>
                    </DialogHeader>
                    <form
                        className="grid gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();
                            revisionForm.post(
                                `/admin/controlled-forms/${form.id}/revisions`,
                                { forceFormData: true, onSuccess: () => setRevisionOpen(false) },
                            );
                        }}
                    >
                        <div className="grid gap-2">
                            <Label>Revision</Label>
                            <Input
                                value={revisionForm.data.revision}
                                placeholder="Leave blank to auto-number"
                                onChange={(event) =>
                                    revisionForm.setData('revision', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Effective date</Label>
                            <Input
                                type="date"
                                value={revisionForm.data.effective_date}
                                onChange={(event) =>
                                    revisionForm.setData('effective_date', event.target.value)
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>File (optional — copies previous PDF if omitted)</Label>
                            <Input
                                type="file"
                                accept=".pdf,.doc,.docx,application/pdf"
                                onChange={(event) =>
                                    revisionForm.setData(
                                        'file',
                                        event.target.files?.[0] ?? null,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Notes</Label>
                            <Textarea
                                value={revisionForm.data.notes}
                                onChange={(event) =>
                                    revisionForm.setData('notes', event.target.value)
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button type="submit" disabled={revisionForm.processing}>
                                Create revision
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </>
    );
}

ControlledFormShow.layout = {
    breadcrumbs: [
        { title: 'Controlled Forms', href: '/admin/controlled-forms' },
        { title: 'Details', href: '#' },
    ],
};
