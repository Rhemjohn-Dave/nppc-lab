export type SampleSummary = {
    id?: number | null;
    sample_code?: string | null;
    description?: string | null;
    matrix?: string | null;
    control_number?: string | null;
    submitted_for_review?: boolean;
    released?: boolean;
    can_edit_description?: boolean;
    /** Wastewater / River water / Abstracted water, or omitted for free text. */
    description_choices?: string[] | null;
};

export type ResultFact = {
    key: string;
    label: string;
    type: 'text' | 'datetime' | 'time';
    value: string;
    time: string | null;
};

export type ReportSummary = {
    kind: 'combined' | 'individual' | 'waiting' | 'unavailable';
    title: string;
    message?: string | null;
    can_preview: boolean;
    can_print?: boolean;
    sample_id?: number | null;
    form_id?: number | null;
    control_number?: string | null;
    /** Assays on this result sheet still needing completion. */
    missing?: string[];
};

export type AnalystTask = {
    id: number;
    name: string;
    sample_id?: number | null;
    sample_label?: string | null;
    control_number?: string | null;
    analysis_type_id?: number | null;
    category_label?: string | null;
    status: string;
    status_label: string;
    analysis_type_code?: string | null;
    /** DW-02/DW-03: separate Raw / Finish measurements. */
    uses_raw_finish?: boolean;
    /** DW-05: Finish + Chlorinated (Raw Results always "-"). */
    uses_residual_chlorine?: boolean;
    result_value?: string | null;
    result_raw?: string | null;
    result_finish?: string | null;
    result_chlorinated?: string | null;
    result_pass_fail?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
    result_remarks?: string | null;
    result_remarks_raw?: string | null;
    result_remarks_finish?: string | null;
    result_remarks_chlorinated?: string | null;
    result_method?: string | null;
    /** FO2/FO2A Notes mark: null = catalog default, '' = none, *, **, ***. */
    accreditation_footnote?: string | null;
    supports_accreditation_footnote?: boolean;
    performed_at?: string | null;
    procedure_method?: string | null;
    result_mode?: 'value' | 'pass_fail';
    requires_pass_fail?: boolean;
    /** Billable JO line only: no result is encoded or printed. */
    is_sample_preparation?: boolean;
    updated_at?: string | null;
    assigned_to?: number | null;
    assignee_name?: string | null;
    is_mine?: boolean;
    can_work?: boolean;
    report: ReportSummary;
    result_facts?: ResultFact[];
    can_edit_result_facts?: boolean;
    job_order: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name?: string | null;
        classification?: string | null;
        sampling_site?: string | null;
        sample_storage_temp?: string | null;
        field_data?: string | null;
        status?: string;
        received_at?: string | null;
        reviewed_at?: string | null;
        review_notes?: string | null;
        samples: SampleSummary[];
    };
};

export type ResultSignatoryConfig = {
    slots: number;
    require_prc: boolean;
    confirmed: boolean;
    can_edit?: boolean;
    signatories: Array<{ name: string; prc_id: string | null }>;
    save_url?: string | null;
};

export type ResultFooterConfig = {
    temperature_c?: string;
    humidity_pct?: string;
    test_report_no?: string;
    test_report_prefix?: string;
    lab_conditions: string;
    references: string;
    notes: string;
    field_data?: string;
    field_data_temperature?: string;
    field_data_dissolved_oxygen?: string;
    field_data_time_of_sampling?: string;
    field_data_time_of_sampling_from?: string;
    field_data_time_of_sampling_to?: string;
    requires_atmosphere?: boolean;
    requires_field_data?: boolean;
    atmosphere_complete?: boolean;
    field_data_complete?: boolean;
    show_notes_fields?: boolean;
    show_field_data?: boolean;
    save_url?: string | null;
    can_edit?: boolean;
};

export type ConsolidationReport = {
    form_id?: number | null;
    title?: string;
    can_submit: boolean;
    can_preview?: boolean;
    preview_url?: string | null;
    submitted?: boolean;
    missing: string[];
};

export type ConsolidationSample = {
    id: number;
    sample_code?: string | null;
    description?: string | null;
    control_number?: string | null;
    can_submit: boolean;
    can_preview?: boolean;
    form_id?: number | null;
    preview_url?: string | null;
    submitted: boolean;
    released: boolean;
    missing: string[];
    reports?: ConsolidationReport[];
};

