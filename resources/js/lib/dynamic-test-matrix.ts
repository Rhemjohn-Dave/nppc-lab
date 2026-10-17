import type { DesignerField } from '@/lib/controlled-forms';

export type MatrixColumnConfig = {
    key: string;
    label: string;
    sublabel?: string | null;
    sublabels?: string[] | null;
    width_pct?: number;
    align?: 'L' | 'C' | 'R';
    header_align?: 'L' | 'C' | 'R';
    sublabel_align?: 'L' | 'C' | 'R';
    /** Body cell font size (pt) for this column */
    font_size?: number;
    /** Header cell font size (pt) for this column */
    header_font_size?: number;
};

export type MatrixTableConfig = {
    columns?: MatrixColumnConfig[];
    row_height_mm?: number;
    /** Per-row minimum heights keyed by lowercased test name (designer row drag). */
    row_heights_mm?: Record<string, number>;
    /** Extra spacing (mm) added to every body row (designer last-line drag). */
    row_extra_mm?: number;
    /** Minimum header band height; auto-expands when sublabels need more space */
    header_row_height_mm?: number;
    /** Default header label font size (pt) when a column has no header_font_size */
    header_font_size?: number;
    header_row?: boolean;
    border?: boolean;
    preview_rows?: number;
    /** Designer-only sample row text (not printed — runtime uses job data) */
    preview_data?: Array<Record<string, string>>;
    /** When false, body rows keep natural height (no stretch-to-fill). */
    stretch_body?: boolean;
    method_font_size?: number;
    test_name_bold?: boolean;
    header_bold?: boolean;
};

/** Which table cell the admin is editing (Word-style selection). */
export type MatrixCellSelection =
    | { area: 'header'; columnKey: string; part: 'label' | 'sublabel' }
    | { area: 'body'; rowIndex: number; columnKey: string; part: 'test' | 'test_method' | 'value' };

export function matrixCellKey(selection: MatrixCellSelection): string {
    if (selection.area === 'header') {
        return `header:${selection.columnKey}:${selection.part}`;
    }

    return `body:${selection.rowIndex}:${selection.columnKey}:${selection.part}`;
}

/** Approximate single-line height in mm for a font size in pt (matches PDF filler). */
export function matrixLineHeightMm(fontSizePt: number): number {
    return Math.max(2.5, fontSizePt * 0.352778 * 1.2);
}

export function matrixHeaderFontSize(fieldFontSize: number, config: MatrixTableConfig): number {
    if (typeof config.header_font_size === 'number' && config.header_font_size > 0) {
        return config.header_font_size;
    }

    return fieldFontSize;
}

export function matrixHeaderSublines(column: MatrixColumnConfig): string[] {
    if (Array.isArray(column.sublabels) && column.sublabels.length > 0) {
        return column.sublabels.map((line) => line.trim()).filter(Boolean);
    }

    if (!column.sublabel) {
        return [];
    }

    return column.sublabel
        .split(/\r\n|\n|\r/)
        .map((line) => line.trim())
        .filter(Boolean);
}

export function measureMatrixHeaderRowHeightMm(
    config: MatrixTableConfig,
    fieldFontSize: number,
): number {
    const columns =
        Array.isArray(config.columns) && config.columns.length > 0
            ? config.columns
            : DEFAULT_MATRIX_CONFIG.columns!;
    const base = config.header_row_height_mm ?? config.row_height_mm ?? 7;
    const defaultHeaderFont = matrixHeaderFontSize(fieldFontSize, config);
    const padding = 1.0;
    const gap = 0.35;
    let maxHeight = base;

    for (const column of columns) {
        const colFontSize = column.header_font_size ?? defaultHeaderFont;
        const labelLine = matrixLineHeightMm(colFontSize);
        const sublines = matrixHeaderSublines(column);
        let colHeight = padding + labelLine + padding;

        if (sublines.length > 0) {
            const subLine = matrixLineHeightMm(Math.max(6, colFontSize - 1));
            colHeight = padding + labelLine + gap + sublines.length * subLine + padding;
        }

        maxHeight = Math.max(maxHeight, colHeight);
    }

    return maxHeight;
}

