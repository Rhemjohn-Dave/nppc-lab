import { Link } from '@inertiajs/react';
import {
    ArrowLeft,
    Eye,
    FileUp,
    Grid3x3,
    Import,
    Redo2,
    Save,
    Undo2,
} from 'lucide-react';
import { useState } from 'react';
import type { AnalysisPackageOption } from '@/components/package-select';
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
    statusBadgeClass
    
    
} from '@/lib/controlled-forms';
import type {ControlledFormSummary, ControlledRevisionSummary} from '@/lib/controlled-forms';

export type NewPdfRevisionPayload = {
    revision: string;
    file: File;
    notes: string;
    effective_date: string;
};

type Props = {
    form: ControlledFormSummary;
    revision: ControlledRevisionSummary;
    nextRevision: string;
    isDirty: boolean;
    isSaving: boolean;
    justSaved: boolean;
    canEdit: boolean;
    previewJobId: string;
    jobOrders: Array<{ id: number; label: string }>;
    onUndo: () => void;
    onRedo: () => void;
    onSave: () => void;
    onPreview: () => void;
    onPreviewJobChange: (id: string) => void;
    onNewPdfRevision: (payload: NewPdfRevisionPayload) => void;
    onImportBlueprint?: () => void;
    calibrationHref: string;
    onToggleLibrary?: () => void;
    onToggleProperties?: () => void;
    packages?: AnalysisPackageOption[];
    packageId?: number | null;
    onPackageChange?: (packageId: number | null, typeIds: number[]) => void;
};

