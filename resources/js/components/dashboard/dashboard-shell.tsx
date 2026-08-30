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
    layout?: 'default' | 'wide';
};

export default function DashboardShell({
    role,
    header,
    links,
    children,
    layout = 'default',
}: Props) {
    const Icon = roleIcons[role];
    const isWide = layout === 'wide';

    return (
        <div className="bg-[#f4f7fb]">
            <div
                className={cn(
                    'mx-auto flex w-full flex-col',
                    isWide
                        ? 'max-w-[1680px] gap-4 px-4 sm:px-5 lg:px-6 xl:px-8'
                        : 'max-w-6xl gap-6 p-4 md:p-6',
                )}
            >
                <DashboardHeader
                    variant="hero"
                    density={isWide ? 'compact' : 'default'}
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
                <h2 className="mb-3 text-xs font-semibold tracking-wide text-[#1A3694] uppercase">
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
        <div className="border-t border-slate-100 px-4 py-3 text-right">
            <Link
                href={href}
                className="text-sm font-medium text-[#1A3694] hover:underline"
            >
                {label} →
            </Link>
        </div>
    );
}
