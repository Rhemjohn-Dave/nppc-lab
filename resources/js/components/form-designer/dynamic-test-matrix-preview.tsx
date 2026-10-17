import { useEffect, useMemo, useRef, useState } from 'react';
import { cssFontFamily } from '@/components/form-designer/utils';
import type { DesignerField } from '@/lib/controlled-forms';
import {
    columnBodyAlign,
    columnFontSizePt,
    columnHeaderAlign,
    columnWidthPct,
    createMatrixTextMeasurer,
    fitMatrixBodyFonts,
    MATRIX_CELL_TEXT_INSET_MM,
    MATRIX_DESIGNER_DRAG_HANDLE_PX,
    matrixCellKey,
    matrixConfig,
    matrixDetailFontSize,
    matrixHeaderFontSize,
    matrixHeaderSublines,
    matrixLineHeightMm,
    matrixMethodFontSize,
    matrixPreviewData,
    matrixPrintedCellValue,
    matrixRowExtraMm,
    matrixRowKey,
    matrixScientificExponentSizePt,
    matrixScientificFontSizePt,
    matrixTestCellLineHeightMm,
    matrixTestCellSpacing,
    matrixWrappedLineHeightMm,
    measureMatrixHeaderRowHeightMm,
    planMatrixBodyRows,
    PT_TO_MM,
    splitMatrixScientific,
    stretchBodyRowHeightsMm,
} from '@/lib/dynamic-test-matrix';
import type {
    MatrixCellSelection,
    MatrixColumnConfig,
    MatrixTableConfig,
    MatrixTextMeasurer,
} from '@/lib/dynamic-test-matrix';

/** Header text inset used by ControlledPdfFiller::writeAlignedMatrixText (mm). */
const HEADER_TEXT_PAD_MM = 0.6;
const HEADER_PADDING_MM = 1.0;
const HEADER_GAP_MM = 0.35;
const HANDLE_PX = 6;
const MIN_COLUMN_PCT = 5;

type Props = {
    field: DesignerField;
    widthPx: number;
    canEdit: boolean;
    /** Field is selected on the canvas (shows row/column drag handles). */
    active: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    onUpdateTableConfig: (
        config: MatrixTableConfig,
        recordHistory?: boolean,
        extra?: { height?: number },
    ) => void;
    /** Drawn table height (mm) for natural-height tables; null when rows stretch to fill the box. */
    onContentHeightMm?: (heightMm: number | null) => void;
    packagePreviewRows?: Array<Record<string, string>>;
};

type DragState =
    | { kind: 'column'; index: number; startX: number; startPcts: number[] }
    | {
          kind: 'row';
          startY: number;
          startMm: number;
          startContentMm: number;
          rowKey: string | null;
          label: string;
      }
    | { kind: 'table'; startY: number; startExtra: number; rowCount: number; startContentMm: number }
    | { kind: 'header'; startY: number; startMm: number };

type Scale = {
    mm: (valueMm: number) => number;
    pt: (fontSizePt: number) => number;
};

