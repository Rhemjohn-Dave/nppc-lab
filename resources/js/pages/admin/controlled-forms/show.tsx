import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import AnalystBindingSheet from '@/components/controlled-forms/analyst-binding-sheet';
import ControlledFormHeader from '@/components/controlled-forms/controlled-form-header';
import CurrentRevisionCard from '@/components/controlled-forms/current-revision-card';
import FormInformationCard from '@/components/controlled-forms/form-information-card';
import FormUsageCard from '@/components/controlled-forms/form-usage-card';
import RevisionHistoryList, {
    type RevisionTransitionAction,
} from '@/components/controlled-forms/revision-history-list';
import RevisionStatusSummary from '@/components/controlled-forms/revision-status-summary';
import RevisionWorkflow from '@/components/controlled-forms/revision-workflow';
import SourceFileLayoutCard from '@/components/controlled-forms/source-file-layout-card';
import LimsWorkspace from '@/components/lims/lims-workspace';
import type { AnalysisPackageOption } from '@/components/package-select';
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
import type {
    AnalysisGroup,
    ControlledFormSummary,
    ControlledRevisionSummary,
} from '@/lib/controlled-forms';

type Props = {
    form: ControlledFormSummary;
    analysisGroups: AnalysisGroup[];
    packages?: AnalysisPackageOption[];
};

