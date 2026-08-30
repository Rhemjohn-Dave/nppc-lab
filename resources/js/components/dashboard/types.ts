export type DashboardKpi = {
    key: string;
    label: string;
    value: number;
    href: string | null;
    tone: 'default' | 'success' | 'warning' | 'info';
    hint?: string | null;
};

export type DashboardAttentionItem = {
    text: string;
    href: string | null;
};

export type DashboardNeedsAttention = {
    title: string;
    summary: string;
    items: DashboardAttentionItem[];
    action: { label: string; href: string } | null;
};

export type DashboardActivityItem = {
    title: string;
    meta: string;
    time: string | null;
    href: string | null;
};

export type DashboardActivity = {
    title: string;
    empty: string;
    items: DashboardActivityItem[];
    action: { label: string; href: string } | null;
};

export type DashboardQueue = {
    title: string;
    empty: string;
    columns: string[];
    rows: Array<Record<string, unknown>>;
    preview_limit?: number;
};

export type DashboardLink = {
    label: string;
    href: string;
};

export type DashboardLinks = {
    primary: DashboardLink | null;
    secondary: DashboardLink[];
};

export type DashboardHeaderData = {
    title: string;
    subtitle: string;
    greeting_name?: string | null;
};

export type AnalystDashboardTask = {
    id: number;
    name: string;
    status: string;
    status_label: string;
    assigned_to?: number | null;
    assignee_name?: string | null;
    is_mine?: boolean;
    category_label?: string | null;
};

export type AnalystDashboardJobGroup = {
    job: {
        id: number;
        reference_no: string;
        customer_name: string;
        company_name?: string | null;
        classification?: string | null;
        sample_storage_temp?: string | null;
        review_notes?: string | null;
        samples?: Array<{
            sample_code?: string | null;
            description?: string | null;
            matrix?: string | null;
        }>;
    };
    tasks: AnalystDashboardTask[];
};

export type DashboardProps = {
    role: 'admin' | 'receiving' | 'analyst' | 'head' | 'generic';
    header: DashboardHeaderData;
    kpis: DashboardKpi[];
    needsAttention: DashboardNeedsAttention;
    queue: DashboardQueue;
    activity: DashboardActivity;
    links: DashboardLinks;
    extras?: {
        workflow_strip?: Array<{ status: string; count: number }>;
        job_groups?: AnalystDashboardJobGroup[];
    };
};

export type DashboardRole =
    | 'admin'
    | 'receiving'
    | 'analyst'
    | 'head'
    | 'generic';
