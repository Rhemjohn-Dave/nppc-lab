import DashboardPreviewList from '@/components/dashboard/dashboard-preview-list';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity from '@/components/dashboard/recent-activity';
import type { DashboardProps } from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'links'
>;

export default function ReceivingDashboard({
    kpis,
    needsAttention,
    queue,
    activity,
    links,
}: Props) {
    const viewAllHref = links.primary?.href ?? '/receiving';
    const viewAllLabel =
        links.primary?.label ?? 'View all in Receiving Workspace';

    return (
        <div className="flex flex-col gap-6">
            <NeedsAttentionPanel data={needsAttention} />
            <KpiGrid kpis={kpis} />
            <DashboardPreviewList
                data={queue}
                viewAllHref={viewAllHref}
                viewAllLabel={viewAllLabel}
            />
            <RecentActivity data={activity} compact />
        </div>
    );
}
