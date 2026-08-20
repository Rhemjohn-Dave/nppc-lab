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

type Card = {
    id: string;
    label: string;
    value: number;
    unit: string;
    tone?: Tone;
};

type Props = {
    cards: Card[];
    activeId: string;
    onSelect: (id: string) => void;
};

export default function AnalystSummaryCards({
    cards,
    activeId,
    onSelect,
}: Props) {
    return (
        <div className="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            {cards.map((card) => {
                const active = activeId === card.id;
                const tone = card.tone ?? 'default';

                return (
                    <button
                        key={card.id}
                        type="button"
                        onClick={() => onSelect(card.id)}
                        className={cn(
                            'rounded-xl border bg-gradient-to-br p-4 text-left transition',
                            tones[tone],
                            active
                                ? 'border-[#1A3694] ring-2 ring-[#1A3694]/25'
                                : 'hover:border-[#5282D3]',
                        )}
                    >
                        <p className="text-xs font-semibold tracking-wide text-slate-500 uppercase">
                            {card.label}
                        </p>
                        <p
                            className={cn(
                                'mt-1 font-heading text-3xl font-semibold',
                                valueTones[tone],
                            )}
                        >
                            {card.value}
                        </p>
                        <p className="mt-0.5 text-xs text-muted-foreground">
                            {card.unit}
                        </p>
                    </button>
                );
            })}
        </div>
    );
}
