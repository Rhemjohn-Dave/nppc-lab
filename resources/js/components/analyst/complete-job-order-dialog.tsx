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
import { Check, ChevronDown, ChevronRight } from 'lucide-react';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    processing?: boolean;
    onConfirm: () => void;
    jobReference?: string | null;
    samples: SampleBreakdownRow[];
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
    const description =
        group.label.includes('—')
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

export default function CompleteJobOrderDialog({
    open,
    onOpenChange,
    processing = false,
    onConfirm,
    jobReference,
    samples,
    activeTaskId = null,
    formResultValue = '',
}: Props) {
    const workableSamples = useMemo((): SampleStats[] => {
        return samples
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
    }, [samples, activeTaskId, formResultValue]);

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

    const sampleCount = workableSamples.length;
    const samplesComplete = workableSamples.filter((g) => g.complete).length;
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
            <DialogContent className="flex max-h-[82vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-[800px]">
                <DialogHeader className="shrink-0 space-y-1 border-b border-slate-200 px-5 py-3.5 text-left sm:px-6">
                    <DialogTitle className="font-heading text-base text-nppc-900 sm:text-lg">
                        Complete all tests for this job order?
                    </DialogTitle>
                    {jobReference ? (
                        <p className="font-mono text-xs font-medium text-slate-500">
                            JO #{jobReference.replace(/^JO\s*#?/i, '')}
                        </p>
                    ) : null}
                    <DialogDescription className="text-xs text-slate-600 sm:text-sm">
                        Mark every encoded test on this job order complete. You
                        can still Send to Head afterward.
                    </DialogDescription>
                </DialogHeader>

                <div className="flex shrink-0 flex-wrap items-center gap-2 border-b border-slate-100 bg-nppc-50/50 px-5 py-2.5 sm:gap-3 sm:px-6">
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold',
                            testEncoded >= testTotal && testTotal > 0
                                ? 'bg-emerald-50 text-emerald-800'
                                : 'bg-white text-slate-700 ring-1 ring-slate-200',
                        )}
                    >
                        {testEncoded >= testTotal && testTotal > 0 ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : null}
                        {testEncoded}/{testTotal} Tests
                    </span>
                    <span
                        className={cn(
                            'inline-flex items-center gap-1 rounded-md px-2 py-1 text-xs font-semibold',
                            samplesComplete >= sampleCount && sampleCount > 0
                                ? 'bg-emerald-50 text-emerald-800'
                                : 'bg-white text-slate-700 ring-1 ring-slate-200',
                        )}
                    >
                        {samplesComplete >= sampleCount && sampleCount > 0 ? (
                            <Check className="size-3.5" aria-hidden />
                        ) : null}
                        {samplesComplete}/{sampleCount} Samples
                    </span>
                    {ready ? (
                        <span className="inline-flex items-center gap-1 rounded-md bg-emerald-100 px-2 py-1 text-xs font-semibold text-emerald-900">
                            <Check className="size-3.5" aria-hidden />
                            Ready
                        </span>
                    ) : (
                        <span className="inline-flex items-center rounded-md bg-amber-50 px-2 py-1 text-xs font-semibold text-amber-900">
                            {remaining} left
                        </span>
                    )}
                </div>

                {workableSamples.length > 0 ? (
                    <div className="shrink-0 border-b border-slate-200 px-5 py-2.5 sm:px-6">
                        <p className="mb-1.5 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                            Samples
                        </p>
                        <div className="grid grid-cols-1 gap-2 sm:grid-cols-2 lg:grid-cols-3">
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
                                            'flex items-center justify-between gap-2 rounded-md border px-2.5 py-2 text-left text-xs transition',
                                            selected
                                                ? 'border-nppc-500 bg-nppc-50 font-semibold text-nppc-900 shadow-xs ring-1 ring-inset ring-nppc-500/20'
                                                : 'border-slate-200 bg-white font-medium text-slate-700 hover:bg-slate-50',
                                        )}
                                    >
                                        <span className="flex min-w-0 items-center gap-1.5">
                                            <span
                                                className={cn(
                                                    'shrink-0 text-sm leading-none',
                                                    group.complete
                                                        ? 'text-emerald-600'
                                                        : selected
                                                          ? 'text-nppc-900'
                                                          : 'text-slate-400',
                                                )}
                                                aria-hidden
                                            >
                                                {group.complete ? '✓' : '●'}
                                            </span>
                                            <span className="truncate">
                                                {label}
                                            </span>
                                        </span>
                                        <span
                                            className={cn(
                                                'shrink-0 tabular-nums',
                                                selected
                                                    ? 'text-nppc-900'
                                                    : 'text-slate-500',
                                            )}
                                        >
                                            {group.encoded}/{group.total}
                                        </span>
                                    </button>
                                );
                            })}
                        </div>
                    </div>
                ) : null}

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-2.5 sm:px-6">
                    <p className="mb-2 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                        Test results
                    </p>
                    {workableSamples.length === 0 ? (
                        <p className="text-sm text-slate-500">
                            No assays to complete.
                        </p>
                    ) : (
                        <div className="space-y-1.5">
                            {workableSamples.map((group, index) => {
                                const expanded = group.key === expandedKey;
                                const label = shortSampleLabel(group, index);

                                return (
                                    <div
                                        key={group.key}
                                        id={`complete-jo-sample-${group.key}`}
                                        className={cn(
                                            'rounded-md border',
                                            expanded
                                                ? 'border-nppc-200 bg-white'
                                                : 'border-slate-200 bg-slate-50/50',
                                        )}
                                    >
                                        <button
                                            type="button"
                                            className="flex w-full items-center justify-between gap-2 px-2.5 py-1.5 text-left text-xs font-semibold text-slate-800"
                                            onClick={() =>
                                                setExpandedKey(
                                                    expanded
                                                        ? null
                                                        : group.key,
                                                )
                                            }
                                            aria-expanded={expanded}
                                        >
                                            <span className="flex min-w-0 items-center gap-1.5">
                                                {expanded ? (
                                                    <ChevronDown className="size-3.5 shrink-0 text-slate-500" />
                                                ) : (
                                                    <ChevronRight className="size-3.5 shrink-0 text-slate-500" />
                                                )}
                                                <span className="truncate">
                                                    {label}
                                                </span>
                                            </span>
                                            <span className="flex shrink-0 items-center gap-1.5 tabular-nums text-slate-600">
                                                {group.encoded}/{group.total}
                                                {group.complete ? (
                                                    <span className="text-emerald-600">
                                                        ✓
                                                    </span>
                                                ) : null}
                                            </span>
                                        </button>

                                        {expanded ? (
                                            <ul className="border-t border-slate-100 px-2.5 pb-1.5">
                                                {group.tasks.map((task) => (
                                                    <li
                                                        key={task.id}
                                                        className="flex items-baseline justify-between gap-3 border-b border-slate-50 py-1 text-xs last:border-b-0"
                                                    >
                                                        <span className="min-w-0 truncate text-slate-700">
                                                            {task.name}
                                                        </span>
                                                        <span className="shrink-0 text-right font-medium text-slate-800 tabular-nums">
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
                                        ) : null}
                                    </div>
                                );
                            })}
                        </div>
                    )}
                </div>

                <DialogFooter className="shrink-0 gap-2 border-t border-slate-200 bg-slate-50/90 px-5 py-3 sm:flex-col sm:items-stretch sm:px-6">
                    <p
                        className={cn(
                            'text-left text-xs',
                            ready
                                ? 'font-medium text-emerald-800'
                                : 'text-amber-800',
                        )}
                    >
                        {ready
                            ? `✓ All ${testTotal} tests are encoded and ready to complete.`
                            : `${remaining} test${remaining === 1 ? '' : 's'} still need encoding.`}
                    </p>
                    <div className="flex flex-col-reverse gap-2 sm:flex-row sm:justify-end">
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
                            {processing
                                ? 'Completing…'
                                : 'Confirm & Complete Job Order →'}
                        </Button>
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
