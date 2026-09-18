import { Skeleton } from '@/components/ui/skeleton';

type Props = {
    rows?: number;
    showStats?: boolean;
    compact?: boolean;
};

export default function QueuePageSkeleton({
    rows = 8,
    showStats = true,
    compact = false,
}: Props) {
    if (compact) {
        return (
            <div className="space-y-2 rounded-xl border bg-white/90 p-3 shadow-sm backdrop-blur-[1px]">
                {Array.from({ length: Math.min(rows, 5) }).map((_, index) => (
                    <div
                        key={index}
                        className="flex items-center gap-3 border-b border-slate-100 pb-2 last:border-0 last:pb-0"
                    >
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-4 flex-1" />
                        <Skeleton className="h-6 w-20 rounded-full" />
                        <Skeleton className="h-8 w-16 rounded-md" />
                    </div>
                ))}
            </div>
        );
    }

    return (
        <div className="flex flex-col gap-5 p-4" aria-busy="true" aria-label="Loading queue">
            <div className="space-y-2">
                <Skeleton className="h-8 w-56" />
                <Skeleton className="h-4 w-full max-w-xl" />
            </div>

            {showStats && (
                <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                    {Array.from({ length: 4 }).map((_, index) => (
                        <div
                            key={index}
                            className="space-y-2 rounded-xl border bg-white p-4 shadow-sm"
                        >
                            <Skeleton className="h-3 w-24" />
                            <Skeleton className="h-8 w-16" />
                        </div>
                    ))}
                </div>
            )}

            <div className="flex flex-wrap items-center gap-2">
                {Array.from({ length: 4 }).map((_, index) => (
                    <Skeleton key={index} className="h-9 w-28 rounded-full" />
                ))}
                <Skeleton className="h-9 w-full max-w-xs rounded-md" />
            </div>

            <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div className="grid grid-cols-[1fr_1.5fr_1fr_5rem] gap-3 border-b bg-slate-50 px-4 py-3">
                    <Skeleton className="h-3 w-16" />
                    <Skeleton className="h-3 w-20" />
                    <Skeleton className="h-3 w-14" />
                    <Skeleton className="h-3 w-12 justify-self-end" />
                </div>
                {Array.from({ length: rows }).map((_, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-[1fr_1.5fr_1fr_5rem] items-center gap-3 border-b px-4 py-3 last:border-0"
                    >
                        <Skeleton className="h-4 w-24" />
                        <Skeleton className="h-4 w-full max-w-xs" />
                        <Skeleton className="h-6 w-24 rounded-full" />
                        <Skeleton className="h-8 w-16 justify-self-end rounded-md" />
                    </div>
                ))}
            </div>
        </div>
    );
}
