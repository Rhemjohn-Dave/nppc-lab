import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import {
    Activity,
    Download,
    FilePlus2,
    FileText,
    History,
    Printer,
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { DashboardLink } from './types';

function timeGreeting(): string {
    const hour = new Date().getHours();
    if (hour < 12) {
        return 'Good morning';
    }
    if (hour < 17) {
        return 'Good afternoon';
    }
    return 'Good evening';
}

function formatToday(): string {
    return new Date().toLocaleDateString(undefined, {
        weekday: 'long',
        month: 'long',
        day: 'numeric',
    });
}

function secondaryLinkIcon(label: string): LucideIcon {
    const lower = label.toLowerCase();
    if (lower.includes('audit')) {
        return Activity;
    }
    if (lower.includes('print')) {
        return Printer;
    }
    if (lower.includes('export') || lower.includes('download')) {
        return Download;
    }
    if (lower.includes('history')) {
        return History;
    }
    if (lower.includes('form') || lower.includes('revision')) {
        return FilePlus2;
    }
    return FileText;
}

type BaseProps = {
    title: string;
    subtitle: string;
};

type DefaultProps = BaseProps & {
    variant?: 'default';
    actions?: React.ReactNode;
};

type HeroProps = BaseProps & {
    variant: 'hero';
    density?: 'default' | 'compact';
    greetingName?: string | null;
    icon?: LucideIcon;
    primaryAction?: DashboardLink | null;
    secondaryLinks?: DashboardLink[];
};

type Props = DefaultProps | HeroProps;

export default function DashboardHeader(props: Props) {
    if (props.variant === 'hero') {
        const {
            title,
            subtitle,
            density = 'default',
            greetingName,
            icon: Icon,
            primaryAction,
            secondaryLinks = [],
        } = props;
        const compact = density === 'compact';
        const greeting = greetingName
            ? `${timeGreeting()}, ${greetingName.split(' ')[0]}`
            : timeGreeting();

        return (
            <header
                className={cn(
                    'relative overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm',
                    compact ? 'p-5 md:p-6' : 'p-5 md:p-6',
                )}
            >
                <div className="relative z-10 flex flex-col justify-between gap-6 md:flex-row md:items-center">
                    <div className="flex min-w-0 items-start gap-4">
                        {Icon && (
                            <div
                                className={cn(
                                    'flex shrink-0 items-center justify-center border border-nppc-100 bg-nppc-50 text-nppc-900 shadow-inner',
                                    compact
                                        ? 'size-12 rounded-xl'
                                        : 'size-12 rounded-xl',
                                )}
                                aria-hidden="true"
                            >
                                <Icon className="size-6" />
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="text-xs font-semibold tracking-wider text-nppc-900 uppercase">
                                {greeting}
                            </p>
                            <h1
                                className={cn(
                                    'mt-0.5 font-heading font-bold tracking-tight text-slate-900',
                                    compact
                                        ? 'text-xl sm:text-2xl'
                                        : 'text-2xl md:text-3xl',
                                )}
                            >
                                {title}
                            </h1>
                            <p className="mt-1 text-sm text-slate-500">
                                {subtitle}
                            </p>
                            {!compact && (
                                <p className="mt-2 text-xs text-slate-500">
                                    {formatToday()}
                                </p>
                            )}
                            {secondaryLinks.length > 0 && (
                                <div
                                    className={cn(
                                        'flex flex-wrap items-center gap-3 text-xs text-slate-600',
                                        compact ? 'mt-3' : 'mt-3',
                                    )}
                                >
                                    {secondaryLinks.map((link, index) => {
                                        const LinkIcon = secondaryLinkIcon(
                                            link.label,
                                        );
                                        return (
                                            <span
                                                key={link.href}
                                                className="inline-flex items-center gap-3"
                                            >
                                                {index > 0 ? (
                                                    <span
                                                        className="text-slate-300"
                                                        aria-hidden="true"
                                                    >
                                                        •
                                                    </span>
                                                ) : null}
                                                <Link
                                                    href={link.href}
                                                    className="inline-flex items-center gap-1.5 font-medium transition-colors hover:text-nppc-900"
                                                >
                                                    <LinkIcon
                                                        className="size-3.5 text-slate-400"
                                                        aria-hidden="true"
                                                    />
                                                    {link.label}
                                                </Link>
                                            </span>
                                        );
                                    })}
                                </div>
                            )}
                        </div>
                    </div>
                    {primaryAction && (
                        <Button
                            asChild
                            size={compact ? 'default' : 'lg'}
                            className="w-full shrink-0 gap-2 bg-nppc-900 text-xs font-semibold shadow-sm shadow-nppc-500/20 hover:bg-nppc-700 sm:w-auto"
                        >
                            <Link href={primaryAction.href}>
                                <FilePlus2
                                    className="size-4"
                                    aria-hidden="true"
                                />
                                {primaryAction.label}
                            </Link>
                        </Button>
                    )}
                </div>
            </header>
        );
    }

    const { title, subtitle, actions } = props;

    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="font-heading text-2xl font-bold tracking-tight text-slate-900">
                    {title}
                </h1>
                <p className="text-sm text-slate-500">{subtitle}</p>
            </div>
            {actions}
        </div>
    );
}
