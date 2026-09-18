import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description?: string;
    size?: 'md' | 'lg';
    showDocumentPreview?: boolean;
    className?: string;
};

export default function LoadingState({
    title,
    description,
    size = 'md',
    showDocumentPreview = false,
    className,
}: Props) {
    const spinnerSize = size === 'lg' ? 'size-8' : 'size-6';

    return (
        <div
            className={cn(
                'flex h-full min-h-[inherit] flex-col items-center justify-center gap-4 px-6 py-10',
                className,
            )}
        >
            {showDocumentPreview && (
                <div className="w-full max-w-md space-y-2 rounded-lg border bg-white p-4 shadow-sm">
                    <Skeleton className="mx-auto h-3 w-2/5" />
                    <Skeleton className="h-2 w-full" />
                    <Skeleton className="h-2 w-full" />
                    <Skeleton className="h-2 w-4/5" />
                    <Skeleton className="mt-3 h-16 w-full" />
                    <Skeleton className="h-2 w-3/5" />
                </div>
            )}

            <Spinner className={spinnerSize} />
            <div className="space-y-1 text-center">
                <p className="text-sm font-medium text-[#1A3694]">{title}</p>
                {description && (
                    <p className="text-xs text-muted-foreground">{description}</p>
                )}
            </div>
        </div>
    );
}
