import DashboardPreviewList from '@/components/dashboard/dashboard-preview-list';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity from '@/components/dashboard/recent-activity';
import type { DashboardProps } from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'links'
>;

export default function AccountingDashboard({
    kpis,
    needsAttention,
    queue,
    activity,
    links,
}: Props) {
    const viewAllHref = links.primary?.href ?? '/accounting';
    const viewAllLabel = links.primary?.label ?? 'Open Sales Monitor';

    return (
        <div className="flex flex-col gap-5">
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
