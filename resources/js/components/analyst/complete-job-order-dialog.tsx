import { useEffect, useMemo, useState } from 'react';
import type { SampleBreakdownRow } from '@/components/analyst/types';
import { canWorkTask, hasEncodedResult } from '@/components/analyst/types';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { cn } from '@/lib/utils';
import { ArrowRight, Check } from 'lucide-react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    processing?: boolean;
    onConfirm: () => void;
    jobReference?: string | null;
    samples: SampleBreakdownRow[];
    /** When set, only this sample must be encoded to confirm (multi-sample JOs). */
    activeSampleId?: number | null;
    activeTaskId?: number | null;
    formResultValue?: string;
};

type SampleStats = SampleBreakdownRow & {
    encoded: number;
    complete: boolean;
};

function chipLabel(group: SampleBreakdownRow): string {
    const control = group.control_number?.trim();
    const name = group.label.trim();
    if (control && name && !name.startsWith(control)) {
        return `${control} · ${name}`;
    }
    return control || name || 'Sample';
}

function shortSampleLabel(group: SampleBreakdownRow, index: number): string {
    const description = group.label.includes('—')
        ? group.label.split('—').slice(1).join('—').trim()
        : group.label.trim();
    const base = description || group.label.trim() || `Sample ${index + 1}`;
    return `Sample ${index + 1} — ${base}`;
}

function taskIsEncoded(
    taskId: number,
    baseEncoded: boolean,
    activeTaskId: number | null | undefined,
    formResultValue: string | undefined,
): boolean {
    if (activeTaskId != null && taskId === activeTaskId) {
        return Boolean(formResultValue?.trim()) || baseEncoded;
    }
    return baseEncoded;
}

function resultValueForTask(
    taskId: number,
    stored: string | null | undefined,
    activeTaskId: number | null | undefined,
    formResultValue: string | undefined,
): string {
    if (activeTaskId != null && taskId === activeTaskId) {
        const live = formResultValue?.trim();
        if (live) {
            return live;
        }
    }
    return stored?.trim() || '—';
}

function ResultRows({
    tasks,
    activeTaskId,
    formResultValue,
}: {
    tasks: SampleStats['tasks'];
    activeTaskId: number | null | undefined;
    formResultValue: string | undefined;
}) {
    return (
        <ul className="divide-y divide-slate-100">
            {tasks.map((task) => (
                <li
                    key={task.id}
                    className="flex items-baseline justify-between gap-4 py-2 text-sm"
                >
                    <span className="min-w-0 truncate text-slate-700">
                        {task.name}
                    </span>
                    <span className="shrink-0 text-right font-medium text-nppc-900 tabular-nums">
                        {resultValueForTask(
                            task.id,
                            task.result_value,
                            activeTaskId,
                            formResultValue,
                        )}
                    </span>
                </li>
            ))}
        </ul>
    );
}