/**
 * Distribute leftover body height across drawn rows (mirrors DynamicTestMatrix::stretchBodyRowHeights).
 */
export function stretchBodyRowHeightsMm(
    naturalHeightsMm: number[],
    availableBodyMm: number,
): number[] {
    const count = naturalHeightsMm.length;

    if (count === 0) {
        return [];
    }

    const naturalTotal = naturalHeightsMm.reduce((sum, h) => sum + h, 0);

    if (availableBodyMm <= 0 || naturalTotal <= 0) {
        return [...naturalHeightsMm];
    }

    if (naturalTotal >= availableBodyMm - 0.05) {
        return [...naturalHeightsMm];
    }

    const extraEach = (availableBodyMm - naturalTotal) / count;

    return naturalHeightsMm.map((natural) => natural + extraEach);
}

/** Designer-only drag strip drawn just above the matrix field box. */
export const MATRIX_DESIGNER_DRAG_HANDLE_PX = 16;

export function matrixMethodFontSize(fieldFontSize: number, config: MatrixTableConfig): number {
    if (typeof config.method_font_size === 'number' && config.method_font_size > 0) {
        return config.method_font_size;
    }

    return Math.max(6, fieldFontSize - 1);
}

/*
 * Body measurement below mirrors ControlledPdfFiller (measureMatrixBodyRowHeight,
 * fitMatrixBodyFonts) so the Form Designer shows the same rows, heights and font
 * sizes as the form preview and generated PDF. Keep both sides in sync.
 */
export const PT_TO_MM = 0.352778;
/** TCPDF default left/right cell padding (mm). */
const TCPDF_CELL_PADDING_MM = 1.000125;
const TCPDF_CELL_HEIGHT_RATIO = 1.25;
export const MATRIX_DETAIL_MAX_FONT_PT = 5.5;
/** Horizontal inset of matrix cell text from the column edge (0.8 mm offset + TCPDF padding). */
export const MATRIX_CELL_TEXT_INSET_MM = 0.8 + TCPDF_CELL_PADDING_MM;

export type MatrixTextMeasurer = (text: string, fontSizePt: number, bold: boolean) => number;

let measureContext: CanvasRenderingContext2D | null | undefined;

/** Text width in mm using the browser's copy of the font (Calibri on lab PCs). */
export function createMatrixTextMeasurer(cssFamily: string): MatrixTextMeasurer {
    const cache = new Map<string, number>();

    return (text, fontSizePt, bold) => {
        const key = `${bold ? 1 : 0}|${fontSizePt}|${text}`;
        const cached = cache.get(key);

        if (cached !== undefined) {
            return cached;
        }

        if (measureContext === undefined) {
            measureContext =
                typeof document !== 'undefined'
                    ? document.createElement('canvas').getContext('2d')
                    : null;
        }

        let widthMm: number;

        if (measureContext) {
            measureContext.font = `${bold ? 'bold ' : ''}100px ${cssFamily}`;
            const widthPx = (measureContext.measureText(text).width * fontSizePt) / 100;
            // CSS: 1pt = 4/3 px, 1px = 25.4/96 mm.
            widthMm = (widthPx * (4 / 3) * 25.4) / 96;
        } else {
            widthMm = text.length * fontSizePt * PT_TO_MM * 0.5;
        }

        cache.set(key, widthMm);

        return widthMm;
    };
}

export function matrixTestCellSpacing(detail: string): { padding: number; gap: number } {
    return detail !== '' ? { padding: 0.4, gap: 0.1 } : { padding: 1.0, gap: 0.35 };
}

export function matrixDetailFontSize(methodFontSizePt: number): number {
    return Math.min(methodFontSizePt, MATRIX_DETAIL_MAX_FONT_PT);
}

export function matrixTestCellLineHeightMm(fontSizePt: number, detail: string): number {
    return detail !== '' ? fontSizePt * PT_TO_MM * 1.05 : matrixLineHeightMm(fontSizePt);
}

