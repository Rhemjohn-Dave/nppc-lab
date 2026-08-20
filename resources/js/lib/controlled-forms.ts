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
