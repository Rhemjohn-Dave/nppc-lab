import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Spinner } from '@/components/ui/spinner';

export type PdfPreviewSource = {
    blob: Blob;
    filename: string;
    title?: string;
    allowPrint?: boolean;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description?: string;
    load: () => Promise<PdfPreviewSource>;
};

export default function PdfPreviewDialog({
    open,
    onOpenChange,
    title = 'PDF preview',
    description,
    load,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [filename, setFilename] = useState('document.pdf');
    const [resolvedTitle, setResolvedTitle] = useState(title);
    const [allowPrint, setAllowPrint] = useState(true);
    const iframeRef = useRef<HTMLIFrameElement | null>(null);

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;
        let createdUrl: string | null = null;

        void Promise.resolve().then(async () => {
            setLoading(true);
            setError(null);
            setObjectUrl(null);

            try {
                const source = await load();

                if (cancelled) {
                    return;
                }

                createdUrl = URL.createObjectURL(source.blob);

                if (cancelled) {
                    URL.revokeObjectURL(createdUrl);

                    return;
                }

                setObjectUrl(createdUrl);
                setFilename(source.filename);
                setResolvedTitle(source.title || title);
                setAllowPrint(source.allowPrint !== false);
            } catch (cause: unknown) {
                if (cancelled) {
                    return;
                }

                const message =
                    cause instanceof Error
                        ? cause.message
                        : 'Could not prepare the PDF preview.';
                setError(message);
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        });

        return () => {
            cancelled = true;

            if (createdUrl) {
                URL.revokeObjectURL(createdUrl);
            }
        };
    }, [open, load, title]);

    function download() {
        if (!objectUrl) {
            return;
        }

        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = filename;
        link.click();
    }

    function print() {
        const frame = iframeRef.current;

        if (!frame?.contentWindow) {
            return;
        }

        frame.contentWindow.focus();
        frame.contentWindow.print();
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="flex max-h-[95vh] w-[calc(100%-2rem)] max-w-5xl flex-col gap-3 overflow-hidden sm:max-w-5xl">
                <DialogHeader>
                    <DialogTitle>{resolvedTitle}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                <div className="min-h-[60vh] flex-1 overflow-hidden rounded-lg border bg-slate-50">
                    {loading && (
                        <div className="flex h-full min-h-[60vh] flex-col items-center justify-center gap-2 text-sm text-muted-foreground">
                            <Spinner className="size-6" />
                            Preparing preview…
                        </div>
                    )}
                    {!loading && error && (
                        <div className="flex h-full min-h-[60vh] items-center justify-center px-6 text-center text-sm text-red-700">
                            {error}
                        </div>
                    )}
                    {!loading && !error && objectUrl && (
                        <iframe
                            ref={iframeRef}
                            title={resolvedTitle}
                            src={
                                allowPrint
                                    ? objectUrl
                                    : `${objectUrl}#toolbar=0&navpanes=0`
                            }
                            className="h-[60vh] w-full bg-white"
                        />
                    )}
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <Button
                        type="button"
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Close
                    </Button>
                    <div className="flex flex-wrap gap-2">
                        {allowPrint && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!objectUrl}
                                    onClick={print}
                                >
                                    Print
                                </Button>
                                <Button
                                    type="button"
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
                                    disabled={!objectUrl}
                                    onClick={download}
                                >
                                    Download
                                </Button>
                            </>
                        )}
                    </div>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