/** Line pitch of wrapped (MultiCell) text in mm. */
export function matrixWrappedLineHeightMm(fontSizePt: number): number {
    return fontSizePt * PT_TO_MM * TCPDF_CELL_HEIGHT_RATIO;
}

function matrixTextOverflows(textWidthMm: number, cellWidthMm: number): boolean {
    return textWidthMm > cellWidthMm - 2 * TCPDF_CELL_PADDING_MM + 0.01;
}

function wrapLineCount(
    text: string,
    cellWidthMm: number,
    fontSizePt: number,
    bold: boolean,
    measure: MatrixTextMeasurer,
): number {
    const available = Math.max(0.1, cellWidthMm - 2 * TCPDF_CELL_PADDING_MM);
    let lines = 0;

    for (const paragraph of text.split(/\r\n|\n|\r/)) {
        const words = paragraph.split(' ');
        let current = '';
        lines += 1;

        for (const word of words) {
            const candidate = current === '' ? word : `${current} ${word}`;

            if (measure(candidate, fontSizePt, bold) <= available) {
                current = candidate;
                continue;
            }

            if (current !== '') {
                lines += 1;
            }

            current = word;

            // A single word wider than the cell breaks by character, like TCPDF.
            while (current.length > 1 && measure(current, fontSizePt, bold) > available) {
                let fit = current.length - 1;

                while (fit > 1 && measure(current.slice(0, fit), fontSizePt, bold) > available) {
                    fit -= 1;
                }

                current = current.slice(fit);
                lines += 1;
            }
        }
    }

    return Math.max(1, lines);
}

function stringHeightMm(
    text: string,
    cellWidthMm: number,
    fontSizePt: number,
    bold: boolean,
    measure: MatrixTextMeasurer,
): number {
    return wrapLineCount(text, cellWidthMm, fontSizePt, bold, measure) * matrixWrappedLineHeightMm(fontSizePt);
}

/** Matches ControlledPdfFiller::matrixCellValue (result falls back to sample_1). */
export function matrixPrintedCellValue(row: Record<string, string>, key: string): string {
    if (key === 'result' && !(row.result ?? '').trim() && (row.sample_1 ?? '').trim()) {
        return row.sample_1;
    }

    return row[key] ?? '';
}

/** Split "4×10⁻⁴ mg/L" into base / ASCII exponent / tail (ControlledPdfFiller::splitScientificValue). */
export function splitMatrixScientific(text: string): { base: string; exponent: string; tail: string } | null {
    const match = text.match(/^(.*?×\s?10)([⁺⁻]?[⁰¹²³⁴⁵⁶⁷⁸⁹]+)(.*)$/u);

    if (!match) {
        return null;
    }

    const map: Record<string, string> = {
        '⁰': '0', '¹': '1', '²': '2', '³': '3', '⁴': '4',
        '⁵': '5', '⁶': '6', '⁷': '7', '⁸': '8', '⁹': '9',
        '⁺': '+', '⁻': '-',
    };

    return {
        base: match[1],
        exponent: [...match[2]].map((char) => map[char] ?? char).join(''),
        tail: match[3],
    };
}

/** Raised exponent size (ControlledPdfFiller::scientificExponentSize). */
export function matrixScientificExponentSizePt(fontSizePt: number): number {
    return Math.max(4, fontSizePt * 0.65);
}

/**
 * Font size for a scientific value: powers never wrap, so shrink in 0.25 pt steps
 * (minimum 5 pt) until it fits on one line, like the PDF.
 */
export function matrixScientificFontSizePt(
    parts: { base: string; exponent: string; tail: string },
    innerWidthMm: number,
    fontSizePt: number,
    measure: MatrixTextMeasurer,
): number {
    const width = (size: number) =>
        measure(parts.base, size, false) +
        measure(parts.tail, size, false) +
        measure(parts.exponent, matrixScientificExponentSizePt(size), false);
    let size = fontSizePt;

    while (size > 5 && matrixTextOverflows(width(size), innerWidthMm)) {
        size = Math.max(5, size - 0.25);
    }

    return size;
}

