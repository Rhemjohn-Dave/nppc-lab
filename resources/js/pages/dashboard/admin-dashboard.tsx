import { useMemo } from 'react';
import DashboardQueueTable from '@/components/dashboard/dashboard-queue-table';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity, {
    WorkflowOverviewStrip,
} from '@/components/dashboard/recent-activity';
import type {
    DashboardKpi,
    DashboardProps,
} from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'extras' | 'links'
>;

function withAdminKpiHints(kpis: DashboardKpi[]): DashboardKpi[] {
    return kpis.map((kpi) => {
        const next: DashboardKpi = { ...kpi };

        switch (kpi.key) {
            case 'active_forms':
                next.hint = kpi.hint ?? 'Templates in production';
                next.badge =
                    kpi.badge ??
                    (kpi.value > 0 ? '100% Validated' : 'None active');
                next.footnote = kpi.footnote ?? 'v2.4 engine';
                break;
            case 'pending_revisions':
                next.hint =
                    kpi.hint ??
                    (kpi.value === 0
                        ? 'No pending approval backlog'
                        : 'QA sign-off queue');
                next.badge =
                    kpi.badge ??
                    (kpi.value === 0 ? 'Nominal TAT' : 'Needs attention');
                next.footnote =
                    kpi.footnote ?? (kpi.value === 0 ? 'CLEARED' : 'IN QUEUE');
                next.tone =
                    kpi.value === 0
                        ? 'success'
                        : kpi.tone === 'default'
                          ? 'warning'
                          : kpi.tone;
                break;
            case 'draft_revisions':
                next.hint = kpi.hint ?? 'Ready for authoring';
                next.badge = kpi.badge ?? 'In draft staging';
                next.footnote = kpi.footnote ?? 'SANDBOX';
                next.tone =
                    kpi.tone === 'default' && kpi.value > 0
                        ? 'warning'
                        : kpi.tone;
                break;
            case 'audit_events_7d':
                next.hint = kpi.hint ?? 'Integrity hashes validated';
                next.badge = kpi.badge ?? 'Last 7 days';
                next.footnote = kpi.footnote ?? 'SHA-256';
                next.tone = kpi.tone === 'default' ? 'info' : kpi.tone;
                break;
            default:
                break;
        }

        return next;
    });
}

export default function AdminDashboard({
    kpis,
    needsAttention,
    queue,
    activity,
    extras,
    links,
}: Props) {
    const strip = extras?.workflow_strip ?? [];
    const viewAllHref = links.primary?.href ?? '/admin/controlled-forms';
    const annotatedKpis = useMemo(() => withAdminKpiHints(kpis), [kpis]);
    const activeFormsKpi = kpis.find((kpi) => kpi.key === 'active_forms');
    const registryQueue = {
        ...queue,
        title: 'Controlled Forms Registry',
    };
    const auditActivity = {
        ...activity,
        title: 'Live Audit Trail',
        action: activity.action
            ? {
                  ...activity.action,
                  label: 'View all audit logs',
              }
            : activity.action,
    };

    return (
        <div className="flex flex-col gap-5">
            <KpiGrid kpis={annotatedKpis} compact />
            <NeedsAttentionPanel data={needsAttention} variant="banner" />
            <div className="grid grid-cols-1 items-start gap-6 lg:grid-cols-12">
                <div className="min-w-0 space-y-4 lg:col-span-8">
                    <DashboardQueueTable
                        data={registryQueue}
                        compact
                        registry
                        registeredTotal={
                            activeFormsKpi?.value ?? queue.rows.length
                        }
                        viewAllHref={viewAllHref}
                        viewAllLabel="View all catalog"
                    />
                </div>
                <div className="min-w-0 space-y-4 lg:col-span-4">
                    <RecentActivity
                        data={auditActivity}
                        compact
                        showIsoHint
                    />
                    <WorkflowOverviewStrip
                        items={strip}
                        compact
                        title="Lab job-order pipeline"
                    />
                </div>
            </div>
        </div>
    );
}
