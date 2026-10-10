import { CircleAlert, Info, TriangleAlert } from 'lucide-react';
import type { ComponentPropsWithoutRef, ReactNode } from 'react';
import { cn } from '@/lib/utils';

type IntakeAlertVariant = 'error' | 'warning' | 'info';

type Props = {
    variant?: IntakeAlertVariant;
    title?: string;
    children: ReactNode;
    className?: string;
} & Omit<ComponentPropsWithoutRef<'div'>, 'title' | 'children' | 'className'>;

const shell: Record<IntakeAlertVariant, string> = {
    error: 'border-rose-200 bg-rose-50 text-rose-900',
    warning: 'border-amber-200 bg-amber-50 text-amber-900',
    info: 'border-sky-200 bg-sky-50 text-nppc-900',
};

const iconClass: Record<IntakeAlertVariant, string> = {
    error: 'text-rose-600',
    warning: 'text-amber-600',
    info: 'text-nppc-700',
};

/**
 * Compact intake banner for step-level errors, warnings, and info notes.
 */
export default function IntakeAlert({
    variant = 'error',
    title,
    children,
    className,
    ...props
}: Props) {
    const Icon =
        variant === 'warning'
            ? TriangleAlert
            : variant === 'info'
              ? Info
              : CircleAlert;

    return (
        <div
            role={variant === 'info' ? 'status' : 'alert'}
            className={cn(
                'flex gap-2.5 rounded-lg border px-3 py-2.5 text-sm',
                shell[variant],
                className,
            )}
            {...props}
        >
            <Icon
                className={cn('mt-0.5 size-4 shrink-0', iconClass[variant])}
                aria-hidden
            />
            <div className="min-w-0 space-y-0.5">
                {title ? (
                    <p className="font-semibold tracking-tight">{title}</p>
                ) : null}
                <div className="leading-snug [&_p]:leading-snug">{children}</div>
            </div>
        </div>
    );
}