export function columnFontSizePt(column: MatrixColumnConfig, bodyFontSizePt: number): number {
    return typeof column.font_size === 'number' && column.font_size > 0 ? column.font_size : bodyFontSizePt;
}

export function measureMatrixBodyRowHeightMm(
    row: Record<string, string>,
    columns: MatrixColumnConfig[],
    totalWidthMm: number,
    baseRowHeightMm: number,
    bodyFontSizePt: number,
    methodFontSizePt: number,
    testNameBold: boolean,
    measure: MatrixTextMeasurer,
): number {
    let maxHeight = baseRowHeightMm;
    const separateMethodColumn = columns.some((column) => column.key === 'method');

    columns.forEach((column, index) => {
        const colWidth = (totalWidthMm * columnWidthPct(columns, index)) / 100;
        const colFontSize = columnFontSizePt(column, bodyFontSizePt);
        const innerWidth = Math.max(1, colWidth - 1.6);
        const detail = column.key === 'test' ? (row.test_detail ?? '') : '';
        const { padding, gap } = matrixTestCellSpacing(detail);
        let height = padding;

        if (column.key === 'test') {
            const name = row.test ?? '';
            const method = separateMethodColumn ? '' : (row.test_method ?? '');

            if (name !== '') {
                height += matrixTextOverflows(measure(name, colFontSize, testNameBold), innerWidth)
                    ? stringHeightMm(name, innerWidth, colFontSize, testNameBold, measure)
                    : matrixTestCellLineHeightMm(colFontSize, detail);
            }

            if (detail !== '') {
                if (name !== '') {
                    height += gap;
                }

                const detailSize = matrixDetailFontSize(methodFontSizePt);
                height += matrixTextOverflows(measure(detail, detailSize, false), innerWidth)
                    ? stringHeightMm(detail, innerWidth, detailSize, false, measure)
                    : matrixTestCellLineHeightMm(detailSize, detail);
            }

            if (method !== '') {
                if (name !== '' || detail !== '') {
                    height += gap;
                }

                height += stringHeightMm(method, innerWidth, methodFontSizePt, false, measure);
            }
        } else {
            const text = matrixPrintedCellValue(row, column.key);

            if (text !== '') {
                height += splitMatrixScientific(text)
                    ? matrixLineHeightMm(colFontSize)
                    : stringHeightMm(text, innerWidth, colFontSize, false, measure);
            }
        }

        maxHeight = Math.max(maxHeight, height + padding);
    });

    return maxHeight;
}

export type MatrixBodyFit = {
    columns: MatrixColumnConfig[];
    bodyFontSizePt: number;
    methodFontSizePt: number;
};

