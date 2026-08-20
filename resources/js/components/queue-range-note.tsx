type Props = {
    from?: number | null;
    to?: number | null;
    total: number;
    suffix: string;
};

export default function QueueRangeNote({ from, to, total, suffix }: Props) {
    return (
        <div className="rounded-xl border bg-[#f8fafc] px-4 py-3 text-sm text-slate-600">
            Showing{' '}
            <span className="font-medium text-slate-900">
                {from ?? 0}-{to ?? 0}
            </span>{' '}
            of{' '}
            <span className="font-medium text-slate-900">{total}</span>{' '}
            {suffix}
        </div>
    );
}
