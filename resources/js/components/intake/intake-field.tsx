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
            <Label
                htmlFor={htmlFor}
                className="text-[10px] font-semibold tracking-wider text-slate-500 uppercase"
            >
                {label}
                {required ? (
                    <span className="text-nppc-700"> *</span>
                ) : null}
                {hint ? (
                    <span className="font-normal normal-case tracking-normal text-slate-500">
                        {' '}
                        {hint}
                    </span>
                ) : null}
            </Label>
            <div className="mt-1.5">{children}</div>
            {error ? (
                <p
                    className="mt-1 border-l-2 border-rose-300 pl-2 text-xs font-medium text-rose-700"
                    role="alert"
                >
                    {error}
                </p>
            ) : null}
        </div>
    );
}
