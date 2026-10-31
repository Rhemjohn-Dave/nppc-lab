import { useEffect, useRef, useState, type ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import LoadingState from '@/components/ui/loading-state';

export type PdfPreviewSource = {
    blob: Blob;
    filename: string;
    title?: string;
    allowPrint?: boolean;
    /** Kept for callers; native iframe preview uses the PDF MediaBox. */
    pageWidthMm?: number | null;
    pageHeightMm?: number | null;
};

export type PdfPreviewApi = {
    reload: () => Promise<void>;
    runExport: (action: 'download' | 'print') => void;
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    title?: string;
    description?: string;
    load: () => Promise<PdfPreviewSource>;
    onRequestDownload?: () => void;
    onRequestPrint?: () => void;
    onReady?: (api: PdfPreviewApi) => void;
    toolbar?: ReactNode;
    footerExtra?: ReactNode;
    /** Replaces the PDF pane (e.g. analyst signatory form). Stays inside the dialog for focus. */
    bodyOverride?: ReactNode;
};

export default function PdfPreviewDialog({
    open,
    onOpenChange,
    title = 'PDF preview',
    description,
    load,
    onRequestDownload,
    onRequestPrint,
    onReady,
    toolbar,
    footerExtra,
    bodyOverride,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [filename, setFilename] = useState('document.pdf');
    const [resolvedTitle, setResolvedTitle] = useState(title);
    const [allowPrint, setAllowPrint] = useState(true);
    const iframeRef = useRef<HTMLIFrameElement | null>(null);
    const objectUrlRef = useRef<string | null>(null);
    const onReadyRef = useRef(onReady);
    onReadyRef.current = onReady;

    async function loadIntoState() {
        setLoading(true);
        setError(null);

        try {
            const source = await load();
            if (objectUrlRef.current) {
                URL.revokeObjectURL(objectUrlRef.current);
            }

            const createdUrl = URL.createObjectURL(source.blob);
            objectUrlRef.current = createdUrl;
            setObjectUrl(createdUrl);
            setFilename(source.filename);
            setResolvedTitle(source.title || title);
            setAllowPrint(source.allowPrint !== false);
        } catch (cause: unknown) {
            const message =
                cause instanceof Error
                    ? cause.message
                    : 'Could not prepare the PDF preview.';
            setError(message);
            setObjectUrl(null);
        } finally {
            setLoading(false);
        }
    }

    useEffect(() => {
        if (!open) {
            return;
        }

        let cancelled = false;

        void Promise.resolve().then(async () => {
            if (cancelled) {
                return;
            }

            await loadIntoState();
        });

        return () => {
            cancelled = true;
        };
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open, load, title]);

    useEffect(() => {
        return () => {
            if (objectUrlRef.current) {
                URL.revokeObjectURL(objectUrlRef.current);
                objectUrlRef.current = null;
            }
        };
    }, []);

    function download() {
        const url = objectUrlRef.current ?? objectUrl;
        if (!url) {
            return;
        }

        const link = document.createElement('a');
        link.href = url;
        link.download = filename;
        link.click();
    }

    function print() {
        // Use the visible native PDF iframe when present; otherwise a hidden one.
        const url = objectUrlRef.current ?? objectUrl;
        if (!url) {
            return;
        }

        const visible = iframeRef.current;
        if (visible?.contentWindow) {
            try {
                visible.contentWindow.focus();
                visible.contentWindow.print();
                return;
            } catch {
                // Fall through to a temporary frame.
            }
        }

        const frame = document.createElement('iframe');
        frame.style.position = 'fixed';
        frame.style.right = '0';
        frame.style.bottom = '0';
        frame.style.width = '0';
        frame.style.height = '0';
        frame.style.border = '0';
        frame.setAttribute('aria-hidden', 'true');
        document.body.appendChild(frame);
        frame.onload = () => {
            try {
                frame.contentWindow?.focus();
                frame.contentWindow?.print();
            } catch {
                // Ignore print abort / cross-origin edge cases.
            } finally {
                window.setTimeout(() => frame.remove(), 1000);
            }
        };
        frame.src = url;
    }

    useEffect(() => {
        onReadyRef.current?.({
            reload: loadIntoState,
            runExport: (action) => {
                if (action === 'download') {
                    download();
                } else {
                    print();
                }
            },
        });
        // Keep API callbacks current without re-firing parent state loops.
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [filename, objectUrl, loading]);

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="fixed top-[5vh] left-1/2 z-50 flex max-h-[90vh] w-[calc(100%-2rem)] max-w-6xl -translate-x-1/2 translate-y-0 flex-col gap-3 overflow-hidden sm:max-w-6xl data-[state=open]:zoom-in-95">
                <DialogHeader>
                    <DialogTitle>{resolvedTitle}</DialogTitle>
                    {description && (
                        <DialogDescription>{description}</DialogDescription>
                    )}
                </DialogHeader>

                {toolbar}

                <div className="relative min-h-0 flex-1 overflow-hidden rounded-lg border bg-slate-100">
                    {bodyOverride ? (
                        <div className="flex min-h-[min(60vh,520px)] items-center justify-center p-4">
                            {bodyOverride}
                        </div>
                    ) : (
                        <>
                            {loading && (
                                <LoadingState
                                    title="Preparing preview…"
                                    description="Generating PDF from the controlled form"
                                    size="lg"
                                    showDocumentPreview
                                    className="min-h-[50vh]"
                                />
                            )}
                            {!loading && error && (
                                <div className="flex h-full min-h-[50vh] items-center justify-center px-6 text-center text-sm text-red-700">
                                    {error}
                                </div>
                            )}
                            {!loading && !error && objectUrl && (
                                <iframe
                                    ref={iframeRef}
                                    title={resolvedTitle}
                                    src={objectUrl}
                                    className="h-[min(70vh,720px)] w-full bg-slate-200"
                                />
                            )}
                        </>
                    )}
                </div>

                <DialogFooter className="gap-2 sm:justify-between">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button
                            type="button"
                            variant="outline"
                            onClick={() => onOpenChange(false)}
                        >
                            Close
                        </Button>
                        {footerExtra}
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {allowPrint && !bodyOverride && (
                            <>
                                <Button
                                    type="button"
                                    variant="outline"
                                    disabled={!objectUrl || loading}
                                    onClick={() =>
                                        onRequestPrint ? onRequestPrint() : print()
                                    }
                                >
                                    Print
                                </Button>
                                <Button
                                    type="button"
                                    className="bg-nppc-900 hover:bg-nppc-700"
                                    disabled={!objectUrl || loading}
                                    onClick={() =>
                                        onRequestDownload
                                            ? onRequestDownload()
                                            : download()
                                    }
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