const transitions: Record<string, RevisionTransitionAction[]> = {
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
    const [revisionOpen, setRevisionOpen] = useState(false);
    const [editingInfo, setEditingInfo] = useState(false);
    const [bindingOpen, setBindingOpen] = useState(false);
    const [pendingTransition, setPendingTransition] = useState<{
        revisionId: number;
        revisionLabel: string;
        status: string;
        label: string;
    } | null>(null);
    const [transitioning, setTransitioning] = useState(false);

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
        analyst_signatory_slots: form.analyst_signatory_slots ?? 1,
        analyst_require_prc: Boolean(form.analyst_require_prc),
    });

    const current = form.current_revision;

    const boundPackage =
        form.analysis_package_id != null
            ? packages.find((item) => item.id === form.analysis_package_id)
            : null;
    const bindingSummary = boundPackage
        ? `Package: ${boundPackage.name}`
        : form.analysis_type_ids.length > 0
          ? `${form.analysis_type_ids.length} analysis type${form.analysis_type_ids.length === 1 ? '' : 's'} bound`
          : 'Not bound — click to configure';

    function resetBindingFields() {
        bindForm.setData({
            ...bindForm.data,
            analysis_type_ids: form.analysis_type_ids,
            analysis_package_id: form.analysis_package_id ?? '',
            analyst_signatory_slots: form.analyst_signatory_slots ?? 1,
            analyst_require_prc: Boolean(form.analyst_require_prc),
        });
        bindForm.clearErrors('analysis_type_ids');
    }

    function handleBindingOpenChange(open: boolean) {
        if (!open && !bindForm.processing) {
            resetBindingFields();
        }
        setBindingOpen(open);
    }

    function handleTransition(
        revision: ControlledRevisionSummary,
        action: RevisionTransitionAction,
    ) {
        const needsConfirm =
            action.status === 'active' || action.status === 'archived';
        if (needsConfirm) {
            setPendingTransition({
                revisionId: revision.id,
                revisionLabel: revision.revision,
                status: action.status,
                label: action.label,
            });
            return;
        }

        router.post(
            `/admin/controlled-forms/${form.id}/revisions/${revision.id}/transition`,
            { status: action.status },
        );
    }

    return (
        <>
            <Head title={form.form_code} />
            <LimsWorkspace className="gap-2.5">
                <ControlledFormHeader
                    formCode={form.form_code}
                    name={bindForm.data.name || form.name}
                    department={bindForm.data.department || form.department}
                    categoryLabel={form.category_label}
                    status={form.status}
                    statusLabel={form.status_label}
                    onNewRevision={() => setRevisionOpen(true)}
                />

                <RevisionStatusSummary
                    revision={current?.revision ?? null}
                    status={current?.status ?? form.status}
                    statusLabel={current?.status_label ?? form.status_label}
                    effectiveDate={current?.effective_date ?? null}
                />

                <RevisionWorkflow currentStatus={current?.status ?? form.status} />

                <div className="grid min-w-0 gap-2.5 lg:grid-cols-2">
                    <FormInformationCard
                        editing={editingInfo}
                        data={{
                            name: bindForm.data.name,
                            department: bindForm.data.department,
                            description: bindForm.data.description,
                        }}
                        errors={{
                            name: bindForm.errors.name,
                        }}
                        processing={bindForm.processing}
                        onEdit={() => setEditingInfo(true)}
                        onCancel={() => {
                            bindForm.setData({
                                ...bindForm.data,
                                name: form.name,
                                department: form.department ?? '',
                                description: form.description ?? '',
                            });
                            setEditingInfo(false);
                        }}
                        onChange={(field, value) => bindForm.setData(field, value)}
                        onSave={() => {
                            bindForm.put(`/admin/controlled-forms/${form.id}`, {
                                onSuccess: () => setEditingInfo(false),
                            });
                        }}
                    />
                    <CurrentRevisionCard
                        formCode={form.form_code}
                        updatedAt={form.updated_at}
                        revision={current}
                    />
                </div>

                <div className="grid min-w-0 gap-2.5 lg:grid-cols-2">
                    <SourceFileLayoutCard
                        formId={form.id}
                        hasBlueprint={Boolean(form.has_blueprint)}
                        revision={current}
                    />
                    <FormUsageCard
                        packages={packages}
                        analysisPackageId={form.analysis_package_id}
                        analysisTypes={form.analysis_types ?? []}
                    />
                </div>

                {form.category === 'analysis_result' && (
                    <AnalystBindingSheet
                        open={bindingOpen}
                        onOpenChange={handleBindingOpenChange}
                        packages={packages}
                        analysisGroups={analysisGroups}
                        data={{
                            analysis_type_ids: bindForm.data.analysis_type_ids,
                            analysis_package_id: bindForm.data.analysis_package_id,
                            analyst_signatory_slots:
                                bindForm.data.analyst_signatory_slots,
                            analyst_require_prc: bindForm.data.analyst_require_prc,
                        }}
                        errors={{
                            analysis_type_ids: bindForm.errors.analysis_type_ids,
                        }}
                        processing={bindForm.processing}
                        summaryLabel={bindingSummary}
                        onChange={(patch) => {
                            bindForm.setData({
                                ...bindForm.data,
                                ...patch,
                            });
                        }}
                        onSave={() => {
                            bindForm.put(`/admin/controlled-forms/${form.id}`, {
                                onSuccess: () => setBindingOpen(false),
                            });
                        }}
                    />
                )}

                <RevisionHistoryList
                    formId={form.id}
                    revisions={form.revisions ?? []}
                    transitions={transitions}
                    onTransition={handleTransition}
                />
            </LimsWorkspace>

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
                                {
                                    forceFormData: true,
                                    onSuccess: () => setRevisionOpen(false),
                                },
                            );
                        }}
                    >
                        {current ? (
                            <p className="rounded-md border bg-slate-50 px-3 py-2 text-xs text-muted-foreground">
                                Current revision:{' '}
                                <span className="font-mono text-slate-800">
                                    {current.revision}
                                </span>
                                {current.status_label
                                    ? ` · ${current.status_label}`
                                    : ''}
                            </p>
                        ) : null}
                        <div className="grid gap-2">
                            <Label>New revision</Label>
                            <Input
                                value={revisionForm.data.revision}
                                placeholder="Leave blank to auto-number"
                                onChange={(event) =>
                                    revisionForm.setData(
                                        'revision',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>Effective date</Label>
                            <Input
                                type="date"
                                value={revisionForm.data.effective_date}
                                onChange={(event) =>
                                    revisionForm.setData(
                                        'effective_date',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label>
                                Source file (optional — copies previous PDF if
                                omitted)
                            </Label>
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
                            <Label>Reason for revision / notes</Label>
                            <Textarea
                                value={revisionForm.data.notes}
                                onChange={(event) =>
                                    revisionForm.setData(
                                        'notes',
                                        event.target.value,
                                    )
                                }
                            />
                        </div>
                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setRevisionOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={revisionForm.processing}
                                className="bg-[#1A3694] hover:bg-[#365BB0]"
                            >
                                Create revision
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <ConfirmDialog
                open={pendingTransition !== null}
                onOpenChange={(open) => {
                    if (!open && !transitioning) {
                        setPendingTransition(null);
                    }
                }}
                title={
                    pendingTransition?.status === 'active'
                        ? `Activate revision ${pendingTransition.revisionLabel}?`
                        : `Archive revision ${pendingTransition?.revisionLabel ?? ''}?`
                }
                description={
                    pendingTransition?.status === 'active'
                        ? 'This becomes the live document used in operations.'
                        : 'It will no longer be used for new documents.'
                }
                confirmLabel={pendingTransition?.label ?? 'Confirm'}
                variant={
                    pendingTransition?.status === 'archived'
                        ? 'destructive'
                        : 'default'
                }
                processing={transitioning}
                onConfirm={() => {
                    if (!pendingTransition) {
                        return;
                    }

                    setTransitioning(true);
                    router.post(
                        `/admin/controlled-forms/${form.id}/revisions/${pendingTransition.revisionId}/transition`,
                        { status: pendingTransition.status },
                        {
                            onFinish: () => {
                                setTransitioning(false);
                                setPendingTransition(null);
                            },
                        },
                    );
                }}
            />
        </>
    );
}

ControlledFormShow.layout = {
    breadcrumbs: [
        { title: 'Controlled Forms', href: '/admin/controlled-forms' },
        { title: 'Details', href: '#' },
    ],
};
