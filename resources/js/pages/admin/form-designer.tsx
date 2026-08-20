import { Head, router, usePage } from '@inertiajs/react';
import { MousePointerClick } from 'lucide-react';
import * as pdfjs from 'pdfjs-dist';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import React from 'react';
import CanvasToolbar from '@/components/form-designer/canvas-toolbar';
import DesignerHeader from '@/components/form-designer/designer-header';
import {
    HorizontalRuler,
    RULER_SIZE,
    RulerCorner,
    VerticalRuler,
} from '@/components/form-designer/designer-rulers';
import FieldLibrary from '@/components/form-designer/field-library';
import PropertiesPanel from '@/components/form-designer/properties-panel';
import {
    snapMove,
    snapPoint,
    snapResize,
    snapThresholdMm,
} from '@/components/form-designer/snap';
import type { SnapGuides } from '@/components/form-designer/snap';
import {
    cloneFields,
    clientId,
    cssFontFamily,
    DRAG_SOURCE_MIME,
    fieldsEqual,
    sourceToField,
} from '@/components/form-designer/utils';
import type { AnalysisPackageOption } from '@/components/package-select';
import PdfPreviewDialog from '@/components/pdf-preview-dialog';
import { Input } from '@/components/ui/input';
import { useSidebar } from '@/components/ui/sidebar';
import DesignerLayout from '@/layouts/designer-layout';
import type {
    ControlledFormSummary,
    ControlledRevisionSummary,
    DataSource,
    DesignerField,
    FieldTypeOption,
} from '@/lib/controlled-forms';

pdfjs.GlobalWorkerOptions.workerSrc = pdfWorker;

const PDF_CSS_SCALE = 96 / 72;

/** Collapse the global nav sidebar while the designer is open, restore on unmount. */
function SidebarCollapser() {
    const { setOpen, state } = useSidebar();
    useEffect(() => {
        if (state !== 'collapsed') {
            setOpen(false);
        }

        return () => {
            setOpen(true);
        };
    }, []); // eslint-disable-line react-hooks/exhaustive-deps

    return null;
}

type Props = {
    form: ControlledFormSummary;
    revision: ControlledRevisionSummary;
    next_revision: string;
    sources: DataSource[];
    fieldTypes: FieldTypeOption[];
    jobOrders: Array<{ id: number; label: string }>;
    packages?: AnalysisPackageOption[];
};

type DragState = {
    id: string;
    mode: 'move' | 'resize';
    startX: number;
    startY: number;
    origX: number;
    origY: number;
    origW: number;
    origH: number;
};

