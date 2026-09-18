import { Loader2Icon } from 'lucide-react';

import { cn } from '@/lib/utils';

type SpinnerProps = React.ComponentProps<'div'> & {
    variant?: 'brand' | 'inline';
};

function Spinner({ className, variant = 'brand', ...props }: SpinnerProps) {
    if (variant === 'inline') {
        return (
            <Loader2Icon
                role="status"
                aria-label="Loading"
                className={cn('size-4 animate-spin', className)}
                {...(props as React.ComponentProps<'svg'>)}
            />
        );
    }

    return (
        <div
            role="status"
            aria-label="Loading"
            className={cn('nppc-orbit-spinner size-4 shrink-0', className)}
            {...props}
        />
    );
}

export { Spinner };
