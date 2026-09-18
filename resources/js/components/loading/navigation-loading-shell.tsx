import type { ReactNode } from 'react';
import DashboardPageSkeleton from '@/components/loading/dashboard-page-skeleton';
import QueuePageSkeleton from '@/components/loading/queue-page-skeleton';
import { useInertiaNavigation } from '@/hooks/use-inertia-navigation';

type Props = {
    variant: 'queue' | 'dashboard';
    children: ReactNode;
};

export default function NavigationLoadingShell({ variant, children }: Props) {
    const { isInitialLoading } = useInertiaNavigation();

    if (isInitialLoading) {
        return variant === 'dashboard' ? (
            <DashboardPageSkeleton />
        ) : (
            <QueuePageSkeleton />
        );
    }

    return <>{children}</>;
}