export type Consolidation = {
    id: number;
    reference_no: string;
    customer_name: string;
    can_submit: boolean;
    can_preview?: boolean;
    form_id?: number | null;
    preview_url?: string | null;
    preview_message?: string | null;
    missing: string[];
    review_notes?: string | null;
    signatory?: ResultSignatoryConfig | null;
    footer?: ResultFooterConfig | null;
    samples?: ConsolidationSample[];
    unscoped_can_submit?: boolean;
    lines: Array<{
        id: number;
        sample_id?: number | null;
        name: string;
        assignee_name?: string | null;
        status: string;
        status_label: string;
        result_value?: string | null;
        completed: boolean;
    }>;
};

export type ReleasedPrint = {
    id: number;
    reference_no: string;
    customer_name: string;
    job_status?: string;
    can_print: boolean;
    can_preview?: boolean;
    print_url: string | null;
    preview_url?: string | null;
    samples?: Array<{
        id: number;
        can_preview?: boolean;
        preview_url?: string | null;
        submitted?: boolean;
        released?: boolean;
    }>;
    signatory?: ResultSignatoryConfig | null;
    footer?: ResultFooterConfig | null;
};

export type JobGroup = {
    job: AnalystTask['job_order'];
    tasks: AnalystTask[];
};

export type SampleBreakdownRow = {
    key: string;
    sample_id: number | null;
    label: string;
    control_number: string | null;
    total: number;
    done: number;
    tasks: AnalystTask[];
};

export type WorkflowStep = {
    key: string;
    label: string;
    state: 'done' | 'current' | 'upcoming';
    detail?: string | null;
};

export function statusBadgeClass(status: string): string {
    if (status === 'returned') {
        return 'border-amber-200 bg-amber-50 text-amber-900';
    }

    if (status === 'in_progress') {
        return 'border-sky-200 bg-sky-50 text-sky-900';
    }

    if (status === 'completed') {
        return 'border-emerald-200 bg-emerald-50 text-emerald-900';
    }

    return 'border-nppc-200 bg-nppc-50 text-nppc-900';
}

/** Icon glyph + label for analysis/job aggregate statuses (not color alone). */
export function statusDisplay(status: string): { icon: string; label: string } {
    switch (status) {
        case 'returned':
            return { icon: '↩', label: 'Returned' };
        case 'in_progress':
            return { icon: '●', label: 'In progress' };
        case 'completed':
            return { icon: '✓', label: 'Completed' };
        case 'pending':
        case 'assigned':
            return { icon: '○', label: 'Assigned' };
        default:
            return { icon: '○', label: status.replace(/_/g, ' ') };
    }
}

export function summarizeRawFinishResults(
    raw?: string | null,
    finish?: string | null,
): string {
    const parts: string[] = [];
    const rawText = (raw ?? '').trim();
    const finishText = (finish ?? '').trim();
    if (rawText) {
        parts.push(`Raw: ${rawText}`);
    }
    if (finishText) {
        parts.push(`Finish: ${finishText}`);
    }
    return parts.join('; ');
}

/** Sentinel stored in result_raw when Residual Chlorine mode is Raw. */
export const RESIDUAL_CHLORINE_RAW_SENTINEL = '-';

export function summarizeResidualChlorineResults(
    finish?: string | null,
    chlorinated?: string | null,
    raw?: string | null,
): string {
    const rawText = (raw ?? '').trim();
    if (rawText) {
        return `Raw: ${RESIDUAL_CHLORINE_RAW_SENTINEL}`;
    }
    const parts: string[] = [];
    const finishText = (finish ?? '').trim();
    const chlorinatedText = (chlorinated ?? '').trim();
    if (finishText) {
        parts.push(`Finish: ${finishText}`);
    }
    if (chlorinatedText) {
        parts.push(`Chlorinated: ${chlorinatedText}`);
    }
    return parts.join('; ');
}

/** Exclusive Residual Chlorine encode mode from stored fields. */
export function residualChlorineMode(fields: {
    result_raw?: string | null;
    result_finish?: string | null;
    result_chlorinated?: string | null;
}): 'finish' | 'chlorinated' | 'raw' | null {
    if ((fields.result_raw ?? '').trim()) {
        return 'raw';
    }
    if ((fields.result_finish ?? '').trim()) {
        return 'finish';
    }
    if ((fields.result_chlorinated ?? '').trim()) {
        return 'chlorinated';
    }
    return null;
}

