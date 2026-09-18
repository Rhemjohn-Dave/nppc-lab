import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    steps: readonly string[];
    step: number;
    onStepSelect: (index: number) => void;
    error?: string | null;
};

/**
 * Compact intake progress: mobile “Step N of M” + bar; desktop check/current/upcoming row.
 */
export default function IntakeStepper({
    steps,
    step,
    onStepSelect,
    error,
}: Props) {
    const progress = ((step + 1) / steps.length) * 100;

    return (
        <div className="shrink-0 border-b border-slate-100 pb-3">
            <div className="sm:hidden">
                <div className="mb-1.5 flex items-center justify-between text-sm">
                    <span className="font-semibold text-[#1A3694]">
                        Step {step + 1} of {steps.length}
                    </span>
                    <span className="text-slate-600">{steps[step]}</span>
                </div>
                <div className="h-1.5 overflow-hidden rounded-full bg-[#e8eef8]">
                    <div
                        className="h-full rounded-full bg-[#1A3694] transition-all duration-500 ease-out"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>

            <ol className="mt-0 hidden grid-cols-5 gap-1.5 sm:grid sm:gap-2">
                {steps.map((label, index) => {
                    const done = index < step;
                    const current = index === step;

                    return (
                        <li
                            key={label}
                            className="flex min-w-0 flex-col items-center gap-1 text-center"
                        >
                            <button
                                type="button"
                                disabled={!done}
                                onClick={() => done && onStepSelect(index)}
                                aria-current={current ? 'step' : undefined}
                                aria-label={`${String(index + 1).padStart(2, '0')} ${label}`}
                                className={cn(
                                    'flex size-7 items-center justify-center rounded-full text-xs font-semibold transition focus-visible:ring-2 focus-visible:ring-[#1A3694]/40 focus-visible:outline-none sm:size-8',
                                    current &&
                                        'bg-[#1A3694] text-white shadow-sm',
                                    done &&
                                        'cursor-pointer bg-[#5282D3]/25 text-[#1A3694] hover:bg-[#5282D3]/35',
                                    !current &&
                                        !done &&
                                        'bg-slate-100 text-slate-400',
                                )}
                            >
                                {done ? (
                                    <Check className="size-3.5" aria-hidden />
                                ) : (
                                    String(index + 1).padStart(2, '0')
                                )}
                            </button>
                            <span
                                className={cn(
                                    'truncate text-[11px] font-medium',
                                    current
                                        ? 'text-[#1A3694]'
                                        : 'text-slate-500',
                                )}
                            >
                                {label}
                            </span>
                        </li>
                    );
                })}
            </ol>

            {error ? (
                <p className="mt-2 text-sm text-amber-800" role="status">
                    {error}
                </p>
            ) : null}
        </div>
    );
}
