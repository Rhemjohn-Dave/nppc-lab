import { Head } from '@inertiajs/react';
import DashboardShell from '@/components/dashboard/dashboard-shell';
import DashboardHeader from '@/components/dashboard/dashboard-header';
import NeedsAttentionPanel from '@/components/dashboard/needs-attention-panel';
import type { DashboardProps } from '@/components/dashboard/types';
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
                <div className="bg-[#f4f7fb] p-4 md:p-6">
                    <div className="mx-auto flex max-w-6xl flex-col gap-6">
                        <DashboardHeader
                            variant="hero"
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
                    layout={role === 'admin' ? 'wide' : 'default'}
                >
                    {role === 'admin' && <AdminDashboard {...props} />}
                    {role === 'receiving' && <ReceivingDashboard {...props} />}
                    {role === 'analyst' && <AnalystDashboard {...props} />}
                    {role === 'head' && <HeadDashboard {...props} />}
                </DashboardShell>
            )}
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [{ title: 'Dashboard', href: '/dashboard' }],
};
