import { Link } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
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
                    'rounded-xl border border-slate-200/80 bg-white shadow-sm',
                    compact ? 'p-4' : 'p-5 md:p-6',
                )}
            >
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex min-w-0 flex-1 items-start gap-3">
                        {Icon && (
                            <div
                                className={cn(
                                    'flex shrink-0 items-center justify-center rounded-lg bg-[#eef3fb] text-[#1A3694]',
                                    compact ? 'size-10' : 'size-12 rounded-xl',
                                )}
                                aria-hidden="true"
                            >
                                <Icon className={compact ? 'size-5' : 'size-6'} />
                            </div>
                        )}
                        <div className="min-w-0">
                            <p className="text-sm text-muted-foreground">
                                {greeting}
                            </p>
                            <h1
                                className={cn(
                                    'font-heading font-semibold text-[#1A3694]',
                                    compact ? 'text-2xl' : 'text-2xl md:text-3xl',
                                )}
                            >
                                {title}
                            </h1>
                            <p className="mt-0.5 text-sm text-muted-foreground">
                                {subtitle}
                            </p>
                            {!compact && (
                                <p className="mt-2 text-xs text-slate-500">
                                    {formatToday()}
                                </p>
                            )}
                            {secondaryLinks.length > 0 && (
                                <p
                                    className={cn(
                                        'text-sm text-[#365BB0]',
                                        compact ? 'mt-1.5' : 'mt-3',
                                    )}
                                >
                                    {secondaryLinks.map((link, index) => (
                                        <span key={link.href}>
                                            {index > 0 && (
                                                <span className="text-slate-400">
                                                    {' · '}
                                                </span>
                                            )}
                                            <Link
                                                href={link.href}
                                                className="hover:underline"
                                            >
                                                {link.label}
                                            </Link>
                                        </span>
                                    ))}
                                </p>
                            )}
                        </div>
                    </div>
                    {primaryAction && (
                        <Button
                            asChild
                            size={compact ? 'default' : 'lg'}
                            className="shrink-0 bg-[#1A3694] hover:bg-[#365BB0]"
                        >
                            <Link href={primaryAction.href}>
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
                <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                    {title}
                </h1>
                <p className="text-sm text-muted-foreground">{subtitle}</p>
            </div>
            {actions}
        </div>
    );
}