export default function DesignerHeader({
    form,
    revision,
    isDirty,
    isSaving,
    justSaved,
    canEdit,
    previewJobId,
    jobOrders,
    onUndo,
    onRedo,
    onSave,
    onPreview,
    onPreviewJobChange,
    onNewPdfRevision,
    onImportBlueprint,
    calibrationHref,
    onToggleLibrary,
    onToggleProperties,
    packages = [],
    packageId,
    onPackageChange,
    nextRevision,
}: Props) {
    const [revisionOpen, setRevisionOpen] = useState(false);
    const [revisionNo, setRevisionNo] = useState(nextRevision);
    const [effectiveDate, setEffectiveDate] = useState('');
    const [notes, setNotes] = useState('');
    const [file, setFile] = useState<File | null>(null);
    const [fileKey, setFileKey] = useState(0);

    function openRevisionDialog() {
        setRevisionNo(nextRevision);
        setEffectiveDate('');
        setNotes('');
        setFile(null);
        setFileKey((value) => value + 1);
        setRevisionOpen(true);
    }

    let saveLabel = 'Save';

    if (isSaving) {
        saveLabel = 'Saving…';
    } else if (justSaved && !isDirty) {
        saveLabel = 'Saved';
    } else if (isDirty) {
        saveLabel = 'Save changes';
    }

    return (
        <header className="relative z-20 shrink-0 border-b bg-white shadow-sm">
            <div className="flex flex-wrap items-center justify-between gap-2 px-3 py-2 lg:px-4">
                <div className="flex min-w-0 items-center gap-2">
                    <Button variant="ghost" size="sm" className="shrink-0 px-2" asChild>
                        <Link href={`/admin/controlled-forms/${form.id}`}>
                            <ArrowLeft className="size-4" />
                            <span className="hidden sm:inline">Controlled Forms</span>
                        </Link>
                    </Button>
                    <div className="hidden h-5 w-px bg-border sm:block" />
                    <span className="hidden text-xs font-medium tracking-wide text-[#1A3694] sm:inline">
                        Form Designer
                    </span>
                </div>

                <div className="flex flex-wrap items-center justify-end gap-1.5">
                    {(onToggleLibrary || onToggleProperties) && (
                        <div className="flex gap-1 lg:hidden">
                            {onToggleLibrary && (
                                <Button variant="outline" size="sm" onClick={onToggleLibrary}>
                                    Fields
                                </Button>
                            )}
                            {onToggleProperties && (
                                <Button variant="outline" size="sm" onClick={onToggleProperties}>
                                    Properties
                                </Button>
                            )}
                        </div>
                    )}

                    {form.category === 'analysis_result' && onPackageChange && (
                        <select
                            className="h-8 max-w-[220px] rounded-md border bg-white px-2 text-xs"
                            value={packageId ?? ''}
                            onChange={(event) => {
                                const raw = event.target.value;

                                if (raw === '') {
                                    onPackageChange(null, []);

                                    return;
                                }

                                const id = Number(raw);
                                const selected = packages.find((item) => item.id === id);
                                onPackageChange(id, selected?.analysis_type_ids ?? []);
                            }}
                            aria-label="Analysis package"
                        >
                            <option value="">No package</option>
                            {packages.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.name}
                                </option>
                            ))}
                        </select>
                    )}

                    <select
                        className="hidden h-8 max-w-[180px] rounded-md border bg-white px-2 text-xs md:block"
                        value={previewJobId}
                        onChange={(event) => onPreviewJobChange(event.target.value)}
                        aria-label="Preview job order"
                    >
                        <option value="">Sample data</option>
                        {jobOrders.map((order) => (
                            <option key={order.id} value={order.id}>
                                {order.label}
                            </option>
                        ))}
                    </select>

                    <Button variant="outline" size="sm" onClick={onPreview}>
                        <Eye className="size-4" />
                        <span className="hidden sm:inline">Preview</span>
                    </Button>

                    <Button variant="outline" size="sm" asChild>
                        <a href={calibrationHref} target="_blank" rel="noreferrer">
                            <Grid3x3 className="size-4" />
                            <span className="hidden sm:inline">Grid</span>
                        </a>
                    </Button>

                    <Button variant="outline" size="sm" onClick={openRevisionDialog}>
                        <FileUp className="size-4" />
                        <span className="hidden lg:inline">New PDF revision</span>
                    </Button>

                    {onImportBlueprint && (
                        <Button variant="outline" size="sm" onClick={onImportBlueprint}>
                            <Import className="size-4" />
                            <span className="hidden lg:inline">Import blueprint</span>
                        </Button>
                    )}

                    <Button variant="outline" size="sm" onClick={onUndo}>
                        <Undo2 className="size-4" />
                    </Button>
                    <Button variant="outline" size="sm" onClick={onRedo}>
                        <Redo2 className="size-4" />
                    </Button>

                    <div className="flex items-center gap-2">
                        {isDirty && !isSaving && (
                            <span className="hidden text-xs text-amber-700 md:inline">
                                Unsaved changes
                            </span>
                        )}
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            size="sm"
                            onClick={onSave}
                            disabled={!canEdit || isSaving || (!isDirty && justSaved)}
                        >
                            <Save className="size-4" />
                            {saveLabel}
                        </Button>
                    </div>
                </div>
            </div>

            <div className="flex flex-wrap items-center gap-x-3 gap-y-1 border-t bg-slate-50/80 px-3 py-2 text-sm lg:px-4">
                <h1 className="truncate font-medium text-slate-900">{form.name}</h1>
                <span className="text-muted-foreground">·</span>
                <span className="font-mono text-xs text-muted-foreground">{form.form_code}</span>
                <span className="text-muted-foreground">·</span>
                <span className="text-xs text-muted-foreground">Rev {revision.revision}</span>
                <Badge variant="outline" className={statusBadgeClass(revision.status)}>
                    {revision.status_label}
                </Badge>
            </div>

            <Dialog open={revisionOpen} onOpenChange={setRevisionOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>New PDF revision</DialogTitle>
                        <DialogDescription>
                            Upload the updated official PDF and set the new revision number. Overlay
                            boxes are copied from this design at the same millimetre positions. If
                            the new page size or layout shifted, nudge the boxes after upload. The
                            new revision stays a draft until you activate it.
                        </DialogDescription>
                    </DialogHeader>
                    <form
                        className="grid gap-3"
                        onSubmit={(event) => {
                            event.preventDefault();

                            if (!file) {
                                return;
                            }

                            onNewPdfRevision({
                                revision: revisionNo,
                                file,
                                notes,
                                effective_date: effectiveDate,
                            });
                            setRevisionOpen(false);
                        }}
                    >
                        <div className="grid gap-2">
                            <Label htmlFor="new-rev-number">Revision</Label>
                            <Input
                                id="new-rev-number"
                                value={revisionNo}
                                onChange={(event) => setRevisionNo(event.target.value)}
                                required
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-rev-file">New official PDF</Label>
                            <Input
                                id="new-rev-file"
                                key={fileKey}
                                type="file"
                                accept=".pdf,.doc,.docx,application/pdf"
                                required
                                onChange={(event) => setFile(event.target.files?.[0] ?? null)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-rev-effective">Effective date</Label>
                            <Input
                                id="new-rev-effective"
                                type="date"
                                value={effectiveDate}
                                onChange={(event) => setEffectiveDate(event.target.value)}
                            />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="new-rev-notes">Notes</Label>
                            <Textarea
                                id="new-rev-notes"
                                value={notes}
                                onChange={(event) => setNotes(event.target.value)}
                            />
                        </div>
                        {isDirty && canEdit ? (
                            <p className="text-xs text-amber-800">
                                Unsaved box changes will be saved on this revision before the new
                                PDF is created.
                            </p>
                        ) : null}
                        <DialogFooter>
                            <Button type="submit" disabled={isSaving || !file}>
                                Create revision
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </header>
    );
}
