import { useEffect, useRef, useState } from 'react';
import {
    cellValue,
    columnBodyAlign,
    columnHeaderAlign,
    columnWidthPct,
    MATRIX_DESIGNER_DRAG_HANDLE_PX,
    matrixCellKey,
    matrixConfig,
    matrixHeaderFontSize,
    matrixHeaderSublines,
    matrixMethodFontSize,
    measureMatrixHeaderRowHeightMm,
    matrixPreviewData,
    stretchBodyRowHeightsMm,
    type MatrixCellSelection,
    type MatrixColumnConfig,
    type MatrixTableConfig,
} from '@/lib/dynamic-test-matrix';
import type { DesignerField } from '@/lib/controlled-forms';
import { cssFontFamily } from '@/components/form-designer/utils';

type Props = {
    field: DesignerField;
    widthPx: number;
    heightPx: number;
    canEdit: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    onUpdateTableConfig: (config: MatrixTableConfig, recordHistory?: boolean) => void;
    packagePreviewRows?: Array<Record<string, string>>;
};

export default function DynamicTestMatrixPreview({
    field,
    widthPx,
    heightPx,
    canEdit,
    selection,
    onSelectCell,
    onUpdateTableConfig,
    packagePreviewRows,
}: Props) {
    const config = matrixConfig(field);
    const columns = config.columns ?? [];
    const showHeader = config.header_row !== false;
    const showBorder = config.border !== false;
    const baseFontSize = field.font_size ?? 8;
    const headerBaseFontSize = matrixHeaderFontSize(baseFontSize, config);
    const rowHeightMm = config.row_height_mm ?? 7;
    const pxPerMm = widthPx / Math.max(field.width, 1);
    // Drag strip is Designer-only chrome above the table (FO2-style), not an overlay.
    const dragHandlePx = canEdit ? MATRIX_DESIGNER_DRAG_HANDLE_PX : 0;
    const printableHeightPx = Math.max(0, heightPx - dragHandlePx);
    const headerHeightMm = showHeader
        ? measureMatrixHeaderRowHeightMm(config, baseFontSize)
        : 0;
    const headerHeightPx = showHeader ? Math.max(14, headerHeightMm * pxPerMm) : 0;
    const availableBodyPx = Math.max(0, printableHeightPx - headerHeightPx);
    const availableBodyMm = pxPerMm > 0 ? availableBodyPx / pxPerMm : 0;

    // Residue F016 nitrite keeps natural row height (N samples → N short rows).
    const stretchBody = config.stretch_body !== false;

    // How many rows fit at the configured minimum row height (same clip idea as PDF).
    const minRowHeightPx = Math.max(14, rowHeightMm * pxPerMm);
    const maxBodyRows = Math.max(1, Math.floor(availableBodyPx / Math.max(minRowHeightPx, 1)));
    const previewRows = matrixPreviewData(config, packagePreviewRows).slice(0, maxBodyRows);

    const naturalHeightsMm = previewRows.map(() => rowHeightMm);
    const stretchedHeightsMm = stretchBody
        ? stretchBodyRowHeightsMm(naturalHeightsMm, availableBodyMm)
        : naturalHeightsMm;
    const defaultFontSize = Math.max(6, Math.min(11, baseFontSize * pxPerMm * 0.35));
    const methodSize = Math.max(5, matrixMethodFontSize(baseFontSize, config) * pxPerMm * 0.35);
    const textColor = field.font_color?.startsWith('#') ? field.font_color : '#000000';
    const headerBold = config.header_bold !== false;
    const testNameBold = config.test_name_bold !== false;

    function updateConfig(next: MatrixTableConfig, recordHistory = false) {
        onUpdateTableConfig(next, recordHistory);
    }

    return (
        <div
            className="absolute inset-0 z-[1] flex h-full flex-col bg-white/95"
            style={{ fontFamily: cssFontFamily(field.font_family), color: textColor }}
        >
            {canEdit && (
                <div
                    data-matrix-drag
                    className="flex shrink-0 cursor-move items-center justify-center border-b border-dashed border-slate-300 bg-slate-50/90 text-[8px] tracking-widest text-slate-400"
                    style={{ height: MATRIX_DESIGNER_DRAG_HANDLE_PX }}
                    title="Drag here to move the table region"
                >
                    ⋮⋮ drag
                </div>
            )}
            {/* Outer frame matches ControlledPdfFiller field-box Rect (Preview/Download). */}
            <div
                className={`box-border w-full overflow-hidden ${
                    stretchBody ? 'min-h-0 flex-1' : 'shrink-0'
                } ${showBorder ? 'border border-black' : ''}`}
            >
                <table
                    className="w-full border-collapse"
                    style={{
                        fontSize: `${defaultFontSize}px`,
                        lineHeight: 1.15,
                        color: textColor,
                        tableLayout: 'fixed',
                        height: stretchBody ? '100%' : 'auto',
                    }}
                >
                    {showHeader && (
                        <thead>
                            <tr style={{ height: headerHeightPx }}>
                                {columns.map((column) => (
                                    <MatrixHeaderCell
                                        key={column.key}
                                        column={column}
                                        columns={columns}
                                        widthPct={columnWidthPct(columns, columns.indexOf(column))}
                                        showBorder={showBorder}
                                        headerDefaultFontSize={Math.max(
                                            5,
                                            headerBaseFontSize * pxPerMm * 0.35,
                                        )}
                                        pxPerMm={pxPerMm}
                                        headerBold={headerBold}
                                        canEdit={canEdit}
                                        selection={selection}
                                        onSelectCell={onSelectCell}
                                        config={config}
                                        onUpdateConfig={updateConfig}
                                    />
                                ))}
                            </tr>
                        </thead>
                    )}
                    <tbody>
                        {previewRows.map((row, rowIndex) => {
                            const rowHeightPx = Math.max(
                                14,
                                (stretchedHeightsMm[rowIndex] ?? rowHeightMm) * pxPerMm,
                            );

                            return (
                                <tr
                                    key={rowIndex}
                                    style={{ height: rowHeightPx, maxHeight: rowHeightPx }}
                                >
                                    {columns.map((column) => (
                                        <MatrixBodyCell
                                            key={`${rowIndex}-${column.key}`}
                                            column={column}
                                            columns={columns}
                                            row={row}
                                            rowIndex={rowIndex}
                                            widthPct={columnWidthPct(
                                                columns,
                                                columns.indexOf(column),
                                            )}
                                            showBorder={showBorder}
                                            defaultFontSize={defaultFontSize}
                                            pxPerMm={pxPerMm}
                                            methodSize={methodSize}
                                            testNameBold={testNameBold}
                                            rowHeightPx={stretchBody ? undefined : rowHeightPx}
                                            canEdit={canEdit}
                                            selection={selection}
                                            onSelectCell={onSelectCell}
                                            config={config}
                                            onUpdateConfig={updateConfig}
                                            packagePreviewRows={packagePreviewRows}
                                        />
                                    ))}
                                </tr>
                            );
                        })}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

function alignClass(align: 'L' | 'C' | 'R'): string {
    return align === 'C' ? 'text-center' : align === 'R' ? 'text-right' : 'text-left';
}

function isSelected(
    selection: MatrixCellSelection | null,
    target: MatrixCellSelection,
): boolean {
    if (!selection) {
        return false;
    }

    return matrixCellKey(selection) === matrixCellKey(target);
}

function selectionRing(selected: boolean): string {
    return selected ? 'ring-2 ring-[#1A3694] ring-inset bg-[#1A3694]/10' : 'hover:bg-slate-50/80';
}

function InlineEditable({
    value,
    canEdit,
    selected,
    className,
    style,
    onSelect,
    onCommit,
    placeholder,
}: {
    value: string;
    canEdit: boolean;
    selected: boolean;
    className?: string;
    style?: React.CSSProperties;
    onSelect: () => void;
    onCommit: (text: string) => void;
    placeholder?: string;
}) {
    const [editing, setEditing] = useState(false);
    const [draft, setDraft] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);

    useEffect(() => {
        setDraft(value);
    }, [value]);

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    if (!canEdit) {
        if (value) {
            return (
                <span className={`whitespace-pre-line ${className ?? ''}`} style={style}>
                    {value}
                </span>
            );
        }

        if (placeholder) {
            return (
                <span className={`whitespace-pre-line opacity-40 ${className ?? ''}`} style={style}>
                    {placeholder}
                </span>
            );
        }

        return null;
    }

    if (editing) {
        return (
            <input
                ref={inputRef}
                className={`w-full min-w-0 border border-[#1A3694] bg-white px-0.5 outline-none ${className ?? ''}`}
                style={style}
                value={draft}
                onChange={(event) => setDraft(event.target.value)}
                onBlur={() => {
                    setEditing(false);
                    onCommit(draft);
                }}
                onKeyDown={(event) => {
                    if (event.key === 'Enter') {
                        event.preventDefault();
                        setEditing(false);
                        onCommit(draft);
                    }
                    if (event.key === 'Escape') {
                        setEditing(false);
                        setDraft(value);
                    }
                }}
                onClick={(event) => event.stopPropagation()}
                onPointerDown={(event) => event.stopPropagation()}
            />
        );
    }

    return (
        <span
            role="button"
            tabIndex={0}
            className={`relative z-[2] block cursor-text rounded-sm px-0.5 select-text whitespace-pre-line ${selectionRing(selected)} ${className ?? ''}`}
            style={style}
            onPointerDown={(event) => event.stopPropagation()}
            onClick={(event) => {
                event.stopPropagation();
                onSelect();
            }}
            onDoubleClick={(event) => {
                event.stopPropagation();
                onSelect();
                setEditing(true);
            }}
            onKeyDown={(event) => {
                if (event.key === 'Enter') {
                    setEditing(true);
                }
            }}
            title="Click to select · Double-click to edit"
        >
            {value ? (
                value
            ) : (
                <span className="opacity-40">{placeholder || '—'}</span>
            )}
        </span>
    );
}

function MatrixHeaderCell({
    column,
    columns,
    widthPct,
    showBorder,
    headerDefaultFontSize,
    pxPerMm,
    headerBold,
    canEdit,
    selection,
    onSelectCell,
    config,
    onUpdateConfig,
}: {
    column: MatrixColumnConfig;
    columns: MatrixColumnConfig[];
    widthPct: number;
    showBorder: boolean;
    headerDefaultFontSize: number;
    pxPerMm: number;
    headerBold: boolean;
    canEdit: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    config: MatrixTableConfig;
    onUpdateConfig: (config: MatrixTableConfig, recordHistory?: boolean) => void;
}) {
    const align = columnHeaderAlign(column);
    const subAlign = column.sublabel_align ?? align;
    const fontSize = column.header_font_size
        ? Math.max(5, column.header_font_size * pxPerMm * 0.35)
        : headerDefaultFontSize;
    const sublines = matrixHeaderSublines(column);
    const hasSublabel = sublines.length > 0;
    const labelTarget: MatrixCellSelection = {
        area: 'header',
        columnKey: column.key,
        part: 'label',
    };
    const sublabelTarget: MatrixCellSelection = {
        area: 'header',
        columnKey: column.key,
        part: 'sublabel',
    };

    return (
        <th
            className={`h-full align-middle uppercase ${headerBold ? 'font-bold' : 'font-semibold'} ${alignClass(align)} ${showBorder ? 'border border-black' : ''}`}
            style={{ width: `${widthPct}%`, padding: '2px 3px', fontSize }}
        >
            <div
                className={`flex h-full flex-col ${hasSublabel ? 'justify-start gap-0.5' : 'justify-center'} ${align === 'C' ? 'items-center' : align === 'R' ? 'items-end' : 'items-start'}`}
            >
                <InlineEditable
                    value={column.label}
                    canEdit={canEdit}
                    selected={isSelected(selection, labelTarget)}
                    className="uppercase"
                    style={{ fontSize }}
                    placeholder="Header"
                    onSelect={() => onSelectCell(labelTarget)}
                    onCommit={(text) =>
                        onUpdateConfig(
                            {
                                ...config,
                                columns: columns.map((item) =>
                                    item.key === column.key ? { ...item, label: text } : item,
                                ),
                            },
                            true,
                        )
                    }
                />
                {(sublines.length > 0 ||
                    isSelected(selection, sublabelTarget) ||
                    (canEdit && Boolean(column.sublabel))) && (
                    <div
                        className={`w-full ${
                            subAlign === 'C'
                                ? 'text-center'
                                : subAlign === 'R'
                                  ? 'text-right'
                                  : 'text-left'
                        }`}
                    >
                        <InlineEditable
                            value={column.sublabel ?? ''}
                            canEdit={canEdit}
                            selected={isSelected(selection, sublabelTarget)}
                            className="font-normal normal-case whitespace-pre-line"
                            style={{ fontSize: Math.max(5, fontSize - 1) }}
                            placeholder="+ Sublabel"
                            onSelect={() => onSelectCell(sublabelTarget)}
                            onCommit={(text) =>
                                onUpdateConfig(
                                    {
                                        ...config,
                                        columns: columns.map((item) =>
                                            item.key === column.key
                                                ? { ...item, sublabel: text || null, sublabels: null }
                                                : item,
                                        ),
                                    },
                                    true,
                                )
                            }
                        />
                    </div>
                )}
            </div>
        </th>
    );
}

function MatrixBodyCell({
    column,
    columns,
    row,
    rowIndex,
    widthPct,
    showBorder,
    defaultFontSize,
    pxPerMm,
    methodSize,
    testNameBold,
    rowHeightPx,
    canEdit,
    selection,
    onSelectCell,
    config,
    onUpdateConfig,
    packagePreviewRows,
}: {
    column: MatrixColumnConfig;
    columns: MatrixColumnConfig[];
    row: Record<string, string>;
    rowIndex: number;
    widthPct: number;
    showBorder: boolean;
    defaultFontSize: number;
    pxPerMm: number;
    methodSize: number;
    testNameBold: boolean;
    rowHeightPx?: number;
    canEdit: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    config: MatrixTableConfig;
    onUpdateConfig: (config: MatrixTableConfig, recordHistory?: boolean) => void;
    packagePreviewRows?: Array<Record<string, string>>;
}) {
    const align = alignClass(columnBodyAlign(column));
    const fontSize = column.font_size
        ? Math.max(5, column.font_size * pxPerMm * 0.35)
        : defaultFontSize;
    const cellHeightStyle =
        rowHeightPx !== undefined
            ? { height: rowHeightPx, maxHeight: rowHeightPx, overflow: 'hidden' as const }
            : undefined;

    function commitPreview(part: 'test' | 'test_method' | 'value', text: string) {
        const preview_data = matrixPreviewData(config, packagePreviewRows).map((previewRow, index) => {
            if (index !== rowIndex) {
                return previewRow;
            }

            if (part === 'test') {
                return { ...previewRow, test: text };
            }
            if (part === 'test_method') {
                return { ...previewRow, test_method: text };
            }

            return { ...previewRow, [column.key]: text };
        });

        onUpdateConfig({ ...config, preview_data }, true);
    }

    if (column.key === 'test') {
        const nestMethod = !columns.some((item) => item.key === 'method');
        const nameTarget: MatrixCellSelection = {
            area: 'body',
            rowIndex,
            columnKey: column.key,
            part: 'test',
        };
        const methodTarget: MatrixCellSelection = {
            area: 'body',
            rowIndex,
            columnKey: column.key,
            part: 'test_method',
        };

        return (
            <td
                className={`align-top ${align} ${showBorder ? 'border border-black' : ''}`}
                style={{ width: `${widthPct}%`, padding: '2px 4px', ...cellHeightStyle }}
            >
                <InlineEditable
                    value={row.test ?? ''}
                    canEdit={canEdit}
                    selected={isSelected(selection, nameTarget)}
                    className={testNameBold ? 'font-bold' : 'font-medium'}
                    style={{ fontSize }}
                    placeholder="Test name"
                    onSelect={() => onSelectCell(nameTarget)}
                    onCommit={(text) => commitPreview('test', text)}
                />
                {nestMethod ? (
                    <InlineEditable
                        value={row.test_method ?? ''}
                        canEdit={canEdit}
                        selected={isSelected(selection, methodTarget)}
                        className="text-slate-700"
                        style={{ fontSize: methodSize, marginTop: 1 }}
                        placeholder="Method"
                        onSelect={() => onSelectCell(methodTarget)}
                        onCommit={(text) => commitPreview('test_method', text)}
                    />
                ) : null}
            </td>
        );
    }

    const valueTarget: MatrixCellSelection = {
        area: 'body',
        rowIndex,
        columnKey: column.key,
        part: 'value',
    };

    return (
        <td
            className={`align-middle ${align} ${showBorder ? 'border border-black' : ''}`}
            style={{ width: `${widthPct}%`, padding: '2px 3px', fontSize, ...cellHeightStyle }}
        >
            <InlineEditable
                value={cellValue(row, column.key)}
                canEdit={canEdit}
                selected={isSelected(selection, valueTarget)}
                style={{ fontSize }}
                placeholder={
                    column.key === 'result' || column.key === 'remarks' || column.key === 'sample_1'
                        ? ''
                        : '—'
                }
                onSelect={() => onSelectCell(valueTarget)}
                onCommit={(text) => commitPreview('value', text)}
            />
        </td>
    );
}
