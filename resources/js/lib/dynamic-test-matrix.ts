import type { DesignerField } from '@/lib/controlled-forms';

export type MatrixColumnConfig = {
    key: string;
    label: string;
    sublabel?: string | null;
    width_pct?: number;
    align?: 'L' | 'C' | 'R';
    header_align?: 'L' | 'C' | 'R';
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
        let colHeight = padding + labelLine + padding;

        if (column.sublabel) {
            const subLine = matrixLineHeightMm(Math.max(6, colFontSize - 1));
            colHeight = padding + labelLine + gap + subLine + padding;
        }

        maxHeight = Math.max(maxHeight, colHeight);
    }

    return maxHeight;
}

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
        { key: 'test', label: 'TEST', width_pct: 42, align: 'L' },
        { key: 'sample_1', label: 'Sample 1', sublabel: 'SS: 18g', width_pct: 29, align: 'C' },
        { key: 'sample_2', label: 'Sample 2', sublabel: 'SS: 18g', width_pct: 29, align: 'C' },
    ],
    row_height_mm: 7,
    header_row: true,
    border: true,
    preview_rows: 8,
};

export const MATRIX_DESIGNER_PREVIEW_ROWS: Array<Record<string, string>> = [
    { test: '% Fat', test_method: 'Soxhlet Extraction Method', sample_1: '0.68', sample_2: '0.59' },
    { test: '% Protein', test_method: 'Kjeldahl Method', sample_1: '18.2', sample_2: '18.0' },
    { test: '% Moisture', test_method: 'Gravimetric Oven Drying at 105°C', sample_1: '65.0', sample_2: '64.8' },
    { test: '% Fiber', test_method: 'Weende Method', sample_1: '2.1', sample_2: '2.0' },
    { test: '% Carbohydrates', test_method: 'Phenol Sulfuric Acid Method', sample_1: '12.4', sample_2: '12.6' },
    { test: '% Ash', test_method: 'Oxidation at 550°C', sample_1: '1.2', sample_2: '1.1' },
    { test: 'Sugar, Bx', test_method: 'Refractometer', sample_1: '4.5', sample_2: '4.4' },
    { test: 'Nitrite (mg/kg)', test_method: 'Spectrophotometric Method', sample_1: '12', sample_2: '11' },
];

export function matrixConfig(field: DesignerField): MatrixTableConfig {
    const raw = (field.table_config ?? {}) as MatrixTableConfig;
    const columns =
        Array.isArray(raw.columns) && raw.columns.length > 0
            ? raw.columns
            : DEFAULT_MATRIX_CONFIG.columns!;

    return {
        ...DEFAULT_MATRIX_CONFIG,
        ...raw,
        columns,
    };
}

export function matrixPreviewData(config: MatrixTableConfig): Array<Record<string, string>> {
    const count = Math.min(
        config.preview_rows ?? MATRIX_DESIGNER_PREVIEW_ROWS.length,
        MATRIX_DESIGNER_PREVIEW_ROWS.length,
    );
    const stored = config.preview_data ?? [];

    return Array.from({ length: count }, (_, index) => ({
        ...MATRIX_DESIGNER_PREVIEW_ROWS[index],
        ...(stored[index] ?? {}),
    }));
}

export function columnWidthPct(columns: MatrixColumnConfig[], index: number): number {
    const column = columns[index];
    if (column?.width_pct && column.width_pct > 0) {
        return column.width_pct;
    }

    return 100 / Math.max(1, columns.length);
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
): string {
    const column = columns.find((item) => item.key === selection.columnKey);
    if (!column) {
        return '';
    }

    if (selection.area === 'header') {
        return selection.part === 'label' ? column.label : column.sublabel ?? '';
    }

    const row = matrixPreviewData(config)[selection.rowIndex] ?? {};
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

    const preview_data = matrixPreviewData(config).map((row, index) => {
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
