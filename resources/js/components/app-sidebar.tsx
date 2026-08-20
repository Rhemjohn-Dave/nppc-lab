import { Link, usePage } from '@inertiajs/react';
import {
    Beaker,
    ClipboardCheck,
    FileText,
    Hash,
    History,
    LayoutGrid,
    PackageOpen,
    Printer,
    ScrollText,
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
                      title: 'Signing queue',
                      href: '/head',
                      icon: ClipboardCheck,
                  } satisfies NavItem,
              ]
            : []),
        ...(canAccessHistory
            ? [
                  {
                      title: 'History archive',
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
                      title: 'Packages',
                      href: '/admin/packages',
                      icon: PackageOpen,
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

    const documentNavItems: NavItem[] = isAdmin
        ? [
              {
                  title: 'Controlled Forms',
                  href: '/admin/controlled-forms',
                  icon: FileText,
              },
              {
                  title: 'Print History',
                  href: '/admin/print-history',
                  icon: Printer,
              },
              {
                  title: 'Audit Logs',
                  href: '/admin/document-audit',
                  icon: ScrollText,
              },
          ]
        : [];

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
                <NavMain items={documentNavItems} label="Document Control" />
            </SidebarContent>

            <SidebarFooter>
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
