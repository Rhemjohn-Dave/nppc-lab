import { cn } from '@/lib/utils';

type Props = {
    className?: string;
    alt?: string;
};

export default function NppcLogo({
    className,
    alt = 'NPPC Analytical & Diagnostic Laboratory',
}: Props) {
    return (
        <img
            src="/nppc-logo.jpg"
            alt={alt}
            className={cn('object-contain', className)}
        />
    );
}