/** Mirrors ControlledPdfFiller::fitMatrixBodyFonts: shrink Method first, then everything. */
export function fitMatrixBodyFonts(
    rows: Array<Record<string, string>>,
    columns: MatrixColumnConfig[],
    totalWidthMm: number,
    rowHeightMm: number,
    bodyFontSizePt: number,
    methodFontSizePt: number,
    testNameBold: boolean,
    availableHeightMm: number,
    measure: MatrixTextMeasurer,
    rowHeightsMm?: MatrixTableConfig['row_heights_mm'],
    rowExtraMm = 0,
): MatrixBodyFit {
    const measureTotal = (cols: MatrixColumnConfig[], bodySize: number, methodSize: number) =>
        rows.reduce(
            (total, row) =>
                total +
                measureMatrixBodyRowHeightMm(
                    row,
                    cols,
                    totalWidthMm,
                    matrixRowMinHeightMm(rowHeightsMm, row, rowHeightMm),
                    bodySize,
                    methodSize,
                    testNameBold,
                    measure,
                ) +
                matrixRowExtraMm(rowExtraMm),
            0,
        );

    if (measureTotal(columns, bodyFontSizePt, methodFontSizePt) <= availableHeightMm + 0.01) {
        return { columns, bodyFontSizePt, methodFontSizePt };
    }

    let minimum = 6;
    const methodIndex = columns.findIndex((column) => column.key === 'method');
    let columnSize = methodIndex >= 0 ? columnFontSizePt(columns[methodIndex], bodyFontSizePt) : 0;
    let nestedSize = methodFontSizePt;

    for (;;) {
        const nextColumnSize = methodIndex >= 0 && columnSize - 0.5 >= minimum ? columnSize - 0.5 : columnSize;
        const nextNestedSize = nestedSize - 0.5 >= minimum ? nestedSize - 0.5 : nestedSize;

        if (nextColumnSize === columnSize && nextNestedSize === nestedSize) {
            break;
        }

        columnSize = nextColumnSize;
        nestedSize = nextNestedSize;
        const candidate =
            methodIndex >= 0
                ? columns.map((column, index) => (index === methodIndex ? { ...column, font_size: columnSize } : column))
                : columns;

        if (measureTotal(candidate, bodyFontSizePt, nestedSize) <= availableHeightMm + 0.01) {
            return { columns: candidate, bodyFontSizePt, methodFontSizePt: nestedSize };
        }
    }

    let reference = Math.max(bodyFontSizePt, methodFontSizePt);

    for (const column of columns) {
        if (typeof column.font_size === 'number') {
            reference = Math.max(reference, column.font_size);
        }
    }

    minimum = 5;
    let scale = 1;
    let scaledColumns = columns;

    for (let step = 1; ; step++) {
        const total = measureTotal(scaledColumns, bodyFontSizePt * scale, methodFontSizePt * scale);
        const nextReference = reference - 0.5 * step;

        if (total <= availableHeightMm + 0.01 || nextReference < minimum) {
            break;
        }

        scale = nextReference / reference;
        const factor = scale;
        scaledColumns = columns.map((column) =>
            typeof column.font_size === 'number' ? { ...column, font_size: column.font_size * factor } : column,
        );
    }

    return {
        columns: scaledColumns,
        bodyFontSizePt: bodyFontSizePt * scale,
        methodFontSizePt: methodFontSizePt * scale,
    };
}

/** Extra spacing added to every body row (DynamicTestMatrix::rowExtraMm). */
export function matrixRowExtraMm(rowExtraMm: number | undefined): number {
    return typeof rowExtraMm === 'number' && Number.isFinite(rowExtraMm) ? Math.max(0, rowExtraMm) : 0;
}

/** Key for a body row in `row_heights_mm` (same as DynamicTestMatrix::rowHeightKey). */
export function matrixRowKey(row: Record<string, string>): string {
    return (row.test ?? '').trim().toLowerCase();
}

/** Per-row override when set, else the table row height (DynamicTestMatrix::rowMinHeightMm). */
export function matrixRowMinHeightMm(
    rowHeightsMm: MatrixTableConfig['row_heights_mm'],
    row: Record<string, string>,
    baseMm: number,
): number {
    const key = matrixRowKey(row);
    const override = key !== '' ? rowHeightsMm?.[key] : undefined;

    return typeof override === 'number' && Number.isFinite(override) ? Math.max(1, override) : baseMm;
}

/** Rows that fit the box at natural height, like the PDF (stops before the first overflow). */
export function planMatrixBodyRows(
    rows: Array<Record<string, string>>,
    fit: MatrixBodyFit,
    totalWidthMm: number,
    rowHeightMm: number,
    testNameBold: boolean,
    availableHeightMm: number,
    measure: MatrixTextMeasurer,
    rowHeightsMm?: MatrixTableConfig['row_heights_mm'],
    rowExtraMm = 0,
): Array<{ row: Record<string, string>; heightMm: number }> {
    const plan: Array<{ row: Record<string, string>; heightMm: number }> = [];
    let total = 0;

    for (const row of rows) {
        const heightMm =
            measureMatrixBodyRowHeightMm(
                row,
                fit.columns,
                totalWidthMm,
                matrixRowMinHeightMm(rowHeightsMm, row, rowHeightMm),
                fit.bodyFontSizePt,
                fit.methodFontSizePt,
                testNameBold,
                measure,
            ) + matrixRowExtraMm(rowExtraMm);

        if (total + heightMm > availableHeightMm + 0.01) {
            break;
        }

        plan.push({ row, heightMm });
        total += heightMm;
    }

    return plan;
}

