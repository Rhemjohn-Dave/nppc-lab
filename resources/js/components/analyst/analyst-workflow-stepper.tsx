import { cn } from '@/lib/utils';
import type { AnalystTask } from './types';
import { jobWorkflowSteps } from './types';

type Props = {
    job: AnalystTask['job_order'];
    /** Dense single-row strip for the encoding workbench header. */
    compact?: boolean;
};

export default function AnalystWorkflowStepper({
    job,
    compact = false,
}: Props) {
    const steps = jobWorkflowSteps(job);

    if (compact) {
        return (
            <ol
                className="flex max-w-3xl items-center gap-1 overflow-x-auto"
                aria-label="Job order workflow"
            >
                {steps.map((step, index) => {
                    const isLast = index === steps.length - 1;

                    return (
                        <li
                            key={step.key}
                            className="flex shrink-0 items-center gap-1"
                        >
                            <div
                                className={cn(
                                    'inline-flex items-center gap-1.5 rounded-full px-2 py-0.5',
                                    step.state === 'current' &&
                                        'border border-nppc-200 bg-nppc-50 shadow-xs',
                                )}
                            >
                                <span
                                    className={cn(
                                        'flex size-3.5 items-center justify-center rounded-full text-[9px] font-semibold',
                                        step.state === 'done' &&
                                            'bg-emerald-500 text-white',
                                        step.state === 'current' &&
                                            'bg-nppc-900 text-white',
                                        step.state === 'upcoming' &&
                                            'border border-slate-300 bg-white text-slate-400',
                                    )}
                                    aria-hidden="true"
                                >
                                    {step.state === 'done'
                                        ? '✓'
                                        : step.state === 'current'
                                          ? '●'
                                          : ''}
                                </span>
                                <span
                                    className={cn(
                                        'text-[11px] font-medium whitespace-nowrap',
                                        step.state === 'upcoming'
                                            ? 'text-slate-400'
                                            : step.state === 'current'
                                              ? 'font-semibold text-nppc-900'
                                              : 'text-slate-700',
                                    )}
                                >
                                    {step.label}
                                </span>
                                {step.state === 'current' ? (
                                    <span className="rounded-full bg-nppc-100 px-1.5 py-px text-[9px] font-bold tracking-wide text-nppc-800 uppercase">
                                        In progress
                                    </span>
                                ) : null}
                            </div>
                            {!isLast ? (
                                <span
                                    className={cn(
                                        'mx-0.5 h-px w-4 shrink-0',
                                        step.state === 'done'
                                            ? 'bg-emerald-300'
                                            : 'bg-slate-200',
                                    )}
                                    aria-hidden="true"
                                />
                            ) : null}
                        </li>
                    );
                })}
            </ol>
        );
    }

    return (
        <ol
            className="grid grid-cols-4 gap-1"
            aria-label="Job order workflow"
        >
            {steps.map((step, index) => {
                const isLast = index === steps.length - 1;

                return (
                    <li key={step.key} className="relative min-w-0">
                        {!isLast && (
                            <span
                                className={cn(
                                    'absolute top-2.5 left-[calc(50%+10px)] right-[-50%] h-px',
                                    step.state === 'done'
                                        ? 'bg-emerald-300'
                                        : 'bg-slate-200',
                                )}
                                aria-hidden="true"
                            />
                        )}
                        <div className="relative z-[1] flex flex-col items-center text-center">
                            <span
                                className={cn(
                                    'flex size-5 items-center justify-center rounded-full text-[10px] font-semibold',
                                    step.state === 'done' &&
                                        'bg-emerald-100 text-emerald-800',
                                    step.state === 'current' &&
                                        'bg-nppc-900 text-white',
                                    step.state === 'upcoming' &&
                                        'bg-slate-100 text-slate-400',
                                )}
                                aria-hidden="true"
                            >
                                {step.state === 'done'
                                    ? '✓'
                                    : step.state === 'current'
                                      ? '●'
                                      : '○'}
                            </span>
                            <p
                                className={cn(
                                    'mt-1.5 text-[11px] leading-tight font-medium',
                                    step.state === 'upcoming'
                                        ? 'text-slate-400'
                                        : 'text-slate-800',
                                    step.state === 'current' && 'text-nppc-900',
                                )}
                            >
                                {step.label}
                            </p>
                            {step.detail && (
                                <p className="mt-0.5 line-clamp-2 text-[10px] leading-tight text-muted-foreground">
                                    {step.detail}
                                </p>
                            )}
                        </div>
                    </li>
                );
            })}
        </ol>
    );
}
