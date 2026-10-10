import { cn } from '@/lib/utils';
import {
    CheckCircle2,
    ClipboardList,
    FlaskConical,
    TriangleAlert,
} from 'lucide-react';
import type { LucideIcon } from 'lucide-react';

export type QueueSummaryTone =
    | 'default'
    | 'success'
    | 'warning'
    | 'info'
    | 'danger';

const iconByTone: Record<QueueSummaryTone, LucideIcon> = {
    default: ClipboardList,
    info: ClipboardList,
    warning: FlaskConical,
    danger: TriangleAlert,
    success: CheckCircle2,
};

const iconWrap: Record<QueueSummaryTone, string> = {
    default: 'bg-slate-100 text-slate-600',
    info: 'bg-nppc-50 text-nppc-700',
    warning: 'bg-amber-50 text-amber-600',
    danger: 'bg-rose-50 text-rose-600',
    success: 'bg-emerald-50 text-emerald-600',
};

const valueTones: Record<QueueSummaryTone, string> = {
    default: 'text-slate-900',
    info: 'text-nppc-900',
    warning: 'text-amber-600',
    danger: 'text-rose-600',
    success: 'text-emerald-600',
};

const barTones: Record<QueueSummaryTone, string> = {
    default: 'bg-slate-500',
    info: 'bg-nppc-900',
    warning: 'bg-amber-500',
    danger: 'bg-rose-500',
    success: 'bg-emerald-600',
};

const hoverBorder: Record<QueueSummaryTone, string> = {
    default: 'hover:border-slate-300',
    info: 'hover:border-blue-300',
    warning: 'hover:border-amber-300',
    danger: 'hover:border-rose-300',
    success: 'hover:border-emerald-300',
};

export type QueueSummaryCard = {
    id: string;
    label: string;
    value: number;
    unit: string;
    tone?: QueueSummaryTone;
    share?: number;
    badge?: string | null;
};

type Props = {
    cards: QueueSummaryCard[];
    activeId: string;
    onSelect: (id: string) => void;
    /** Grid class override, e.g. `sm:grid-cols-2 lg:grid-cols-5`. */
    className?: string;
    /** When false, cards are static summary tiles (no click / active ring). */
    interactive?: boolean;
};

/**
 * Analyst-style clickable queue KPI cards shared across LIMS queues.
 */
export default function QueueSummaryCards({
    cards,
    activeId,
    onSelect,
    className,
    interactive = true,
}: Props) {
    return (
        <div
            className={cn(
                'grid gap-4 sm:grid-cols-2 lg:grid-cols-4',
                className,
            )}
        >
            {cards.map((card) => {
                const active = interactive && activeId === card.id;
                const tone = card.tone ?? 'default';
                const Icon = iconByTone[tone];
                const share = Math.max(0, Math.min(100, card.share ?? 0));

                const body = (
                    <>
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0">
                                <div className="flex flex-wrap items-center gap-1.5">
                                    <span
                                        className={cn(
                                            'text-[11px] font-bold tracking-wider uppercase',
                                            tone === 'danger'
                                                ? 'text-rose-700'
                                                : 'text-slate-500',
                                        )}
                                    >
                                        {card.label}
                                    </span>
                                    {card.badge ? (
                                        <span className="rounded bg-rose-100 px-1.5 py-0.5 text-[9px] font-semibold text-rose-700 uppercase">
                                            {card.badge}
                                        </span>
                                    ) : null}
                                </div>
                                <p
                                    className={cn(
                                        'mt-1 font-heading text-2xl font-extrabold tracking-tight tabular-nums',
                                        valueTones[tone],
                                    )}
                                >
                                    {card.value}
                                </p>
                                <div className="mt-1 flex items-center gap-1.5">
                                    <span
                                        className={cn(
                                            'inline-block size-1.5 rounded-full',
                                            barTones[tone],
                                        )}
                                    />
                                    <span className="text-xs font-medium text-slate-500">
                                        {card.unit}
                                    </span>
                                </div>
                            </div>
                            <div
                                className={cn(
                                    'flex size-10 shrink-0 items-center justify-center rounded-lg',
                                    iconWrap[tone],
                                )}
                            >
                                <Icon className="size-4" />
                            </div>
                        </div>
                        <div className="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-slate-100">
                            <div
                                className={cn(
                                    'h-full rounded-full transition-all',
                                    barTones[tone],
                                )}
                                style={{ width: `${share}%` }}
                            />
                        </div>
                    </>
                );

                const shellClass = cn(
                    'group relative overflow-hidden rounded-xl border bg-white p-4 text-left shadow-xs',
                    interactive && 'transition',
                    interactive && hoverBorder[tone],
                    tone === 'danger' &&
                        'border-rose-200/70 bg-gradient-to-br from-white via-white to-rose-50/20',
                    active
                        ? 'border-nppc-900 ring-2 ring-nppc-900/20'
                        : 'border-slate-200/80',
                );

                if (!interactive) {
                    return (
                        <div key={card.id} className={shellClass}>
                            {body}
                        </div>
                    );
                }

                return (
                    <button
                        key={card.id}
                        type="button"
                        onClick={() => onSelect(card.id)}
                        className={shellClass}
                    >
                        {body}
                    </button>
                );
            })}
        </div>
    );
}
