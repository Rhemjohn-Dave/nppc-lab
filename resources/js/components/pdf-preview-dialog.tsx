import { useEffect, useRef, useState, type ReactNode } from 'react';
import PdfBlobCanvasViewer from '@/components/pdf-blob-canvas-viewer';
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
    /** Optional FPDI page metrics so the canvas viewer matches designer/fill. */
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
    footerExtra,
    bodyOverride,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const [blob, setBlob] = useState<Blob | null>(null);
    const [filename, setFilename] = useState('document.pdf');
    const [resolvedTitle, setResolvedTitle] = useState(title);
    const [allowPrint, setAllowPrint] = useState(true);
    const [pageWidthMm, setPageWidthMm] = useState<number | null>(null);
    const [pageHeightMm, setPageHeightMm] = useState<number | null>(null);
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
            setBlob(source.blob);
            setFilename(source.filename);
            setResolvedTitle(source.title || title);
            setAllowPrint(source.allowPrint !== false);
            setPageWidthMm(
                typeof source.pageWidthMm === 'number' ? source.pageWidthMm : null,
            );
            setPageHeightMm(
                typeof source.pageHeightMm === 'number' ? source.pageHeightMm : null,
            );
        } catch (cause: unknown) {
            const message =
                cause instanceof Error
                    ? cause.message
                    : 'Could not prepare the PDF preview.';
            setError(message);
            setObjectUrl(null);
            setBlob(null);
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
        // Prefer a hidden iframe of the original blob so print uses native PDF print.
        const url = objectUrlRef.current ?? objectUrl;
        if (!url) {
            return;
        }

        let frame = iframeRef.current;
        if (!frame) {
            frame = document.createElement('iframe');
            frame.style.position = 'fixed';
            frame.style.right = '0';
            frame.style.bottom = '0';
            frame.style.width = '0';
            frame.style.height = '0';
            frame.style.border = '0';
            frame.setAttribute('aria-hidden', 'true');
            document.body.appendChild(frame);
            iframeRef.current = frame;
        }

        frame.onload = () => {
            try {
                frame?.contentWindow?.focus();
                frame?.contentWindow?.print();
            } catch {
                // Ignore print abort / cross-origin edge cases.
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
                            {!loading && !error && blob && (
                                <PdfBlobCanvasViewer
                                    blob={blob}
                                    title={resolvedTitle}
                                    pageWidthMm={pageWidthMm}
                                    pageHeightMm={pageHeightMm}
                                    className="max-h-[min(70vh,720px)]"
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
                                    className="bg-[#1A3694] hover:bg-[#365BB0]"
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
