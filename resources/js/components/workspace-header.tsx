import { RefreshCw } from 'lucide-react';
import type { ReactNode } from 'react';
import { Spinner } from '@/components/ui/spinner';

type Props = {
    title: string;
    description: string;
    refreshing?: boolean;
    lastUpdated?: Date;
    refreshLabel?: string;
    hint?: string;
    actions?: ReactNode;
};

export default function WorkspaceHeader({
    title,
    description,
    refreshing = false,
    lastUpdated,
    refreshLabel = 'Refreshing…',
    hint,
    actions,
}: Props) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 className="font-heading text-xl font-semibold text-[#1A3694]">
                    {title}
                </h1>
                <p className="text-sm leading-snug text-muted-foreground">
                    {description}
                </p>
                {(lastUpdated || hint || refreshing) && (
                    <div className="mt-1 flex flex-wrap items-center gap-2 text-xs text-muted-foreground">
                        {(lastUpdated || refreshing) && (
                            <span className="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-white px-2 py-0.5">
                                {refreshing ? (
                                    <Spinner className="size-3" />
                                ) : (
                                    <RefreshCw className="size-3.5 text-slate-400" />
                                )}
                                {refreshing
                                    ? refreshLabel
                                    : lastUpdated
                                      ? `Last updated ${lastUpdated.toLocaleTimeString()}`
                                      : null}
                            </span>
                        )}
                        {hint && <span>{hint}</span>}
                    </div>
                )}
            </div>
            {actions}
        </div>
    );
}
