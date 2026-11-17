import type {
    AnalystTask,
    ConsolidationSample,
    SampleBreakdownRow,
    SampleSummary,
} from '@/components/analyst/types';
import {
    actionableTasks,
    canWorkTask,
    hasEncodedResult,
} from '@/components/analyst/types';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { ArrowRight } from 'lucide-react';

type Props = {
    groups: SampleBreakdownRow[];
    jobSamples?: SampleSummary[];
    consolidationSamples?: ConsolidationSample[];
    onOpenSample: (task: AnalystTask) => void;
};

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

function sampleStatus(
    group: SampleBreakdownRow,
    jobSample: SampleSummary | undefined,
    consolidationSample: ConsolidationSample | undefined,
): { label: string; className: string } {
    if (jobSample?.released || consolidationSample?.released) {
        return {
            label: 'Released',
            className: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        };
    }
    if (jobSample?.submitted_for_review || consolidationSample?.submitted) {
        return {
            label: 'Submitted',
            className: 'border-amber-200 bg-amber-50 text-amber-900',
        };
    }
    if (group.total > 0 && group.done >= group.total) {
        return {
            label: 'Done',
            className: 'border-emerald-200 bg-emerald-50 text-emerald-900',
        };
    }
    if (encodedCount(group) > 0) {
        return {
            label: 'Encoding',
            className: 'border-sky-200 bg-sky-50 text-sky-900',
        };
    }
    return {
        label: 'Encoding',
        className: 'border-slate-200 bg-slate-50 text-slate-600',
    };
}

export default function EncodingSampleTable({
    groups,
    jobSamples = [],
    consolidationSamples = [],
    onOpenSample,
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
        <div className="flex min-h-0 flex-1 flex-col overflow-hidden px-3 py-3 sm:px-4 sm:py-3.5">
            <div className="mb-3 flex items-end justify-between gap-2">
                <div>
                    <h2 className="font-heading text-sm font-semibold text-nppc-900">
                        Samples
                    </h2>
                    <p className="text-[11px] text-slate-500">
                        Open a sample to encode its assays.
                    </p>
                </div>
                <p className="text-[11px] font-medium text-slate-500">
                    {samplesFullyEncoded}/{groups.length} ready
                </p>
            </div>

            <div className="min-h-0 flex-1 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
                <div className="h-full overflow-x-auto overflow-y-auto">
                    <table className="w-full min-w-[40rem] border-collapse text-left text-sm">
                        <thead className="sticky top-0 z-10 bg-slate-50/90 backdrop-blur-sm">
                            <tr className="border-b border-slate-200">
                                <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    #
                                </th>
                                <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    Control no.
                                </th>
                                <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    Sample
                                </th>
                                <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    Progress
                                </th>
                                <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-slate-500 uppercase">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-200/80">
                            {groups.map((group, index) => {
                                const jobSample = jobSamples.find((s) =>
                                    group.sample_id == null
                                        ? s.id == null
                                        : Number(s.id) ===
                                          Number(group.sample_id),
                                );
                                const consolidationSample =
                                    consolidationSamples.find(
                                        (s) =>
                                            group.sample_id != null &&
                                            Number(s.id) ===
                                                Number(group.sample_id),
                                    );
                                const encoded = encodedCount(group);
                                const status = sampleStatus(
                                    group,
                                    jobSample,
                                    consolidationSample,
                                );
                                const focus = focusTaskForSample(group);
                                const control =
                                    group.control_number?.trim() ||
                                    jobSample?.control_number?.trim() ||
                                    '—';
                                const matrix = jobSample?.matrix?.trim();

                                return (
                                    <tr
                                        key={group.key}
                                        className="hover:bg-slate-50"
                                    >
                                        <td className="px-4 py-4 align-top tabular-nums text-slate-500">
                                            {index + 1}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <span className="rounded border border-slate-200 bg-slate-50 px-1.5 py-0.5 font-mono text-xs font-bold text-slate-900">
                                                {control}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="font-semibold text-slate-900">
                                                {group.label}
                                            </p>
                                            {matrix ? (
                                                <p className="mt-0.5 text-[11px] text-slate-500">
                                                    {matrix}
                                                </p>
                                            ) : null}
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <p className="tabular-nums text-sm font-medium text-slate-800">
                                                {encoded}/{group.total}
                                            </p>
                                            <p className="text-[11px] text-slate-500">
                                                encoded
                                            </p>
                                        </td>
                                        <td className="px-4 py-4 align-top">
                                            <span
                                                className={cn(
                                                    'inline-flex rounded border px-1.5 py-0.5 text-[10px] font-semibold',
                                                    status.className,
                                                )}
                                            >
                                                {status.label}
                                            </span>
                                        </td>
                                        <td className="px-4 py-4 text-right align-top">
                                            <Button
                                                type="button"
                                                size="sm"
                                                className="gap-1.5 bg-nppc-900 text-xs shadow-xs hover:bg-nppc-700"
                                                disabled={!focus}
                                                onClick={() => {
                                                    if (focus) {
                                                        onOpenSample(focus);
                                                    }
                                                }}
                                            >
                                                Open sample
                                                <ArrowRight className="size-3" />
                                            </Button>
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    );
}
