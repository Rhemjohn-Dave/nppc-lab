import { Link, usePage } from '@inertiajs/react';
import {
    Beaker,
    ClipboardCheck,
    FileCheck,
    FileStack,
    FileText,
    Hash,
    History,
    LayoutGrid,
    Mail,
    PackageOpen,
    Printer,
    ScrollText,
    Settings2,
    Shield,
    Users,
    Wallet,
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
        ...(can('accounting')
            ? [
                  {
                      title: 'Accounting',
                      href: '/accounting',
                      icon: Wallet,
                  } satisfies NavItem,
              ]
            : []),
        ...(can('analyst')
            ? [
                  {
                      title: 'Analyst Workspace',
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
                      children: [
                          {
                              title: 'JO Approval',
                              href: '/head/jo',
                              icon: FileText,
                          },
                          {
                              title: 'Results',
                              href: '/head/results',
                              icon: FileCheck,
                          },
                      ],
                  } satisfies NavItem,
              ]
            : []),
        ...(canAccessHistory
            ? [
                  {
                      title: 'History Archive',
                      href: '/history',
                      icon: History,
                  } satisfies NavItem,
              ]
            : []),
        ...(isAdmin
            ? [
                  {
                      title: 'Users & Access',
                      href: '/admin/users',
                      icon: Users,
                  } satisfies NavItem,
                  {
                      title: 'Procedures (SOPs)',
                      href: '/admin/prices',
                      icon: Settings2,
                  } satisfies NavItem,
                  {
                      title: 'Assignments',
                      href: '/admin/assignments',
                      icon: Beaker,
                  } satisfies NavItem,
                  {
                      title: 'History Access',
                      href: '/admin/history-access',
                      icon: Shield,
                  } satisfies NavItem,
                  {
                      title: 'Control Numbers',
                      href: '/admin/control-number',
                      icon: Hash,
                  } satisfies NavItem,
                  {
                      title: 'Email Settings',
                      href: '/admin/email-settings',
                      icon: Mail,
                  } satisfies NavItem,
              ]
            : []),
    ];

    const labCatalogNavItems: NavItem[] = isAdmin
        ? [
              {
                  title: 'Packages',
                  href: '/admin/packages',
                  icon: PackageOpen,
              },
              {
                  title: 'Result Panels',
                  href: '/admin/result-panels',
                  icon: FileStack,
              },
          ]
        : [];

    const documentNavItems: NavItem[] = isAdmin
        ? [
              {
                  title: 'Controlled Forms (COA)',
                  href: '/admin/controlled-forms',
                  icon: FileText,
              },
              {
                  title: 'Print History',
                  href: '/admin/print-history',
                  icon: Printer,
              },
              {
                  title: '21 CFR / ISO Audit Trail',
                  href: '/admin/document-audit',
                  icon: ScrollText,
              },
          ]
        : [];

    return (
        <Sidebar
            collapsible="icon"
            variant="inset"
            className="z-20 border-r border-slate-200 bg-white"
        >
            <SidebarHeader className="border-b border-slate-100 px-2 py-2">
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton
                            size="lg"
                            asChild
                            className="h-auto rounded-lg hover:bg-slate-50 data-[slot=sidebar-menu-button]:p-2 group-data-[collapsible=icon]:size-8! group-data-[collapsible=icon]:p-0!"
                        >
                            <Link href="/dashboard" prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent className="gap-6 px-0 py-3">
                <NavMain items={mainNavItems} />
                <NavMain items={labCatalogNavItems} label="Lab Catalog" />
                <NavMain
                    items={documentNavItems}
                    label="Quality & Compliance"
                />
            </SidebarContent>

            <SidebarFooter className="border-t border-slate-200 bg-slate-50/50 p-3">
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
