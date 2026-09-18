import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    ClipboardList,
    FileCheck,
    FlaskConical,
    Package,
    Shield,
} from 'lucide-react';
import { cn } from '@/lib/utils';
import {
    limsPageBackground,
    limsPageShellFluid,
    limsPageShellWide,
} from '@/lib/lims-page-shell';
import type { ReactNode } from 'react';
import DashboardHeader from '@/components/dashboard/dashboard-header';
import type {
    DashboardHeaderData,
    DashboardLinks,
    DashboardRole,
} from '@/components/dashboard/types';

const roleIcons: Record<DashboardRole, LucideIcon> = {
    admin: Shield,
    receiving: Package,
    analyst: FlaskConical,
    head: FileCheck,
    generic: ClipboardList,
};

type Props = {
    role: DashboardRole;
    header: DashboardHeaderData;
    links: DashboardLinks;
    children: ReactNode;
    layout?: 'default' | 'wide' | 'fluid';
    density?: 'default' | 'compact';
};

export default function DashboardShell({
    role,
    header,
    links,
    children,
    layout = 'default',
    density = 'compact',
}: Props) {
    const Icon = roleIcons[role];
    const isFluid = layout === 'fluid';
    const compact = density === 'compact' || isFluid || layout === 'default';

    return (
        <div className={limsPageBackground}>
            <div className={cn(isFluid ? limsPageShellFluid : limsPageShellWide)}>
                <DashboardHeader
                    variant="hero"
                    density={compact ? 'compact' : 'default'}
                    title={header.title}
                    subtitle={header.subtitle}
                    greetingName={header.greeting_name}
                    icon={Icon}
                    primaryAction={links.primary}
                    secondaryLinks={links.secondary}
                />
                {children}
            </div>
        </div>
    );
}

export function DashboardSection({
    title,
    children,
    className,
}: {
    title?: string;
    children: ReactNode;
    className?: string;
}) {
    return (
        <section className={className}>
            {title && (
                <h2 className="mb-2 text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
                    {title}
                </h2>
            )}
            <div className="rounded-xl border border-slate-200/80 bg-white shadow-sm">
                {children}
            </div>
        </section>
    );
}

export function DashboardViewAllLink({
    href,
    label,
}: {
    href: string;
    label: string;
}) {
    return (
        <div className="border-t border-slate-100 px-3 py-2 text-right">
            <Link
                href={href}
                className="text-sm font-medium text-[#1A3694] hover:underline"
            >
                {label} →
            </Link>
        </div>
    );
}
