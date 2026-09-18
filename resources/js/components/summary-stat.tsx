import type { ReactNode } from 'react';
import { cn } from '@/lib/utils';

type Tone = 'default' | 'success' | 'warning' | 'info';

const tones: Record<Tone, string> = {
    default: 'from-white to-[#e8eef8]/60',
    success: 'from-white to-emerald-50/70',
    warning: 'from-white to-amber-50/70',
    info: 'from-white to-sky-50/70',
};

const valueTones: Record<Tone, string> = {
    default: 'text-[#1A3694]',
    success: 'text-emerald-800',
    warning: 'text-amber-800',
    info: 'text-sky-800',
};

type Props = {
    label: string;
    value: ReactNode;
    tone?: Tone;
};

export default function SummaryStat({
    label,
    value,
    tone = 'default',
}: Props) {
    return (
        <div
            className={cn(
                'flex items-baseline justify-between gap-3 rounded-xl border bg-gradient-to-br p-3',
                tones[tone],
            )}
        >
            <p className="text-xs text-muted-foreground">{label}</p>
            <p
                className={cn(
                    'font-heading text-2xl font-semibold tabular-nums',
                    valueTones[tone],
                )}
            >
                {value}
            </p>
        </div>
    );
}
