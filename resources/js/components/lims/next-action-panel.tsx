import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tone = 'neutral' | 'emphasis' | 'warning';

type Props = {
    title: string;
    description?: string | null;
    actions?: ReactNode;
    tone?: Tone;
    className?: string;
    label?: string;
};

const toneClass: Record<Tone, string> = {
    neutral: 'border-slate-200 bg-[#f8fafc]',
    emphasis: 'border-emerald-200 bg-emerald-50/70',
    warning: 'border-amber-200 bg-amber-50',
};

/**
 * Compact next-action strip for operational JO screens.
 */
export default function NextActionPanel({
    title,
    description,
    actions,
    tone = 'neutral',
    className,
    label = 'Next action',
}: Props) {
    return (
        <div
            className={cn(
                'flex flex-col gap-3 rounded-xl border px-3 py-2.5 sm:flex-row sm:items-center sm:justify-between',
                toneClass[tone],
                className,
            )}
        >
            <div className="min-w-0">
                <p className="text-[10px] font-semibold tracking-wide text-[#365BB0] uppercase">
                    {label}
                </p>
                <p className="text-sm font-semibold text-slate-900">{title}</p>
                {description ? (
                    <p className="mt-0.5 text-xs text-slate-600">{description}</p>
                ) : null}
            </div>
            {actions ? (
                <div className="flex shrink-0 flex-wrap items-center gap-2">
                    {actions}
                </div>
            ) : null}
        </div>
    );
}
