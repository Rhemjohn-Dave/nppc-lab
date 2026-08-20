import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import { statusBadgeClass, statusDisplay } from './types';

type Props = {
    status: string;
    label?: string;
    className?: string;
};

export default function AnalystStatusBadge({
    status,
    label,
    className,
}: Props) {
    const display = statusDisplay(status);

    return (
        <Badge
            variant="outline"
            className={cn(
                'gap-1 text-xs font-medium',
                statusBadgeClass(status),
                className,
            )}
        >
            <span aria-hidden="true">{display.icon}</span>
            <span>{label ?? display.label}</span>
        </Badge>
    );
}
