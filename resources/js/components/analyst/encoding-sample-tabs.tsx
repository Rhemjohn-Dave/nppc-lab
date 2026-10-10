import type {
    AnalystTask,
    SampleBreakdownRow,
} from '@/components/analyst/types';
import {
    actionableTasks,
    canWorkTask,
    hasEncodedResult,
} from '@/components/analyst/types';
import { cn } from '@/lib/utils';

type Props = {
    groups: SampleBreakdownRow[];
    activeSampleId: number | null;
    onFocusTask: (task: AnalystTask) => void;
};

function chipLabel(group: SampleBreakdownRow): string {
    const control = group.control_number?.trim();
    const name = group.label.trim();
    if (control && name && !name.startsWith(control)) {
        return `${control} · ${name}`;
    }
    return control || name || 'Sample';
}

function focusTaskForSample(group: SampleBreakdownRow): AnalystTask | null {
    const open = actionableTasks(group.tasks);
    return (
        open.find((t) => t.status === 'returned') ??
        open[0] ??
        group.tasks.find((t) => canWorkTask(t)) ??
        group.tasks[0] ??
        null
    );
}

function encodedCount(group: SampleBreakdownRow): number {
    return group.tasks.filter((t) => hasEncodedResult(t)).length;
}

export default function EncodingSampleTabs({
    groups,
    activeSampleId,
    onFocusTask,
}: Props) {
    if (groups.length < 2) {
        return null;
    }

    const samplesFullyEncoded = groups.filter((g) => {
        if (g.total <= 0) {
            return false;
        }
        return encodedCount(g) >= g.total || g.done >= g.total;
    }).length;

    return (
        <div className="flex min-h-11 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 py-1.5 sm:px-4">
            <span className="shrink-0 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                Samples ({samplesFullyEncoded}/{groups.length})
            </span>
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto overflow-y-hidden py-0.5">
                {groups.map((group, index) => {
                    const active =
                        activeSampleId == null
                            ? group.sample_id == null
                            : group.sample_id === activeSampleId;
                    const complete =
                        group.total > 0 && group.done >= group.total;
                    const encoded = encodedCount(group);
                    const partial = !complete && encoded > 0;
                    const label = chipLabel(group);

                    return (
                        <button
                            key={group.key}
                            type="button"
                            title={label}
                            onClick={() => {
                                const task = focusTaskForSample(group);
                                if (task) {
                                    onFocusTask(task);
                                }
                            }}
                            className={cn(
                                'flex h-8 max-w-[18rem] shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-0.5 text-xs transition',
                                active
                                    ? 'border-nppc-500 bg-nppc-50 font-semibold text-nppc-900 shadow-xs ring-1 ring-inset ring-nppc-500/20'
                                    : 'border-slate-200 bg-white font-medium text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            <span
                                className={cn(
                                    'flex size-4 shrink-0 items-center justify-center rounded-full text-[10px] font-bold',
                                    active
                                        ? 'bg-nppc-900 text-white'
                                        : complete
                                          ? 'bg-emerald-500 text-white'
                                          : partial
                                            ? 'bg-sky-500 text-white'
                                            : 'bg-slate-200 text-slate-600',
                                )}
                            >
                                {complete && !active ? '✓' : index + 1}
                            </span>
                            <span className="max-w-[11rem] truncate">
                                {label}
                            </span>
                            <span
                                className={cn(
                                    'shrink-0 rounded px-1.5 py-px text-[9px] font-bold tracking-wide uppercase',
                                    active
                                        ? 'bg-nppc-900 text-white'
                                        : complete
                                          ? 'bg-emerald-50 text-emerald-800'
                                          : partial
                                            ? 'bg-sky-50 text-sky-800'
                                            : 'bg-slate-100 text-slate-500',
                                )}
                            >
                                {active
                                    ? 'ACTIVE'
                                    : complete
                                      ? 'Done'
                                      : `${encoded}/${group.total}`}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
