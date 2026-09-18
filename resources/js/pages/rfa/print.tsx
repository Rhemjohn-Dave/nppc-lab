import { Head } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import LoadingState from '@/components/ui/loading-state';
import { markPrintedThisSession } from '@/lib/receiving-workflow';

type Props = {
    jobOrder: {
        id: number;
        reference_no: string;
    };
    pdfUrl: string;
    copies?: number;
    copyLabels?: string[];
    showResults?: boolean;
};

export default function RfaPrint({
    jobOrder,
    pdfUrl,
    copies = 1,
    copyLabels = [],
    showResults = false,
}: Props) {
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState<string | null>(null);
    const [objectUrl, setObjectUrl] = useState<string | null>(null);
    const iframeRef = useRef<HTMLIFrameElement | null>(null);

    useEffect(() => {
        let cancelled = false;
        let createdUrl: string | null = null;

        void Promise.resolve().then(async () => {
            setLoading(true);
            setError(null);
            setObjectUrl(null);

            try {
                const response = await fetch(pdfUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/pdf' },
                });

                if (!response.ok) {
                    const message = await response.text();
                    throw new Error(
                        message.trim() ||
                            'Could not generate the controlled-form Job Order PDF.',
                    );
                }

                const blob = await response.blob();

                if (cancelled) {
                    return;
                }

                createdUrl = URL.createObjectURL(blob);

                if (cancelled) {
                    URL.revokeObjectURL(createdUrl);

                    return;
                }

                setObjectUrl(createdUrl);
                markPrintedThisSession(jobOrder.id);
            } catch (cause: unknown) {
                if (cancelled) {
                    return;
                }

                setError(
                    cause instanceof Error
                        ? cause.message
                        : 'Could not prepare the Job Order PDF preview.',
                );
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
    }, [pdfUrl, jobOrder.id]);

    function print() {
        const frame = iframeRef.current;

        if (!frame?.contentWindow) {
            return;
        }

        frame.contentWindow.focus();
        frame.contentWindow.print();
    }

    function download() {
        if (!objectUrl) {
            return;
        }

        const link = document.createElement('a');
        link.href = objectUrl;
        link.download = `RFA-${jobOrder.reference_no}.pdf`;
        link.click();
    }

    return (
        <>
            <Head title={`Print ${jobOrder.reference_no}`} />
            <div className="flex min-h-screen flex-col bg-slate-100">
                <div className="flex flex-wrap items-center justify-between gap-2 border-b bg-white px-4 py-3 print:hidden">
                    <div>
                        <p className="text-sm font-medium text-[#1A3694]">
                            Print preview — {jobOrder.reference_no}
                        </p>
                        <p className="text-xs text-muted-foreground">
                            {showResults
                                ? 'Controlled Job Order form with results'
                                : 'Controlled Job Order (Request for Analysis) form'}
                            {copies > 1
                                ? ` · set ${copies} copies in the print dialog`
                                : ''}
                            {copyLabels.length > 0
                                ? ` · ${copyLabels.join(' / ')}`
                                : ''}
                        </p>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <Button
                            variant="outline"
                            onClick={() => window.history.back()}
                        >
                            Back
                        </Button>
                        <Button
                            variant="outline"
                            disabled={!objectUrl}
                            onClick={download}
                        >
                            Download
                        </Button>
                        <Button
                            className="bg-[#1A3694] hover:bg-[#365BB0]"
                            disabled={!objectUrl}
                            onClick={print}
                        >
                            Print
                        </Button>
                    </div>
                </div>

                <div className="flex flex-1 flex-col p-4 print:p-0">
                    {loading && (
                        <LoadingState
                            title="Preparing preview…"
                            description="Generating PDF from the controlled form"
                            size="lg"
                            showDocumentPreview
                            className="min-h-[70vh] rounded-xl border bg-white"
                        />
                    )}
                    {!loading && error && (
                        <div className="flex min-h-[70vh] items-center justify-center rounded-xl border bg-white px-6 text-center text-sm text-red-700">
                            {error}
                        </div>
                    )}
                    {!loading && !error && objectUrl && (
                        <iframe
                            ref={iframeRef}
                            title={`RFA ${jobOrder.reference_no}`}
                            src={objectUrl}
                            className="min-h-[calc(100vh-6rem)] w-full flex-1 rounded-xl border bg-white shadow print:min-h-screen print:rounded-none print:border-0 print:shadow-none"
                        />
                    )}
                </div>
            </div>
        </>
    );
}

RfaPrint.layout = null;
