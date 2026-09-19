import { Head, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import ConfirmDialog from '@/components/confirm-dialog';
import AnalystBindingSheet from '@/components/controlled-forms/analyst-binding-sheet';
import ControlledFormHeader from '@/components/controlled-forms/controlled-form-header';
import CurrentRevisionCard from '@/components/controlled-forms/current-revision-card';
import DocumentControlCallout from '@/components/controlled-forms/document-control-callout';
import FormInformationCard from '@/components/controlled-forms/form-information-card';
import FormUsageCard from '@/components/controlled-forms/form-usage-card';
import LabIntegrationCard from '@/components/controlled-forms/lab-integration-card';
import RevisionHistoryList, {
    type RevisionTransitionAction,
} from '@/components/controlled-forms/revision-history-list';
import SignatorySlotsCard from '@/components/controlled-forms/signatory-slots-card';
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
import { cn } from '@/lib/utils';

type Props = {
    form: ControlledFormSummary;
    analysisGroups: AnalysisGroup[];
    packages?: AnalysisPackageOption[];
};

type DetailTab = 'overview' | 'bound' | 'revisions';

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
    const [activeTab, setActiveTab] = useState<DetailTab>('overview');
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
    const isResultForm = form.category === 'analysis_result';
    const analysisTypes = form.analysis_types ?? [];
    const revisions = form.revisions ?? [];

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

    const tabs: Array<{ id: DetailTab; label: string; badge?: string }> = [
        {
            id: 'overview',
            label: 'Overview & blueprint',
            badge: current?.revision ? `v${current.revision}` : undefined,
        },
        {
            id: 'bound',
            label: 'Bound parameters',
            badge: String(analysisTypes.length),
        },
        {
            id: 'revisions',
            label: 'Revision history',
            badge: String(revisions.length),
        },
    ];

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
                    revision={current?.revision ?? null}
                    effectiveDate={current?.effective_date ?? null}
                    workflowStatus={current?.status ?? form.status}
                    onNewRevision={() => setRevisionOpen(true)}
                />

                <div className="border-b">
                    <nav
                        aria-label="Controlled form sections"
                        className="flex flex-wrap gap-x-4 gap-y-0"
                    >
                        {tabs.map((tab) => {
                            const selected = activeTab === tab.id;
                            return (
                                <button
                                    key={tab.id}
                                    type="button"
                                    onClick={() => setActiveTab(tab.id)}
                                    className={cn(
                                        'inline-flex items-center gap-1.5 border-b-2 py-2 text-xs font-semibold transition-colors',
                                        selected
                                            ? 'border-[#1A3694] text-[#1A3694]'
                                            : 'border-transparent text-muted-foreground hover:border-slate-300 hover:text-slate-700',
                                    )}
                                    aria-current={selected ? 'page' : undefined}
                                >
                                    {tab.label}
                                    {tab.badge != null ? (
                                        <span
                                            className={cn(
                                                'rounded-full px-1.5 py-0.5 font-mono text-[10px]',
                                                selected
                                                    ? 'bg-[#eef3fb] text-[#1A3694]'
                                                    : 'bg-slate-100 text-slate-600',
                                            )}
                                        >
                                            {tab.badge}
                                        </span>
                                    ) : null}
                                </button>
                            );
                        })}
                    </nav>
                </div>

                {activeTab === 'overview' ? (
                    <div className="grid min-w-0 gap-2.5 lg:grid-cols-3">
                        <div className="min-w-0 space-y-2.5 lg:col-span-2">
                            <SourceFileLayoutCard
                                formId={form.id}
                                hasBlueprint={Boolean(form.has_blueprint)}
                                revision={current}
                            />
                            <FormUsageCard
                                packages={packages}
                                analysisPackageId={form.analysis_package_id}
                                analysisTypes={analysisTypes}
                                showEdit={isResultForm}
                                onEdit={() => setBindingOpen(true)}
                            />
                            {isResultForm ? (
                                <SignatorySlotsCard
                                    slots={form.analyst_signatory_slots ?? 1}
                                    requirePrc={Boolean(form.analyst_require_prc)}
                                    onEdit={() => setBindingOpen(true)}
                                />
                            ) : null}
                        </div>
                        <div className="min-w-0 space-y-2.5">
                            {editingInfo ? (
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
                                    onChange={(field, value) =>
                                        bindForm.setData(field, value)
                                    }
                                    onSave={() => {
                                        bindForm.put(
                                            `/admin/controlled-forms/${form.id}`,
                                            {
                                                onSuccess: () =>
                                                    setEditingInfo(false),
                                            },
                                        );
                                    }}
                                />
                            ) : null}
                            <CurrentRevisionCard
                                formCode={form.form_code}
                                updatedAt={form.updated_at}
                                revision={current}
                                onEditFormInfo={() => setEditingInfo(true)}
                            />
                            <LabIntegrationCard
                                packages={packages}
                                analysisPackageId={form.analysis_package_id}
                                analysisTypeCount={analysisTypes.length}
                            />
                            <DocumentControlCallout
                                formCode={form.form_code}
                                revision={current?.revision ?? null}
                                effectiveDate={current?.effective_date ?? null}
                            />
                        </div>
                    </div>
                ) : null}

                {activeTab === 'bound' ? (
                    <div className="space-y-2.5">
                        <FormUsageCard
                            packages={packages}
                            analysisPackageId={form.analysis_package_id}
                            analysisTypes={analysisTypes}
                            showEdit={isResultForm}
                            onEdit={() => setBindingOpen(true)}
                        />
                        <LabIntegrationCard
                            packages={packages}
                            analysisPackageId={form.analysis_package_id}
                            analysisTypeCount={analysisTypes.length}
                        />
                        {isResultForm ? (
                            <SignatorySlotsCard
                                slots={form.analyst_signatory_slots ?? 1}
                                requirePrc={Boolean(form.analyst_require_prc)}
                                onEdit={() => setBindingOpen(true)}
                            />
                        ) : (
                            <p className="rounded-lg border bg-white px-3 py-2 text-sm text-muted-foreground">
                                This form category does not use analyst result
                                binding. Package and type links above are
                                informational.
                            </p>
                        )}
                    </div>
                ) : null}

                {activeTab === 'revisions' ? (
                    <RevisionHistoryList
                        formId={form.id}
                        revisions={revisions}
                        transitions={transitions}
                        onTransition={handleTransition}
                    />
                ) : null}

                {isResultForm ? (
                    <AnalystBindingSheet
                        open={bindingOpen}
                        onOpenChange={handleBindingOpenChange}
                        hideTrigger
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
                ) : null}
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
