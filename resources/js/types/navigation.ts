import type { InertiaLinkProps } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    /** Destination link; parents with children may use a category path (e.g. `/head`). */
    href: NonNullable<InertiaLinkProps['href']>;
    /**
     * Optional path used only for active highlighting (e.g. `/receiving/bulk-orders`
     * so create + edit drafts stay active under Bulk order).
     */
    activeMatch?: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
    children?: NavItem[];
};
