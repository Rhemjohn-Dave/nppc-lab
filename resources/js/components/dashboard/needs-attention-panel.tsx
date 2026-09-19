import { Link } from '@inertiajs/react';
import { AlertTriangle, CheckCircle2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { DashboardNeedsAttention } from './types';

type Props = {
    data: DashboardNeedsAttention;
    compact?: boolean;
    /** Banner layout for admin dashboard system-health strip */
    variant?: 'default' | 'banner';
};

export default function NeedsAttentionPanel({
    data,
    compact = false,
    variant = 'default',
}: Props) {
    const hasItems = data.items.length > 0;

    if (variant === 'banner') {
        if (!hasItems) {
            return (
                <section
                    className="flex items-start justify-between gap-3 rounded-lg border border-emerald-200 bg-emerald-50/70 px-3.5 py-3 shadow-sm"
                    aria-labelledby="needs-attention-heading"
                >
                    <div className="flex min-w-0 items-start gap-3">
                        <div className="flex size-7 shrink-0 items-center justify-center rounded-full border border-emerald-200 bg-emerald-100 text-emerald-700">
                            <CheckCircle2 className="size-4" aria-hidden="true" />
                        </div>
                        <div className="min-w-0">
                            <h2
                                id="needs-attention-heading"
                                className="text-xs font-semibold text-emerald-900"
                            >
                                All clear — document control is nominal
                            </h2>
                            <p className="mt-0.5 text-[11px] text-emerald-800/90">
                                {data.summary}
                            </p>
                        </div>
                    </div>
                    {data.action ? (
                        <Button
                            asChild
                            size="sm"
                            variant="outline"
                            className="h-7 shrink-0 border-emerald-200 bg-white text-xs text-emerald-900 hover:bg-emerald-50"
                        >
                            <Link href={data.action.href}>{data.action.label}</Link>
                        </Button>
                    ) : null}
                </section>
            );
        }

        return (
            <section
                className="flex flex-col gap-2 rounded-lg border border-amber-200 bg-amber-50/70 px-3.5 py-3 shadow-sm sm:flex-row sm:items-start sm:justify-between"
                aria-labelledby="needs-attention-heading"
            >
                <div className="flex min-w-0 items-start gap-3">
                    <div className="flex size-7 shrink-0 items-center justify-center rounded-full border border-amber-200 bg-amber-100 text-amber-800">
                        <AlertTriangle className="size-4" aria-hidden="true" />
                    </div>
                    <div className="min-w-0">
                        <h2
                            id="needs-attention-heading"
                            className="text-xs font-semibold text-amber-950"
                        >
                            {data.title}
                        </h2>
                        <p className="mt-0.5 text-[11px] text-amber-900/90">
                            {data.summary}
                        </p>
                        <ul className="mt-1.5 space-y-0.5">
                            {data.items.map((item, index) => (
                                <li
                                    key={`${item.text}-${index}`}
                                    className="text-[11px] text-amber-950"
                                >
                                    {item.href ? (
                                        <Link
                                            href={item.href}
                                            className="hover:underline"
                                        >
                                            {item.text}
                                        </Link>
                                    ) : (
                                        item.text
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                </div>
                {data.action ? (
                    <Button
                        asChild
                        size="sm"
                        className="h-7 shrink-0 bg-[#1A3694] text-xs hover:bg-[#365BB0]"
                    >
                        <Link href={data.action.href}>{data.action.label}</Link>
                    </Button>
                ) : null}
            </section>
        );
    }

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
                compact
                    ? 'px-4 py-2.5'
                    : 'border-amber-300/80 bg-gradient-to-br from-amber-50 to-white p-5',
            )}
            aria-labelledby="needs-attention-heading"
        >
            <div className="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
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
                        className="w-full shrink-0 bg-[#1A3694] hover:bg-[#365BB0] sm:w-auto"
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
