import { Head } from '@inertiajs/react';
import DashboardShell from '@/components/dashboard/dashboard-shell';
import DashboardHeader from '@/components/dashboard/dashboard-header';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import NavigationLoadingShell from '@/components/loading/navigation-loading-shell';
import type { DashboardProps } from '@/components/dashboard/types';
import { limsPageBackground, limsPageShellWide } from '@/lib/lims-page-shell';
import AdminDashboard from '@/pages/dashboard/admin-dashboard';
import AnalystDashboard from '@/pages/dashboard/analyst-dashboard';
import HeadDashboard from '@/pages/dashboard/head-dashboard';
import ReceivingDashboard from '@/pages/dashboard/receiving-dashboard';

export default function Dashboard(props: DashboardProps) {
    const { role, header, links, needsAttention } = props;

    return (
        <>
            <Head title="Dashboard" />
            {role === 'generic' ? (
                <div className={limsPageBackground}>
                    <div className={limsPageShellWide}>
                        <DashboardHeader
                            variant="hero"
                            density="compact"
                            title={header.title}
                            subtitle={header.subtitle}
                            greetingName={header.greeting_name}
                        />
                        <NeedsAttentionPanel data={needsAttention} />
                    </div>
                </div>
            ) : (
                <DashboardShell
                    role={role}
                    header={header}
                    links={links}
                    layout={
                        role === 'receiving' ||
                        role === 'analyst' ||
                        role === 'head'
                            ? 'fluid'
                            : role === 'admin'
                              ? 'wide'
                              : 'default'
                    }
                    density={
                        role === 'receiving' ||
                        role === 'analyst' ||
                        role === 'head' ||
                        role === 'admin'
                            ? 'compact'
                            : 'default'
                    }
                >
                    <NavigationLoadingShell variant="dashboard">
                        {role === 'admin' && <AdminDashboard {...props} />}
                        {role === 'receiving' && <ReceivingDashboard {...props} />}
                        {role === 'analyst' && <AnalystDashboard {...props} />}
                        {role === 'head' && <HeadDashboard {...props} />}
                    </NavigationLoadingShell>
                </DashboardShell>
            )}
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
