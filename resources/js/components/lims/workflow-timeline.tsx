import { cn } from '@/lib/utils';

export type WorkflowTimelineStep = {
    id: string;
    label: string;
    state: 'done' | 'current' | 'todo';
    detail?: string | null;
};

type Props = {
    steps: WorkflowTimelineStep[];
    title?: string;
    className?: string;
};

function StepGlyph({ state }: { state: WorkflowTimelineStep['state'] }) {
    return (
        <span
            className={cn(
                'flex size-5 shrink-0 items-center justify-center rounded-full text-[11px] font-bold',
                state === 'done' && 'bg-emerald-600 text-white',
                state === 'current' && 'bg-[#1A3694] text-white',
                state === 'todo' &&
                    'border border-slate-300 bg-white text-slate-400',
            )}
            aria-hidden="true"
        >
            {state === 'done' ? '✓' : state === 'current' ? '●' : '○'}
        </span>
    );
}

/**
 * Compact workflow progress: horizontal on md+, stacked on mobile.
 */
export default function WorkflowTimeline({
    steps,
    title = 'Progress',
    className,
}: Props) {
    return (
        <div
            className={cn(
                'rounded-xl border border-slate-200/80 bg-white px-3 py-2.5 shadow-sm',
                className,
            )}
        >
            <p className="text-[10px] font-semibold tracking-wide text-[#365BB0] uppercase">
                {title}
            </p>

            {/* Desktop / tablet horizontal */}
            <ol className="mt-2 hidden flex-wrap items-center gap-x-1 gap-y-1.5 md:flex">
                {steps.map((step, index) => (
                    <li key={step.id} className="flex items-center gap-1">
                        {index > 0 && (
                            <span
                                className="mx-0.5 text-slate-300"
                                aria-hidden="true"
                            >
                                →
                            </span>
                        )}
                        <span className="inline-flex items-center gap-1.5">
                            <StepGlyph state={step.state} />
                            <span
                                className={cn(
                                    'text-xs font-medium whitespace-nowrap',
                                    step.state === 'todo'
                                        ? 'text-slate-500'
                                        : 'text-slate-900',
                                )}
                                title={step.detail ?? undefined}
                            >
                                {step.label}
                            </span>
                        </span>
                    </li>
                ))}
            </ol>

            {/* Mobile vertical */}
            <ol className="mt-2 space-y-1.5 md:hidden">
                {steps.map((step) => (
                    <li
                        key={step.id}
                        className="flex items-start gap-2 text-sm"
                    >
                        <StepGlyph state={step.state} />
                        <div className="min-w-0">
                            <p
                                className={cn(
                                    'text-xs font-medium',
                                    step.state === 'todo'
                                        ? 'text-slate-500'
                                        : 'text-slate-900',
                                )}
                            >
                                {step.label}
                            </p>
                            {step.detail ? (
                                <p className="text-[11px] text-muted-foreground">
                                    {step.detail}
                                </p>
                            ) : null}
                        </div>
                    </li>
                ))}
            </ol>
        </div>
    );
}
