import type { AnalystTask, SampleSummary } from '@/components/analyst/types';
import { hasEncodedResult } from '@/components/analyst/types';
import { cn } from '@/lib/utils';
import { TriangleAlert } from 'lucide-react';

type Props = {
    job: AnalystTask['job_order'];
    active: AnalystTask | null;
    sample: SampleSummary | null;
    sampleLabel: string | null;
    checklist: AnalystTask[];
    onFocusTask: (task: AnalystTask) => void;
    className?: string;
};

function Field({
    label,
    value,
}: {
    label: string;
    value: string | null | undefined;
}) {
    if (!value?.trim()) {
        return null;
    }

    return (
        <div className="min-w-0">
            <span className="block text-[10px] text-slate-400">{label}</span>
            <p className="break-words text-[11px] leading-snug font-medium text-slate-800">
                {value}
            </p>
        </div>
    );
}

function checklistBadge(task: AnalystTask, active: boolean) {
    if (active) {
        return {
            label: 'EDITING',
            className: 'bg-amber-50 text-amber-900',
        };
    }
    if (task.status === 'completed') {
        return {
            label: 'Done',
            className: 'bg-emerald-50 text-emerald-800',
        };
    }
    if (task.status === 'returned') {
        return {
            label: 'Returned',
            className: 'bg-rose-50 text-rose-800',
        };
    }
    if (hasEncodedResult(task)) {
        return {
            label: 'Encoded',
            className: 'bg-sky-50 text-sky-800',
        };
    }
    return {
        label: 'Waiting',
        className: 'bg-slate-100 text-slate-500',
    };
}

export default function EncodingContextRail({
    job,
    active,
    sample,
    sampleLabel,
    checklist,
    onFocusTask,
    className,
}: Props) {
    const controlCode =
        active?.control_number?.trim() ||
        sample?.control_number?.trim() ||
        sample?.sample_code?.trim() ||
        null;
    const matrix =
        sample?.matrix?.trim() || job.classification?.trim() || null;
    const showCustody = Boolean(
        job.received_at?.trim() || job.sample_storage_temp?.trim(),
    );
    const encodedCount = checklist.filter((t) => hasEncodedResult(t)).length;

    return (
        <aside
            className={cn(
                'flex w-[350px] shrink-0 flex-col border-l border-slate-200 bg-white xl:w-[400px]',
                className,
            )}
        >
            <div className="min-h-0 flex-1 space-y-3 overflow-y-auto overscroll-contain p-3 [scrollbar-gutter:stable]">
                <section>
                    <h3 className="mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Sample specimen log
                    </h3>
                    <div className="space-y-1.5 rounded-md border border-slate-200 bg-slate-50 p-2">
                        <Field label="Control code" value={controlCode} />
                        <Field
                            label="Sample"
                            value={sampleLabel || sample?.description}
                        />
                        <Field label="Matrix" value={matrix} />
                        <Field
                            label="Customer / facility"
                            value={job.company_name || job.customer_name}
                        />
                        <Field
                            label="Storage"
                            value={job.sample_storage_temp}
                        />
                    </div>
                </section>

                <section>
                    <h3 className="mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Test checklist ({encodedCount}/{checklist.length}{' '}
                        encoded)
                    </h3>
                    <div className="space-y-1">
                        {checklist.map((task, index) => {
                            const isActive = task.id === active?.id;
                            const badge = checklistBadge(task, isActive);
                            const done =
                                task.status === 'completed' && !isActive;
                            const encoded =
                                !isActive && !done && hasEncodedResult(task);

                            return (
                                <button
                                    key={task.id}
                                    type="button"
                                    onClick={() => onFocusTask(task)}
                                    className={cn(
                                        'flex w-full items-center gap-2 rounded-md border px-2 py-1.5 text-left transition',
                                        isActive
                                            ? 'border-nppc-500 bg-nppc-50 shadow-xs ring-1 ring-inset ring-nppc-500/20'
                                            : 'border-slate-200 bg-white hover:bg-slate-50',
                                    )}
                                >
                                    <span
                                        className={cn(
                                            'flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                            isActive
                                                ? 'bg-nppc-900 text-white'
                                                : done
                                                  ? 'bg-emerald-500 text-white'
                                                  : encoded
                                                    ? 'bg-sky-500 text-white'
                                                    : 'bg-slate-200 text-slate-600',
                                        )}
                                    >
                                        {done ? '✓' : index + 1}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="flex items-center gap-2">
                                            <span className="min-w-0 flex-1 break-words text-[11px] leading-snug font-semibold text-slate-900">
                                                {task.name}
                                            </span>
                                            <span
                                                className={cn(
                                                    'ml-auto shrink-0 rounded px-1 py-px text-[9px] font-bold tracking-wide uppercase',
                                                    badge.className,
                                                )}
                                            >
                                                {badge.label}
                                            </span>
                                        </span>
                                    </span>
                                </button>
                            );
                        })}
                    </div>
                </section>

                {showCustody ? (
                    <div className="rounded-md border border-amber-200/80 bg-amber-50/70 p-2 text-[11px] text-amber-950">
                        <div className="mb-0.5 flex items-center gap-1.5 font-bold">
                            <TriangleAlert className="size-3.5 shrink-0 text-amber-600" />
                            Receipt / storage
                        </div>
                        <p className="break-words text-[10px] leading-relaxed text-amber-900">
                            {[
                                job.received_at
                                    ? `Received ${job.received_at}`
                                    : null,
                                job.sample_storage_temp
                                    ? `Storage ${job.sample_storage_temp}`
                                    : null,
                            ]
                                .filter(Boolean)
                                .join(' · ')}
                        </p>
                    </div>
                ) : null}
            </div>
        </aside>
    );
}
