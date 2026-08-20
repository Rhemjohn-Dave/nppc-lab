import { RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    title: string;
    description: string;
    flash?: string;
    refreshing?: boolean;
    lastUpdated?: Date;
    refreshLabel?: string;
    hint?: string;
    actions?: ReactNode;
};

export default function WorkspaceHeader({
    title,
    description,
    flash,
    refreshing = false,
    lastUpdated,
    refreshLabel = 'Refreshing…',
    hint,
    actions,
}: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="font-heading text-2xl font-semibold text-[#1A3694]">
                    {title}
                </h1>
                <p className="text-sm text-muted-foreground">{description}</p>
                {(lastUpdated || hint) && (
                    <div className="mt-2 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {lastUpdated && (
                            <span className="inline-flex items-center gap-1 rounded-full border border-slate-200 bg-white px-2.5 py-1">
                                <RefreshCw
                                    className={cn(
                                        'size-3.5',
                                        refreshing && 'animate-spin',
                                    )}
                                />
                                {refreshing
                                    ? refreshLabel
                                    : `Last updated ${lastUpdated.toLocaleTimeString()}`}
                            </span>
                        )}
                        {hint && <span>{hint}</span>}
                    </div>
                )}
                {flash && (
                    <p className="mt-2 text-sm text-emerald-700">{flash}</p>
                )}
            </div>
            {actions}
        </div>
    );
}
