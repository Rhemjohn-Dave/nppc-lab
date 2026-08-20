export type SampleSummary = {
    sample_code?: string | null;
    description?: string | null;
    matrix?: string | null;
};

export type ReportSummary = {
    kind: 'combined' | 'individual' | 'waiting' | 'unavailable';
    title: string;
    message?: string | null;
    can_preview: boolean;
    can_print?: boolean;
};

export type AnalystTask = {
    id: number;
    name: string;
    analysis_type_id?: number | null;
    category_label?: string | null;
    status: string;
    status_label: string;
    result_value?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
    result_remarks?: string | null;
    result_mode?: 'value' | 'pass_fail';
    updated_at?: string | null;
    assigned_to?: number | null;
    assignee_name?: string | null;
    is_mine?: boolean;
    report: ReportSummary;
    job_order: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name?: string | null;
        classification?: string | null;
        sample_storage_temp?: string | null;
        field_data?: string | null;
        status?: string;
        received_at?: string | null;
        reviewed_at?: string | null;
        review_notes?: string | null;
        samples: SampleSummary[];
    };
};

export type Consolidation = {
    id: number;
    reference_no: string;
    customer_name: string;
    can_submit: boolean;
    can_preview?: boolean;
    preview_url?: string | null;
    preview_message?: string | null;
    missing: string[];
    review_notes?: string | null;
    lines: Array<{
        id: number;
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
    can_print: boolean;
    print_url: string | null;
};

export type JobGroup = {
    job: AnalystTask['job_order'];
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

    return 'border-[#c5d4f0] bg-[#eef3fb] text-[#1A3694]';
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

export function encodedResultLabel(task: {
    result_value?: string | null;
    result_measurement?: string | null;
    result_unit?: string | null;
}): string {
    return [task.result_value, task.result_measurement, task.result_unit]
        .map((part) => (part ?? '').trim())
        .filter(Boolean)
        .join(' ');
}

export function taskActionLabel(task: AnalystTask): string {
    if (task.status === 'returned') {
        return 'Correct result';
    }

    if (task.status === 'completed') {
        return 'View result';
    }

    if (task.status === 'in_progress' || task.result_value) {
        return 'Continue';
    }

    return 'Enter result';
}

export function mineTasks(tasks: AnalystTask[]): AnalystTask[] {
    return tasks.filter((t) => t.is_mine !== false);
}

export function jobProgress(tasks: AnalystTask[]): {
    done: number;
    total: number;
    percent: number;
} {
    const scope = mineTasks(tasks);
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
    const scope = mineTasks(tasks);

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

export function jobMetaLine(job: AnalystTask['job_order']): string {
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

export function actionableTasks(tasks: AnalystTask[]): AnalystTask[] {
    return tasks.filter(
        (t) => t.is_mine !== false && t.status !== 'completed',
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
