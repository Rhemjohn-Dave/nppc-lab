import DashboardPreviewList from '@/components/dashboard/dashboard-preview-list';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity from '@/components/dashboard/recent-activity';
import type { DashboardProps } from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'links'
>;

export default function HeadDashboard({
    kpis,
    needsAttention,
    queue,
    activity,
    links,
}: Props) {
    const viewAllHref = links.primary?.href ?? '/head';
    const viewAllLabel = links.primary?.label ?? 'View all in Signing Queue';

    return (
        <div className="flex flex-col gap-3 md:gap-4">
            <NeedsAttentionPanel data={needsAttention} compact />
            <KpiGrid kpis={kpis} compact />
            <DashboardPreviewList
                data={queue}
                viewAllHref={viewAllHref}
                viewAllLabel={viewAllLabel}
                compact
            />
            <RecentActivity data={activity} compact />
        </div>
    );
}
