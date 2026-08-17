import { Link, router, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { Breadcrumbs } from '@/components/breadcrumbs';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import type { BreadcrumbItem as BreadcrumbItemType } from '@/types';

type NotificationItem = {
    id: string;
    data: { message?: string; reference_no?: string };
    read_at: string | null;
    created_at: string | null;
};

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const { notifications = [], unreadNotificationsCount = 0 } = usePage()
        .props as {
        notifications?: NotificationItem[];
        unreadNotificationsCount?: number;
    };

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="relative">
                        <Bell className="size-4" />
                        {unreadNotificationsCount > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-[#1A3694] text-[10px] text-white">
                                {unreadNotificationsCount}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-80">
                    <DropdownMenuLabel className="flex items-center justify-between">
                        Notifications
                        {unreadNotificationsCount > 0 && (
                            <button
                                type="button"
                                className="text-xs font-normal text-[#1A3694]"
                                onClick={() =>
                                    router.post('/notifications/read-all')
                                }
                            >
                                Mark all read
                            </button>
                        )}
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {notifications.length === 0 && (
                        <div className="px-2 py-4 text-sm text-muted-foreground">
                            No notifications yet.
                        </div>
                    )}
                    {notifications.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            className="flex flex-col items-start gap-1"
                            onClick={() =>
                                router.post(
                                    `/notifications/${notification.id}/read`,
                                )
                            }
                        >
                            <span
                                className={
                                    notification.read_at
                                        ? 'text-muted-foreground'
                                        : 'font-medium'
                                }
                            >
                                {notification.data.message ??
                                    'Lab notification'}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {notification.created_at}
                            </span>
                        </DropdownMenuItem>
                    ))}
                    <DropdownMenuSeparator />
                    <DropdownMenuItem asChild>
                        <Link href="/intake">Open intake kiosk</Link>
                    </DropdownMenuItem>
                </DropdownMenuContent>
            </DropdownMenu>
        </header>
    );
}
