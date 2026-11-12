export type AnalysisGroup = {
    label: string;
    items: Array<{ id: number; code: string; name: string }>;
};

export type ControlledFormSummary = {
    id: number;
    form_code: string;
    name: string;
    description: string | null;
    department: string | null;
    category: string;
    category_label: string;
    combination_key: string | null;
    current_revision: ControlledRevisionSummary | null;
    status: string | null;
    status_label: string;
    updated_at: string | null;
    revisions_count: number;
    analysis_type_ids: number[];
    analysis_package_id?: number | null;
    analyst_signatory_slots?: number;
    analyst_require_prc?: boolean;
    has_blueprint?: boolean;
    revisions?: ControlledRevisionSummary[];
    analysis_types?: Array<{ id: number; code: string; name: string }>;
};

export type ControlledRevisionSummary = {
    id: number;
    revision: string;
    status: string;
    status_label: string;
    effective_date: string | null;
    notes: string | null;
    original_name: string | null;
    has_canonical: boolean;
    has_original: boolean;
    page_count: number;
    page_width_mm: number | null;
    page_height_mm: number | null;
    fill_mode: string;
    sha256: string | null;
    created_by: string | null;
    approved_by: string | null;
    approved_at: string | null;
    created_at: string | null;
    editable: boolean;
    field_count: number;
    fields?: DesignerField[];
};

export type FieldAnchor = {
    below: string;
    offset_mm: number;
    /** Clamp toward form-meta (FO2/FO3/FO37); preserved when Designer edits below/offset. */
    above?: string;
    above_offset_mm?: number;
};

export type DesignerField = {
    id?: number;
    name: string;
    label: string;
    field_type: string;
    page_number: number;
    x: number;
    y: number;
    width: number;
    height: number;
    font_size: number;
    font_family: string;
    font_color: string;
    alignment: string;
    data_source_key: string | null;
    format: string | null;
    checkbox_true_value: string | null;
    options: Record<string, unknown> | null;
    table_config: Record<string, unknown> | null;
    z_order: number;
};

export function fieldAnchor(field: DesignerField): FieldAnchor | null {
    const options = field.options;
    if (!options || typeof options !== 'object') {
        return null;
    }
    const raw = options.anchor;
    if (!raw || typeof raw !== 'object') {
        return null;
    }
    const below = String((raw as { below?: unknown }).below ?? '').trim();
    if (below === '') {
        return null;
    }
    const offsetRaw = (raw as { offset_mm?: unknown }).offset_mm;
    const offset_mm = typeof offsetRaw === 'number' && Number.isFinite(offsetRaw) ? offsetRaw : 0;
    const above = String((raw as { above?: unknown }).above ?? '').trim();
    const aboveOffsetRaw = (raw as { above_offset_mm?: unknown }).above_offset_mm;
    const above_offset_mm =
        typeof aboveOffsetRaw === 'number' && Number.isFinite(aboveOffsetRaw)
            ? aboveOffsetRaw
            : undefined;

    return {
        below,
        offset_mm,
        ...(above !== '' ? { above } : {}),
        ...(above_offset_mm !== undefined ? { above_offset_mm } : {}),
    };
}

/** Keep above/above_offset_mm when Designer only edits below or offset. */
export function patchFieldAnchor(
    current: FieldAnchor | null,
    patch: { below?: string; offset_mm?: number },
): FieldAnchor | null {
    const below = (patch.below ?? current?.below ?? '').trim();
    if (below === '') {
        return null;
    }

    return {
        below,
        offset_mm:
            typeof patch.offset_mm === 'number' && Number.isFinite(patch.offset_mm)
                ? patch.offset_mm
                : (current?.offset_mm ?? 3),
        ...(current?.above ? { above: current.above } : {}),
        ...(current?.above_offset_mm !== undefined
            ? { above_offset_mm: current.above_offset_mm }
            : {}),
    };
}

