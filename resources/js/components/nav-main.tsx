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

const navButtonClass =
    'h-9 gap-2.5 rounded-md px-2.5 text-xs font-medium text-slate-600 transition-colors hover:bg-slate-100 hover:text-slate-900 data-[active=true]:bg-nppc-50/80 data-[active=true]:font-semibold data-[active=true]:text-nppc-900 data-[active=true]:shadow-[inset_0_0_0_1px_rgba(26,54,148,0.2)] [&>svg]:size-4 [&>svg]:text-slate-400 data-[active=true]:[&>svg]:text-nppc-700';

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
                className={navButtonClass}
            >
                <Link href={item.href} prefetch>
                    {item.icon && <item.icon />}
                    <span className="flex-1 truncate">{item.title}</span>
                    {isActive ? (
                        <span
                            className="size-1.5 shrink-0 rounded-full bg-nppc-900 group-data-[collapsible=icon]:hidden"
                            aria-hidden="true"
                        />
                    ) : null}
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
    const exactParent = pathMatches(currentPath, parentPath, false);
    const underParent = exactParent || childActive;
    const parentActive = exactParent && !childActive;
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
                        isActive={parentActive}
                        className={cn(
                            navButtonClass,
                            (open || childActive) &&
                                !parentActive &&
                                'font-semibold text-nppc-900 [&>svg]:text-nppc-700',
                        )}
                    >
                        {item.icon && <item.icon />}
                        <span className="flex-1 truncate">{item.title}</span>
                        <ChevronRight
                            className={cn(
                                'ml-auto size-3.5 shrink-0 text-slate-400 transition-transform',
                                open && 'rotate-90',
                            )}
                        />
                    </SidebarMenuButton>
                </CollapsibleTrigger>
                <CollapsibleContent>
                    <SidebarMenuSub className="mx-0 ml-3.5 border-l border-slate-200 px-0 pl-2.5">
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
                                        size="sm"
                                        className="h-8 gap-2 rounded-md px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-100 hover:text-slate-900 data-[active=true]:bg-nppc-50 data-[active=true]:font-semibold data-[active=true]:text-nppc-900 data-[active=true]:shadow-[inset_0_0_0_1px_rgba(26,54,148,0.2)] [&>svg]:size-3.5 [&>svg]:text-slate-400 data-[active=true]:[&>svg]:text-nppc-700"
                                    >
                                        <Link href={child.href} prefetch>
                                            {child.icon && <child.icon />}
                                            <span className="truncate">
                                                {child.title}
                                            </span>
                                            {active ? (
                                                <span
                                                    className="ml-auto size-1.5 shrink-0 rounded-full bg-nppc-900 group-data-[collapsible=icon]:hidden"
                                                    aria-hidden="true"
                                                />
                                            ) : null}
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
        <SidebarGroup className="px-3 py-0">
            <SidebarGroupLabel className="mb-1.5 px-2 text-[10px] font-semibold tracking-wider text-slate-400 uppercase">
                {label}
            </SidebarGroupLabel>
            <SidebarMenu className="gap-0.5">
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
