import type { ReactNode } from 'react';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

type Props = {
    label: string;
    htmlFor?: string;
    required?: boolean;
    hint?: ReactNode;
    error?: ReactNode;
    children: ReactNode;
    className?: string;
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
    error,
    children,
    className,
}: Props) {
    return (
        <div className={cn('min-w-0', className)}>
            <Label htmlFor={htmlFor}>
                {label}
                {required ? ' *' : null}
                {hint ? (
                    <span className="font-normal text-slate-500"> {hint}</span>
                ) : null}
            </Label>
            <div className="mt-1">{children}</div>
            {error ? (
                <p className="mt-1 text-sm text-red-600" role="alert">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