export default function DynamicTestMatrixPreview({
    field,
    widthPx,
    canEdit,
    active,
    selection,
    onSelectCell,
    onUpdateTableConfig,
    onContentHeightMm,
    packagePreviewRows,
}: Props) {
    const config = matrixConfig(field);
    const columns = config.columns ?? [];
    const showHeader = config.header_row !== false;
    const showBorder = config.border !== false;
    const stretchBody = config.stretch_body !== false;
    const headerBold = config.header_bold !== false;
    const testNameBold = config.test_name_bold !== false;
    const baseFontSize = field.font_size ?? 8;
    const headerBaseFontSize = matrixHeaderFontSize(baseFontSize, config);
    const methodFontSize = matrixMethodFontSize(baseFontSize, config);
    const rowHeightMm = config.row_height_mm ?? 7;
    const rowExtraMm = matrixRowExtraMm(config.row_extra_mm);
    const widthMm = Math.max(field.width, 1);
    const heightMm = Math.max(field.height, 0);
    const pxPerMm = widthPx / widthMm;
    const fontFamily = cssFontFamily(field.font_family);
    const textColor = field.font_color?.startsWith('#') ? field.font_color : '#000000';
    const measure = useMemo(() => createMatrixTextMeasurer(fontFamily), [fontFamily]);
    const scale: Scale = {
        mm: (valueMm) => valueMm * pxPerMm,
        pt: (fontSizePt) => fontSizePt * PT_TO_MM * pxPerMm,
    };

    // Same layout steps as ControlledPdfFiller::writeDynamicTestMatrix.
    const headerHeightMm = showHeader ? measureMatrixHeaderRowHeightMm(config, baseFontSize) : 0;
    const availableBodyMm = Math.max(0, heightMm - headerHeightMm);
    const sourceRows = matrixPreviewData(config, packagePreviewRows);
    const fit = fitMatrixBodyFonts(
        sourceRows,
        columns,
        widthMm,
        rowHeightMm,
        baseFontSize,
        methodFontSize,
        testNameBold,
        availableBodyMm,
        measure,
        config.row_heights_mm,
        rowExtraMm,
    );
    const plan = planMatrixBodyRows(
        sourceRows,
        fit,
        widthMm,
        rowHeightMm,
        testNameBold,
        availableBodyMm,
        measure,
        config.row_heights_mm,
        rowExtraMm,
    );
    const naturalHeightsMm = plan.map((item) => item.heightMm);
    const rowHeightsMm = stretchBody
        ? stretchBodyRowHeightsMm(naturalHeightsMm, availableBodyMm)
        : naturalHeightsMm;
    const rowTopsMm: number[] = [];
    let cursorMm = headerHeightMm;

    for (const height of rowHeightsMm) {
        rowTopsMm.push(cursorMm);
        cursorMm += height;
    }

    const contentHeightMm = cursorMm;
    const frameHeightMm = stretchBody ? heightMm : contentHeightMm;

    const columnWidthsMm = columns.map((_, index) => (widthMm * columnWidthPct(columns, index)) / 100);
    const columnLeftsMm: number[] = [];
    let leftMm = 0;

    for (const width of columnWidthsMm) {
        columnLeftsMm.push(leftMm);
        leftMm += width;
    }

    const cellLinePx = Math.max(0.5, 0.1 * pxPerMm);
    const frameLinePx = Math.max(1, 0.15 * pxPerMm);

    const [drag, setDrag] = useState<DragState | null>(null);
    const [readout, setReadout] = useState<string | null>(null);
    const pendingConfig = useRef<MatrixTableConfig | null>(null);
    const pendingHeight = useRef<number | undefined>(undefined);
    const reportedContentMm = stretchBody ? null : Math.round(contentHeightMm * 100) / 100;

    useEffect(() => {
        onContentHeightMm?.(reportedContentMm);
    }, [onContentHeightMm, reportedContentMm]);

    function updateConfig(next: MatrixTableConfig, recordHistory = false, height?: number) {
        onUpdateTableConfig(next, recordHistory, height !== undefined ? { height } : undefined);
    }

    function beginDrag(event: React.PointerEvent<HTMLElement>, state: DragState) {
        event.preventDefault();
        event.stopPropagation();
        event.currentTarget.setPointerCapture(event.pointerId);
        pendingConfig.current = null;
        pendingHeight.current = undefined;
        setDrag(state);
    }

    function moveDrag(event: React.PointerEvent<HTMLElement>) {
        if (!drag || pxPerMm <= 0) {
            return;
        }

        event.stopPropagation();
        let next: MatrixTableConfig;

        if (drag.kind === 'column') {
            const deltaPct = ((event.clientX - drag.startX) / widthPx) * 100;
            const pair = drag.startPcts[drag.index] + drag.startPcts[drag.index + 1];
            const left = clamp(snap(drag.startPcts[drag.index] + deltaPct, 0.5), MIN_COLUMN_PCT, pair - MIN_COLUMN_PCT);
            const right = pair - left;
            next = {
                ...config,
                columns: columns.map((column, index) => ({
                    ...column,
                    width_pct: round2(
                        index === drag.index ? left : index === drag.index + 1 ? right : drag.startPcts[index],
                    ),
                })),
            };
            setReadout(`${left.toFixed(1)}% | ${right.toFixed(1)}%`);
        } else if (drag.kind === 'table') {
            const deltaMm = (event.clientY - drag.startY) / pxPerMm;
            const extra = Math.max(0, round2(snap(drag.startExtra + deltaMm / drag.rowCount, 0.1)));
            next = { ...config, row_extra_mm: extra > 0 ? extra : undefined };
            setReadout(`All rows: +${extra.toFixed(1)} mm spacing`);

            // Rows that no longer fit the box are not drawn, so grow the box with the table.
            const neededMm = drag.startContentMm + (extra - drag.startExtra) * drag.rowCount;
            pendingHeight.current = neededMm > heightMm ? Math.ceil(neededMm * 2) / 2 : pendingHeight.current;
        } else {
            const deltaMm = (event.clientY - drag.startY) / pxPerMm;
            const [min, max] = drag.kind === 'row' ? (drag.rowKey ? [3, 60] : [3, 30]) : [3, 40];
            const value = clamp(snap(drag.startMm + deltaMm, 0.5), min, max);
            const suffix = stretchBody ? ' (min, rows fill box)' : '';

            if (drag.kind === 'header') {
                next = { ...config, header_row_height_mm: value };
                setReadout(`Header ${value.toFixed(1)} mm`);
            } else if (drag.rowKey) {
                next = { ...config, row_heights_mm: { ...config.row_heights_mm, [drag.rowKey]: value } };
                setReadout(`${drag.label}: ${value.toFixed(1)} mm${suffix}`);

                // Rows that no longer fit the box are not drawn, so grow the box with the table.
                const neededMm = drag.startContentMm - drag.startMm + value;
                pendingHeight.current = neededMm > heightMm ? Math.ceil(neededMm * 2) / 2 : pendingHeight.current;
            } else {
                next = { ...config, row_height_mm: value };
                setReadout(`All rows: ${value.toFixed(1)} mm${suffix}`);
            }
        }

        pendingConfig.current = next;
        updateConfig(next, false, pendingHeight.current);
    }

    function endDrag(event: React.PointerEvent<HTMLElement>) {
        if (!drag) {
            return;
        }

        event.stopPropagation();

        if (event.currentTarget.hasPointerCapture(event.pointerId)) {
            event.currentTarget.releasePointerCapture(event.pointerId);
        }

        if (pendingConfig.current) {
            updateConfig(pendingConfig.current, true, pendingHeight.current);
        }

        pendingConfig.current = null;
        pendingHeight.current = undefined;
        setDrag(null);
        setReadout(null);
    }

    function resetRowHeight(rowKey: string) {
        const remaining = Object.fromEntries(
            Object.entries(config.row_heights_mm ?? {}).filter(([key]) => key !== rowKey),
        );

        updateConfig({ ...config, row_heights_mm: Object.keys(remaining).length > 0 ? remaining : undefined }, true);
    }

    const showHandles = canEdit && active;
    const handleEvents = {
        onPointerMove: moveDrag,
        onPointerUp: endDrag,
        onPointerCancel: endDrag,
        onClick: (event: React.MouseEvent) => event.stopPropagation(),
    };

    return (
        <div
            className="absolute inset-0 z-[1]"
            style={{ fontFamily, color: textColor }}
        >
            {canEdit && (
                <div
                    data-matrix-drag
                    className="absolute right-0 left-0 flex cursor-move items-center justify-center border border-b-0 border-dashed border-slate-300 bg-slate-50/90 text-[8px] tracking-widest text-slate-400"
                    style={{ top: -MATRIX_DESIGNER_DRAG_HANDLE_PX, height: MATRIX_DESIGNER_DRAG_HANDLE_PX }}
                    title="Drag here to move the table region"
                >
                    ⋮⋮ drag
                </div>
            )}

            <div className="absolute inset-x-0 top-0 bg-white" style={{ height: scale.mm(frameHeightMm) }} />

            {showHeader &&
                columns.map((column, index) => (
                    <MatrixHeaderCell
                        key={`header-${column.key}`}
                        column={column}
                        columns={columns}
                        leftPx={scale.mm(columnLeftsMm[index])}
                        widthPx={scale.mm(columnWidthsMm[index])}
                        heightMm={headerHeightMm}
                        scale={scale}
                        cellLinePx={showBorder ? cellLinePx : 0}
                        fontSizePt={column.header_font_size ?? column.font_size ?? headerBaseFontSize}
                        headerBold={headerBold}
                        canEdit={canEdit}
                        selection={selection}
                        onSelectCell={onSelectCell}
                        config={config}
                        onUpdateConfig={updateConfig}
                    />
                ))}

            {plan.map((item, rowIndex) =>
                columns.map((column, index) => (
                    <MatrixBodyCell
                        key={`${rowIndex}-${column.key}`}
                        column={column}
                        fitColumn={fit.columns[index] ?? column}
                        columns={columns}
                        row={item.row}
                        rowIndex={rowIndex}
                        leftPx={scale.mm(columnLeftsMm[index])}
                        topPx={scale.mm(rowTopsMm[rowIndex])}
                        widthMm={columnWidthsMm[index]}
                        heightMm={rowHeightsMm[rowIndex]}
                        scale={scale}
                        cellLinePx={showBorder ? cellLinePx : 0}
                        bodyFontSizePt={fit.bodyFontSizePt}
                        methodFontSizePt={fit.methodFontSizePt}
                        testNameBold={testNameBold}
                        measure={measure}
                        canEdit={canEdit}
                        selection={selection}
                        onSelectCell={onSelectCell}
                        config={config}
                        onUpdateConfig={updateConfig}
                        packagePreviewRows={packagePreviewRows}
                    />
                )),
            )}

            {showBorder && frameHeightMm > 0.5 && (
                <div
                    className="pointer-events-none absolute inset-x-0 top-0 box-border"
                    style={{ height: scale.mm(frameHeightMm), border: `${frameLinePx}px solid #000` }}
                />
            )}

            {showHandles && (
                <>
                    {columns.slice(0, -1).map((column, index) => (
                        <div
                            key={`col-handle-${column.key}`}
                            className="group absolute z-[5] cursor-col-resize"
                            style={{
                                left: scale.mm(columnLeftsMm[index + 1]) - HANDLE_PX / 2,
                                top: 0,
                                width: HANDLE_PX,
                                height: scale.mm(Math.max(contentHeightMm, headerHeightMm)),
                            }}
                            title="Drag to change column widths"
                            onPointerDown={(event) =>
                                beginDrag(event, {
                                    kind: 'column',
                                    index,
                                    startX: event.clientX,
                                    startPcts: columns.map((_, i) => columnWidthPct(columns, i)),
                                })
                            }
                            {...handleEvents}
                        >
                            <span className="absolute inset-y-0 left-1/2 w-0.5 -translate-x-1/2 bg-nppc-900/0 group-hover:bg-nppc-900/60" />
                        </div>
                    ))}
                    {showHeader && (
                        <div
                            className="group absolute inset-x-0 z-[5] cursor-row-resize"
                            style={{ top: scale.mm(headerHeightMm) - HANDLE_PX / 2, height: HANDLE_PX }}
                            title="Drag to change the header row height"
                            onPointerDown={(event) =>
                                beginDrag(event, { kind: 'header', startY: event.clientY, startMm: headerHeightMm })
                            }
                            {...handleEvents}
                        >
                            <span className="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-nppc-900/0 group-hover:bg-nppc-900/60" />
                        </div>
                    )}
                    {plan.map((item, rowIndex) => {
                        const rowKey = matrixRowKey(item.row) || null;
                        const label = (item.row.test ?? '').trim() || `Row ${rowIndex + 1}`;
                        const hasOverride = rowKey !== null && config.row_heights_mm?.[rowKey] !== undefined;
                        const isTableEdge = !stretchBody && rowIndex === plan.length - 1;
                        const rowDrag = { kind: 'row' as const, startContentMm: contentHeightMm, label };

                        if (isTableEdge) {
                            return (
                                <div
                                    key={`row-handle-${rowIndex}`}
                                    className="group absolute inset-x-0 z-[5] cursor-row-resize"
                                    style={{
                                        top: scale.mm(rowTopsMm[rowIndex] + rowHeightsMm[rowIndex]) - HANDLE_PX / 2,
                                        height: HANDLE_PX,
                                    }}
                                    title={`Drag to make the whole table taller or shorter (spacing is added to every row).${rowExtraMm > 0 ? ' Double-click to reset.' : ''}`}
                                    onPointerDown={(event) =>
                                        beginDrag(event, {
                                            kind: 'table',
                                            startY: event.clientY,
                                            startExtra: rowExtraMm,
                                            rowCount: Math.max(1, plan.length),
                                            startContentMm: contentHeightMm,
                                        })
                                    }
                                    onDoubleClick={(event) => {
                                        event.stopPropagation();

                                        if (rowExtraMm > 0) {
                                            updateConfig({ ...config, row_extra_mm: undefined }, true);
                                        }
                                    }}
                                    {...handleEvents}
                                >
                                    <span className="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-nppc-900/0 group-hover:bg-nppc-900/60" />
                                    <span className="pointer-events-none absolute top-1/2 left-1/2 h-1.5 w-6 -translate-x-1/2 -translate-y-1/2 rounded-sm border border-white bg-nppc-900 shadow-sm" />
                                </div>
                            );
                        }

                        return (
                            <div
                                key={`row-handle-${rowIndex}`}
                                className="group absolute inset-x-0 z-[5] cursor-row-resize"
                                style={{
                                    top: scale.mm(rowTopsMm[rowIndex] + rowHeightsMm[rowIndex]) - HANDLE_PX / 2,
                                    height: HANDLE_PX,
                                }}
                                title={`Drag to change the height of "${label}". Shift+drag changes all rows.${hasOverride ? ' Double-click to reset.' : ''}`}
                                onPointerDown={(event) =>
                                    beginDrag(
                                        event,
                                        event.shiftKey || rowKey === null
                                            ? { ...rowDrag, startY: event.clientY, startMm: rowHeightMm, rowKey: null }
                                            : { ...rowDrag, startY: event.clientY, startMm: item.heightMm, rowKey },
                                    )
                                }
                                onDoubleClick={(event) => {
                                    event.stopPropagation();

                                    if (rowKey !== null && hasOverride) {
                                        resetRowHeight(rowKey);
                                    }
                                }}
                                {...handleEvents}
                            >
                                <span className="absolute inset-x-0 top-1/2 h-0.5 -translate-y-1/2 bg-nppc-900/0 group-hover:bg-nppc-900/60" />
                            </div>
                        );
                    })}
                </>
            )}

            {readout && (
                <span className="pointer-events-none absolute right-0 z-[6] rounded bg-nppc-900 px-1.5 py-0.5 text-[10px] font-medium whitespace-nowrap text-white shadow-sm"
                    style={{ top: -MATRIX_DESIGNER_DRAG_HANDLE_PX - 20 }}
                >
                    {readout}
                </span>
            )}
        </div>
    );
}