export function columnHeaderAlign(column: MatrixColumnConfig): 'L' | 'C' | 'R' {
    return column.header_align ?? 'C';
}

export function columnBodyAlign(column: MatrixColumnConfig): 'L' | 'C' | 'R' {
    if (column.key === 'test') {
        return column.align ?? 'L';
    }

    return column.align ?? 'C';
}

export const DEFAULT_MATRIX_CONFIG: MatrixTableConfig = {
    columns: [
        { key: 'test', label: 'TEST', width_pct: 55, align: 'C', header_align: 'C' },
        {
            key: 'result',
            label: 'Control Number',
            sublabel: 'Sample Description:\nSS:',
            sublabel_align: 'C',
            width_pct: 45,
            align: 'C',
            header_align: 'C',
        },
    ],
    row_height_mm: 9,
    header_row_height_mm: 14,
    header_row: true,
    border: true,
    preview_rows: 8,
    test_name_bold: true,
    header_bold: true,
    method_font_size: 7,
};

export const MATRIX_DESIGNER_PREVIEW_ROWS: Array<Record<string, string>> = [
    { test: '% Fat', test_method: 'Soxhlet Extraction Method', result: '0.68' },
    { test: '% Protein', test_method: 'Kjeldahl Method', result: '18.2' },
    { test: '% Moisture', test_method: 'Gravimetric Oven Drying at 105°C', result: '65.0' },
    { test: '% Fiber', test_method: 'Weende Method', result: '2.1' },
    { test: '% Carbohydrates', test_method: 'Phenol Sulfuric Acid Method', result: '12.4' },
    { test: '% Ash', test_method: 'Oxidation at 550°C', result: '1.2' },
    { test: '% Sodium', test_method: 'FLAME-AES', result: '0.45' },
    { test: 'Sugar, Bx', test_method: 'Refractometer', result: '4.5' },
];

export function matrixConfig(field: DesignerField): MatrixTableConfig {
    const raw = (field.table_config ?? {}) as MatrixTableConfig;
    const columns =
        Array.isArray(raw.columns) && raw.columns.length > 0
            ? raw.columns
            : DEFAULT_MATRIX_CONFIG.columns!;

    const merged: MatrixTableConfig = {
        ...DEFAULT_MATRIX_CONFIG,
        ...raw,
        columns,
    };

    // Nitrite: keep natural row height (1 sample → 1 short row, not one stretched cell).
    if (field.name === 'nitrite_f016_matrix') {
        return {
            ...merged,
            stretch_body: false,
        };
    }

    return merged;
}

export function matrixPreviewData(
    config: MatrixTableConfig,
    baseRows: Array<Record<string, string>> = MATRIX_DESIGNER_PREVIEW_ROWS,
): Array<Record<string, string>> {
    const stored = config.preview_data ?? [];
    const source = baseRows.length > 0 ? baseRows : MATRIX_DESIGNER_PREVIEW_ROWS;
    const desired =
        typeof config.preview_rows === 'number' && config.preview_rows > 0
            ? config.preview_rows
            : Math.max(source.length, stored.length);

    // Same as other matrices: clamp to bound package/type preview rows from the server.
    const count = Math.min(desired, Math.max(source.length, stored.length || source.length));

    return Array.from({ length: count }, (_, index) => {
        const base = source[index] ?? {};
        const override = stored[index] ?? {};
        const merged: Record<string, string> = { ...base };

        for (const [key, value] of Object.entries(override)) {
            if (
                (key === 'test' || key === 'test_method') &&
                (value === undefined || value === null || String(value).trim() === '')
            ) {
                continue;
            }

            merged[key] = value == null ? '' : String(value);
        }

        return merged;
    });
}

