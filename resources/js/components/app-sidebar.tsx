import { Link, usePage } from '@inertiajs/react';
import {
    Beaker,
    ClipboardCheck,
    Hash,
    History,
    LayoutGrid,
    PackageOpen,
    Settings2,
    Shield,
    Users,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import type { NavItem } from '@/types';

export function AppSidebar() {
    const { auth, canAccessHistory } = usePage().props as {
        auth: { user?: { roles?: string[] } | null };
        canAccessHistory?: boolean;
    };
    const roles = auth.user?.roles ?? [];
    const isAdmin = roles.includes('admin');
    const can = (role: string) => isAdmin || roles.includes(role);

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
            icon: LayoutGrid,
        },
        ...(can('receiving')
            ? [
                  {
                      title: 'Receiving',
                      href: '/receiving',
                      icon: PackageOpen,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('analyst')
            ? [
                  {
                      title: 'Analyst',
                      href: '/analyst',
                      icon: Beaker,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('head_analysis')
            ? [
                  {
                      title: 'Head Analysis',
                      href: '/head',
                      icon: ClipboardCheck,
                  } satisfies NavItem,
              ]
            : []),
        ...(canAccessHistory
            ? [
                  {
                      title: 'History',
                      href: '/history',
                      icon: History,
                  } satisfies NavItem,
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: 'Users',
                      href: '/admin/users',
                      icon: Users,
                  } satisfies NavItem,
                  {
                      title: 'Procedures',
                      href: '/admin/prices',
                      icon: Settings2,
                  } satisfies NavItem,
                  {
                      title: 'Assignments',
                      href: '/admin/assignments',
                      icon: Beaker,
                  } satisfies NavItem,
                  {
                      title: 'History access',
                      href: '/admin/history-access',
                      icon: Shield,
                  } satisfies NavItem,
                  {
                      title: 'Control number',
                      href: '/admin/control-number',
                      icon: Hash,
                  } satisfies NavItem,
              ]
            : []),
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
