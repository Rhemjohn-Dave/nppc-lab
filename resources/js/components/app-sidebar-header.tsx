import { Link, router, usePage } from '@inertiajs/react';
import {
    echoIsConfigured,
    useConnectionStatus,
    useEchoNotification,
} from '@laravel/echo-react';
import { Bell } from 'lucide-react';
import { useCallback, useEffect, useState } from 'react';
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

type NotificationData = {
    message?: string;
    reference_no?: string;
    type?: string;
    job_order_id?: number;
    analysis_id?: number;
    href?: string;
};

type NotificationItem = {
    id: string;
    data: NotificationData;
    read_at: string | null;
    created_at: string | null;
};

type EchoNotificationPayload = NotificationData & {
    id?: string;
    type?: string;
};

function notificationHref(data: NotificationData): string | null {
    if (typeof data.href === 'string' && data.href.startsWith('/')) {
        return data.href;
    }

    if (!data.job_order_id) {
        return null;
    }

    switch (data.type) {
        case 'job_order_submitted':
            return `/receiving/${data.job_order_id}`;
        case 'task_assigned':
            return '/analyst';
        case 'job_order_ready_to_sign':
            return `/head/${data.job_order_id}`;
        default:
            return null;
    }
}

function RealtimeNotificationListener({
    userId,
    onNotification,
}: {
    userId: number;
    onNotification: (payload: EchoNotificationPayload) => void;
}) {
    const status = useConnectionStatus();

    useEchoNotification<EchoNotificationPayload>(
        `App.Models.User.${userId}`,
        (payload) => {
            onNotification(payload);
        },
        undefined,
        [userId, onNotification],
    );

    useEffect(() => {
        if (import.meta.env.DEV && status) {
            console.info('[nppc] reverb connection:', status);
        }
    }, [status]);

    return null;
}

export function AppSidebarHeader({
    breadcrumbs = [],
}: {
    breadcrumbs?: BreadcrumbItemType[];
}) {
    const {
        auth,
        notifications: initialNotifications = [],
        unreadNotificationsCount: initialUnread = 0,
    } = usePage().props as {
        auth?: { user?: { id?: number } | null };
        notifications?: NotificationItem[];
        unreadNotificationsCount?: number;
    };

    const userId = auth?.user?.id;
    const [items, setItems] = useState<NotificationItem[]>(initialNotifications);
    const [unreadCount, setUnreadCount] = useState(initialUnread);

    useEffect(() => {
        setItems(initialNotifications);
        setUnreadCount(initialUnread);
    }, [initialNotifications, initialUnread]);

    const pushLiveNotification = useCallback((payload: EchoNotificationPayload) => {
        const id =
            typeof payload.id === 'string' && payload.id !== ''
                ? payload.id
                : `live-${Date.now()}`;

        const data: NotificationData = {
            message: payload.message,
            reference_no: payload.reference_no,
            type:
                typeof payload.type === 'string' &&
                !payload.type.includes('\\')
                    ? payload.type
                    : payload.type?.includes('JobOrderSubmitted')
                      ? 'job_order_submitted'
                      : payload.type?.includes('TaskAssigned')
                        ? 'task_assigned'
                        : payload.type?.includes('JobOrderPendingReview')
                          ? 'job_order_ready_to_sign'
                          : payload.type,
            job_order_id: payload.job_order_id,
            analysis_id: payload.analysis_id,
            href: payload.href,
        };

        setItems((current) => {
            if (current.some((item) => item.id === id)) {
                return current;
            }

            return [
                {
                    id,
                    data,
                    read_at: null,
                    created_at: 'Just now',
                },
                ...current,
            ].slice(0, 12);
        });
        setUnreadCount((count) => count + 1);
    }, []);

    function openNotification(notification: NotificationItem) {
        const href = notificationHref(notification.data);

        router.post(
            `/notifications/${notification.id}/read`,
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setItems((current) =>
                        current.map((item) =>
                            item.id === notification.id
                                ? { ...item, read_at: new Date().toISOString() }
                                : item,
                        ),
                    );
                    setUnreadCount((count) => Math.max(0, count - 1));

                    if (href) {
                        router.visit(href);
                    }
                },
            },
        );
    }

    function markAllRead() {
        router.post(
            '/notifications/read-all',
            {},
            {
                preserveScroll: true,
                onSuccess: () => {
                    setItems((current) =>
                        current.map((item) => ({
                            ...item,
                            read_at: item.read_at ?? new Date().toISOString(),
                        })),
                    );
                    setUnreadCount(0);
                },
            },
        );
    }

    return (
        <header className="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/50 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4">
            {userId && echoIsConfigured() ? (
                <RealtimeNotificationListener
                    userId={userId}
                    onNotification={pushLiveNotification}
                />
            ) : null}
            <div className="flex items-center gap-2">
                <SidebarTrigger className="-ml-1" />
                <Breadcrumbs breadcrumbs={breadcrumbs} />
            </div>

            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="ghost" size="icon" className="relative">
                        <Bell className="size-4" />
                        {unreadCount > 0 && (
                            <span className="absolute -top-0.5 -right-0.5 flex size-4 items-center justify-center rounded-full bg-[#1A3694] text-[10px] text-white">
                                {unreadCount}
                            </span>
                        )}
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end" className="w-80">
                    <DropdownMenuLabel className="flex items-center justify-between">
                        Notifications
                        {unreadCount > 0 && (
                            <button
                                type="button"
                                className="text-xs font-normal text-[#1A3694]"
                                onClick={markAllRead}
                            >
                                Mark all read
                            </button>
                        )}
                    </DropdownMenuLabel>
                    <DropdownMenuSeparator />
                    {items.length === 0 && (
                        <div className="px-2 py-4 text-sm text-muted-foreground">
                            No notifications yet.
                        </div>
                    )}
                    {items.map((notification) => (
                        <DropdownMenuItem
                            key={notification.id}
                            className="flex cursor-pointer flex-col items-start gap-1"
                            onClick={() => openNotification(notification)}
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