/** Shared matrix name when every selected field anchors below the same matrix; null if mixed/none. */
export function sharedAnchorBelow(fields: DesignerField[]): string | null {
    if (fields.length === 0) {
        return null;
    }

    const first = fieldAnchor(fields[0])?.below ?? '';
    if (first === '') {
        return null;
    }

    return fields.every((field) => (fieldAnchor(field)?.below ?? '') === first)
        ? first
        : null;
}

/** Lowest offset_mm among anchored fields in the selection; null if none are anchored. */
export function minAnchorOffsetMm(fields: DesignerField[]): number | null {
    let min: number | null = null;
    for (const field of fields) {
        const anchor = fieldAnchor(field);
        if (!anchor) {
            continue;
        }
        min = min === null ? anchor.offset_mm : Math.min(min, anchor.offset_mm);
    }

    return min;
}

/**
 * Printed single-line rhythm in mm (matches ControlledPdfFiller multiline lineHeight).
 */
export function textLineHeightMm(fontSize: number): number {
    const size = Number.isFinite(fontSize) ? fontSize : 11;

    return Math.max(3, Math.round(size * 0.42 * 1000) / 1000);
}

/**
 * Shift a field's flow offset and designer Y together (preserves above clamp).
 * Unanchored fields still move on the canvas via y.
 */
export function shiftFieldFlow(
    field: DesignerField,
    deltaMm: number,
): Pick<DesignerField, 'y' | 'options'> {
    const nextY = Math.round((field.y + deltaMm) * 1000) / 1000;
    const current = fieldAnchor(field);
    if (!current) {
        return { y: nextY, options: field.options };
    }

    const nextAnchor = patchFieldAnchor(current, {
        offset_mm: Math.round((current.offset_mm + deltaMm) * 1000) / 1000,
    });

    return {
        y: nextY,
        options: patchFieldOptions(field.options, { anchor: nextAnchor }),
    };
}

export function fieldStaticText(field: DesignerField): string {
    const options = field.options;
    if (!options || typeof options !== 'object') {
        return '';
    }

    return String(options.static_text ?? '');
}

export function fieldBold(field: DesignerField): boolean {
    return field.options?.bold === true;
}

export type InlineBoldSegment = { text: string; bold: boolean };

/**
 * Exact **…** pairs only — mirror App\\Support\\InlineBoldMarkup
 * (do not steal FO2 footnote markers like **Not / ***Not).
 */
const INLINE_BOLD_PAIR = /(?<!\*)\*\*(?!\*)([\s\S]*?)(?<!\*)\*\*(?!\*)/;

/** Mirror App\\Support\\InlineBoldMarkup::segments for Designer canvas parity. */
export function parseInlineBoldSegments(text: string): InlineBoldSegment[] {
    if (text === '') {
        return [];
    }
    if (!text.includes('**')) {
        return [{ text, bold: false }];
    }

    const parts = text.split(INLINE_BOLD_PAIR);
    const segments: InlineBoldSegment[] = [];
    for (let index = 0; index < parts.length; index += 1) {
        const part = parts[index];
        if (part === '') {
            continue;
        }
        segments.push({ text: part, bold: index % 2 === 1 });
    }

    return segments.length === 0 ? [{ text, bold: false }] : segments;
}

/** Wrap or unwrap ** markers around a textarea selection. */
export function toggleInlineBoldSelection(
    value: string,
    selectionStart: number,
    selectionEnd: number,
): { value: string; selectionStart: number; selectionEnd: number } {
    const start = Math.max(0, Math.min(selectionStart, selectionEnd));
    const end = Math.max(0, Math.max(selectionStart, selectionEnd));
    if (start === end) {
        const next = `${value.slice(0, start)}****${value.slice(end)}`;
        return {
            value: next,
            selectionStart: start + 2,
            selectionEnd: start + 2,
        };
    }

    const selected = value.slice(start, end);
    const before = value.slice(0, start);
    const after = value.slice(end);

    if (selected.startsWith('**') && selected.endsWith('**') && selected.length >= 4) {
        const inner = selected.slice(2, -2);
        return {
            value: `${before}${inner}${after}`,
            selectionStart: start,
            selectionEnd: start + inner.length,
        };
    }

    if (before.endsWith('**') && after.startsWith('**')) {
        return {
            value: `${before.slice(0, -2)}${selected}${after.slice(2)}`,
            selectionStart: start - 2,
            selectionEnd: end - 2,
        };
    }

    return {
        value: `${before}**${selected}**${after}`,
        selectionStart: start,
        selectionEnd: end + 4,
    };
}