export function encodedResultLabel(task: {
    result_value?: string | null;
    result_raw?: string | null;
    result_finish?: string | null;
    result_chlorinated?: string | null;
    uses_raw_finish?: boolean;
    uses_residual_chlorine?: boolean;
    result_pass_fail?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
    requires_pass_fail?: boolean;
}): string {
    const measured =
        task.uses_raw_finish === true
            ? summarizeRawFinishResults(task.result_raw, task.result_finish) ||
              (task.result_value ?? '').trim()
            : task.uses_residual_chlorine === true
              ? summarizeResidualChlorineResults(
                    task.result_finish,
                    task.result_chlorinated,
                    task.result_raw,
                ) || (task.result_value ?? '').trim()
              : [task.result_value, task.result_unit]
                    .map((part) => (part ?? '').trim())
                    .filter(Boolean)
                    .join(' ');
    const withUnit =
        (task.uses_raw_finish === true ||
            task.uses_residual_chlorine === true) &&
        measured &&
        (task.result_unit ?? '').trim()
            ? `${measured} ${task.result_unit!.trim()}`
            : measured;
    const passFail =
        task.requires_pass_fail === true
            ? (task.result_pass_fail ?? '').trim()
            : '';

    return [withUnit, passFail].filter(Boolean).join(' · ');
}

/** True when the analyst has saved a draft/complete quantitative result. */
export function hasEncodedResult(task: AnalystTask): boolean {
    if (task.is_sample_preparation === true) {
        return true;
    }
    if (task.uses_raw_finish === true) {
        return (
            Boolean(task.result_raw?.trim()) ||
            Boolean(task.result_finish?.trim())
        );
    }
    if (task.uses_residual_chlorine === true) {
        return (
            Boolean(task.result_finish?.trim()) ||
            Boolean(task.result_chlorinated?.trim()) ||
            Boolean(task.result_raw?.trim())
        );
    }
    return Boolean(task.result_value?.trim());
}

export function taskActionLabel(task: AnalystTask): string {
    if (task.status === 'returned') {
        return 'Correct result';
    }

    if (task.status === 'completed') {
        return canWorkTask(task) ? 'Edit result' : 'View result';
    }

    if (task.status === 'in_progress' || task.result_value) {
        return 'Continue';
    }

    return 'Enter result';
}

export function canWorkTask(task: AnalystTask): boolean {
    if (task.can_work !== undefined) {
        return task.can_work;
    }

    return task.is_mine !== false;
}

export function mineTasks(tasks: AnalystTask[]): AnalystTask[] {
    return tasks.filter((t) => t.is_mine !== false);
}

export function workableTasks(tasks: AnalystTask[]): AnalystTask[] {
    return tasks.filter((t) => canWorkTask(t));
}

export function jobProgress(tasks: AnalystTask[]): {
    done: number;
    total: number;
    percent: number;
} {
    const scope = workableTasks(tasks);
    const total = scope.length;
    const done = scope.filter((t) => t.status === 'completed').length;

    return {
        done,
        total,
        percent: total === 0 ? 0 : Math.round((done / total) * 100),
    };
}

export function jobAggregateStatus(tasks: AnalystTask[]): {
    key: string;
    label: string;
} {
    const scope = workableTasks(tasks);

    if (scope.some((t) => t.status === 'returned')) {
        return { key: 'returned', label: 'Returned' };
    }

    if (scope.some((t) => t.status === 'in_progress')) {
        return { key: 'in_progress', label: 'In progress' };
    }

    if (scope.length > 0 && scope.every((t) => t.status === 'completed')) {
        return { key: 'completed', label: 'Completed' };
    }

    return { key: 'assigned', label: 'Needs action' };
}

export function jobMetaLine(
    job: Pick<
        AnalystTask['job_order'],
        'classification' | 'sample_storage_temp'
    > & {
        samples?: SampleSummary[] | null;
    },
): string {
    const parts: string[] = [];

    if (job.classification) {
        parts.push(job.classification);
    }

    const sampleCount = job.samples?.length ?? 0;
    parts.push(`${sampleCount} sample${sampleCount === 1 ? '' : 's'}`);

    if (job.sample_storage_temp) {
        parts.push(`Storage ${job.sample_storage_temp}`);
    }

    return parts.join(' · ');
}

