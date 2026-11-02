import type { LucideIcon } from 'lucide-react';
import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    htmlFor?: string;
    required?: boolean;
    hint?: ReactNode;
    /** Compact helper line under the label. */
    description?: ReactNode;
    error?: ReactNode;
    children: ReactNode;
    className?: string;
    /** Optional leading icon rendered inside the control slot. */
    icon?: LucideIcon;
};

/**
 * Label + control + inline error wrapper.
 * Controls keep their own classes; this only standardizes spacing and messaging.
 */
export default function IntakeField({
    label,
    htmlFor,
    required = false,
    hint,
    description,
    error,
    children,
    className,
    icon: Icon,
}: Props) {
    return (
        <div className={cn('min-w-0', className)}>
            <Label
                htmlFor={htmlFor}
                className="text-[11px] font-semibold text-slate-700"
            >
                {label}
                {required ? <span className="text-rose-600"> *</span> : null}
                {hint ? (
                    <span className="font-normal text-slate-400"> {hint}</span>
                ) : null}
            </Label>
            {description ? (
                <p className="mt-0.5 text-[10px] leading-snug text-slate-500">
                    {description}
                </p>
            ) : null}
            <div
                className={cn(
                    'relative mt-1 [&_input]:rounded-md [&_textarea]:rounded-md',
                    Icon && '[&_input]:pl-9 [&_textarea]:pl-9',
                )}
            >
                {Icon ? (
                    <Icon
                        className="pointer-events-none absolute top-2.5 left-2.5 size-3.5 text-slate-400"
                        aria-hidden
                    />
                ) : null}
                {children}
            </div>
            {error ? (
                <p
                    className="mt-1 text-[11px] font-medium text-rose-700"
                    role="alert"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
