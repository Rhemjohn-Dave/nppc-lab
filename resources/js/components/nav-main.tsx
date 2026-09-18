import { Link } from '@inertiajs/react';
import { ChevronRight } from 'lucide-react';
import { useEffect, useState } from 'react';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import {
    SidebarGroup,
    SidebarGroupLabel,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
    SidebarMenuSub,
    SidebarMenuSubButton,
    SidebarMenuSubItem,
} from '@/components/ui/sidebar';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import type { NavItem } from '@/types';

function itemPath(item: NavItem): string {
    return toUrl(item.href);
}

function pathMatches(
    currentPath: string,
    target: string,
    startsWith = false,
): boolean {
    if (startsWith) {
        return (
            currentPath === target ||
            currentPath.startsWith(target.endsWith('/') ? target : `${target}/`)
        );
    }

    return currentPath === target;
}

function NavLeaf({
    item,
    isActive,
}: {
    item: NavItem;
    isActive: boolean;
}) {
    return (
        <SidebarMenuItem>
            <SidebarMenuButton
                asChild
                isActive={isActive}
                tooltip={{ children: item.title }}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                </Link>
            </SidebarMenuButton>
        </SidebarMenuItem>
    );
}

function NavBranch({
    item,
    currentPath,
}: {
    item: NavItem;
    currentPath: string;
}) {
    const children = item.children ?? [];
    const parentPath = itemPath(item);
    const childActive = children.some((child) =>
        pathMatches(currentPath, itemPath(child), true),
    );
    const underParent =
        pathMatches(currentPath, parentPath, true) || childActive;
    const [open, setOpen] = useState(underParent);

    useEffect(() => {
        if (underParent) {
            setOpen(true);
        }
    }, [underParent]);

    return (
        <Collapsible
            open={open}
            onOpenChange={setOpen}
            className="group/collapsible"
            asChild
        >
            <SidebarMenuItem>
                <CollapsibleTrigger asChild>
                    <SidebarMenuButton
                        tooltip={{ children: item.title }}
                        isActive={underParent}
                    >
                        {item.icon && <item.icon />}
                        <span>{item.title}</span>
                        <ChevronRight
                            className={cn(
                                'ml-auto size-4 transition-transform',
                                open && 'rotate-90',
                            )}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub>
                        {children.map((child) => {
                            const active = pathMatches(
                                currentPath,
                                itemPath(child),
                                true,
                            );

                            return (
                                <SidebarMenuSubItem key={child.title}>
                                    <SidebarMenuSubButton
                                        asChild
                                        isActive={active}
                                    >
                                        <Link href={child.href} prefetch>
                                            {child.icon && <child.icon />}
                                            <span>{child.title}</span>
                                        </Link>
                                    </SidebarMenuSubButton>
                                </SidebarMenuSubItem>
                            );
                        })}
                    </SidebarMenuSub>
                </CollapsibleContent>
            </SidebarMenuItem>
        </Collapsible>
    );
}

export function NavMain({
    items = [],
    label = 'Platform',
}: {
    items: NavItem[];
    label?: string;
}) {
    const { currentUrl, isCurrentUrl } = useCurrentUrl();

    if (items.length === 0) {
        return null;
    }

    return (
        <SidebarGroup className="px-2 py-0">
            <SidebarGroupLabel>{label}</SidebarGroupLabel>
            <SidebarMenu>
                {items.map((item) =>
                    item.children && item.children.length > 0 ? (
                        <NavBranch
                            key={item.title}
                            item={item}
                            currentPath={currentUrl}
                        />
                    ) : (
                        <NavLeaf
                            key={item.title}
                            item={item}
                            isActive={isCurrentUrl(item.href)}
                        />
                    ),
                )}
            </SidebarMenu>
        </SidebarGroup>
    );
}
