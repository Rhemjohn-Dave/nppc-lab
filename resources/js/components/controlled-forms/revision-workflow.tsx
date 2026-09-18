import { Check } from 'lucide-react';
import { cn } from '@/lib/utils';

const MAIN_PATH = [
    { status: 'draft', label: 'Draft' },
    { status: 'for_review', label: 'For Review' },
    { status: 'for_approval', label: 'For Approval' },
    { status: 'approved', label: 'Approved' },
    { status: 'active', label: 'Active' },
] as const;

const TERMINAL = new Set(['superseded', 'archived']);

type Props = {
    currentStatus: string | null | undefined;
};

export default function RevisionWorkflow({ currentStatus }: Props) {
    const status = currentStatus ?? null;
    const isTerminal = status !== null && TERMINAL.has(status);
    const currentIndex = MAIN_PATH.findIndex((step) => step.status === status);

    return (
        <div className="rounded-lg border bg-white px-3 py-2">
            <p className="mb-1.5 text-[11px] font-semibold tracking-wider text-[#1A3694] uppercase">
                Revision workflow
            </p>
            <div className="-mx-1 overflow-x-auto px-1">
                <ol className="flex w-max max-w-none items-center gap-1 pb-1 md:w-full md:flex-wrap">
                    {MAIN_PATH.map((step, index) => {
                        const isCurrent = !isTerminal && step.status === status;
                        const isComplete =
                            !isTerminal && currentIndex >= 0 && index < currentIndex;
                        const isFuture =
                            !isTerminal && (currentIndex < 0 || index > currentIndex);

                        return (
                            <li key={step.status} className="flex items-center gap-1">
                                <div
                                    className={cn(
                                        'flex items-center gap-1.5 rounded-md px-2 py-1 text-xs font-medium',
                                        isCurrent &&
                                            'border border-[#1A3694]/40 bg-[#eef3fb] text-[#1A3694]',
                                        isComplete &&
                                            'border border-emerald-200 bg-emerald-50/80 text-emerald-800',
                                        isFuture &&
                                            'border border-transparent text-slate-400',
                                        isTerminal && 'border border-transparent text-slate-400',
                                    )}
                                    aria-current={isCurrent ? 'step' : undefined}
                                >
                                    {isComplete ? (
                                        <Check
                                            className="size-3.5 shrink-0"
                                            aria-hidden="true"
                                        />
                                    ) : (
                                        <span
                                            aria-hidden="true"
                                            className={cn(
                                                'size-1.5 shrink-0 rounded-full',
                                                isCurrent ? 'bg-[#1A3694]' : 'bg-slate-300',
                                            )}
                                        />
                                    )}
                                    <span>{step.label}</span>
                                </div>
                                {index < MAIN_PATH.length - 1 ? (
                                    <span
                                        aria-hidden="true"
                                        className="px-0.5 text-slate-300"
                                    >
                                        →
                                    </span>
                                ) : null}
                            </li>
                        );
                    })}
                    {isTerminal && status ? (
                        <li className="ml-2 flex items-center gap-1.5 rounded-md border border-zinc-200 bg-zinc-50 px-2.5 py-1.5 text-xs font-medium text-zinc-600">
                            <span aria-hidden="true" className="size-1.5 rounded-full bg-zinc-500" />
                            {status === 'archived' ? 'Archived' : 'Superseded'}
                        </li>
                    ) : null}
                </ol>
            </div>
        </div>
    );
}