export default function CompleteJobOrderDialog({
    open,
    onOpenChange,
    processing = false,
    onConfirm,
    jobReference,
    samples,
    activeSampleId = null,
    activeTaskId = null,
    formResultValue = '',
}: Props) {
    const sampleScoped = activeSampleId != null;

    const workableSamples = useMemo((): SampleStats[] => {
        return samples
            .filter((group) =>
                sampleScoped
                    ? Number(group.sample_id) === Number(activeSampleId)
                    : true,
            )
            .map((group) => {
                const tasks = group.tasks.filter((t) => canWorkTask(t));
                const encoded = tasks.filter((t) =>
                    taskIsEncoded(
                        t.id,
                        hasEncodedResult(t),
                        activeTaskId,
                        formResultValue,
                    ),
                ).length;
                const total = tasks.length;
                return {
                    ...group,
                    tasks,
                    total,
                    encoded,
                    complete: total > 0 && encoded >= total,
                };
            })
            .filter((group) => group.tasks.length > 0);
    }, [samples, sampleScoped, activeSampleId, activeTaskId, formResultValue]);

    const singleSample = workableSamples.length <= 1;

    const initialKey = useMemo(() => {
        if (activeTaskId != null) {
            const match = workableSamples.find((g) =>
                g.tasks.some((t) => t.id === activeTaskId),
            );
            if (match) {
                return match.key;
            }
        }
        const firstIncomplete = workableSamples.find((g) => !g.complete);
        return firstIncomplete?.key ?? workableSamples[0]?.key ?? null;
    }, [activeTaskId, workableSamples]);

    const [expandedKey, setExpandedKey] = useState<string | null>(initialKey);

    useEffect(() => {
        if (open) {
            setExpandedKey(initialKey);
        }
    }, [open, initialKey]);

    const testTotal = workableSamples.reduce((sum, g) => sum + g.total, 0);
    const testEncoded = workableSamples.reduce((sum, g) => sum + g.encoded, 0);
    const remaining = Math.max(testTotal - testEncoded, 0);
    const ready = testTotal > 0 && testEncoded >= testTotal;

    function selectSample(key: string) {
        setExpandedKey(key);
        requestAnimationFrame(() => {
            document
                .getElementById(`complete-jo-sample-${key}`)
                ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
        });
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (processing && !next) {
                    return;
                }
                onOpenChange(next);
            }}
        >
            <DialogContent className="flex max-h-[82vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="shrink-0 space-y-1.5 border-b border-slate-200 px-5 py-4 text-left sm:px-6">
                    <DialogTitle className="font-heading text-base text-nppc-900 sm:text-lg">
                        {sampleScoped
                            ? 'Complete this sample?'
                            : 'Complete this job order?'}
                    </DialogTitle>
                    {jobReference ? (
                        <p className="font-mono text-xs text-slate-500">
                            JO #{jobReference.replace(/^JO\s*#?/i, '')}
                            {sampleScoped && workableSamples[0]
                                ? ` · ${chipLabel(workableSamples[0])}`
                                : ''}
                        </p>
                    ) : null}
                    <DialogDescription className="text-sm text-slate-600">
                        {sampleScoped
                            ? 'Marks every encoded test on this sample complete. Other samples stay open.'
                            : 'Marks every encoded test on this job order complete.'}
                    </DialogDescription>
                </DialogHeader>

                {!singleSample && workableSamples.length > 0 ? (
                    <div className="shrink-0 border-b border-slate-200 px-5 py-3 sm:px-6">
                        <div className="flex flex-wrap gap-1.5">
                            {workableSamples.map((group, index) => {
                                const selected = group.key === expandedKey;
                                const label = shortSampleLabel(group, index);

                                return (
                                    <button
                                        key={group.key}
                                        type="button"
                                        title={chipLabel(group)}
                                        onClick={() => selectSample(group.key)}
                                        className={cn(
                                            'inline-flex max-w-full items-center gap-2 rounded-md border px-2.5 py-1.5 text-left text-xs transition',
                                            selected
                                                ? 'border-nppc-500 bg-nppc-50 font-medium text-nppc-900'
                                                : 'border-slate-200 bg-white text-slate-700 hover:bg-slate-50',
                                        )}
                                    >
                                        {group.complete ? (
                                            <Check
                                                className="size-3.5 shrink-0 text-emerald-600"
                                                aria-hidden
                                            />
                                        ) : null}
                                        <span className="truncate">{label}</span>
                                        <span className="shrink-0 tabular-nums text-slate-500">
                                            {group.encoded}/{group.total}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ) : null}

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-3 sm:px-6">
                    {workableSamples.length === 0 ? (
                        <p className="text-sm text-slate-500">
                            No assays to complete.
                        </p>
                    ) : singleSample ? (
                        <ResultRows
                            tasks={workableSamples[0].tasks}
                            activeTaskId={activeTaskId}
                            formResultValue={formResultValue}
                        />
                    ) : (
                        <div className="space-y-4">
                            {workableSamples.map((group, index) => {
                                const expanded = group.key === expandedKey;
                                const label = shortSampleLabel(group, index);

                                return (
                                    <div
                                        key={group.key}
                                        id={`complete-jo-sample-${group.key}`}
                                    >
                                        <button
                                            type="button"
                                            className="mb-1 flex w-full items-center justify-between gap-2 text-left text-xs font-medium text-slate-800"
                                            onClick={() =>
                                                setExpandedKey(
                                                    expanded
                                                        ? null
                                                        : group.key,
                                                )
                                            }
                                            aria-expanded={expanded}
                                        >
                                            <span className="truncate">
                                                {label}
                                            </span>
                                            <span className="shrink-0 tabular-nums text-slate-500">
                                                {group.encoded}/{group.total}
                                            </span>
                                        </button>
                                        {expanded ? (
                                            <ResultRows
                                                tasks={group.tasks}
                                                activeTaskId={activeTaskId}
                                                formResultValue={
                                                    formResultValue
                                                }
                                            />
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <DialogFooter className="shrink-0 gap-3 border-t border-slate-200 px-5 py-3 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    {!ready ? (
                        <p className="text-left text-xs text-amber-800">
                            {remaining} test
                            {remaining === 1 ? '' : 's'} still need encoding.
                        </p>
                    ) : (
                        <span className="hidden sm:block" />
                    )}
                    <div className="flex w-full flex-col-reverse gap-2 sm:w-auto sm:flex-row sm:justify-end">
                        <Button
                            type="button"
                            variant="outline"
                            disabled={processing}
                            onClick={() => onOpenChange(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="button"
                            className="bg-nppc-900 text-white hover:bg-nppc-700"
                            disabled={processing || !ready}
                            onClick={(event) => {
                                event.preventDefault();
                                event.stopPropagation();
                                if (processing || !ready) {
                                    return;
                                }
                                onConfirm();
                            }}
                        >
                            {processing ? (
                                'Completing…'
                            ) : (
                                <>
                                    {sampleScoped
                                        ? 'Complete sample'
                                        : 'Complete job order'}
                                    <ArrowRight className="size-3.5" />
                                </>
                            )}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