export function fieldListStyle(
    field: DesignerField,
): 'numbered' | 'bullet' | null {
    const raw = String(field.options?.list_style ?? '')
        .trim()
        .toLowerCase();
    if (raw === 'numbered' || raw === 'bullet') {
        return raw;
    }

    return null;
}

export function fieldListIndentMm(field: DesignerField): number {
    const raw = Number(field.options?.list_indent_mm);

    return Number.isFinite(raw) && raw > 0 ? raw : 6;
}

export type MultilineListPreview = {
    headers: string[];
    items: Array<{ marker: string; body: string }>;
};

/** Mirror ControlledPdfFiller list parsing for Designer canvas parity. */
export function parseMultilineListPreview(
    text: string,
    listStyle: 'numbered' | 'bullet',
): MultilineListPreview {
    const lines = text.split(/\r\n|\n|\r/);
    const headers: string[] = [];
    const items: Array<{ marker: string; body: string }> = [];
    let inItems = false;

    for (const raw of lines) {
        const line = raw.replace(/\s+$/u, '');
        if (line === '' && !inItems) {
            continue;
        }

        const numbered = line.match(/^(\d+\.)\s+(.*)$/u);
        if (listStyle === 'numbered' && numbered) {
            inItems = true;
            items.push({ marker: numbered[1], body: numbered[2] });
            continue;
        }

        const bullet = line.match(/^([-*•])\s+(.*)$/u);
        if (listStyle === 'bullet' && bullet) {
            inItems = true;
            items.push({ marker: '•', body: bullet[2] });
            continue;
        }

        if (inItems && line !== '' && /^\s+/u.test(line) && items.length > 0) {
            items[items.length - 1].body += ` ${line.trim()}`;
            continue;
        }

        if (!inItems && line !== '') {
            headers.push(line);
        }
    }

    return { headers, items };
}

/** Merge patch into options; omit empty keys; return null when empty. */
export function patchFieldOptions(
    current: Record<string, unknown> | null,
    patch: Record<string, unknown | null | undefined>,
): Record<string, unknown> | null {
    const next: Record<string, unknown> = { ...(current ?? {}) };
    for (const [key, value] of Object.entries(patch)) {
        if (value === null || value === undefined || value === '') {
            delete next[key];
        } else {
            next[key] = value;
        }
    }

    return Object.keys(next).length > 0 ? next : null;
}

export type DataSource = {
    key: string;
    label: string;
    type: string;
    group: string;
    hint?: string | null;
    focused?: boolean;
};

export type FieldTypeOption = {
    value: string;
    label: string;
    width: number;
    height: number;
};

export function statusBadgeClass(status: string | null | undefined): string {
    switch (status) {
        case 'active':
            return 'border-emerald-200 bg-emerald-50 text-emerald-800';
        case 'draft':
            return 'border-slate-200 bg-slate-50 text-slate-700';
        case 'for_review':
            return 'border-amber-200 bg-amber-50 text-amber-900';
        case 'for_approval':
            return 'border-orange-200 bg-orange-50 text-orange-900';
        case 'approved':
            return 'border-sky-200 bg-sky-50 text-sky-800';
        case 'superseded':
            return 'border-zinc-200 bg-zinc-100 text-zinc-600';
        case 'archived':
            return 'border-zinc-200 bg-zinc-50 text-zinc-500';
        default:
            return 'border-slate-200 bg-white text-slate-600';
    }
}
