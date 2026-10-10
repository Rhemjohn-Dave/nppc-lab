import { cn } from '@/lib/utils';

export default function JoReferenceChip({
    referenceNo,
    emphasize = false,
}: {
    referenceNo: string;
    emphasize?: boolean;
}) {
    return (
        <span
            className={cn(
                'rounded border px-2 py-0.5 font-mono text-xs font-bold',
                emphasize
                    ? 'border-amber-200 bg-amber-100/70 text-amber-900'
                    : 'border-slate-200 bg-slate-100 text-slate-900',
            )}
        >
            JO #{referenceNo}
        </span>
    );
}
