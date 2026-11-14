import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    items: string[];
    jobReference?: string | null;
};

function splitGap(item: string): { label: string; reason: string | null } {
    const match = item.trim().match(/^(.+?)\s+\(([^)]+)\)\s*$/);
    if (!match) {
        return { label: item, reason: null };
    }
    return { label: match[1], reason: match[2] };
}

export default function ResultSheetNotReadyDialog({
    open,
    onOpenChange,
    items,
    jobReference = null,
}: Props) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[82vh] flex-col gap-0 overflow-hidden p-0 sm:max-w-lg">
                <DialogHeader className="shrink-0 space-y-1.5 border-b border-slate-200 px-5 py-4 text-left sm:px-6">
                    <DialogTitle className="font-heading text-base text-nppc-900 sm:text-lg">
                        Result sheet not ready
                    </DialogTitle>
                    {jobReference ? (
                        <p className="font-mono text-xs text-slate-500">
                            JO #{jobReference.replace(/^JO\s*#?/i, '')}
                        </p>
                    ) : null}
                    <DialogDescription className="text-sm text-slate-600">
                        Finish encoding these items, then try Complete again.
                    </DialogDescription>
                </DialogHeader>

                <div className="min-h-0 flex-1 overflow-y-auto px-5 py-3 sm:px-6">
                    {items.length === 0 ? (
                        <p className="text-sm text-slate-500">
                            Nothing listed.
                        </p>
                    ) : (
                        <ul className="divide-y divide-slate-100">
                            {items.map((item) => {
                                const { label, reason } = splitGap(item);
                                return (
                                    <li
                                        key={item}
                                        className="flex items-baseline justify-between gap-4 py-2 text-sm"
                                    >
                                        <span className="min-w-0 truncate text-slate-700">
                                            {label}
                                        </span>
                                        {reason ? (
                                            <span className="shrink-0 text-right text-amber-800">
                                                {reason}
                                            </span>
                                        ) : null}
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </div>

                <DialogFooter className="shrink-0 border-t border-slate-200 px-5 py-3 sm:justify-end sm:px-6">
                    <Button
                        type="button"
                        className="bg-nppc-900 text-white hover:bg-nppc-700"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
