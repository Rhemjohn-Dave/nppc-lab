import AnalystJobPreviewCards from '@/components/dashboard/analyst-job-preview-cards';
import { KpiGrid } from '@/components/dashboard/kpi-card';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import RecentActivity from '@/components/dashboard/recent-activity';
import type { DashboardProps } from '@/components/dashboard/types';

type Props = Pick<
    DashboardProps,
    'kpis' | 'needsAttention' | 'queue' | 'activity' | 'links' | 'extras'
>;

export default function AnalystDashboard({
    kpis,
    needsAttention,
    queue,
    activity,
    links,
    extras,
}: Props) {
    const groups = extras?.job_groups ?? [];
    const viewAllHref = links.primary?.href ?? '/analyst';
    const viewAllLabel = links.primary?.label ?? 'Open Analyst Workspace';

    return (
        <div className="flex flex-col gap-3 md:gap-4">
            <NeedsAttentionPanel data={needsAttention} compact />
            <KpiGrid kpis={kpis} compact />
            <section className="space-y-2">
                <h2 className="text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
                    {queue.title}
                </h2>
                <AnalystJobPreviewCards
                    groups={groups}
                    empty={queue.empty}
                    viewAllHref={viewAllHref}
                    viewAllLabel={viewAllLabel}
                    compact
                />
            </section>
            <RecentActivity data={activity} compact />
        </div>
    );
}
