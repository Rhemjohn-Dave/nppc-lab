import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

type Props = {
    steps: readonly string[];
    step: number;
    onStepSelect: (index: number) => void;
};

/**
 * Compact intake progress: mobile bar + desktop circular stepper.
 */
export default function IntakeStepper({ steps, step, onStepSelect }: Props) {
    const progress = ((step + 1) / steps.length) * 100;

    return (
        <div className="shrink-0 border-b border-slate-100 pb-2">
            <div className="sm:hidden">
                <div className="mb-1 flex items-center justify-between text-sm">
                    <span className="font-semibold text-nppc-900">
                        Step {step + 1} of {steps.length}
                    </span>
                    <span className="text-slate-600">{steps[step]}</span>
                </div>
                <div className="h-1 overflow-hidden rounded-full bg-nppc-100">
                    <div
                        className="h-full rounded-full bg-nppc-900 transition-all duration-500 ease-out"
                        style={{ width: `${progress}%` }}
                    />
                </div>
            </div>

            <ol className="mt-0 hidden w-full items-start sm:flex">
                {steps.map((label, index) => {
                    const done = index < step;
                    const current = index === step;
                    const upcoming = index > step;

                    return (
                        <li
                            key={label}
                            className="relative flex min-w-0 flex-1 flex-col items-center"
                        >
                            {index < steps.length - 1 ? (
                                <span
                                    className={cn(
                                        'absolute top-3.5 left-[calc(50%+0.9rem)] h-0.5 w-[calc(100%-1.8rem)]',
                                        done || current
                                            ? 'bg-nppc-900'
                                            : 'bg-slate-200',
                                    )}
                                    aria-hidden
                                />
                            ) : null}

                            <button
                                type="button"
                                disabled={!done}
                                onClick={() => done && onStepSelect(index)}
                                aria-current={current ? 'step' : undefined}
                                aria-label={`${String(index + 1).padStart(2, '0')} ${label}`}
                                className={cn(
                                    'relative z-10 flex flex-col items-center gap-0.5 rounded-lg px-0.5 focus-visible:ring-2 focus-visible:ring-nppc-900/40 focus-visible:outline-none',
                                    done && 'cursor-pointer',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-7 items-center justify-center rounded-full text-[11px] font-bold',
                                        current &&
                                            'bg-nppc-900 text-white shadow-sm',
                                        done && 'bg-nppc-100 text-nppc-900',
                                        upcoming &&
                                            'bg-slate-100 text-slate-400',
                                    )}
                                >
                                    {done ? (
                                        <Check
                                            className="size-3.5"
                                            aria-hidden
                                        />
                                    ) : (
                                        String(index + 1).padStart(2, '0')
                                    )}
                                </span>
                                <span className="text-center">
                                    <span
                                        className={cn(
                                            'block text-[11px] font-semibold leading-tight',
                                            current
                                                ? 'text-nppc-900'
                                                : done
                                                  ? 'text-nppc-800'
                                                  : 'text-slate-400',
                                        )}
                                    >
                                        {label}
                                    </span>
                                </span>
                            </button>
                        </li>
                    );
                })}
            </ol>
        </div>
    );
}
