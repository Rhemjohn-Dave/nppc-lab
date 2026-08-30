import DashboardQueueTable from '@/components/dashboard/dashboard-queue-table';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity, {
    WorkflowOverviewStrip,
} from '@/components/dashboard/recent-activity';
import type { DashboardProps } from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'extras' | 'links'
>;

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

    return (
        <div className="flex flex-col gap-4">
            <KpiGrid kpis={kpis} compact />
            <NeedsAttentionPanel data={needsAttention} compact />
            <DashboardQueueTable
                data={queue}
                compact
                viewAllHref={viewAllHref}
                viewAllLabel="View all controlled forms"
            />
            <div className="grid gap-4 xl:grid-cols-[1.65fr_1fr]">
                <RecentActivity data={activity} compact />
                <WorkflowOverviewStrip items={strip} compact />
            </div>
        </div>
    );
}
