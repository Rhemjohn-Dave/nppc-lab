import { Skeleton } from '@/components/ui/skeleton';

export default function DashboardPageSkeleton() {
    return (
        <div className="flex flex-col gap-6" aria-busy="true" aria-label="Loading dashboard">
            <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                {Array.from({ length: 4 }).map((_, index) => (
                    <div
                        key={index}
                        className="space-y-2 rounded-xl border bg-white p-4 shadow-sm"
                    >
                        <Skeleton className="h-3 w-28" />
                        <Skeleton className="h-9 w-14" />
                        <Skeleton className="h-3 w-20" />
                    </div>
                ))}
            </div>

            <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.1fr)]">
                <div className="space-y-3 rounded-xl border bg-white p-4 shadow-sm">
                    <Skeleton className="h-5 w-40" />
                    <Skeleton className="h-3 w-full max-w-sm" />
                    {Array.from({ length: 4 }).map((_, index) => (
                        <Skeleton key={index} className="h-10 w-full rounded-md" />
                    ))}
                </div>

                <div className="space-y-3 rounded-xl border bg-white p-4 shadow-sm">
                    <Skeleton className="h-5 w-36" />
                    {Array.from({ length: 5 }).map((_, index) => (
                        <div key={index} className="flex items-start gap-3">
                            <Skeleton className="h-8 w-8 shrink-0 rounded-full" />
                            <div className="flex-1 space-y-2">
                                <Skeleton className="h-3 w-3/4" />
                                <Skeleton className="h-3 w-1/2" />
                            </div>
                        </div>
                    ))}
                </div>
            </div>

            <div className="overflow-hidden rounded-xl border bg-white shadow-sm">
                <div className="border-b px-4 py-3">
                    <Skeleton className="h-5 w-44" />
                </div>
                {Array.from({ length: 5 }).map((_, index) => (
                    <div
                        key={index}
                        className="grid grid-cols-4 gap-3 border-b px-4 py-3 last:border-0"
                    >
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-4 w-full" />
                        <Skeleton className="h-8 w-20 justify-self-end rounded-md" />
                    </div>
                ))}
            </div>
        </div>
    );
}
