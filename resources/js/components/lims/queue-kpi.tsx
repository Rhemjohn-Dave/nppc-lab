import { cn } from '@/lib/utils';

type KpiTone = 'default' | 'warning' | 'success' | 'info';

type Props = {
    label: string;
    value: number | string;
    hint?: string;
    tone?: KpiTone;
    emphasize?: boolean;
};

/**
 * Dense queue/admin KPI tile shared across LIMS workspace pages.
 */
export default function QueueKpi({
    label,
    value,
    hint,
    tone = 'default',
    emphasize = false,
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col justify-between rounded-xl border border-slate-200 bg-white p-4 shadow-xs',
                emphasize &&
                    tone === 'warning' &&
                    'border-2 border-amber-300 bg-gradient-to-b from-amber-50/40 to-white',
                tone === 'success' &&
                    'border-emerald-200 bg-gradient-to-b from-emerald-50/30 to-white',
                tone === 'info' &&
                    'border-sky-200 bg-gradient-to-b from-sky-50/30 to-white',
            )}
        >
            <div className="flex items-center justify-between gap-2">
                <span
                    className={cn(
                        'text-[10px] font-semibold tracking-wider uppercase',
                        emphasize && tone === 'warning'
                            ? 'text-amber-900'
                            : tone === 'success'
                              ? 'text-emerald-800'
                              : tone === 'info'
                                ? 'text-sky-800'
                                : 'text-slate-500',
                    )}
                >
                    {label}
                </span>
                {emphasize && tone === 'warning' ? (
                    <span className="size-1.5 animate-pulse rounded-full bg-amber-500" />
                ) : null}
            </div>
            <div className="mt-2 flex items-baseline gap-2">
                <span
                    className={cn(
                        'font-heading text-2xl font-extrabold tracking-tight tabular-nums',
                        typeof value === 'string' && value.length > 8 && 'text-xl',
                        emphasize && tone === 'warning'
                            ? 'text-amber-700'
                            : tone === 'success'
                              ? 'text-emerald-700'
                              : tone === 'info'
                                ? 'text-sky-700'
                                : 'text-slate-900',
                    )}
                >
                    {value}
                </span>
                {hint ? (
                    <span className="text-xs text-slate-500">{hint}</span>
                ) : null}
            </div>
        </div>
    );
}
