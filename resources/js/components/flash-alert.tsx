import { AlertCircle, CheckCircle2, Info, TriangleAlert } from 'lucide-react';
import type { ReactNode } from 'react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { cn } from '@/lib/utils';

type FlashAlertVariant = 'success' | 'error' | 'warning' | 'info';

type FlashAlertProps = {
    variant?: FlashAlertVariant;
    title?: string;
    children: ReactNode;
    className?: string;
};

const icons: Record<FlashAlertVariant, typeof CheckCircle2> = {
    success: CheckCircle2,
    error: AlertCircle,
    warning: TriangleAlert,
    info: Info,
};

/**
 * Persistent inline banner when a toast alone is not enough (long messages).
 */
export default function FlashAlert({
    variant = 'info',
    title,
    children,
    className,
}: FlashAlertProps) {
    const Icon = icons[variant];

    return (
        <Alert
            variant={variant === 'error' ? 'destructive' : 'default'}
            className={cn(
                variant === 'success' && 'border-emerald-200 bg-emerald-50 text-emerald-900',
                variant === 'warning' && 'border-amber-200 bg-amber-50 text-amber-950',
                variant === 'info' && 'border-sky-200 bg-sky-50 text-sky-950',
                className,
            )}
        >
            <Icon />
            {title ? <AlertTitle>{title}</AlertTitle> : null}
            <AlertDescription>{children}</AlertDescription>
        </Alert>
    );
}