function clamp(value: number, min: number, max: number): number {
    return Math.min(Math.max(value, min), Math.max(min, max));
}

function snap(value: number, step: number): number {
    return Math.round(value / step) * step;
}

function round2(value: number): number {
    return Math.round(value * 100) / 100;
}

function alignClass(align: 'L' | 'C' | 'R'): string {
    return align === 'C' ? 'text-center' : align === 'R' ? 'text-right' : 'text-left';
}

function cellBorderStyle(cellLinePx: number): React.CSSProperties {
    return cellLinePx > 0 ? { border: `${cellLinePx}px solid #000` } : {};
}

/** Text area of a body cell after the 0.8 mm offset and TCPDF cell padding (mm). */
function innerTextWidthMm(columnWidthMm: number): number {
    return Math.max(0.1, columnWidthMm - 2 * MATRIX_CELL_TEXT_INSET_MM);
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
    return selected ? 'ring-2 ring-nppc-900 ring-inset bg-nppc-900/10' : 'hover:bg-slate-50/80';
}

function InlineEditable({
    value,
    display,
    canEdit,
    selected,
    className,
    style,
    onSelect,
    onCommit,
    placeholder,
}: {
    value: string;
    /** Rendered instead of the raw value when not editing (e.g. raised exponent). */
    display?: React.ReactNode;
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
    const [syncedValue, setSyncedValue] = useState(value);
    const inputRef = useRef<HTMLInputElement>(null);

    if (value !== syncedValue) {
        setSyncedValue(value);
        setDraft(value);
    }

    useEffect(() => {
        if (editing) {
            inputRef.current?.focus();
            inputRef.current?.select();
        }
    }, [editing]);

    const shown = display ?? value;

    if (!canEdit) {
        if (value) {
            return (
                <span className={`block whitespace-pre-line ${className ?? ''}`} style={style}>
                    {shown}
                </span>
            );
        }

        if (placeholder) {
            return (
                <span className={`block whitespace-pre-line opacity-40 ${className ?? ''}`} style={style}>
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
                className={`w-full min-w-0 border border-nppc-900 bg-white px-0.5 outline-none ${className ?? ''}`}
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
            className={`relative z-[2] block cursor-text rounded-sm select-text whitespace-pre-line ${selectionRing(selected)} ${className ?? ''}`}
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
            {value ? shown : <span className="opacity-40">{placeholder || '—'}</span>}
        </span>
    );
}

function MatrixHeaderCell({
    column,
    columns,
    leftPx,
    widthPx,
    heightMm,
    scale,
    cellLinePx,
    fontSizePt,
    headerBold,
    canEdit,
    selection,
    onSelectCell,
    config,
    onUpdateConfig,
}: {
    column: MatrixColumnConfig;
    columns: MatrixColumnConfig[];
    leftPx: number;
    widthPx: number;
    heightMm: number;
    scale: Scale;
    cellLinePx: number;
    fontSizePt: number;
    headerBold: boolean;
    canEdit: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    config: MatrixTableConfig;
    onUpdateConfig: (config: MatrixTableConfig, recordHistory?: boolean) => void;
}) {
    const align = columnHeaderAlign(column);
    const subAlign = column.sublabel_align ?? align;
    const sublines = matrixHeaderSublines(column);
    const subFontSizePt = Math.max(6, fontSizePt - 1);
    const labelTarget: MatrixCellSelection = { area: 'header', columnKey: column.key, part: 'label' };
    const sublabelTarget: MatrixCellSelection = { area: 'header', columnKey: column.key, part: 'sublabel' };
    const showSublabel =
        sublines.length > 0 || isSelected(selection, sublabelTarget) || (canEdit && Boolean(column.sublabel));

    return (
        <div
            className={`absolute box-border flex flex-col overflow-hidden ${showSublabel ? 'justify-start' : 'justify-center'}`}
            style={{
                left: leftPx,
                top: 0,
                width: widthPx,
                height: scale.mm(heightMm),
                padding: `${showSublabel ? scale.mm(HEADER_PADDING_MM) : 0}px ${scale.mm(HEADER_TEXT_PAD_MM)}px 0`,
                ...cellBorderStyle(cellLinePx),
            }}
        >
            <InlineEditable
                value={column.label}
                canEdit={canEdit}
                selected={isSelected(selection, labelTarget)}
                className={`${alignClass(align)} ${headerBold ? 'font-bold' : 'font-normal'} whitespace-nowrap`}
                style={{
                    fontSize: scale.pt(fontSizePt),
                    lineHeight: `${scale.mm(matrixLineHeightMm(fontSizePt))}px`,
                }}
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
            {showSublabel && (
                <InlineEditable
                    value={column.sublabel ?? ''}
                    display={sublines.join('\n')}
                    canEdit={canEdit}
                    selected={isSelected(selection, sublabelTarget)}
                    className={`${alignClass(subAlign)} font-normal`}
                    style={{
                        fontSize: scale.pt(subFontSizePt),
                        lineHeight: `${scale.mm(matrixLineHeightMm(subFontSizePt))}px`,
                        marginTop: scale.mm(HEADER_GAP_MM),
                    }}
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
            )}
        </div>
    );
}

function MatrixBodyCell({
    column,
    fitColumn,
    columns,
    row,
    rowIndex,
    leftPx,
    topPx,
    widthMm,
    heightMm,
    scale,
    cellLinePx,
    bodyFontSizePt,
    methodFontSizePt,
    testNameBold,
    measure,
    canEdit,
    selection,
    onSelectCell,
    config,
    onUpdateConfig,
    packagePreviewRows,
}: {
    column: MatrixColumnConfig;
    /** Column after the PDF's auto-shrink (font sizes only). */
    fitColumn: MatrixColumnConfig;
    columns: MatrixColumnConfig[];
    row: Record<string, string>;
    rowIndex: number;
    leftPx: number;
    topPx: number;
    widthMm: number;
    heightMm: number;
    scale: Scale;
    cellLinePx: number;
    bodyFontSizePt: number;
    methodFontSizePt: number;
    testNameBold: boolean;
    measure: MatrixTextMeasurer;
    canEdit: boolean;
    selection: MatrixCellSelection | null;
    onSelectCell: (selection: MatrixCellSelection | null) => void;
    config: MatrixTableConfig;
    onUpdateConfig: (config: MatrixTableConfig, recordHistory?: boolean) => void;
    packagePreviewRows?: Array<Record<string, string>>;
}) {
    const align = alignClass(columnBodyAlign(column));
    const fontSizePt = columnFontSizePt(fitColumn, bodyFontSizePt);
    const innerMm = innerTextWidthMm(widthMm);
    const fitsOneLine = (text: string, sizePt: number, bold: boolean) =>
        measure(text, sizePt, bold) <= innerMm + 0.01;
    const cellStyle: React.CSSProperties = {
        left: leftPx,
        top: topPx,
        width: scale.mm(widthMm),
        height: scale.mm(heightMm),
        paddingLeft: scale.mm(MATRIX_CELL_TEXT_INSET_MM),
        paddingRight: scale.mm(MATRIX_CELL_TEXT_INSET_MM),
        ...cellBorderStyle(cellLinePx),
    };

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
        const name = row.test ?? '';
        const detail = row.test_detail ?? '';
        const method = nestMethod ? (row.test_method ?? '') : '';
        const { padding, gap } = matrixTestCellSpacing(detail);
        const detailSizePt = matrixDetailFontSize(methodFontSizePt);
        const nameLineMm = fitsOneLine(name, fontSizePt, testNameBold)
            ? matrixTestCellLineHeightMm(fontSizePt, detail)
            : matrixWrappedLineHeightMm(fontSizePt);
        const detailLineMm = fitsOneLine(detail, detailSizePt, false)
            ? matrixTestCellLineHeightMm(detailSizePt, detail)
            : matrixWrappedLineHeightMm(detailSizePt);
        const nameTarget: MatrixCellSelection = { area: 'body', rowIndex, columnKey: column.key, part: 'test' };
        const methodTarget: MatrixCellSelection = {
            area: 'body',
            rowIndex,
            columnKey: column.key,
            part: 'test_method',
        };

        return (
            <div
                className={`absolute box-border overflow-hidden ${align}`}
                style={{ ...cellStyle, paddingTop: scale.mm(padding) }}
            >
                <InlineEditable
                    value={name}
                    canEdit={canEdit}
                    selected={isSelected(selection, nameTarget)}
                    className={testNameBold ? 'font-bold' : 'font-normal'}
                    style={{ fontSize: scale.pt(fontSizePt), lineHeight: `${scale.mm(nameLineMm)}px` }}
                    placeholder="Test name"
                    onSelect={() => onSelectCell(nameTarget)}
                    onCommit={(text) => commitPreview('test', text)}
                />
                {detail ? (
                    <span
                        className="block whitespace-pre-line"
                        style={{
                            fontSize: scale.pt(detailSizePt),
                            lineHeight: `${scale.mm(detailLineMm)}px`,
                            marginTop: name ? scale.mm(gap) : 0,
                        }}
                    >
                        {detail}
                    </span>
                ) : null}
                {nestMethod ? (
                    <InlineEditable
                        value={method}
                        canEdit={canEdit}
                        selected={isSelected(selection, methodTarget)}
                        style={{
                            fontSize: scale.pt(methodFontSizePt),
                            lineHeight: `${scale.mm(matrixWrappedLineHeightMm(methodFontSizePt))}px`,
                            marginTop: name || detail ? scale.mm(gap) : 0,
                        }}
                        placeholder="Method"
                        onSelect={() => onSelectCell(methodTarget)}
                        onCommit={(text) => commitPreview('test_method', text)}
                    />
                ) : null}
            </div>
        );
    }

    const valueTarget: MatrixCellSelection = { area: 'body', rowIndex, columnKey: column.key, part: 'value' };
    const text = matrixPrintedCellValue(row, column.key);
    const scientific = splitMatrixScientific(text);
    const valueFontPt = scientific ? matrixScientificFontSizePt(scientific, Math.max(1, widthMm - 1.6), fontSizePt, measure) : fontSizePt;
    const topAligned = column.key === 'method' || column.key === 'acceptable_values';
    const wrap = !scientific && (topAligned || text.length > 40 || !fitsOneLine(text, fontSizePt, false));
    const lineMm = wrap ? matrixWrappedLineHeightMm(fontSizePt) : matrixLineHeightMm(valueFontPt);
    const linePx = scale.mm(lineMm);
    const display = scientific ? (
        <span className="whitespace-nowrap">
            {scientific.base}
            <span
                className="inline-block align-top"
                style={{
                    fontSize: scale.pt(matrixScientificExponentSizePt(valueFontPt)),
                    lineHeight: `${linePx}px`,
                    transform: `translateY(${-linePx * 0.35}px)`,
                }}
            >
                {scientific.exponent}
            </span>
            {scientific.tail}
        </span>
    ) : undefined;

    return (
        <div
            className={`absolute box-border flex flex-col overflow-hidden ${align} ${topAligned ? 'justify-start' : 'justify-center'}`}
            style={{ ...cellStyle, paddingTop: topAligned ? scale.mm(1.0) : 0 }}
        >
            <InlineEditable
                value={text}
                display={display}
                canEdit={canEdit}
                selected={isSelected(selection, valueTarget)}
                style={{ fontSize: scale.pt(valueFontPt), lineHeight: `${linePx}px` }}
                placeholder={
                    column.key === 'result' || column.key === 'remarks' || column.key === 'sample_1'
                        ? ''
                        : '—'
                }
                onSelect={() => onSelectCell(valueTarget)}
                onCommit={(next) => commitPreview('value', next)}
            />
        </div>
    );
}
