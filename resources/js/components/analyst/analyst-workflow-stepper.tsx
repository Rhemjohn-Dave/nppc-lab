import { cn } from '@/lib/utils';
import type { AnalystTask } from './types';
import { jobWorkflowSteps } from './types';

type Props = {
    job: AnalystTask['job_order'];
};

export default function AnalystWorkflowStepper({ job }: Props) {
    const steps = jobWorkflowSteps(job);

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
                                        'bg-[#1A3694] text-white',
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
                                    step.state === 'current' && 'text-[#1A3694]',
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
