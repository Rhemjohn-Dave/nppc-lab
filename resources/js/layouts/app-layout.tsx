import AppLayoutTemplate from '@/layouts/app/app-sidebar-layout';
import { InertiaNavigationProvider } from '@/hooks/use-inertia-navigation';
import type { BreadcrumbItem } from '@/types';

export default function AppLayout({
    breadcrumbs = [],
    children,
}: {
    breadcrumbs?: BreadcrumbItem[];
    children: React.ReactNode;
}) {
    return (
        <InertiaNavigationProvider>
            <AppLayoutTemplate breadcrumbs={breadcrumbs}>
                {children}
            </AppLayoutTemplate>
        </InertiaNavigationProvider>
    );
}
