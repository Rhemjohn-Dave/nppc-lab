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
        if (kpi.hint) {
            return kpi;
        }
        switch (kpi.key) {
            case 'active_forms':
                return {
                    ...kpi,
                    hint:
                        kpi.value > 0
                            ? 'Templates in production'
                            : 'None active yet',
                };
            case 'pending_revisions':
                return {
                    ...kpi,
                    hint: kpi.value === 0 ? 'No backlog' : 'QA sign-off queue',
                };
            case 'draft_revisions':
                return {
                    ...kpi,
                    hint: kpi.value === 0 ? 'In draft mode' : 'Needs mapping',
                };
            case 'audit_events_7d':
                return {
                    ...kpi,
                    hint: 'Last 7 days',
                };
            default:
                return kpi;
        }
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
    const viewAllHref =
        links.primary?.href ?? '/admin/controlled-forms';
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
                  label: `Inspect audit events`,
              }
            : activity.action,
    };

    return (
        <div className="flex flex-col gap-3">
            <KpiGrid kpis={annotatedKpis} compact />
            <NeedsAttentionPanel
                data={needsAttention}
                variant="banner"
            />
            <div className="grid gap-3 xl:grid-cols-3">
                <div className="min-w-0 xl:col-span-2">
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
                <div className="min-w-0 space-y-3">
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
