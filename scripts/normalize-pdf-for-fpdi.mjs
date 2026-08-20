import { readFileSync, writeFileSync } from 'node:fs';
import { PDFDocument } from 'pdf-lib';

const input = process.argv[2];
const output = process.argv[3];

if (!input || !output) {
    console.error('Usage: node scripts/normalize-pdf-for-fpdi.mjs <input.pdf> <output.pdf>');
    process.exit(1);
}

const bytes = readFileSync(input);
const document = await PDFDocument.load(bytes, {
    ignoreEncryption: true,
    updateMetadata: false,
});

const saved = await document.save({
    useObjectStreams: false,
    addDefaultPage: false,
});

writeFileSync(output, saved);