export function columnWidthPct(columns: MatrixColumnConfig[], index: number): number {
    const count = Math.max(1, columns.length);
    const raw = columns.map((column) =>
        column?.width_pct && column.width_pct > 0 ? column.width_pct : 0,
    );
    const sum = raw.reduce((total, pct) => total + pct, 0);

    if (sum <= 0) {
        return 100 / count;
    }

    const pct = raw[index] ?? 0;

    if (pct <= 0) {
        return 0;
    }

    // Last positive column absorbs floating-point drift so widths sum to 100.
    const positiveIndexes = raw
        .map((value, i) => (value > 0 ? i : -1))
        .filter((i) => i >= 0);
    const lastPositive = positiveIndexes[positiveIndexes.length - 1];

    if (index === lastPositive) {
        const others = positiveIndexes
            .filter((i) => i !== lastPositive)
            .reduce((total, i) => total + (100 * (raw[i] ?? 0)) / sum, 0);

        return 100 - others;
    }

    return (100 * pct) / sum;
}

export function columnWidthPctSum(columns: MatrixColumnConfig[]): number {
    return columns.reduce((total, column) => {
        const pct = column?.width_pct && column.width_pct > 0 ? column.width_pct : 0;

        return total + pct;
    }, 0);
}

export function cellValue(row: Record<string, string>, key: string): string {
    if (key === 'test') {
        return row.test ?? '';
    }

    if (key === 'result') {
        return row.result ?? row.sample_1 ?? '';
    }

    return row[key] ?? '';
}

export function selectionLabel(selection: MatrixCellSelection): string {
    if (selection.area === 'header') {
        return `Header · ${selection.columnKey} · ${selection.part}`;
    }

    return `Row ${selection.rowIndex + 1} · ${selection.columnKey}${selection.part !== 'value' ? ` · ${selection.part}` : ''}`;
}

export function readCellText(
    config: MatrixTableConfig,
    columns: MatrixColumnConfig[],
    selection: MatrixCellSelection,
    baseRows?: Array<Record<string, string>>,
): string {
    const column = columns.find((item) => item.key === selection.columnKey);

    if (!column) {
        return '';
    }

    if (selection.area === 'header') {
        return selection.part === 'label' ? column.label : column.sublabel ?? '';
    }

    const row = matrixPreviewData(config, baseRows)[selection.rowIndex] ?? {};

    if (selection.part === 'test') {
        return row.test ?? '';
    }

    if (selection.part === 'test_method') {
        return row.test_method ?? '';
    }

    return cellValue(row, selection.columnKey);
}

export function patchCellText(
    config: MatrixTableConfig,
    selection: MatrixCellSelection,
    text: string,
    baseRows?: Array<Record<string, string>>,
): MatrixTableConfig {
    if (selection.area === 'header') {
        const columns = (config.columns ?? []).map((column) => {
            if (column.key !== selection.columnKey) {
                return column;
            }

            if (selection.part === 'label') {
                return { ...column, label: text };
            }

            return { ...column, sublabel: text || null };
        });

        return { ...config, columns };
    }

    const preview_data = matrixPreviewData(config, baseRows).map((row, index) => {
        if (index !== selection.rowIndex) {
            return row;
        }

        if (selection.part === 'test') {
            return { ...row, test: text };
        }

        if (selection.part === 'test_method') {
            return { ...row, test_method: text };
        }

        return { ...row, [selection.columnKey]: text };
    });

    return { ...config, preview_data };
}

export function patchSelectedColumn(
    config: MatrixTableConfig,
    selection: MatrixCellSelection,
    patch: Partial<MatrixColumnConfig>,
): MatrixTableConfig {
    const columns = (config.columns ?? []).map((column) =>
        column.key === selection.columnKey ? { ...column, ...patch } : column,
    );

    return { ...config, columns };
}

export function findColumn(config: MatrixTableConfig, columnKey: string): MatrixColumnConfig | undefined {
    return config.columns?.find((column) => column.key === columnKey);
}
