import { Bold, Copy, Trash2 } from 'lucide-react';
import { useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { DataSource, DesignerField } from '@/lib/controlled-forms';
import {
    fieldAnchor,
    patchFieldAnchor,
    fieldBold,
    fieldListIndentMm,
    fieldListStyle,
    fieldStaticText,
    minAnchorOffsetMm,
    patchFieldOptions,
    sharedAnchorBelow,
    shiftFieldFlow,
    textLineHeightMm,
    toggleInlineBoldSelection,
} from '@/lib/controlled-forms';
import { defaultTableColumns } from '@/components/form-designer/utils';
import MatrixCellProperties from '@/components/form-designer/matrix-cell-properties';
import type { MatrixCellSelection, MatrixColumnConfig, MatrixTableConfig } from '@/lib/dynamic-test-matrix';
import { matrixConfig } from '@/lib/dynamic-test-matrix';

type Props = {
    selected: DesignerField | null;
    selectedCount?: number;
    selectedFields?: DesignerField[];
    /** All fields on the form (for matrix anchor dropdown). */
    pageFields?: DesignerField[];
    groupedSources: Array<[string, DataSource[]]>;
    canEdit: boolean;
    matrixCellSelection?: MatrixCellSelection | null;
    onClearMatrixCell?: () => void;
    onUpdate: (patch: Partial<DesignerField>, recordHistory?: boolean) => void;
    onDuplicate: () => void;
    onDelete: () => void;
    matrixPreviewRows?: Array<Record<string, string>>;
    matrixDefaultConfig?: MatrixTableConfig | null;
    onUpdateMapped?: (
        mapField: (field: DesignerField, id: string) => Partial<DesignerField>,
        recordHistory?: boolean,
    ) => void;
};

function sharedAppearanceValue(
    fields: DesignerField[],
    key: 'font_size' | 'font_family' | 'alignment' | 'font_color',
): string {
    if (fields.length === 0) {
        return '';
    }

    const first = String(fields[0][key] ?? '');

    return fields.every((field) => String(field[key] ?? '') === first) ? first : '';
}

function sharedHeightMm(fields: DesignerField[]): string {
    if (fields.length === 0) {
        return '';
    }

    const first = Number(fields[0].height);

    return fields.every((field) => Number(field.height) === first) ? String(first) : '';
}

const TABLE_COLUMN_OPTIONS: Record<string, string[]> = {
    'samples[]': ['sample_code', 'description', 'control_number', 'matrix', 'quantity', 'unit', 'remarks'],
    'analyses[]': [
        'name',
        'category',
        'unit_price',
        'total_cost',
        'result_value',
        'result_unit',
        'result_remarks',
    ],
};

export default function PropertiesPanel({
    selected,
    selectedCount = selected ? 1 : 0,
    selectedFields = selected ? [selected] : [],
    pageFields = [],
    groupedSources,
    canEdit,
    matrixCellSelection = null,
    onClearMatrixCell,
    onUpdate,
    onUpdateMapped,
    onDuplicate,
    onDelete,
    matrixPreviewRows,
    matrixDefaultConfig = null,
}: Props) {
    const staticTextRef = useRef<HTMLTextAreaElement | null>(null);
    const [stackGapMm, setStackGapMm] = useState(1);

    if (selectedCount > 1) {
        const fontSize = sharedAppearanceValue(selectedFields, 'font_size');
        const fontFamily = sharedAppearanceValue(selectedFields, 'font_family');
        const alignment = sharedAppearanceValue(selectedFields, 'alignment');
        const fontColor = sharedAppearanceValue(selectedFields, 'font_color');
        const heightMm = sharedHeightMm(selectedFields);
        const pageNumber = selectedFields[0]?.page_number ?? 1;
        const matrixAnchors = pageFields.filter(
            (field) =>
                field.field_type === 'dynamic_test_matrix' &&
                field.page_number === pageNumber,
        );
        const sharedBelow = sharedAnchorBelow(selectedFields);
        const minOffset = minAnchorOffsetMm(selectedFields);
        const belowMixed =
            selectedFields.some((field) => fieldAnchor(field) !== null) &&
            sharedBelow === null &&
            !selectedFields.every((field) => fieldAnchor(field) === null);

        function applyGroupBelow(below: string) {
            if (!onUpdateMapped) {
                return;
            }
            onUpdateMapped((field) => {
                const current = fieldAnchor(field);
                if (below === '') {
                    return {
                        options: patchFieldOptions(field.options, { anchor: null }),
                    };
                }
                const next = patchFieldAnchor(current, {
                    below,
                    offset_mm: current?.offset_mm ?? minOffset ?? 3,
                });

                return {
                    options: patchFieldOptions(field.options, { anchor: next }),
                };
            }, true);
        }

        function applyGroupOffset(nextOffset: number) {
            if (!onUpdateMapped || minOffset === null || !Number.isFinite(nextOffset)) {
                return;
            }
            const delta = nextOffset - minOffset;
            if (Math.abs(delta) < 0.0001) {
                return;
            }
            onUpdateMapped((field) => shiftFieldFlow(field, delta));
        }

        function spaceStack() {
            if (!onUpdateMapped) {
                return;
            }
            const gapMm = Number.isFinite(stackGapMm) ? Math.max(0, stackGapMm) : 0;
            const ordered = [...selectedFields].sort((a, b) => a.y - b.y || a.x - b.x);
            if (ordered.length < 2) {
                return;
            }
            const top = ordered[0];
            const sharedBelowName = sharedAnchorBelow(ordered);
            const topAnchor = fieldAnchor(top);
            const syncOffsets =
                sharedBelowName !== null &&
                topAnchor !== null &&
                ordered.every((field) => fieldAnchor(field) !== null);
            const positions = new Map<
                string,
                { y: number; height: number; offset_mm?: number }
            >();
            let cursorY = top.y;
            let cursorOffset = syncOffsets ? topAnchor.offset_mm : null;

            for (let index = 0; index < ordered.length; index += 1) {
                const field = ordered[index];
                const lineH = textLineHeightMm(field.font_size);
                if (index === 0) {
                    positions.set(field.name, {
                        y: field.y,
                        height: lineH,
                        ...(cursorOffset !== null ? { offset_mm: cursorOffset } : {}),
                    });
                } else {
                    cursorY = Math.round((cursorY + gapMm) * 1000) / 1000;
                    if (cursorOffset !== null) {
                        cursorOffset = Math.round((cursorOffset + gapMm) * 1000) / 1000;
                    }
                    positions.set(field.name, {
                        y: cursorY,
                        height: lineH,
                        ...(cursorOffset !== null ? { offset_mm: cursorOffset } : {}),
                    });
                }
                cursorY = Math.round((cursorY + lineH) * 1000) / 1000;
                if (cursorOffset !== null) {
                    cursorOffset = Math.round((cursorOffset + lineH) * 1000) / 1000;
                }
            }

            onUpdateMapped((field) => {
                const next = positions.get(field.name);
                if (!next) {
                    return {};
                }
                const current = fieldAnchor(field);
                if (current && next.offset_mm !== undefined) {
                    return {
                        y: next.y,
                        height: next.height,
                        options: patchFieldOptions(field.options, {
                            anchor: patchFieldAnchor(current, {
                                offset_mm: next.offset_mm,
                            }),
                        }),
                    };
                }

                return { y: next.y, height: next.height };
            }, true);
        }

        return (
            <aside className="flex h-full min-h-0 flex-col bg-white">
                <div className="shrink-0 border-b px-3 py-3">
                    <h2 className="text-[11px] font-semibold tracking-wider text-nppc-900 uppercase">
                        Field Properties
                    </h2>
                    <p className="mt-1 text-xs text-muted-foreground">
                        {selectedCount} fields selected
                    </p>
                </div>
                <div className="min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3">
                    <section className="space-y-2">
                        <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Appearance
                        </p>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Changes apply to every selected field. Empty means mixed values.
                        </p>
                        <div>
                            <Label htmlFor="multi-height">Height (mm)</Label>
                            <Input
                                id="multi-height"
                                type="number"
                                min={0.5}
                                step="0.1"
                                disabled={!canEdit}
                                placeholder="Mixed"
                                value={heightMm}
                                onChange={(event) => {
                                    const value = Number(event.target.value);

                                    if (!Number.isFinite(value) || event.target.value === '') {
                                        return;
                                    }

                                    onUpdate({ height: Math.max(0.5, value) });
                                }}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="multi-font-size">Font size</Label>
                            <Input
                                id="multi-font-size"
                                type="number"
                                step="0.5"
                                disabled={!canEdit}
                                placeholder="Mixed"
                                value={fontSize}
                                onChange={(event) => {
                                    const value = Number(event.target.value);

                                    if (!Number.isFinite(value) || event.target.value === '') {
                                        return;
                                    }

                                    onUpdate({ font_size: value });
                                }}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="multi-font-family">Font</Label>
                            <select
                                id="multi-font-family"
                                className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                disabled={!canEdit}
                                value={fontFamily}
                                onChange={(event) =>
                                    onUpdate({ font_family: event.target.value }, true)
                                }
                            >
                                {!fontFamily && <option value="">Mixed</option>}
                                <option value="calibri">Calibri</option>
                                <option value="helvetica">Helvetica</option>
                                <option value="times">Times</option>
                                <option value="courier">Courier</option>
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="multi-align">Alignment</Label>
                            <select
                                id="multi-align"
                                className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                disabled={!canEdit}
                                value={alignment}
                                onChange={(event) =>
                                    onUpdate({ alignment: event.target.value }, true)
                                }
                            >
                                {!alignment && <option value="">Mixed</option>}
                                <option value="L">Left</option>
                                <option value="C">Center</option>
                                <option value="R">Right</option>
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="multi-color">Text color</Label>
                            <Input
                                id="multi-color"
                                type="color"
                                disabled={!canEdit}
                                value={fontColor.startsWith('#') ? fontColor : '#000000'}
                                onChange={(event) =>
                                    onUpdate({ font_color: event.target.value }, true)
                                }
                            />
                            {!fontColor && (
                                <p className="mt-1 text-[10px] text-muted-foreground">
                                    Mixed colors — picking one applies to all.
                                </p>
                            )}
                        </div>
                    </section>

                    <Separator />

                    <section className="space-y-2">
                        <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                            Flow under matrix
                        </p>
                        <p className="text-[11px] leading-snug text-muted-foreground">
                            Gap is between text lines; field heights are fitted to the font.
                        </p>
                        <Label htmlFor="multi-anchor-below">Anchor below matrix</Label>
                        <select
                            id="multi-anchor-below"
                            className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                            disabled={!canEdit || matrixAnchors.length === 0 || !onUpdateMapped}
                            value={sharedBelow ?? ''}
                            onChange={(event) => applyGroupBelow(event.target.value)}
                        >
                            <option value="">
                                {belowMixed ? 'Mixed' : 'None (fixed Y)'}
                            </option>
                            {matrixAnchors.map((matrix) => (
                                <option key={matrix.name} value={matrix.name}>
                                    {matrix.label || matrix.name}
                                </option>
                            ))}
                        </select>
                        {minOffset !== null ? (
                            <div>
                                <Label htmlFor="multi-anchor-offset">
                                    Offset below table (mm)
                                </Label>
                                <Input
                                    id="multi-anchor-offset"
                                    type="number"
                                    step="0.1"
                                    disabled={!canEdit || !onUpdateMapped}
                                    value={minOffset}
                                    onChange={(event) => {
                                        const value = Number(event.target.value);
                                        if (!Number.isFinite(value)) {
                                            return;
                                        }
                                        applyGroupOffset(value);
                                    }}
                                    onBlur={() => onUpdateMapped?.((field) => ({}), true)}
                                />
                            </div>
                        ) : (
                            <p className="text-[11px] text-muted-foreground">
                                Set an anchor below a matrix to edit group offset.
                            </p>
                        )}
                        <div>
                            <Label htmlFor="multi-stack-gap">Space between fields (mm)</Label>
                            <Input
                                id="multi-stack-gap"
                                type="number"
                                min={0}
                                step="0.1"
                                disabled={!canEdit || !onUpdateMapped}
                                value={stackGapMm}
                                onChange={(event) => {
                                    const value = Number(event.target.value);
                                    if (!Number.isFinite(value)) {
                                        return;
                                    }
                                    setStackGapMm(Math.max(0, value));
                                }}
                            />
                        </div>
                        {canEdit && onUpdateMapped ? (
                            <Button
                                type="button"
                                variant="outline"
                                size="sm"
                                className="w-full"
                                onClick={spaceStack}
                            >
                                Space stack
                            </Button>
                        ) : null}
                    </section>

                    <Separator />

                    <div className="space-y-2">
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            Drag to move together, or press Delete to remove them.
                        </p>
                        {canEdit && (
                            <Button type="button" variant="destructive" size="sm" onClick={onDelete}>
                                <Trash2 className="size-3.5" />
                                Delete selected
                            </Button>
                        )}
                    </div>
                </div>
            </aside>
        );
    }

    if (!selected) {
        return (
            <aside className="flex h-full min-h-0 flex-col bg-white">
                <div className="shrink-0 border-b px-3 py-3">
                    <h2 className="text-[11px] font-semibold tracking-wider text-nppc-900 uppercase">
                        Field Properties
                    </h2>
                </div>
                <div className="flex flex-1 flex-col items-center justify-center px-6 text-center">
                    <div className="space-y-3">
                        <div className="text-3xl text-slate-300">✦</div>
                        <p className="text-sm font-medium text-slate-600">
                            Select a field on the PDF
                        </p>
                        <p className="text-xs leading-relaxed text-muted-foreground">
                            to configure its properties.
                        </p>
                        <ul className="mt-2 space-y-1 text-left text-xs text-muted-foreground">
                            <li>• Data source</li>
                            <li>• Position &amp; Size</li>
                            <li>• Font &amp; Alignment</li>
                            <li>• Options</li>
                        </ul>
                    </div>
                </div>
            </aside>
        );
    }

    const isTable = selected.field_type === 'table';
    const isMatrix = selected.field_type === 'dynamic_test_matrix';
    const isMultiline = selected.field_type === 'multiline';
    const anchor = fieldAnchor(selected);
    const staticText = fieldStaticText(selected);
    const listStyle = fieldListStyle(selected);
    const listIndentMm = fieldListIndentMm(selected);

    function applyInlineBoldSelection() {
        const el = staticTextRef.current;
        if (!el || !canEdit || !selected) {
            return;
        }
        const next = toggleInlineBoldSelection(
            el.value,
            el.selectionStart ?? 0,
            el.selectionEnd ?? 0,
        );
        onUpdate({
            options: patchFieldOptions(selected.options, {
                static_text: next.value.trim() === '' ? null : next.value,
            }),
        });
        requestAnimationFrame(() => {
            const node = staticTextRef.current;
            if (!node) {
                return;
            }
            node.focus();
            node.setSelectionRange(next.selectionStart, next.selectionEnd);
        });
    }
    const matrixAnchors = pageFields.filter(
        (field) =>
            field.field_type === 'dynamic_test_matrix' &&
            field.page_number === selected.page_number &&
            field.name !== selected.name,
    );
    const tableConfig = (selected.table_config ?? {}) as {
        row_height?: number;
        row_height_mm?: number;
        max_rows?: number;
        columns?: string[] | Array<{ key: string; label: string; width_pct: number }>;
        header_row?: boolean;
        border?: boolean;
        max_rows_per_page?: number;
    };
    const tableColumns = tableConfig.columns ?? [];
    const availableColumns =
        TABLE_COLUMN_OPTIONS[selected.data_source_key ?? ''] ?? defaultTableColumns(selected.data_source_key ?? '');
    const selectedSource = groupedSources
        .flatMap(([, items]) => items)
        .find((source) => source.key === selected.data_source_key);

    function toggleTableColumn(column: string) {
        const current = new Set(tableColumns);
        if (current.has(column)) {
            current.delete(column);
        } else {
            current.add(column);
        }

        onUpdate(
            {
                table_config: {
                    ...tableConfig,
                    columns: [...current],
                },
            },
            true,
        );
    }

    return (
        <aside className="flex h-full min-h-0 flex-col bg-white">
            <div className="border-b px-3 py-3">
                <h2 className="text-[11px] font-semibold tracking-wider text-nppc-900 uppercase">
                    Field Properties
                </h2>
                <p className="mt-1 truncate text-sm font-medium text-slate-900">{selected.label}</p>
            </div>

            <div className="designer-scroll min-h-0 flex-1 space-y-4 overflow-y-auto px-3 py-3 text-sm">
                <section className="space-y-2">
                    <Label htmlFor="prop-label">Display label</Label>
                    <Input
                        id="prop-label"
                        value={selected.label}
                        disabled={!canEdit}
                        onChange={(event) => onUpdate({ label: event.target.value })}
                        onBlur={() => onUpdate({}, true)}
                    />
                    <Label htmlFor="prop-name">Internal key</Label>
                    <Input
                        id="prop-name"
                        value={selected.name}
                        disabled={!canEdit}
                        className="font-mono text-xs"
                        onChange={(event) => onUpdate({ name: event.target.value })}
                    />
                    <Label htmlFor="prop-source">Data source</Label>
                    {isMatrix ? (
                        <p className="rounded-md border bg-amber-50 px-3 py-2 text-xs text-amber-900">
                            Rows are filled at runtime from selected job-order tests.
                            Only tests the customer chose at intake appear — waived
                            package members are omitted.
                        </p>
                    ) : (
                        <>
                            <select
                                id="prop-source"
                                className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                value={selected.data_source_key ?? ''}
                                disabled={!canEdit}
                                onChange={(event) =>
                                    onUpdate({ data_source_key: event.target.value || null }, true)
                                }
                            >
                                <option value="">Not mapped</option>
                                {groupedSources.map(([group, items]) => (
                                    <optgroup key={group} label={group}>
                                        {items.map((source) => (
                                            <option key={source.key} value={source.key}>
                                                {source.label}
                                            </option>
                                        ))}
                                    </optgroup>
                                ))}
                            </select>
                            {selectedSource?.hint && (
                                <p className="text-[11px] leading-snug text-muted-foreground">
                                    {selectedSource.hint}
                                </p>
                            )}
                        </>
                    )}
                </section>

                {!isMatrix && !isTable && (
                    <>
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Flow under matrix
                            </p>
                            <Label htmlFor="prop-anchor-below">
                                Anchor below matrix
                            </Label>
                            <select
                                id="prop-anchor-below"
                                className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                disabled={!canEdit || matrixAnchors.length === 0}
                                value={anchor?.below ?? ''}
                                onChange={(event) => {
                                    const below = event.target.value;
                                    onUpdate(
                                        {
                                            options: patchFieldOptions(
                                                selected.options,
                                                {
                                                    anchor:
                                                        below === ''
                                                            ? null
                                                            : patchFieldAnchor(
                                                                  anchor,
                                                                  { below },
                                                              ),
                                                },
                                            ),
                                        },
                                        true,
                                    );
                                }}
                            >
                                <option value="">None (fixed Y)</option>
                                {matrixAnchors.map((matrix) => (
                                    <option key={matrix.name} value={matrix.name}>
                                        {matrix.label || matrix.name}
                                    </option>
                                ))}
                            </select>
                            {anchor ? (
                                <div>
                                    <Label htmlFor="prop-anchor-offset">
                                        Offset below table (mm)
                                    </Label>
                                    <Input
                                        id="prop-anchor-offset"
                                        type="number"
                                        step="0.1"
                                        disabled={!canEdit}
                                        value={anchor.offset_mm}
                                        onChange={(event) =>
                                            onUpdate({
                                                options: patchFieldOptions(
                                                    selected.options,
                                                    {
                                                        anchor: patchFieldAnchor(
                                                            anchor,
                                                            {
                                                                offset_mm: Number(
                                                                    event.target
                                                                        .value,
                                                                ),
                                                            },
                                                        ),
                                                    },
                                                ),
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                            ) : null}
                            <p className="text-[11px] leading-snug text-muted-foreground">
                                Print Y follows the drawn table bottom (Word-like).
                                Stored Y is the fallback when the matrix is empty.
                            </p>
                            <Separator />
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Footer / printed text
                            </p>
                            <div className="flex items-center justify-between gap-2">
                                <Label htmlFor="prop-static-text">
                                    Static text (what prints)
                                </Label>
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-7 gap-1 px-2 text-[11px]"
                                    disabled={!canEdit}
                                    title="Bold the selected text (**markers**)"
                                    onMouseDown={(event) => {
                                        // Keep textarea selection when clicking the button.
                                        event.preventDefault();
                                    }}
                                    onClick={() => applyInlineBoldSelection()}
                                >
                                    <Bold className="size-3.5" />
                                    Bold selection
                                </Button>
                            </div>
                            <textarea
                                id="prop-static-text"
                                ref={staticTextRef}
                                className="min-h-36 w-full rounded-md border bg-white px-2 py-1.5 font-mono text-xs leading-snug"
                                disabled={!canEdit}
                                placeholder="Printed when no data source value (notes, labels, titles…). Select text and click Bold selection."
                                value={staticText}
                                onChange={(event) =>
                                    onUpdate({
                                        options: patchFieldOptions(
                                            selected.options,
                                            {
                                                static_text:
                                                    event.target.value.trim() ===
                                                    ''
                                                        ? null
                                                        : event.target.value,
                                            },
                                        ),
                                    })
                                }
                                onBlur={() => onUpdate({}, true)}
                            />
                            <p className="text-[11px] leading-snug text-muted-foreground">
                                Line breaks match the PDF. Wrap phrases in{' '}
                                <span className="font-mono">**bold**</span> (or
                                use Bold selection). Whole-field Bold in
                                Appearance still bolds everything.
                            </p>
                            {isMultiline ? (
                                <>
                                    <Separator />
                                    <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                        List layout
                                    </p>
                                    <Label htmlFor="prop-list-style">
                                        List style
                                    </Label>
                                    <select
                                        id="prop-list-style"
                                        className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                        disabled={!canEdit}
                                        value={listStyle ?? ''}
                                        onChange={(event) => {
                                            const next = event.target.value;
                                            onUpdate(
                                                {
                                                    options: patchFieldOptions(
                                                        selected.options,
                                                        {
                                                            list_style:
                                                                next === ''
                                                                    ? null
                                                                    : next,
                                                            list_indent_mm:
                                                                next === ''
                                                                    ? null
                                                                    : listIndentMm,
                                                        },
                                                    ),
                                                },
                                                true,
                                            );
                                        }}
                                    >
                                        <option value="">None</option>
                                        <option value="numbered">
                                            Numbered
                                        </option>
                                        <option value="bullet">Bullet</option>
                                    </select>
                                    <p className="text-[11px] leading-snug text-muted-foreground">
                                        Numbered lines must start with{' '}
                                        <span className="font-mono">1. </span>/
                                        <span className="font-mono"> 2. </span>
                                        (or <span className="font-mono">- </span>
                                        for bullets). A header like{' '}
                                        <span className="font-mono">NOTES :</span>{' '}
                                        stays above the list. Wrapped lines stay
                                        indented under the body text.
                                    </p>
                                    {listStyle ? (
                                        <div>
                                            <Label htmlFor="prop-list-indent">
                                                List indent (mm)
                                            </Label>
                                            <Input
                                                id="prop-list-indent"
                                                type="number"
                                                step="0.5"
                                                min={4}
                                                disabled={!canEdit}
                                                value={listIndentMm}
                                                onChange={(event) =>
                                                    onUpdate({
                                                        options:
                                                            patchFieldOptions(
                                                                selected.options,
                                                                {
                                                                    list_indent_mm:
                                                                        Number(
                                                                            event
                                                                                .target
                                                                                .value,
                                                                        ),
                                                                },
                                                            ),
                                                    })
                                                }
                                                onBlur={() =>
                                                    onUpdate({}, true)
                                                }
                                            />
                                        </div>
                                    ) : null}
                                </>
                            ) : null}
                        </section>
                    </>
                )}

                <Separator />

                <section className="space-y-2">
                    <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                        Position (mm)
                    </p>
                    <div className="grid grid-cols-2 gap-2">
                        <div>
                            <Label htmlFor="prop-x">X</Label>
                            <Input
                                id="prop-x"
                                type="number"
                                step="0.1"
                                disabled={!canEdit}
                                value={selected.x}
                                onChange={(event) => onUpdate({ x: Number(event.target.value) })}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="prop-y">Y</Label>
                            <Input
                                id="prop-y"
                                type="number"
                                step="0.1"
                                disabled={!canEdit}
                                value={selected.y}
                                onChange={(event) => onUpdate({ y: Number(event.target.value) })}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="prop-w">Width</Label>
                            <Input
                                id="prop-w"
                                type="number"
                                step="0.1"
                                disabled={!canEdit}
                                value={selected.width}
                                onChange={(event) => onUpdate({ width: Number(event.target.value) })}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                        <div>
                            <Label htmlFor="prop-h">Height</Label>
                            <Input
                                id="prop-h"
                                type="number"
                                step="0.1"
                                disabled={!canEdit}
                                value={selected.height}
                                onChange={(event) => onUpdate({ height: Number(event.target.value) })}
                                onBlur={() => onUpdate({}, true)}
                            />
                        </div>
                    </div>
                </section>

                {!isTable && !isMatrix && (
                    <>
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Appearance
                            </p>
                            <div>
                                <Label htmlFor="prop-font-size">Font size</Label>
                                <Input
                                    id="prop-font-size"
                                    type="number"
                                    step="0.5"
                                    disabled={!canEdit}
                                    value={selected.font_size}
                                    onChange={(event) =>
                                        onUpdate({ font_size: Number(event.target.value) })
                                    }
                                    onBlur={() => onUpdate({}, true)}
                                />
                            </div>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox
                                    checked={fieldBold(selected)}
                                    disabled={!canEdit}
                                    onCheckedChange={(checked) =>
                                        onUpdate(
                                            {
                                                options: patchFieldOptions(selected.options, {
                                                    bold: checked === true ? true : null,
                                                }),
                                            },
                                            true,
                                        )
                                    }
                                />
                                Bold
                            </label>
                            <div>
                                <Label htmlFor="prop-font-family">Font</Label>
                                <select
                                    id="prop-font-family"
                                    className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                    disabled={!canEdit}
                                    value={selected.font_family}
                                    onChange={(event) =>
                                        onUpdate({ font_family: event.target.value }, true)
                                    }
                                >
                                    <option value="calibri">Calibri</option>
                                    <option value="helvetica">Helvetica</option>
                                    <option value="times">Times</option>
                                    <option value="courier">Courier</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="prop-align">Alignment</Label>
                                <select
                                    id="prop-align"
                                    className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                    disabled={!canEdit}
                                    value={selected.alignment}
                                    onChange={(event) =>
                                        onUpdate({ alignment: event.target.value }, true)
                                    }
                                >
                                    <option value="L">Left</option>
                                    <option value="C">Center</option>
                                    <option value="R">Right</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="prop-color">Text color</Label>
                                <Input
                                    id="prop-color"
                                    type="color"
                                    disabled={!canEdit}
                                    value={selected.font_color.startsWith('#') ? selected.font_color : '#000000'}
                                    onChange={(event) =>
                                        onUpdate({ font_color: event.target.value }, true)
                                    }
                                />
                            </div>
                        </section>
                    </>
                )}

                {selected.field_type === 'checkbox' && (
                    <>
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Checkbox
                            </p>
                            <div>
                                <Label htmlFor="prop-check-value">Checked when value equals</Label>
                                <Input
                                    id="prop-check-value"
                                    disabled={!canEdit}
                                    value={selected.checkbox_true_value ?? ''}
                                    onChange={(event) =>
                                        onUpdate({
                                            checkbox_true_value: event.target.value || null,
                                        })
                                    }
                                    onBlur={() => onUpdate({}, true)}
                                />
                            </div>
                        </section>
                    </>
                )}

                {isMatrix && matrixCellSelection && (
                    <>
                        <MatrixCellProperties
                            field={selected}
                            selection={matrixCellSelection}
                            canEdit={canEdit}
                            onUpdate={onUpdate}
                            onClearSelection={() => onClearMatrixCell?.()}
                            packagePreviewRows={matrixPreviewRows}
                        />
                        <Separator />
                    </>
                )}

                {isMatrix && (
                    <>
                        {!matrixCellSelection && (
                            <>
                                <Separator />
                                <section className="space-y-2">
                                    <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                        Table defaults
                                    </p>
                                    <p className="text-[10px] text-muted-foreground">
                                        Click or double-click a cell in the table to edit that
                                        cell only. Defaults below apply when a column has no
                                        override.
                                    </p>
                                </section>
                            </>
                        )}
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                {matrixCellSelection ? 'Table defaults' : 'Table typography'}
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label htmlFor="prop-matrix-font-size">Body font size</Label>
                                    <Input
                                        id="prop-matrix-font-size"
                                        type="number"
                                        step="0.5"
                                        min={6}
                                        max={14}
                                        disabled={!canEdit}
                                        value={selected.font_size}
                                        onChange={(event) =>
                                            onUpdate({ font_size: Number(event.target.value) })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-matrix-header-font">Header font size</Label>
                                    <Input
                                        id="prop-matrix-header-font"
                                        type="number"
                                        step="0.5"
                                        min={6}
                                        max={14}
                                        disabled={!canEdit}
                                        value={
                                            (tableConfig as MatrixTableConfig).header_font_size ??
                                            selected.font_size
                                        }
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    header_font_size: Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-matrix-method-font">Method font size</Label>
                                    <Input
                                        id="prop-matrix-method-font"
                                        type="number"
                                        step="0.5"
                                        min={5}
                                        max={12}
                                        disabled={!canEdit}
                                        value={
                                            (tableConfig as MatrixTableConfig).method_font_size ??
                                            Math.max(6, selected.font_size - 1)
                                        }
                                        onChange={(event) => {
                                            const next = Number(event.target.value);
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    method_font_size:
                                                        Number.isFinite(next) && next > 0
                                                            ? next
                                                            : Math.max(
                                                                  6,
                                                                  selected.font_size - 1,
                                                              ),
                                                },
                                            });
                                        }}
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                            </div>
                            <div>
                                <Label htmlFor="prop-matrix-font-family">Font</Label>
                                <select
                                    id="prop-matrix-font-family"
                                    className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                    disabled={!canEdit}
                                    value={selected.font_family}
                                    onChange={(event) =>
                                        onUpdate({ font_family: event.target.value }, true)
                                    }
                                >
                                    <option value="calibri">Calibri</option>
                                    <option value="helvetica">Helvetica</option>
                                    <option value="times">Times</option>
                                    <option value="courier">Courier</option>
                                </select>
                            </div>
                            <div>
                                <Label htmlFor="prop-matrix-color">Text color</Label>
                                <Input
                                    id="prop-matrix-color"
                                    type="color"
                                    disabled={!canEdit}
                                    value={
                                        selected.font_color.startsWith('#')
                                            ? selected.font_color
                                            : '#000000'
                                    }
                                    onChange={(event) =>
                                        onUpdate({ font_color: event.target.value }, true)
                                    }
                                />
                            </div>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox
                                    checked={(tableConfig as MatrixTableConfig).header_bold !== false}
                                    disabled={!canEdit}
                                    onCheckedChange={(checked) =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...tableConfig,
                                                    header_bold: checked === true,
                                                },
                                            },
                                            true,
                                        )
                                    }
                                />
                                Bold header labels
                            </label>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox
                                    checked={(tableConfig as MatrixTableConfig).test_name_bold !== false}
                                    disabled={!canEdit}
                                    onCheckedChange={(checked) =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...tableConfig,
                                                    test_name_bold: checked === true,
                                                },
                                            },
                                            true,
                                        )
                                    }
                                />
                                Bold test names
                            </label>
                        </section>
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Dynamic test matrix
                            </p>
                            {matrixDefaultConfig && Object.keys(matrixDefaultConfig).length > 0 && (
                                <Button
                                    type="button"
                                    variant="outline"
                                    size="sm"
                                    className="h-8 w-full text-xs"
                                    disabled={!canEdit}
                                    onClick={() =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...matrixDefaultConfig,
                                                },
                                            },
                                            true,
                                        )
                                    }
                                >
                                    Apply package preset
                                </Button>
                            )}
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label htmlFor="prop-matrix-row-height">Body row height (mm)</Label>
                                    <Input
                                        id="prop-matrix-row-height"
                                        type="number"
                                        step="0.1"
                                        disabled={!canEdit}
                                        value={tableConfig.row_height_mm ?? 7}
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    row_height_mm: Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-matrix-rows-per-page">Rows per page</Label>
                                    <Input
                                        id="prop-matrix-rows-per-page"
                                        type="number"
                                        min={0}
                                        step={1}
                                        disabled={!canEdit}
                                        placeholder="10 on FO28"
                                        value={tableConfig.max_rows_per_page ?? ''}
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    max_rows_per_page:
                                                        event.target.value === ''
                                                            ? undefined
                                                            : Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-matrix-row-extra">Row spacing (mm)</Label>
                                    <Input
                                        id="prop-matrix-row-extra"
                                        type="number"
                                        step="0.1"
                                        min={0}
                                        disabled={!canEdit}
                                        placeholder="0"
                                        value={(tableConfig as MatrixTableConfig).row_extra_mm ?? ''}
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    row_extra_mm:
                                                        event.target.value === '' || Number(event.target.value) <= 0
                                                            ? undefined
                                                            : Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-matrix-header-row-height">Header row height (mm)</Label>
                                    <Input
                                        id="prop-matrix-header-row-height"
                                        type="number"
                                        step="0.1"
                                        min={3}
                                        disabled={!canEdit}
                                        placeholder="Auto"
                                        value={
                                            (tableConfig as MatrixTableConfig).header_row_height_mm ??
                                            ''
                                        }
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    header_row_height_mm:
                                                        event.target.value === ''
                                                            ? undefined
                                                            : Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                            </div>
                            <p className="text-[10px] text-muted-foreground">
                                Header height auto-expands for sublabels (e.g. SS: 18g). Set a
                                minimum here if needed.
                            </p>
                            <div>
                                <Label htmlFor="prop-matrix-rows">Rows</Label>
                                <select
                                    id="prop-matrix-rows"
                                    className="h-9 w-full rounded-md border bg-white px-2 text-sm"
                                    disabled={!canEdit}
                                    value={matrixConfig(selected).stretch_body === false ? 'natural' : 'stretch'}
                                    onChange={(event) =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...tableConfig,
                                                    stretch_body: event.target.value !== 'natural',
                                                },
                                            },
                                            true,
                                        )
                                    }
                                >
                                    <option value="stretch">Stretch to fill box</option>
                                    <option value="natural">Natural height (table ends after last test)</option>
                                </select>
                                {matrixConfig(selected).stretch_body === false && (
                                    <p className="mt-1 text-[10px] text-muted-foreground">
                                        Drag the table&apos;s last line to make the whole table taller or
                                        shorter; spacing is shared by every row.
                                    </p>
                                )}
                            </div>
                            {Object.keys((tableConfig as MatrixTableConfig).row_heights_mm ?? {}).length > 0 && (
                                <div className="space-y-1.5">
                                    <div className="flex items-center justify-between">
                                        <Label>Custom row heights (mm)</Label>
                                        <Button
                                            type="button"
                                            variant="ghost"
                                            size="sm"
                                            className="h-6 px-2 text-xs"
                                            disabled={!canEdit}
                                            onClick={() =>
                                                onUpdate(
                                                    { table_config: { ...tableConfig, row_heights_mm: undefined } },
                                                    true,
                                                )
                                            }
                                        >
                                            Reset row heights
                                        </Button>
                                    </div>
                                    {Object.entries((tableConfig as MatrixTableConfig).row_heights_mm ?? {}).map(
                                        ([rowKey, heightMm]) => (
                                            <div key={rowKey} className="flex items-center gap-2">
                                                <span className="min-w-0 flex-1 truncate text-xs" title={rowKey}>
                                                    {rowKey}
                                                </span>
                                                <Input
                                                    type="number"
                                                    step="0.5"
                                                    min={3}
                                                    max={60}
                                                    className="h-7 w-20"
                                                    disabled={!canEdit}
                                                    value={heightMm}
                                                    aria-label={`Row height for ${rowKey}`}
                                                    onChange={(event) =>
                                                        onUpdate({
                                                            table_config: {
                                                                ...tableConfig,
                                                                row_heights_mm: {
                                                                    ...(tableConfig as MatrixTableConfig).row_heights_mm,
                                                                    [rowKey]: Number(event.target.value),
                                                                },
                                                            },
                                                        })
                                                    }
                                                    onBlur={() => onUpdate({}, true)}
                                                />
                                                <Button
                                                    type="button"
                                                    variant="ghost"
                                                    size="sm"
                                                    className="h-7 px-2 text-xs"
                                                    disabled={!canEdit}
                                                    onClick={() => {
                                                        const remaining = Object.fromEntries(
                                                            Object.entries(
                                                                (tableConfig as MatrixTableConfig).row_heights_mm ?? {},
                                                            ).filter(([key]) => key !== rowKey),
                                                        );
                                                        onUpdate(
                                                            {
                                                                table_config: {
                                                                    ...tableConfig,
                                                                    row_heights_mm:
                                                                        Object.keys(remaining).length > 0
                                                                            ? remaining
                                                                            : undefined,
                                                                },
                                                            },
                                                            true,
                                                        );
                                                    }}
                                                >
                                                    Remove
                                                </Button>
                                            </div>
                                        ),
                                    )}
                                </div>
                            )}
                            <p className="text-[10px] text-muted-foreground">
                                On the canvas, select the table and drag like in Word: column
                                borders change widths, the header&apos;s bottom edge changes header
                                height, and each row&apos;s bottom edge changes that row&apos;s height
                                (Shift+drag changes all rows; double-click resets a row). With
                                natural-height rows, the table&apos;s last line adds even spacing to
                                every row. Rows still
                                grow when text wraps. The side and bottom handles resize the whole
                                table box. The canvas uses the same layout as Preview and the
                                generated PDF.
                            </p>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox
                                    checked={tableConfig.header_row !== false}
                                    disabled={!canEdit}
                                    onCheckedChange={(checked) =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...tableConfig,
                                                    header_row: checked === true,
                                                },
                                            },
                                            true,
                                        )
                                    }
                                />
                                Show column headers
                            </label>
                            <label className="flex items-center gap-2 text-xs">
                                <Checkbox
                                    checked={tableConfig.border !== false}
                                    disabled={!canEdit}
                                    onCheckedChange={(checked) =>
                                        onUpdate(
                                            {
                                                table_config: {
                                                    ...tableConfig,
                                                    border: checked === true,
                                                },
                                            },
                                            true,
                                        )
                                    }
                                />
                                Draw cell borders
                            </label>
                            <div>
                                <Label htmlFor="prop-matrix-preview-rows">Preview rows in designer</Label>
                                <p className="text-[11px] text-muted-foreground">
                                    Uses bound package members and Methods when
                                    available; otherwise sample proximate rows.
                                </p>
                                <Input
                                    id="prop-matrix-preview-rows"
                                    type="number"
                                    min={1}
                                    max={12}
                                    disabled={!canEdit}
                                    value={
                                        (tableConfig as { preview_rows?: number }).preview_rows ?? 8
                                    }
                                    onChange={(event) =>
                                        onUpdate({
                                            table_config: {
                                                ...tableConfig,
                                                preview_rows: Number(event.target.value),
                                            },
                                        })
                                    }
                                    onBlur={() => onUpdate({}, true)}
                                />
                            </div>
                            {Array.isArray(tableConfig.columns) &&
                                (tableConfig.columns as MatrixColumnConfig[]).length > 0 && (
                                    <div className="space-y-2">
                                        <Label>Columns</Label>
                                        {(tableConfig.columns as MatrixColumnConfig[]).map(
                                            (column, columnIndex) => (
                                                <div
                                                    key={column.key}
                                                    className="space-y-1.5 rounded-md border bg-slate-50/80 p-2"
                                                >
                                                    <p className="text-[10px] font-semibold uppercase text-slate-500">
                                                        {column.key}
                                                    </p>
                                                    <Input
                                                        disabled={!canEdit}
                                                        placeholder="Header label"
                                                        value={column.label}
                                                        onChange={(event) => {
                                                            const next = [
                                                                ...(tableConfig.columns as MatrixColumnConfig[]),
                                                            ];
                                                            next[columnIndex] = {
                                                                ...column,
                                                                label: event.target.value,
                                                            };
                                                            onUpdate({
                                                                table_config: {
                                                                    ...tableConfig,
                                                                    columns: next,
                                                                },
                                                            });
                                                        }}
                                                        onBlur={() => onUpdate({}, true)}
                                                    />
                                                    {column.key !== 'test' && (
                                                        <Input
                                                            disabled={!canEdit}
                                                            placeholder="Subtitle (e.g. SS: 18g)"
                                                            value={column.sublabel ?? ''}
                                                            onChange={(event) => {
                                                                const next = [
                                                                    ...(tableConfig.columns as MatrixColumnConfig[]),
                                                                ];
                                                                next[columnIndex] = {
                                                                    ...column,
                                                                    sublabel:
                                                                        event.target.value || null,
                                                                };
                                                                onUpdate({
                                                                    table_config: {
                                                                        ...tableConfig,
                                                                        columns: next,
                                                                    },
                                                                });
                                                            }}
                                                            onBlur={() => onUpdate({}, true)}
                                                        />
                                                    )}
                                                    <div className="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <Label className="text-[10px]">
                                                                Body align
                                                            </Label>
                                                            <select
                                                                className="h-8 w-full rounded-md border bg-white px-2 text-xs"
                                                                disabled={!canEdit}
                                                                value={column.align ?? (column.key === 'test' ? 'L' : 'C')}
                                                                onChange={(event) => {
                                                                    const next = [
                                                                        ...(tableConfig.columns as MatrixColumnConfig[]),
                                                                    ];
                                                                    next[columnIndex] = {
                                                                        ...column,
                                                                        align: event.target
                                                                            .value as 'L' | 'C' | 'R',
                                                                    };
                                                                    onUpdate({
                                                                        table_config: {
                                                                            ...tableConfig,
                                                                            columns: next,
                                                                        },
                                                                    });
                                                                }}
                                                            >
                                                                <option value="L">Left</option>
                                                                <option value="C">Center</option>
                                                                <option value="R">Right</option>
                                                            </select>
                                                        </div>
                                                        <div>
                                                            <Label className="text-[10px]">
                                                                Header align
                                                            </Label>
                                                            <select
                                                                className="h-8 w-full rounded-md border bg-white px-2 text-xs"
                                                                disabled={!canEdit}
                                                                value={column.header_align ?? 'C'}
                                                                onChange={(event) => {
                                                                    const next = [
                                                                        ...(tableConfig.columns as MatrixColumnConfig[]),
                                                                    ];
                                                                    next[columnIndex] = {
                                                                        ...column,
                                                                        header_align: event.target
                                                                            .value as 'L' | 'C' | 'R',
                                                                    };
                                                                    onUpdate({
                                                                        table_config: {
                                                                            ...tableConfig,
                                                                            columns: next,
                                                                        },
                                                                    });
                                                                }}
                                                            >
                                                                <option value="L">Left</option>
                                                                <option value="C">Center</option>
                                                                <option value="R">Right</option>
                                                            </select>
                                                        </div>
                                                    </div>
                                                    <div className="grid grid-cols-2 gap-2">
                                                        <div>
                                                            <Label className="text-[10px]">
                                                                Width %
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                min={5}
                                                                max={90}
                                                                disabled={!canEdit}
                                                                value={column.width_pct ?? ''}
                                                                placeholder="Auto"
                                                                onChange={(event) => {
                                                                    const next = [
                                                                        ...(tableConfig.columns as MatrixColumnConfig[]),
                                                                    ];
                                                                    next[columnIndex] = {
                                                                        ...column,
                                                                        width_pct: event.target.value
                                                                            ? Number(event.target.value)
                                                                            : undefined,
                                                                    };
                                                                    onUpdate({
                                                                        table_config: {
                                                                            ...tableConfig,
                                                                            columns: next,
                                                                        },
                                                                    });
                                                                }}
                                                                onBlur={() => onUpdate({}, true)}
                                                            />
                                                        </div>
                                                        <div>
                                                            <Label className="text-[10px]">
                                                                Font size
                                                            </Label>
                                                            <Input
                                                                type="number"
                                                                step="0.5"
                                                                min={6}
                                                                max={14}
                                                                disabled={!canEdit}
                                                                value={column.font_size ?? ''}
                                                                placeholder="Default"
                                                                onChange={(event) => {
                                                                    const next = [
                                                                        ...(tableConfig.columns as MatrixColumnConfig[]),
                                                                    ];
                                                                    next[columnIndex] = {
                                                                        ...column,
                                                                        font_size: event.target.value
                                                                            ? Number(event.target.value)
                                                                            : undefined,
                                                                    };
                                                                    onUpdate({
                                                                        table_config: {
                                                                            ...tableConfig,
                                                                            columns: next,
                                                                        },
                                                                    });
                                                                }}
                                                                onBlur={() => onUpdate({}, true)}
                                                            />
                                                        </div>
                                                    </div>
                                                </div>
                                            ),
                                        )}
                                    </div>
                                )}
                            <p className="text-[10px] text-muted-foreground">
                                Preview rows show sample data for layout only. Test names and
                                results at print time come from the job order.
                            </p>
                        </section>
                    </>
                )}

                {isTable && (
                    <>
                        <Separator />
                        <section className="space-y-2">
                            <p className="text-[10px] font-semibold tracking-wider text-muted-foreground uppercase">
                                Table field
                            </p>
                            <p className="text-xs text-muted-foreground">
                                Source:{' '}
                                <span className="font-mono text-slate-700">
                                    {selected.data_source_key ?? '—'}
                                </span>
                            </p>
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label htmlFor="prop-row-height">Row height (mm)</Label>
                                    <Input
                                        id="prop-row-height"
                                        type="number"
                                        step="0.1"
                                        disabled={!canEdit}
                                        value={tableConfig.row_height ?? 4.5}
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    row_height: Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                                <div>
                                    <Label htmlFor="prop-max-rows">Max rows</Label>
                                    <Input
                                        id="prop-max-rows"
                                        type="number"
                                        disabled={!canEdit}
                                        value={tableConfig.max_rows ?? 9}
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    max_rows: Number(event.target.value),
                                                },
                                            })
                                        }
                                        onBlur={() => onUpdate({}, true)}
                                    />
                                </div>
                            </div>
                            {availableColumns.length > 0 && (
                                <div>
                                    <Label>Columns</Label>
                                    <div className="mt-1 space-y-1">
                                        {availableColumns.map((column) => (
                                            <label
                                                key={column}
                                                className="flex items-center gap-2 rounded-md px-1 py-0.5 text-xs hover:bg-slate-50"
                                            >
                                                <input
                                                    type="checkbox"
                                                    disabled={!canEdit}
                                                    checked={tableColumns.includes(column)}
                                                    onChange={() => toggleTableColumn(column)}
                                                />
                                                {column.replace(/_/g, ' ')}
                                            </label>
                                        ))}
                                    </div>
                                </div>
                            )}
                            <p className="text-[10px] text-muted-foreground">
                                Rows are filled automatically from job order data. Extra rows continue on the same
                                page until max rows is reached.
                            </p>
                        </section>
                    </>
                )}
            </div>

            <div className="flex gap-2 border-t p-3">
                <Button variant="outline" size="sm" className="flex-1" disabled={!canEdit} onClick={onDuplicate}>
                    <Copy className="size-4" />
                    Duplicate
                </Button>
                <Button variant="destructive" size="sm" className="flex-1" disabled={!canEdit} onClick={onDelete}>
                    <Trash2 className="size-4" />
                    Delete
                </Button>
            </div>
        </aside>
    );
}
