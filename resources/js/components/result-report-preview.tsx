import { useCallback, useEffect, useRef, useState } from 'react';
import PdfPreviewDialog, {
    type PdfPreviewSource,
} from '@/components/pdf-preview-dialog';
import ResultSignatoryPanel, {
    type ResultSignatoryConfig,
    type ResultSignatoryRow,
} from '@/components/result-signatory-dialog';
import { fillPdfForm } from '@/lib/pdf-form';

type Manifest = {
    kind: 'combined' | 'individual' | 'waiting' | 'unavailable';
    filename: string;
    title: string;
    message?: string | null;
    can_preview: boolean;
    can_print?: boolean;
    values?: Record<string, string | boolean | null | undefined>;
    template_url?: string | null;
    pdf_url?: string | null;
    signatory?: ResultSignatoryConfig | null;
};

type LoadedReport = PdfPreviewSource & {
    signatory?: ResultSignatoryConfig | null;
};

function csrfToken(): string | null {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

export async function loadResultReport(reportUrl: string): Promise<LoadedReport> {
    const response = await fetch(reportUrl, {
        credentials: 'same-origin',
        headers: { Accept: 'application/json' },
    });

    if (!response.ok) {
        throw new Error('Could not load the result report.');
    }

    const manifest = (await response.json()) as Manifest;

    if (!manifest.can_preview) {
        throw new Error(
            manifest.message || 'This result report is not available yet.',
        );
    }

    if (manifest.kind === 'combined') {
        if (manifest.pdf_url) {
            const pdfResponse = await fetch(manifest.pdf_url, {
                credentials: 'same-origin',
            });

            if (!pdfResponse.ok) {
                throw new Error('Could not open the combined result form.');
            }

            return {
                blob: await pdfResponse.blob(),
                filename: manifest.filename,
                title: manifest.title,
                allowPrint: Boolean(manifest.can_print),
                signatory: manifest.signatory ?? null,
            };
        }

        if (!manifest.template_url) {
            throw new Error('The combined result form is missing.');
        }

        const templateResponse = await fetch(manifest.template_url, {
            credentials: 'same-origin',
        });

        if (!templateResponse.ok) {
            throw new Error('Could not open the official result form.');
        }

        const filled = await fillPdfForm(
            await templateResponse.arrayBuffer(),
            manifest.values ?? {},
        );

        return {
            blob: new Blob([Uint8Array.from(filled)], { type: 'application/pdf' }),
            filename: manifest.filename,
            title: manifest.title,
            allowPrint: Boolean(manifest.can_print),
            signatory: manifest.signatory ?? null,
        };
    }

    if (!manifest.pdf_url) {
        throw new Error('The result sheet is missing.');
    }

    const pdfResponse = await fetch(manifest.pdf_url, {
        credentials: 'same-origin',
    });

    if (!pdfResponse.ok) {
        throw new Error('Could not open the result sheet.');
    }

    return {
        blob: await pdfResponse.blob(),
        filename: manifest.filename,
        title: manifest.title,
        allowPrint: Boolean(manifest.can_print),
        signatory: manifest.signatory ?? null,
    };
}

export default function ResultReportPreview({
    open,
    onOpenChange,
    reportUrl,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reportUrl: string | null;
}) {
    const [signatory, setSignatory] = useState<ResultSignatoryConfig | null>(null);
    const [signatoryOpen, setSignatoryOpen] = useState(false);
    const [signatoryError, setSignatoryError] = useState<string | null>(null);
    const [signatorySaving, setSignatorySaving] = useState(false);
    const [allowPrint, setAllowPrint] = useState(false);
    const pendingActionRef = useRef<'download' | 'print' | 'reload' | null>(null);
    const reloadPreviewRef = useRef<(() => Promise<void>) | null>(null);
    const runExportRef = useRef<((action: 'download' | 'print') => void) | null>(null);

    const canEditSignatories = Boolean(
        signatory?.save_url || signatory?.can_edit,
    );

    const load = useCallback(async () => {
        if (!reportUrl) {
            throw new Error('No report selected.');
        }

        const source = await loadResultReport(reportUrl);
        setSignatory(source.signatory ?? null);
        setAllowPrint(Boolean(source.allowPrint));
        // Signatories are required at Send to Head; preview does not auto-prompt.
        setSignatoryOpen(false);

        return source;
    }, [reportUrl]);

    useEffect(() => {
        if (!open) {
            setSignatoryOpen(false);
            setSignatoryError(null);
            setSignatory(null);
            setAllowPrint(false);
            pendingActionRef.current = null;
        }
    }, [open]);

    async function saveSignatories(rows: ResultSignatoryRow[]) {
        if (!signatory?.save_url) {
            throw new Error('Signatory save URL is missing.');
        }

        const token = csrfToken();
        const response = await fetch(signatory.save_url, {
            method: 'PUT',
            credentials: 'same-origin',
            headers: {
                Accept: 'application/json',
                'Content-Type': 'application/json',
                ...(token ? { 'X-XSRF-TOKEN': token } : {}),
            },
            body: JSON.stringify({ signatories: rows }),
        });

        const payload = (await response.json().catch(() => null)) as {
            message?: string;
            errors?: Record<string, string[]>;
            signatory?: ResultSignatoryConfig;
        } | null;

        if (!response.ok) {
            const firstError = payload?.errors
                ? Object.values(payload.errors)[0]?.[0]
                : null;
            throw new Error(firstError || payload?.message || 'Could not save signatories.');
        }

        if (payload?.signatory) {
            setSignatory(payload.signatory);
        } else {
            setSignatory((current) =>
                current
                    ? { ...current, confirmed: true, signatories: rows }
                    : current,
            );
        }
    }

    async function confirmSignatories(rows: ResultSignatoryRow[]) {
        setSignatorySaving(true);
        setSignatoryError(null);

        try {
            await saveSignatories(rows);
            setSignatoryOpen(false);

            if (reloadPreviewRef.current) {
                await reloadPreviewRef.current();
            }

            const action = pendingActionRef.current;
            pendingActionRef.current = null;

            if (action === 'download' || action === 'print') {
                runExportRef.current?.(action);
            }
        } catch (cause: unknown) {
            setSignatoryError(
                cause instanceof Error ? cause.message : 'Could not save signatories.',
            );
        } finally {
            setSignatorySaving(false);
        }
    }

    function requestExport(action: 'download' | 'print') {
        // Analyst may confirm missing name/PRC at print; Head never enters them.
        if (canEditSignatories && signatory && !signatory.confirmed) {
            pendingActionRef.current = action;
            setSignatoryError(null);
            setSignatoryOpen(true);

            return;
        }

        runExportRef.current?.(action);
    }

    function requestEditSignatories() {
        if (!canEditSignatories) {
            return;
        }

        pendingActionRef.current = 'reload';
        setSignatoryError(null);
        setSignatoryOpen(true);
    }

    return (
        <PdfPreviewDialog
            open={open && Boolean(reportUrl)}
            onOpenChange={onOpenChange}
            title="Result report"
            description={
                allowPrint
                    ? canEditSignatories
                        ? 'Print uses the analyst name/PRC entered when this job was sent to Head. You can edit them before download if needed.'
                        : 'Print uses the analyst name/PRC entered when this job was sent to Head.'
                    : 'Review the filled form. Print and download unlock after Head releases the results.'
            }
            load={load}
            onRequestDownload={() => requestExport('download')}
            onRequestPrint={() => requestExport('print')}
            onReady={(api) => {
                reloadPreviewRef.current = api.reload;
                runExportRef.current = api.runExport;
            }}
            footerExtra={
                canEditSignatories &&
                signatory &&
                allowPrint &&
                !signatoryOpen ? (
                    <button
                        type="button"
                        className="text-sm text-[#1A3694] underline-offset-2 hover:underline"
                        onClick={requestEditSignatories}
                    >
                        {signatory.confirmed ? 'Edit signatories' : 'Enter signatories'}
                    </button>
                ) : null
            }
            bodyOverride={
                canEditSignatories && signatory && signatoryOpen ? (
                    <ResultSignatoryPanel
                        config={signatory}
                        submitting={signatorySaving}
                        error={signatoryError}
                        onCancel={() => {
                            setSignatoryOpen(false);
                            pendingActionRef.current = null;
                        }}
                        onConfirm={confirmSignatories}
                    />
                ) : null
            }
        />
    );
}
