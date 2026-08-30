import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { DashboardNeedsAttention } from './types';

type Props = {
    data: DashboardNeedsAttention;
    compact?: boolean;
};

export default function NeedsAttentionPanel({
    data,
    compact = false,
}: Props) {
    const hasItems = data.items.length > 0;

    if (!hasItems) {
        return (
            <section
                className={cn(
                    'flex items-center gap-3 rounded-xl border border-emerald-200/80 bg-emerald-50/50 shadow-sm',
                    compact ? 'px-4 py-2.5' : 'px-4 py-3',
                )}
                aria-labelledby="needs-attention-heading"
            >
                <CheckCircle2
                    className="size-5 shrink-0 text-emerald-700"
                    aria-hidden="true"
                />
                <div>
                    <h2
                        id="needs-attention-heading"
                        className="text-sm font-semibold text-emerald-950"
                    >
                        All clear
                    </h2>
                    <p className="text-sm text-emerald-900/80">{data.summary}</p>
                </div>
            </section>
        );
    }

    return (
        <section
            className={cn(
                'rounded-xl border border-amber-200/90 bg-amber-50/40 shadow-sm',
                compact ? 'px-4 py-3' : 'border-amber-300/80 bg-gradient-to-br from-amber-50 to-white p-5',
            )}
            aria-labelledby="needs-attention-heading"
        >
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                    <AlertTriangle
                        className="size-4 shrink-0 text-amber-700"
                        aria-hidden="true"
                    />
                    <h2
                        id="needs-attention-heading"
                        className="text-sm font-semibold text-amber-950"
                    >
                        {data.title}
                    </h2>
                    <span className="rounded-full bg-amber-200/80 px-2 py-0.5 text-xs font-semibold text-amber-950">
                        {data.items.length}
                    </span>
                </div>
                {data.action && (
                    <Button
                        asChild
                        size="sm"
                        className="shrink-0 bg-[#1A3694] hover:bg-[#365BB0]"
                    >
                        <Link href={data.action.href}>{data.action.label}</Link>
                    </Button>
                )}
            </div>
            <ul className={cn('space-y-1', compact ? 'mt-2' : 'mt-3')}>
                {data.items.map((item, index) => (
                    <li
                        key={`${item.text}-${index}`}
                        className="flex items-start gap-2 text-sm text-slate-800"
                    >
                        <span
                            className="mt-1.5 size-1.5 shrink-0 rounded-full bg-amber-600"
                            aria-hidden="true"
                        />
                        {item.href ? (
                            <Link
                                href={item.href}
                                className="hover:text-[#1A3694] hover:underline"
                            >
                                {item.text}
                            </Link>
                        ) : (
                            item.text
                        )}
                    </li>
                ))}
            </ul>
        </section>
    );
}
