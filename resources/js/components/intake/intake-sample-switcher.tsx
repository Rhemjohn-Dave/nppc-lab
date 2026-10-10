import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

export type IntakeSampleSwitcherItem = {
    label: string;
    hasSelection: boolean;
    analysisCount: number;
};

type Props = {
    samples: IntakeSampleSwitcherItem[];
    activeIndex: number;
    onSelect: (index: number) => void;
    showMissing?: boolean;
    /** Optional meta shown inline after tabs (active sample summary). */
    meta?: string | null;
};

export default function IntakeSampleSwitcher({
    samples,
    activeIndex,
    onSelect,
    showMissing = false,
    meta,
}: Props) {
    if (samples.length <= 1 && !meta) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            {samples.length > 1 ? (
                <div
                    className="flex flex-wrap gap-1.5"
                    role="tablist"
                    aria-label="Samples"
                >
                    {samples.map((sample, index) => {
                        const active = activeIndex === index;
                        const missing = showMissing && !sample.hasSelection;

                        return (
                            <button
                                key={index}
                                type="button"
                                role="tab"
                                aria-selected={active}
                                onClick={() => onSelect(index)}
                                className={cn(
                                    'inline-flex max-w-full items-center gap-1.5 rounded-md border px-2.5 py-1.5 text-xs font-medium transition focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                    active
                                        ? 'border-nppc-900 bg-nppc-900 text-white'
                                        : missing
                                          ? 'border-red-300 bg-red-50 text-red-800'
                                          : 'border-slate-200 bg-white text-slate-700 hover:border-nppc-500/60',
                                )}
                            >
                                <span className="min-w-0 truncate">
                                    {sample.label}
                                </span>
                                {sample.analysisCount > 0 ? (
                                    <span
                                        className={cn(
                                            'inline-flex shrink-0 rounded px-1 py-0.5 text-[10px] font-semibold',
                                            active
                                                ? 'bg-white/20 text-white'
                                                : 'bg-nppc-50 text-nppc-900',
                                        )}
                                    >
                                        {sample.analysisCount} test
                                        {sample.analysisCount === 1
                                            ? ''
                                            : 's'}
                                    </span>
                                ) : sample.hasSelection ? (
                                    <Check
                                        className={cn(
                                            'size-3 shrink-0',
                                            active
                                                ? 'text-white'
                                                : 'text-nppc-700',
                                        )}
                                        aria-hidden
                                    />
                                ) : null}
                            </button>
                        );
                    })}
                </div>
            ) : null}
            {meta ? (
                <p className="min-w-0 flex-1 truncate text-xs text-slate-500">
                    {meta}
                </p>
            ) : null}
        </div>
    );
}
