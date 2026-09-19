import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const MAIN_PATH = [
    { status: 'draft', label: '1. Draft' },
    { status: 'for_review', label: '2. For Review' },
    { status: 'for_approval', label: '3. For Approval' },
    { status: 'approved', label: '4. Approved' },
    { status: 'active', label: '5. Active' },
] as const;

const TERMINAL = new Set(['superseded', 'archived']);

type Props = {
    currentStatus: string | null | undefined;
    currentRevision?: string | null;
    embedded?: boolean;
};

export default function RevisionWorkflow({
    currentStatus,
    currentRevision,
    embedded = false,
}: Props) {
    const status = currentStatus ?? null;
    const isTerminal = status !== null && TERMINAL.has(status);
    const currentIndex = MAIN_PATH.findIndex((step) => step.status === status);

    return (
        <div className={cn(!embedded && 'rounded-lg border bg-white px-3 py-2')}>
            <div className="mb-1.5 flex items-center justify-between gap-2">
                <p className="text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                    Lifecycle &amp; approval pipeline
                </p>
                {currentRevision ? (
                    <span className="text-[11px] text-muted-foreground">
                        Current revision:{' '}
                        <strong className="font-mono text-slate-800">
                            {currentRevision}
                        </strong>
                    </span>
                ) : null}
            </div>
            <div className="grid grid-cols-2 gap-1.5 md:grid-cols-3 xl:grid-cols-5">
                {MAIN_PATH.map((step, index) => {
                    const isCurrent = !isTerminal && step.status === status;
                    const isComplete =
                        !isTerminal && currentIndex >= 0 && index < currentIndex;
                    const isFuture =
                        !isTerminal && (currentIndex < 0 || index > currentIndex);

                    return (
                        <div
                            key={step.status}
                            className={cn(
                                'flex items-start gap-2 rounded-md border px-2 py-1.5',
                                isCurrent &&
                                    'border-[#1A3694]/40 bg-[#eef3fb]',
                                isComplete &&
                                    'border-emerald-200 bg-emerald-50/60',
                                (isFuture || isTerminal) &&
                                    'border-slate-100 bg-slate-50/50',
                            )}
                            aria-current={isCurrent ? 'step' : undefined}
                        >
                            <div
                                className={cn(
                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full',
                                    isCurrent && 'bg-[#1A3694] text-white',
                                    isComplete && 'bg-emerald-100 text-emerald-700',
                                    (isFuture || isTerminal) &&
                                        'bg-slate-100 text-slate-400',
                                )}
                            >
                                {isComplete ? (
                                    <Check className="size-3" aria-hidden="true" />
                                ) : (
                                    <span
                                        aria-hidden="true"
                                        className={cn(
                                            'size-1.5 rounded-full',
                                            isCurrent ? 'bg-white' : 'bg-slate-300',
                                        )}
                                    />
                                )}
                            </div>
                            <div className="min-w-0">
                                <p
                                    className={cn(
                                        'text-xs font-medium',
                                        isCurrent
                                            ? 'text-[#1A3694]'
                                            : isComplete
                                              ? 'text-emerald-900'
                                              : 'text-slate-500',
                                    )}
                                >
                                    {step.label}
                                </p>
                            </div>
                        </div>
                    );
                })}
                {isTerminal && status ? (
                    <div className="col-span-full flex items-center gap-2 rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs font-medium text-zinc-600 md:col-span-1">
                        <span
                            aria-hidden="true"
                            className="size-1.5 rounded-full bg-zinc-500"
                        />
                        {status === 'archived' ? 'Archived' : 'Superseded'}
                    </div>
                ) : null}
            </div>
        </div>
    );
}
