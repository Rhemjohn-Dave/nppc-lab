import type { AnalystTask } from '@/components/analyst/types';
import { hasEncodedResult } from '@/components/analyst/types';
import { cn } from '@/lib/utils';

type Props = {
    tasks: AnalystTask[];
    activeId: number | null;
    onFocusTask: (task: AnalystTask) => void;
};

function tabMeta(task: AnalystTask, active: boolean) {
    if (active) {
        return { label: 'ACTIVE', className: 'bg-nppc-900 text-white' };
    }
    if (task.status === 'completed') {
        return { label: 'Done', className: 'bg-emerald-50 text-emerald-800' };
    }
    if (task.status === 'returned') {
        return { label: 'Revise', className: 'bg-rose-50 text-rose-800' };
    }
    if (hasEncodedResult(task)) {
        return { label: 'Encoded', className: 'bg-sky-50 text-sky-800' };
    }
    return { label: 'Pending', className: 'bg-slate-100 text-slate-500' };
}

export default function EncodingAssayTabs({
    tasks,
    activeId,
    onFocusTask,
}: Props) {
    const done = tasks.filter((t) => t.status === 'completed').length;
    const encoded = tasks.filter((t) => hasEncodedResult(t)).length;

    return (
        <div className="flex min-h-11 shrink-0 items-center gap-2 border-b border-slate-200 bg-white px-3 py-1.5 sm:px-4">
            <span className="shrink-0 text-[10px] font-bold tracking-wider text-slate-400 uppercase">
                Tests ({encoded}/{tasks.length}
                {done > 0 ? ` · ${done} done` : ''})
            </span>
            <div className="flex min-w-0 flex-1 items-center gap-2 overflow-x-auto overflow-y-hidden py-0.5">
                {tasks.map((task, index) => {
                    const active = task.id === activeId;
                    const meta = tabMeta(task, active);
                    const encodedIdle =
                        !active &&
                        task.status !== 'completed' &&
                        hasEncodedResult(task);

                    return (
                        <button
                            key={task.id}
                            type="button"
                            onClick={() => onFocusTask(task)}
                            className={cn(
                                'flex h-8 shrink-0 items-center gap-1.5 rounded-md border px-2.5 py-0.5 text-xs transition',
                                active
                                    ? 'border-nppc-500 bg-nppc-50 font-semibold text-nppc-900 shadow-xs ring-1 ring-inset ring-nppc-500/20'
                                    : 'border-slate-200 bg-white font-medium text-slate-600 hover:bg-slate-50',
                            )}
                        >
                            <span
                                className={cn(
                                    'size-1.5 shrink-0 rounded-full',
                                    active
                                        ? 'animate-pulse bg-nppc-900'
                                        : task.status === 'completed'
                                          ? 'bg-emerald-500'
                                          : encodedIdle
                                            ? 'bg-sky-500'
                                            : 'bg-slate-300',
                                )}
                            />
                            <span className="max-w-[12rem] truncate">
                                {index + 1}. {task.name}
                            </span>
                            <span
                                className={cn(
                                    'rounded px-1.5 py-px text-[9px] font-bold tracking-wide uppercase',
                                    meta.className,
                                )}
                            >
                                {meta.label}
                            </span>
                        </button>
                    );
                })}
            </div>
        </div>
    );
}