export default function FormDesigner({
    form,
    revision,
    sources,
    fieldTypes,
    jobOrders,
    packages = [],
    next_revision,
}: Props) {
    const { flash } = usePage().props as { flash?: { success?: string } };

    const canvasRef = useRef<HTMLCanvasElement | null>(null);
    const wrapRef = useRef<HTMLDivElement | null>(null);
    const scrollRef = useRef<HTMLDivElement | null>(null);

    const initialFields = useMemo(
        () =>
            (revision.fields ?? []).map((field, index) => ({
                ...field,
                name: field.name || `field_${index + 1}`,
            })),
        [revision.fields],
    );

    const [fields, setFields] = useState<DesignerField[]>(() => cloneFields(initialFields));
    const [savedFields, setSavedFields] = useState<DesignerField[]>(() => cloneFields(initialFields));
    const [selectedId, setSelectedId] = useState<string | null>(null);
    const [pendingSource, setPendingSource] = useState<DataSource | null>(null);
    const [page, setPage] = useState(1);
    const [zoom, setZoom] = useState(0.75);
    const didAutoFit = useRef(false);
    const initialWidthMm = revision.page_width_mm ?? 215.9;
    const initialHeightMm = revision.page_height_mm ?? 330.2;
    const [basePageSize, setBasePageSize] = useState({
        widthPx: (initialWidthMm / 25.4) * 96,
        heightPx: (initialHeightMm / 25.4) * 96,
    });
    const [pageSize, setPageSize] = useState({
        widthMm: initialWidthMm,
        heightMm: initialHeightMm,
        widthPx: ((initialWidthMm / 25.4) * 96) * 0.85,
        heightPx: ((initialHeightMm / 25.4) * 96) * 0.85,
    });
    const [history, setHistory] = useState<DesignerField[][]>([cloneFields(initialFields)]);
    const [historyIndex, setHistoryIndex] = useState(0);
    const [previewOpen, setPreviewOpen] = useState(false);
    const [previewJobId, setPreviewJobId] = useState('');
    const [fileInputKey, setFileInputKey] = useState(0);
    const [isSaving, setIsSaving] = useState(false);
    const [justSaved, setJustSaved] = useState(false);
    const [mobileLibraryOpen, setMobileLibraryOpen] = useState(false);
    const [mobilePropertiesOpen, setMobilePropertiesOpen] = useState(false);
    const [showAllFields, setShowAllFields] = useState(!form.analysis_package_id);
    const [snapEnabled, setSnapEnabled] = useState(true);
    const [cursorMm, setCursorMm] = useState<{ x: number; y: number } | null>(null);
    const [guides, setGuides] = useState<SnapGuides>({ vertical: [], horizontal: [] });
    const dragRef = useRef<DragState | null>(null);
    const fieldsRef = useRef(fields);

    useEffect(() => {
        fieldsRef.current = fields;
    }, [fields]);

    const canEdit = revision.status !== 'superseded' && revision.status !== 'archived';
    const pageCount = revision.page_count || 1;
    const isDirty = !fieldsEqual(fields, savedFields);

    const selected =
        fields.find((field, index) => clientId(field, index) === selectedId) ?? null;

    const pxPerMm = pageSize.widthPx / pageSize.widthMm;
    const mm = useCallback((px: number) => Number((px / pxPerMm).toFixed(3)), [pxPerMm]);
    const px = useCallback((value: number) => value * pxPerMm, [pxPerMm]);

    const librarySources = useMemo(() => {
        if (!form.analysis_package_id || showAllFields) {
            return sources;
        }

        return sources.filter((source) => source.focused);
    }, [form.analysis_package_id, showAllFields, sources]);

    const groupedSources = useMemo(() => {
        const groups = new Map<string, DataSource[]>();

        for (const source of librarySources) {
            const list = groups.get(source.group) ?? [];
            list.push(source);
            groups.set(source.group, list);
        }

        return [...groups.entries()];
    }, [librarySources]);

    const pageFields = fields.filter((field) => field.page_number === page);

    const pushHistory = useCallback(
        (next: DesignerField[]) => {
            setHistory((current) => {
                const trimmed = current.slice(0, historyIndex + 1);

                return [...trimmed, cloneFields(next)].slice(-50);
            });
            setHistoryIndex((index) => Math.min(index + 1, 49));
            setFields(next);
            setJustSaved(false);
        },
        [historyIndex],
    );

    function updateSelected(patch: Partial<DesignerField>, recordHistory = false) {
        setFields((current) => {
            const next = current.map((field, index) =>
                clientId(field, index) === selectedId ? { ...field, ...patch } : field,
            );

            fieldsRef.current = next;

            if (recordHistory) {
                setHistory((items) => [...items.slice(0, historyIndex + 1), cloneFields(next)].slice(-50));
                setHistoryIndex((value) => value + 1);
            }

            setJustSaved(false);

            return next;
        });

        if (patch.name) {
            setSelectedId(patch.name);
        }
    }

    useEffect(() => {
        function onKeyDown(event: KeyboardEvent) {
            if (!canEdit || !selectedId) {
                return;
            }

            const target = event.target as HTMLElement | null;
            if (
                target?.closest(
                    'input, textarea, select, [contenteditable="true"], [contenteditable=""]',
                )
            ) {
                return;
            }

            const step = event.shiftKey ? 5 : 0.5;
            let dx = 0;
            let dy = 0;

            switch (event.key) {
                case 'ArrowLeft':
                    dx = -step;
                    break;
                case 'ArrowRight':
                    dx = step;
                    break;
                case 'ArrowUp':
                    dy = -step;
                    break;
                case 'ArrowDown':
                    dy = step;
                    break;
                default:
                    return;
            }

            event.preventDefault();

            const current = fieldsRef.current.find(
                (field, index) => clientId(field, index) === selectedId,
            );
            if (!current) {
                return;
            }

            const maxX = Math.max(0, pageSize.widthMm - current.width);
            const maxY = Math.max(0, pageSize.heightMm - current.height);
            const x = Number(Math.min(maxX, Math.max(0, current.x + dx)).toFixed(3));
            const y = Number(Math.min(maxY, Math.max(0, current.y + dy)).toFixed(3));

            if (x === current.x && y === current.y) {
                return;
            }

            updateSelected({ x, y }, !event.repeat);
        }

        window.addEventListener('keydown', onKeyDown);

        return () => window.removeEventListener('keydown', onKeyDown);
        // eslint-disable-next-line react-hooks/exhaustive-deps -- fieldsRef keeps live box position during key repeat
    }, [canEdit, selectedId, pageSize.widthMm, pageSize.heightMm, historyIndex]);

    useEffect(() => {
        if (flash?.success) {
            // eslint-disable-next-line react-hooks/set-state-in-effect -- Inertia flash is the save-success signal
            setSavedFields(cloneFields(fields));
            setJustSaved(true);
        }
    }, [flash?.success]); // eslint-disable-line react-hooks/exhaustive-deps

    useEffect(() => {
        let cancelled = false;
        const url = `/admin/controlled-forms/${form.id}/revisions/${revision.id}/canonical`;

        void (async () => {
            const pdf = await pdfjs.getDocument({ url, withCredentials: true }).promise;

            if (cancelled) {
                return;
            }

            const pdfPage = await pdf.getPage(page);
            const baseViewport = pdfPage.getViewport({ scale: PDF_CSS_SCALE });
            const viewport = pdfPage.getViewport({ scale: PDF_CSS_SCALE * zoom });
            const canvas = canvasRef.current;

            if (!canvas) {
                return;
            }

            canvas.width = viewport.width;
            canvas.height = viewport.height;
            const context = canvas.getContext('2d');

            if (!context) {
                return;
            }

            await pdfPage.render({ canvasContext: context, viewport, canvas }).promise;

            setBasePageSize({
                widthPx: baseViewport.width,
                heightPx: baseViewport.height,
            });
            setPageSize({
                widthMm: revision.page_width_mm ?? 215.9,
                heightMm: revision.page_height_mm ?? 330.2,
                widthPx: viewport.width,
                heightPx: viewport.height,
            });

            // Auto-fit the first time the PDF loads so it fills the canvas.
            if (!didAutoFit.current) {
                didAutoFit.current = true;
                const container = scrollRef.current;

                if (container) {
                    const padding = 64 + RULER_SIZE;
                    const fitScale = (container.clientWidth - padding) / baseViewport.width;
                    const clamped = Number(Math.min(Math.max(fitScale, 0.3), 2.5).toFixed(2));
                    setZoom(clamped);
                }
            }
        })();

        return () => {
            cancelled = true;
        };
    }, [form.id, revision.id, revision.page_height_mm, revision.page_width_mm, page, zoom]);

    function placeField(source: DataSource, xMm: number, yMm: number) {
        const origin = snapEnabled
            ? snapPoint(
                  xMm,
                  yMm,
                  pageFields,
                  pageSize.widthMm,
                  pageSize.heightMm,
                  snapThresholdMm(pxPerMm),
              )
            : { x: xMm, y: yMm };
        const field = sourceToField(source, fieldTypes, page, origin.x, origin.y, fields.length + 1);
        const next = [...fields, field];
        pushHistory(next);
        setSelectedId(field.name);
        setPendingSource(null);
        setMobileLibraryOpen(false);
    }

    function addFieldType(type: FieldTypeOption) {
        const index = fields.length + 1;
        const field: DesignerField = {
            name: `${type.value}_${index}`,
            label: type.label,
            field_type: type.value,
            page_number: page,
            x: 20,
            y: 20 + (index % 8) * 6,
            width: type.width,
            height: type.height,
            font_size: 11,
            font_family: 'calibri',
            font_color: '#000000',
            alignment: 'L',
            data_source_key: null,
            format: null,
            checkbox_true_value: null,
            options: null,
            table_config:
                type.value === 'table'
                    ? { row_height: 4.5, max_rows: 9, columns: [] }
                    : null,
            z_order: index,
        };
        pushHistory([...fields, field]);
        setSelectedId(field.name);
        setMobilePropertiesOpen(true);
    }

    function deleteSelected() {
        if (!selectedId) {
            return;
        }

        pushHistory(fields.filter((field, index) => clientId(field, index) !== selectedId));
        setSelectedId(null);
    }

    function duplicateSelected() {
        if (!selected) {
            return;
        }

        const copy: DesignerField = {
            ...selected,
            id: undefined,
            name: `${selected.name}_copy`,
            label: `${selected.label} copy`,
            x: selected.x + 4,
            y: selected.y + 4,
        };
        pushHistory([...fields, copy]);
        setSelectedId(copy.name);
    }

    function undo() {
        if (historyIndex <= 0) {
            return;
        }

        const nextIndex = historyIndex - 1;
        setHistoryIndex(nextIndex);
        setFields(cloneFields(history[nextIndex] ?? []));
        setJustSaved(false);
    }

    function redo() {
        if (historyIndex >= history.length - 1) {
            return;
        }

        const nextIndex = historyIndex + 1;
        setHistoryIndex(nextIndex);
        setFields(cloneFields(history[nextIndex] ?? []));
        setJustSaved(false);
    }

    function save() {
        setIsSaving(true);
        router.put(
            `/admin/controlled-forms/${form.id}/revisions/${revision.id}/fields`,
            { fields: JSON.parse(JSON.stringify(fields)) },
            {
                preserveScroll: true,
                onSuccess: () => {
                    setSavedFields(cloneFields(fields));
                    setJustSaved(true);
                },
                onFinish: () => setIsSaving(false),
            },
        );
    }

    function createPdfRevision(payload: {
        revision: string;
        file: File;
        notes: string;
        effective_date: string;
    }) {
        const postRevision = () => {
            router.post(
                `/admin/controlled-forms/${form.id}/revisions`,
                {
                    revision: payload.revision,
                    file: payload.file,
                    notes: payload.notes,
                    effective_date: payload.effective_date || null,
                    copy_from_revision_id: revision.id,
                },
                { forceFormData: true },
            );
        };

        if (isDirty && canEdit) {
            setIsSaving(true);
            router.put(
                `/admin/controlled-forms/${form.id}/revisions/${revision.id}/fields`,
                { fields: JSON.parse(JSON.stringify(fields)) },
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setSavedFields(cloneFields(fields));
                        postRevision();
                    },
                    onFinish: () => setIsSaving(false),
                },
            );

            return;
        }

        postRevision();
    }

    function canvasPointFromEvent(clientX: number, clientY: number): { x: number; y: number } | null {
        const wrap = wrapRef.current;

        if (!wrap) {
            return null;
        }

        const rect = wrap.getBoundingClientRect();

        return {
            x: mm(clientX - rect.left),
            y: mm(clientY - rect.top),
        };
    }

    function handleCanvasClick(event: React.MouseEvent<HTMLDivElement>) {
        if (pendingSource && canEdit) {
            const point = canvasPointFromEvent(event.clientX, event.clientY);

            if (point) {
                placeField(pendingSource, point.x, point.y);
            }

            return;
        }

        setSelectedId(null);
    }

    function handleCanvasDrop(event: React.DragEvent<HTMLDivElement>) {
        event.preventDefault();

        if (!canEdit) {
            return;
        }

        const raw = event.dataTransfer.getData(DRAG_SOURCE_MIME);

        if (!raw) {
            return;
        }

        try {
            const source = JSON.parse(raw) as DataSource;
            const point = canvasPointFromEvent(event.clientX, event.clientY);

            if (point) {
                placeField(source, point.x, point.y);
            }
        } catch {
            // Ignore invalid drag payloads.
        }
    }

    function onPointerDown(
        event: React.PointerEvent,
        field: DesignerField,
        index: number,
        mode: 'move' | 'resize',
    ) {
        event.preventDefault();
        event.stopPropagation();
        setSelectedId(clientId(field, index));
        setPendingSource(null);
        dragRef.current = {
            id: clientId(field, index),
            mode,
            startX: event.clientX,
            startY: event.clientY,
            origX: field.x,
            origY: field.y,
            origW: field.width,
            origH: field.height,
        };
        (event.target as HTMLElement).setPointerCapture(event.pointerId);
    }

    function onPointerMove(event: React.PointerEvent) {
        const point = canvasPointFromEvent(event.clientX, event.clientY);

        if (point) {
            setCursorMm(point);
        }

        const drag = dragRef.current;

        if (!drag) {
            return;
        }

        const dx = mm(event.clientX - drag.startX);
        const dy = mm(event.clientY - drag.startY);
        const shouldSnap = snapEnabled && !event.altKey;
        const threshold = snapThresholdMm(pxPerMm);
        const others = fieldsRef.current
            .filter((field, index) => field.page_number === page && clientId(field, index) !== drag.id)
            .map((field) => ({
                x: field.x,
                y: field.y,
                width: field.width,
                height: field.height,
            }));

        if (drag.mode === 'move') {
            const proposed = {
                x: Math.max(0, drag.origX + dx),
                y: Math.max(0, drag.origY + dy),
                width: drag.origW,
                height: drag.origH,
            };
            const snapped = shouldSnap
                ? snapMove(proposed, others, pageSize.widthMm, pageSize.heightMm, threshold)
                : { ...proposed, guides: { vertical: [], horizontal: [] } as SnapGuides };

            setGuides(snapped.guides);
            setFields((current) =>
                current.map((field, index) =>
                    clientId(field, index) === drag.id
                        ? {
                              ...field,
                              x: Number(snapped.x.toFixed(3)),
                              y: Number(snapped.y.toFixed(3)),
                          }
                        : field,
                ),
            );
        } else {
            const proposed = {
                x: drag.origX,
                y: drag.origY,
                width: Math.max(1, drag.origW + dx),
                height: Math.max(1, drag.origH + dy),
            };
            const snapped = shouldSnap
                ? snapResize(proposed, others, pageSize.widthMm, pageSize.heightMm, threshold)
                : { ...proposed, guides: { vertical: [], horizontal: [] } as SnapGuides };

            setGuides(snapped.guides);
            setFields((current) =>
                current.map((field, index) =>
                    clientId(field, index) === drag.id
                        ? {
                              ...field,
                              width: Number(snapped.width.toFixed(3)),
                              height: Number(snapped.height.toFixed(3)),
                          }
                        : field,
                ),
            );
        }

        setJustSaved(false);
    }

    function onPointerUp() {
        if (dragRef.current) {
            setHistory((items) => [...items.slice(0, historyIndex + 1), cloneFields(fields)].slice(-50));
            setHistoryIndex((value) => value + 1);
        }

        dragRef.current = null;
        setGuides({ vertical: [], horizontal: [] });
    }

    function fitWidth() {
        const container = scrollRef.current;

        if (!container || !basePageSize.widthPx) {
            return;
        }

        const padding = 64 + RULER_SIZE;
        const available = container.clientWidth - padding;
        setZoom(Number((available / basePageSize.widthPx).toFixed(2)));
    }

    function fitPage() {
        const container = scrollRef.current;

        if (!container || !basePageSize.widthPx) {
            return;
        }

        const padding = 64 + RULER_SIZE;
        const scaleX = (container.clientWidth - padding) / basePageSize.widthPx;
        const scaleY = (container.clientHeight - padding) / basePageSize.heightPx;
        setZoom(Number(Math.min(scaleX, scaleY, 2.5).toFixed(2)));
    }

    return (
        <>
            <SidebarCollapser />
            <Head title={`Designer · ${form.form_code}`} />

            <div className="flex min-h-0 flex-1 flex-col overflow-hidden bg-white">
                <DesignerHeader
                    form={form}
                    revision={revision}
                    nextRevision={next_revision}
                    isDirty={isDirty}
                    isSaving={isSaving}
                    justSaved={justSaved}
                    canEdit={canEdit}
                    previewJobId={previewJobId}
                    jobOrders={jobOrders}
                    onUndo={undo}
                    onRedo={redo}
                    onSave={save}
                    onPreview={() => setPreviewOpen(true)}
                    onPreviewJobChange={setPreviewJobId}
                    onNewPdfRevision={createPdfRevision}
                    calibrationHref={`/admin/controlled-forms/${form.id}/revisions/${revision.id}/calibration`}
                    onImportBlueprint={
                        form.category === 'job_order' &&
                        revision.status !== 'superseded' &&
                        revision.status !== 'archived'
                            ? () =>
                                  router.post(
                                      `/admin/controlled-forms/${form.id}/revisions/${revision.id}/import-blueprint`,
                                  )
                            : undefined
                    }
                    onToggleLibrary={() => setMobileLibraryOpen((value) => !value)}
                    onToggleProperties={() => setMobilePropertiesOpen((value) => !value)}
                    packages={packages}
                    packageId={form.analysis_package_id}
                    onPackageChange={(packageId, typeIds) => {
                        router.put(
                            `/admin/controlled-forms/${form.id}`,
                            {
                                name: form.name,
                                description: form.description ?? '',
                                department: form.department ?? '',
                                analysis_package_id: packageId,
                                analysis_type_ids: packageId
                                    ? typeIds
                                    : form.analysis_type_ids,
                            },
                            { preserveScroll: true },
                        );
                        setShowAllFields(!packageId);
                    }}
                />

                {!revision.has_canonical && (
                    <div className="border-b bg-amber-50 px-4 py-3 text-sm">
                        Upload a canonical PDF before placing fields.
                        <Input
                            key={fileInputKey}
                            className="mt-2 max-w-md"
                            type="file"
                            accept=".pdf,.doc,.docx,application/pdf"
                            onChange={(event) => {
                                const file = event.target.files?.[0];

                                if (!file) {
                                    return;
                                }

                                router.post(
                                    `/admin/controlled-forms/${form.id}/revisions/${revision.id}/file`,
                                    { file },
                                    {
                                        forceFormData: true,
                                        onFinish: () => setFileInputKey((value) => value + 1),
                                    },
                                );
                            }}
                        />
                    </div>
                )}

                <div className="relative grid min-h-0 flex-1 overflow-hidden grid-cols-1 xl:grid-cols-[280px_minmax(0,1fr)_300px]">
                    {(mobileLibraryOpen || mobilePropertiesOpen) && (
                        <button
                            type="button"
                            className="fixed inset-0 z-30 bg-black/20 xl:hidden"
                            aria-label="Close panel"
                            onClick={() => {
                                setMobileLibraryOpen(false);
                                setMobilePropertiesOpen(false);
                            }}
                        />
                    )}

                    <div
                        className={`min-h-0 overflow-hidden border-r shadow-sm ${
                            mobileLibraryOpen
                                ? 'fixed inset-x-0 bottom-0 top-32 z-40 block bg-white xl:relative xl:inset-auto xl:top-auto xl:z-auto'
                                : 'hidden xl:block'
                        }`}
                    >
                        <FieldLibrary
                            sources={librarySources}
                            fieldTypes={fieldTypes}
                            fields={fields}
                            pendingSourceKey={pendingSource?.key ?? null}
                            canEdit={canEdit}
                            packageMode={Boolean(form.analysis_package_id)}
                            showAllFields={showAllFields}
                            onShowAllFieldsChange={setShowAllFields}
                            onSelectSource={(source) => {
                                setPendingSource(source);
                                setMobileLibraryOpen(false);
                            }}
                            onAddFieldType={addFieldType}
                            onClearPending={() => setPendingSource(null)}
                        />
                    </div>

                    <main className="relative flex min-h-0 min-w-0 overflow-hidden flex-col bg-[#e8eaed]">
                        {pendingSource && (
                            <div className="pointer-events-none absolute inset-x-0 top-3 z-20 flex justify-center">
                                <div className="flex items-center gap-2 rounded-full border bg-white/95 px-4 py-1.5 text-xs shadow-md">
                                    <MousePointerClick className="size-3.5 text-[#1A3694]" />
                                    Click on the PDF to place{' '}
                                    <strong>{pendingSource.label}</strong>
                                </div>
                            </div>
                        )}

                        <div
                            ref={scrollRef}
                            className="designer-scroll min-h-0 min-w-0 flex-1 overflow-auto overscroll-contain p-4 md:p-6"
                            onDragOver={(event) => {
                                if (canEdit) {
                                    event.preventDefault();
                                }
                            }}
                        >
                            <div className="flex min-h-full min-w-full items-start justify-center">
                                <div
                                    className="grid shrink-0"
                                    style={{
                                        gridTemplateColumns: `${RULER_SIZE}px ${pageSize.widthPx}px`,
                                        gridTemplateRows: `${RULER_SIZE}px ${pageSize.heightPx}px`,
                                    }}
                                >
                                    <RulerCorner />
                                    <HorizontalRuler
                                        widthPx={pageSize.widthPx}
                                        lengthMm={pageSize.widthMm}
                                        pxPerMm={pxPerMm}
                                        cursorMm={cursorMm?.x ?? null}
                                    />
                                    <VerticalRuler
                                        heightPx={pageSize.heightPx}
                                        lengthMm={pageSize.heightMm}
                                        pxPerMm={pxPerMm}
                                        cursorMm={cursorMm?.y ?? null}
                                    />
                                    <div
                                        ref={wrapRef}
                                        className={`relative rounded-sm bg-white shadow-[0_4px_24px_rgba(0,0,0,0.12)] ${
                                            pendingSource ? 'cursor-crosshair' : ''
                                        }`}
                                        style={{
                                            width: pageSize.widthPx,
                                            height: pageSize.heightPx,
                                        }}
                                        onClick={handleCanvasClick}
                                        onDrop={handleCanvasDrop}
                                        onPointerMove={onPointerMove}
                                        onPointerUp={onPointerUp}
                                        onPointerLeave={() => {
                                            if (!dragRef.current) {
                                                setCursorMm(null);
                                            }
                                        }}
                                    >
                                    <canvas ref={canvasRef} className="block" />
                                    {guides.vertical.map((position) => (
                                        <div
                                            key={`v-guide-${position}`}
                                            className="pointer-events-none absolute top-0 z-20 w-px bg-[#e11d48]"
                                            style={{
                                                left: px(position),
                                                height: pageSize.heightPx,
                                            }}
                                        />
                                    ))}
                                    {guides.horizontal.map((position) => (
                                        <div
                                            key={`h-guide-${position}`}
                                            className="pointer-events-none absolute left-0 z-20 h-px bg-[#e11d48]"
                                            style={{
                                                top: px(position),
                                                width: pageSize.widthPx,
                                            }}
                                        />
                                    ))}
                                    {pageFields.map((field) => {
                                        const index = fields.indexOf(field);
                                        const id = clientId(field, index);
                                        const active = id === selectedId;
                                        const isTable = field.field_type === 'table';

                                        return (
                                            <div
                                                key={`${id}-${index}`}
                                                className={`absolute z-10 overflow-visible text-[10px] leading-tight select-none ${
                                                    canEdit ? 'cursor-move' : 'cursor-default'
                                                }`}
                                                style={{
                                                    left: px(field.x),
                                                    top: px(field.y),
                                                    width: px(field.width),
                                                    height: px(field.height),
                                                }}
                                                onClick={(event) => {
                                                    event.stopPropagation();
                                                    setSelectedId(id);
                                                    setPendingSource(null);
                                                    setMobilePropertiesOpen(true);
                                                }}
                                                onPointerDown={(event) =>
                                                    canEdit &&
                                                    onPointerDown(event, field, index, 'move')
                                                }
                                            >
                                                <div
                                                    className={`relative h-full w-full overflow-hidden ${
                                                        active
                                                            ? 'bg-[#1A3694]/8'
                                                            : 'bg-emerald-500/8'
                                                    }`}
                                                >
                                                    <div
                                                        className={`pointer-events-none absolute inset-0 ${
                                                            active
                                                                ? 'ring-2 ring-[#1A3694] ring-inset'
                                                                : 'border border-emerald-600/70'
                                                        }`}
                                                    />
                                                    <span
                                                        className="pointer-events-none flex h-full items-start px-1 py-0.5"
                                                        style={{
                                                            fontFamily: cssFontFamily(field.font_family),
                                                            fontSize: `${Math.max(8, field.font_size)}px`,
                                                        }}
                                                    >
                                                        <span className="truncate font-medium text-slate-800">
                                                            {isTable ? `▦ ${field.label}` : field.label}
                                                        </span>
                                                    </span>
                                                </div>
                                                {active && canEdit && (
                                                    <>
                                                        <span className="pointer-events-none absolute -top-5 left-0 rounded bg-[#1A3694] px-1 py-0.5 text-[9px] text-white shadow-sm">
                                                            {field.data_source_key ?? field.name}
                                                        </span>
                                                        <span
                                                            className="absolute right-0 bottom-0 z-20 size-3 translate-x-1/3 translate-y-1/3 cursor-se-resize rounded-sm border border-white bg-[#1A3694] shadow-sm"
                                                            onPointerDown={(event) =>
                                                                onPointerDown(event, field, index, 'resize')
                                                            }
                                                        />
                                                    </>
                                                )}
                                            </div>
                                        );
                                    })}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <CanvasToolbar
                            page={page}
                            pageCount={pageCount}
                            zoom={zoom}
                            pageWidthMm={revision.page_width_mm}
                            pageHeightMm={revision.page_height_mm}
                            snapEnabled={snapEnabled}
                            onSnapEnabledChange={setSnapEnabled}
                            onPageChange={setPage}
                            onZoomChange={setZoom}
                            onFitWidth={fitWidth}
                            onFitPage={fitPage}
                        />
                    </main>

                    <div
                        className={`min-h-0 overflow-hidden border-l shadow-sm ${
                            mobilePropertiesOpen
                                ? 'fixed inset-x-0 bottom-0 top-32 z-40 block bg-white xl:relative xl:inset-auto xl:top-auto xl:z-auto'
                                : 'hidden xl:block'
                        }`}
                    >
                        <PropertiesPanel
                            selected={selected}
                            groupedSources={groupedSources}
                            canEdit={canEdit}
                            onUpdate={updateSelected}
                            onDuplicate={duplicateSelected}
                            onDelete={deleteSelected}
                        />
                    </div>
                </div>
            </div>

            <PdfPreviewDialog
                open={previewOpen}
                onOpenChange={setPreviewOpen}
                title="Populated preview"
                description="Preview uses sample data unless a job order is selected. It is not saved as an official document."
                load={async () => {
                    const query = previewJobId ? `?job_order_id=${previewJobId}` : '';
                    const response = await fetch(
                        `/admin/controlled-forms/${form.id}/revisions/${revision.id}/preview${query}`,
                        { credentials: 'same-origin' },
                    );

                    if (!response.ok) {
                        throw new Error('Could not generate the preview.');
                    }

                    return {
                        blob: await response.blob(),
                        filename: `${form.form_code}-preview.pdf`,
                        title: 'Preview',
                    };
                }}
            />
        </>
    );
}

FormDesigner.layout = (page: React.ReactNode) => <DesignerLayout>{page}</DesignerLayout>;
