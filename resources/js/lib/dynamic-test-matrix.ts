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

/** Designer-only chrome above the printable matrix (in-flow; does not overlay headers). */
export const MATRIX_DESIGNER_DRAG_HANDLE_PX = 16;

export function matrixMethodFontSize(fieldFontSize: number, config: MatrixTableConfig): number {
    if (typeof config.method_font_size === 'number' && config.method_font_size > 0) {
        return config.method_font_size;
    }

    return Math.max(5, fieldFontSize - 1);
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
