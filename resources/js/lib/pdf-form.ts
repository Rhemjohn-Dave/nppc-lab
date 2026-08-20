import {
    PDFCheckBox,
    PDFDocument,
    PDFDropdown,
    PDFTextField,
} from 'pdf-lib';

export type PdfFormValues = Record<string, string | boolean | null | undefined>;

export async function listPdfFieldNames(source: File | ArrayBuffer): Promise<string[]> {
    const bytes = source instanceof File ? await source.arrayBuffer() : source;
    const pdf = await PDFDocument.load(bytes, { ignoreEncryption: true });
    const form = pdf.getForm();

    return form.getFields().map((field) => field.getName());
}

export async function fillPdfForm(
    template: ArrayBuffer,
    values: PdfFormValues,
): Promise<Uint8Array> {
    const pdf = await PDFDocument.load(template, { ignoreEncryption: true });
    const form = pdf.getForm();

    for (const [name, value] of Object.entries(values)) {
        if (value === null || value === undefined || value === '') {
            continue;
        }

        let field;

        try {
            field = form.getField(name);
        } catch {
            continue;
        }

        if (field instanceof PDFTextField) {
            field.setText(String(value));
            continue;
        }

        if (field instanceof PDFCheckBox) {
            if (value === true || value === 'true' || value === 'Yes' || value === '1') {
                field.check();
            } else {
                field.uncheck();
            }

            continue;
        }

        if (field instanceof PDFDropdown) {
            try {
                field.select(String(value));
            } catch {
                // Keep the default option when the value is not in the list.
            }
        }
    }

    try {
        form.updateFieldAppearances();
        form.flatten();
    } catch {
        // Some official PDFs cannot flatten; the filled fields still print.
    }

    return pdf.save();
}

export function requiredResultFieldNames(testCount: number): string[] {
    const names: string[] = [];

    for (let slot = 1; slot <= testCount; slot += 1) {
        names.push(`test_${slot}_result`);
    }

    return names;
}
