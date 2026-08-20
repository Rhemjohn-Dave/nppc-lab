import type { DataSource, DesignerField, FieldTypeOption } from '@/lib/controlled-forms';

export function cloneFields(fields: DesignerField[]): DesignerField[] {
    return fields.map((field) => ({ ...field }));
}

export function clientId(field: DesignerField, index: number): string {
    return field.name || `tmp-${index}`;
}

export function fieldsEqual(a: DesignerField[], b: DesignerField[]): boolean {
    return JSON.stringify(a) === JSON.stringify(b);
}

export function sanitizeFieldName(key: string, index: number): string {
    const cleaned = key
        .replace(/[^a-zA-Z0-9_.]/g, '_')
        .replace(/_+/g, '_')
        .replace(/^_|_$/g, '');

    return cleaned.slice(0, 60) || `field_${index}`;
}

export function defaultTableColumns(sourceKey: string): string[] {
    if (sourceKey === 'samples[]') {
        return ['sample_code', 'description', 'matrix', 'quantity', 'unit'];
    }

    if (sourceKey === 'analyses[]') {
        return ['name', 'category', 'unit_price', 'total_cost'];
    }

    return [];
}

export function sourceToField(
    source: DataSource,
    fieldTypes: FieldTypeOption[],
    page: number,
    x: number,
    y: number,
    index: number,
): DesignerField {
    const fieldType = source.type === 'table' ? 'table' : source.type || 'text';
    const typeOption =
        fieldTypes.find((option) => option.value === fieldType) ?? fieldTypes[0];

    const isTable = fieldType === 'table';

    return {
        name: sanitizeFieldName(source.key, index),
        label: source.label,
        field_type: fieldType,
        page_number: page,
        x: Math.max(0, Number(x.toFixed(3))),
        y: Math.max(0, Number(y.toFixed(3))),
        width: isTable ? 170 : (typeOption?.width ?? 40),
        height: isTable ? 45 : (typeOption?.height ?? 5),
        font_size: 11,
        font_family: 'calibri',
        font_color: '#000000',
        alignment: 'L',
        data_source_key: source.key,
        format: null,
        checkbox_true_value: source.type === 'checkbox' ? '1' : null,
        options: null,
        table_config: isTable
            ? {
                  row_height: 4.5,
                  max_rows: 9,
                  columns: defaultTableColumns(source.key),
              }
            : null,
        z_order: index,
    };
}

export const DRAG_SOURCE_MIME = 'application/x-designer-source';

export function cssFontFamily(family: string): string {
    switch (family) {
        case 'calibri':
            return 'Calibri, Carlito, "Segoe UI", sans-serif';
        case 'times':
            return '"Times New Roman", Times, serif';
        case 'courier':
            return '"Courier New", Courier, monospace';
        default:
            return 'Helvetica, Arial, sans-serif';
    }
}
