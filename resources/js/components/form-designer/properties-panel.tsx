import { Copy, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Separator } from '@/components/ui/separator';
import type { DataSource, DesignerField } from '@/lib/controlled-forms';
import { defaultTableColumns } from '@/components/form-designer/utils';
import MatrixCellProperties from '@/components/form-designer/matrix-cell-properties';
import type { MatrixCellSelection, MatrixColumnConfig, MatrixTableConfig } from '@/lib/dynamic-test-matrix';

type Props = {
    selected: DesignerField | null;
    groupedSources: Array<[string, DataSource[]]>;
    canEdit: boolean;
    matrixCellSelection?: MatrixCellSelection | null;
    onClearMatrixCell?: () => void;
    onUpdate: (patch: Partial<DesignerField>, recordHistory?: boolean) => void;
    onDuplicate: () => void;
    onDelete: () => void;
};

const TABLE_COLUMN_OPTIONS: Record<string, string[]> = {
    'samples[]': ['sample_code', 'description', 'matrix', 'quantity', 'unit', 'remarks'],
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
    groupedSources,
    canEdit,
    matrixCellSelection = null,
    onClearMatrixCell,
    onUpdate,
    onDuplicate,
    onDelete,
}: Props) {
    if (!selected) {
        return (
            <aside className="flex h-full min-h-0 flex-col bg-white">
                <div className="shrink-0 border-b px-3 py-3">
                    <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
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
    const tableConfig = (selected.table_config ?? {}) as {
        row_height?: number;
        row_height_mm?: number;
        max_rows?: number;
        columns?: string[] | Array<{ key: string; label: string; width_pct: number }>;
        header_row?: boolean;
        border?: boolean;
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
                <h2 className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
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
                                            Math.max(5, selected.font_size - 1)
                                        }
                                        onChange={(event) =>
                                            onUpdate({
                                                table_config: {
                                                    ...tableConfig,
                                                    method_font_size: Number(event.target.value),
                                                },
                                            })
                                        }
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
                            <div className="grid grid-cols-2 gap-2">
                                <div>
                                    <Label htmlFor="prop-matrix-row-height">Body row height (mm)</Label>
                                    <Input
                                        id="prop-matrix-row-height"
                                        type="number"
                                        step="0.1"
                                        disabled={!canEdit}
                                        value={tableConfig.row_height_mm ?? 5}
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
