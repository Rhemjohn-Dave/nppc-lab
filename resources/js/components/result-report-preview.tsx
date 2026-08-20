import { useCallback } from 'react';
import PdfPreviewDialog from '@/components/pdf-preview-dialog';
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
};

type Props = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    reportUrl: string | null;
};

async function loadResultReport(reportUrl: string) {
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
        // Flat official sheets are filled on the server and returned as pdf_url.
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
    };
}

export default function ResultReportPreview({
    open,
    onOpenChange,
    reportUrl,
}: Props) {
    const load = useCallback(() => {
        if (!reportUrl) {
            return Promise.reject(new Error('No report selected.'));
        }

        return loadResultReport(reportUrl);
    }, [reportUrl]);

    return (
        <PdfPreviewDialog
            open={open && Boolean(reportUrl)}
            onOpenChange={onOpenChange}
            title="Result report"
            description={
                'Review the filled form. Print and download unlock after Head releases the results.'
            }
            load={load}
        />
    );
}
