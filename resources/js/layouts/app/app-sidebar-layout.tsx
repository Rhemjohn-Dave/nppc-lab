import { AppContent } from '@/components/app-content';
import { AppShell } from '@/components/app-shell';
import { AppSidebar } from '@/components/app-sidebar';
import { AppSidebarHeader } from '@/components/app-sidebar-header';
import { cn } from '@/lib/utils';
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
                {/*
                  When Analyst Encoding Workbench mounts ([data-analyst-workbench]),
                  lock this pane so only the workbench's inner columns scroll —
                  avoids nested page + form scrollbars and footer clipping.
                */}
                <div
                    className={cn(
                        'nppc-scroll min-h-0 flex-1 overflow-x-hidden',
                        'overflow-y-auto',
                        'has-[[data-analyst-workbench]]:flex has-[[data-analyst-workbench]]:flex-col',
                        'has-[[data-analyst-workbench]]:overflow-hidden',
                    )}
                >
                    {children}
                </div>
            </AppContent>
        </AppShell>
    );
}
