import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import {
    ChevronLeft,
    ChevronRight,
    Maximize2,
    Minimize2,
} from 'lucide-react';

type Props = {
    referenceNo: string;
    classification?: string | null;
    sampleLabel?: string | null;
    customerName: string;
    queuePendingCount: number;
    saveStateLabel: string;
    focusPosition?: { index: number; total: number } | null;
    canGoPrev: boolean;
    canGoNext: boolean;
    distractionFree: boolean;
    hasReturned: boolean;
    onBackToQueue?: () => void;
    onPrevJob?: () => void;
    onNextJob?: () => void;
    onToggleDistractionFree: () => void;
};

export default function EncodingWorkbenchHeader({
    referenceNo,
    classification,
    sampleLabel,
    customerName,
    queuePendingCount,
    saveStateLabel,
    focusPosition,
    canGoPrev,
    canGoNext,
    distractionFree,
    hasReturned,
    onBackToQueue,
    onPrevJob,
    onNextJob,
    onToggleDistractionFree,
}: Props) {
    const saving = saveStateLabel.toLowerCase().includes('saving');
    const saved =
        saveStateLabel.startsWith('✓') ||
        saveStateLabel.toLowerCase().includes('saved');

    return (
        <header className="flex h-11 shrink-0 items-center justify-between gap-2 border-b border-slate-200 bg-white px-3 sm:h-[2.75rem] sm:px-4">
            <div className="flex min-w-0 items-center gap-2">
                {onBackToQueue ? (
                    <button
                        type="button"
                        onClick={onBackToQueue}
                        className="inline-flex h-7 shrink-0 items-center gap-1 rounded-md bg-slate-100 px-1.5 py-0.5 text-[11px] font-medium text-slate-700 transition-colors hover:bg-slate-200 sm:px-2 sm:text-xs"
                    >
                        <ChevronLeft className="size-3.5" />
                        <span className="hidden sm:inline">Bench Queue</span>
                        {queuePendingCount > 0 ? (
                            <span className="rounded-full bg-nppc-100 px-1.5 py-0.5 font-mono text-[10px] font-bold text-nppc-900">
                                {queuePendingCount}
                            </span>
                        ) : null}
                    </button>
                ) : null}

                <div className="hidden h-3.5 w-px bg-slate-200 sm:block" />

                <div className="flex min-w-0 items-center gap-1.5 truncate text-xs">
                    <span className="shrink-0 rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-[11px] font-bold text-slate-900">
                        JO #{referenceNo}
                    </span>
                    {classification ? (
                        <>
                            <span className="text-slate-300">·</span>
                            <span className="truncate font-medium text-slate-800">
                                {classification}
                            </span>
                        </>
                    ) : null}
                    {sampleLabel ? (
                        <>
                            <span className="text-slate-300">·</span>
                            <span className="truncate text-slate-600">
                                {sampleLabel}
                            </span>
                        </>
                    ) : null}
                    <span className="hidden text-slate-300 md:inline">·</span>
                    <span className="hidden truncate text-[11px] text-slate-500 md:inline">
                        {customerName}
                    </span>
                </div>
            </div>

            <div className="flex shrink-0 items-center gap-2">
                <div
                    className={cn(
                        'hidden items-center gap-1.5 rounded-full border px-2 py-0.5 text-[11px] font-medium sm:flex',
                        saving
                            ? 'border-amber-200 bg-amber-50 text-amber-800'
                            : saved
                              ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                              : 'border-slate-200 bg-slate-50 text-slate-600',
                    )}
                >
                    <span
                        className={cn(
                            'size-1.5 rounded-full',
                            saving
                                ? 'animate-pulse bg-amber-500'
                                : saved
                                  ? 'bg-emerald-500'
                                  : 'bg-slate-400',
                        )}
                    />
                    <span className="max-w-[9rem] truncate">{saveStateLabel}</span>
                </div>

                <div className="flex items-center rounded-md border border-slate-200 bg-slate-50 p-0.5 text-xs">
                    <button
                        type="button"
                        disabled={!canGoPrev}
                        title="Alt + ←"
                        onClick={onPrevJob}
                        className="flex items-center gap-0.5 rounded px-1.5 py-0.5 text-slate-700 hover:bg-white disabled:cursor-not-allowed disabled:text-slate-400"
                    >
                        <ChevronLeft className="size-3" />
                        <span className="hidden xl:inline">Prev</span>
                    </button>
                    <span className="border-x border-slate-200 px-1.5 font-mono text-[10px] font-bold text-slate-700">
                        {focusPosition
                            ? `${focusPosition.index} of ${focusPosition.total}`
                            : '—'}
                    </span>
                    <button
                        type="button"
                        disabled={!canGoNext}
                        title="Alt + →"
                        onClick={onNextJob}
                        className="flex items-center gap-0.5 rounded px-1.5 py-0.5 text-slate-700 hover:bg-white disabled:cursor-not-allowed disabled:text-slate-400"
                    >
                        <span className="hidden xl:inline">Next</span>
                        <ChevronRight className="size-3" />
                    </button>
                </div>

                {hasReturned ? (
                    <span className="hidden items-center gap-1 rounded-full border border-rose-200 bg-rose-50 px-2 py-0.5 text-[10px] font-bold text-rose-700 md:inline-flex">
                        <span className="size-1.5 animate-pulse rounded-full bg-rose-600" />
                        Revision
                    </span>
                ) : null}

                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className={cn(
                        'h-7 gap-1 border-slate-200 px-2 text-[11px] shadow-xs',
                        distractionFree &&
                            'border-nppc-200 bg-nppc-50 text-nppc-900',
                    )}
                    title="Toggle distraction-free (Alt+F)"
                    onClick={onToggleDistractionFree}
                >
                    {distractionFree ? (
                        <Minimize2 className="size-3.5" />
                    ) : (
                        <Maximize2 className="size-3.5" />
                    )}
                    <span className="hidden lg:inline">
                        {distractionFree ? 'Exit Focus' : 'Focus'}
                    </span>
                </Button>
            </div>
        </header>
    );
}
