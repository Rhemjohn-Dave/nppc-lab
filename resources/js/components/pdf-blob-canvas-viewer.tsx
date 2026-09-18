import * as pdfjs from 'pdfjs-dist';
import pdfWorker from 'pdfjs-dist/build/pdf.worker.min.mjs?url';
import { useEffect, useRef, useState } from 'react';
import {
    fitScaleToWidth,
    mmFromPdfPoints,
    pagePxSize,
    pdfjsViewportScaleForFpdiMm,
} from '@/lib/controlled-pdf-viewport';

pdfjs.GlobalWorkerOptions.workerSrc = pdfWorker;

type Props = {
    blob: Blob;
    title?: string;
    className?: string;
    /** Optional FPDI page width in mm (page 1). When set, raster is stretched to this basis. */
    pageWidthMm?: number | null;
    pageHeightMm?: number | null;
};

/**
 * Fit-to-width multi-page PDF viewer using pdf.js canvases.
 * Avoids browser iframe letterboxing that made controlled-form previews look tiny.
 */
export default function PdfBlobCanvasViewer({
    blob,
    title = 'PDF preview',
    className = '',
    pageWidthMm = null,
    pageHeightMm = null,
}: Props) {
    const scrollRef = useRef<HTMLDivElement | null>(null);
    const [pages, setPages] = useState<
        Array<{ canvas: HTMLCanvasElement; widthPx: number; heightPx: number }>
    >([]);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(true);
    const objectUrlRef = useRef<string | null>(null);

    useEffect(() => {
        let cancelled = false;
        const container = scrollRef.current;

        void (async () => {
            setLoading(true);
            setError(null);
            setPages([]);

            try {
                if (objectUrlRef.current) {
                    URL.revokeObjectURL(objectUrlRef.current);
                }

                const url = URL.createObjectURL(blob);
                objectUrlRef.current = url;
                const pdf = await pdfjs.getDocument({ url }).promise;

                if (cancelled) {
                    return;
                }

                const available = Math.max(
                    320,
                    (container?.clientWidth ?? 720) - 32,
                ); /* horizontal padding */
                const rendered: Array<{
                    canvas: HTMLCanvasElement;
                    widthPx: number;
                    heightPx: number;
                }> = [];

                for (let pageNo = 1; pageNo <= pdf.numPages; pageNo++) {
                    const pdfPage = await pdf.getPage(pageNo);
                    const unscaled = pdfPage.getViewport({ scale: 1 });
                    const widthMm =
                        pageNo === 1 && pageWidthMm && pageWidthMm > 0
                            ? pageWidthMm
                            : mmFromPdfPoints(unscaled.width);
                    const heightMm =
                        pageNo === 1 && pageHeightMm && pageHeightMm > 0
                            ? pageHeightMm
                            : mmFromPdfPoints(unscaled.height);

                    const basePx = pagePxSize(widthMm, heightMm, 1);
                    const zoom = fitScaleToWidth(available, basePx.widthPx, 0.2, 4);
                    const renderScale = pdfjsViewportScaleForFpdiMm(
                        widthMm,
                        unscaled.width,
                        zoom,
                    );
                    const viewport = pdfPage.getViewport({ scale: renderScale });
                    const canvas = document.createElement('canvas');
                    canvas.width = viewport.width;
                    canvas.height = viewport.height;
                    const context = canvas.getContext('2d');

                    if (!context) {
                        continue;
                    }

                    await pdfPage.render({ canvasContext: context, viewport, canvas }).promise;

                    const zoomPx = pagePxSize(widthMm, heightMm, zoom);
                    rendered.push({
                        canvas,
                        widthPx: zoomPx.widthPx,
                        heightPx: zoomPx.heightPx,
                    });
                }

                if (!cancelled) {
                    setPages(rendered);
                }
            } catch (cause: unknown) {
                if (!cancelled) {
                    setError(
                        cause instanceof Error
                            ? cause.message
                            : 'Could not render the PDF preview.',
                    );
                }
            } finally {
                if (!cancelled) {
                    setLoading(false);
                }
            }
        })();

        return () => {
            cancelled = true;
            if (objectUrlRef.current) {
                URL.revokeObjectURL(objectUrlRef.current);
                objectUrlRef.current = null;
            }
        };
    }, [blob, pageWidthMm, pageHeightMm]);

    return (
        <div
            ref={scrollRef}
            className={`min-h-[min(70vh,640px)] overflow-auto bg-slate-200 ${className}`}
            aria-label={title}
        >
            {loading && (
                <div className="flex min-h-[min(60vh,520px)] items-center justify-center text-sm text-slate-600">
                    Rendering preview…
                </div>
            )}
            {!loading && error && (
                <div className="flex min-h-[min(60vh,520px)] items-center justify-center px-6 text-center text-sm text-red-700">
                    {error}
                </div>
            )}
            {!loading && !error && (
                <div className="flex flex-col items-center gap-4 p-4">
                    {pages.map((page, index) => (
                        <PageCanvas
                            key={`page-${index + 1}`}
                            canvas={page.canvas}
                            widthPx={page.widthPx}
                            heightPx={page.heightPx}
                        />
                    ))}
                </div>
            )}
        </div>
    );
}

function PageCanvas({
    canvas,
    widthPx,
    heightPx,
}: {
    canvas: HTMLCanvasElement;
    widthPx: number;
    heightPx: number;
}) {
    const hostRef = useRef<HTMLDivElement | null>(null);

    useEffect(() => {
        const host = hostRef.current;
        if (!host) {
            return;
        }

        host.replaceChildren();
        canvas.className = 'block max-w-full bg-white shadow-md';
        canvas.style.width = `${widthPx}px`;
        canvas.style.height = `${heightPx}px`;
        host.appendChild(canvas);
    }, [canvas, widthPx, heightPx]);

    return <div ref={hostRef} className="shrink-0" />;
}