export function taskSampleLine(task: AnalystTask): string | null {
    return task.sample_label?.trim() || null;
}

/**
 * Group tasks by sample for multi-sample job orders (preserving sample order from job.samples).
 */
export function groupTasksBySample(tasks: AnalystTask[]): SampleBreakdownRow[] {
    if (tasks.length === 0) {
        return [];
    }

    const job = tasks[0].job_order;
    const samples = job.samples ?? [];
    const bySampleId = new Map<number | 'none', AnalystTask[]>();

    for (const task of tasks) {
        const key =
            task.sample_id == null ? 'none' : Number(task.sample_id);
        const list = bySampleId.get(key) ?? [];
        list.push(task);
        bySampleId.set(key, list);
    }

    const rows: SampleBreakdownRow[] = [];

    samples.forEach((sample, index) => {
        const id = sample.id == null ? null : Number(sample.id);
        const sampleTasks =
            id == null ? [] : (bySampleId.get(id) ?? []);
        if (id != null) {
            bySampleId.delete(id);
        }

        const code = sample.sample_code?.trim();
        const description =
            sample.description?.trim() || `Sample ${index + 1}`;
        const label = code ? `${code} — ${description}` : description;
        const done = sampleTasks.filter((t) => t.status === 'completed').length;

        rows.push({
            key: id != null ? `sample-${id}` : `sample-idx-${index}`,
            sample_id: id,
            label,
            control_number: sample.control_number ?? null,
            total: sampleTasks.length,
            done,
            tasks: sampleTasks,
        });
    });

    const orphan = bySampleId.get('none') ?? [];
    for (const [key, sampleTasks] of bySampleId) {
        if (key === 'none') {
            continue;
        }
        const first = sampleTasks[0];
        rows.push({
            key: `sample-${key}`,
            sample_id: Number(key),
            label: first?.sample_label || `Sample`,
            control_number: first?.control_number ?? null,
            total: sampleTasks.length,
            done: sampleTasks.filter((t) => t.status === 'completed').length,
            tasks: sampleTasks,
        });
    }

    if (orphan.length > 0) {
        rows.push({
            key: 'sample-none',
            sample_id: null,
            label: 'Unassigned sample',
            control_number: null,
            total: orphan.length,
            done: orphan.filter((t) => t.status === 'completed').length,
            tasks: orphan,
        });
    }

    return rows.filter((row) => row.total > 0);
}

export function sampleBreakdown(tasks: AnalystTask[]): SampleBreakdownRow[] {
    return groupTasksBySample(tasks);
}

export function actionableTasks(tasks: AnalystTask[]): AnalystTask[] {
    return tasks.filter(
        (t) => canWorkTask(t) && t.status !== 'completed',
    );
}

export function formatLabTimestamp(iso: string | null | undefined): string | null {
    if (!iso) {
        return null;
    }

    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
        return null;
    }

    return date.toLocaleString(undefined, {
        month: 'short',
        day: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

/**
 * Workflow stepper from job status + timestamps (backend truth).
 */
export function jobWorkflowSteps(job: AnalystTask['job_order']): WorkflowStep[] {
    const status = job.status ?? 'in_analysis';
    const received = Boolean(job.received_at);
    const released =
        status === 'ready_for_pickup' || Boolean(job.reviewed_at);
    const atHead = status === 'pending_review';
    const inAnalysis = status === 'in_analysis' || (!released && !atHead);

    return [
        {
            key: 'received',
            label: 'Received',
            state: received || inAnalysis || atHead || released ? 'done' : 'upcoming',
            detail: formatLabTimestamp(job.received_at),
        },
        {
            key: 'analysis',
            label: 'Analysis',
            state: released || atHead ? 'done' : inAnalysis ? 'current' : 'upcoming',
            detail: inAnalysis && !atHead && !released ? 'In progress' : null,
        },
        {
            key: 'head',
            label: 'Head review',
            state: released ? 'done' : atHead ? 'current' : 'upcoming',
            detail: atHead
                ? 'Waiting'
                : released
                  ? formatLabTimestamp(job.reviewed_at)
                  : 'Waiting',
        },
        {
            key: 'released',
            label: 'Released',
            state: released ? 'done' : 'upcoming',
            detail: released
                ? formatLabTimestamp(job.reviewed_at)
                : 'Waiting',
        },
    ];
}
