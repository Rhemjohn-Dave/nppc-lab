import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import type { AppLayoutProps } from '@/types';

export default function AppSidebarLayout({
    children,
    breadcrumbs = [],
}: AppLayoutProps) {
    return (
        <AppShell variant="sidebar">
            <AppSidebar />
            <AppContent
                variant="sidebar"
                className="flex h-svh max-h-svh flex-col overflow-hidden"
            >
                <AppSidebarHeader breadcrumbs={breadcrumbs} />
                <div className="nppc-scroll min-h-0 flex-1 overflow-x-hidden overflow-y-auto">
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
