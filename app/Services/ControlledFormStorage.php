<?php

namespace App\Services;

use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use setasign\Fpdi\Tcpdf\Fpdi;

class ControlledFormStorage
{
    public function storeUpload(
        ControlledForm $form,
        ControlledFormRevision $revision,
        UploadedFile $file,
    ): array {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'pdf');
        $dir = $this->revisionDirectory($form, $revision);
        $originalDir = $dir.'/original';
        $canonicalDir = $dir.'/canonical';

        Storage::disk('local')->makeDirectory($originalDir);
        Storage::disk('local')->makeDirectory($canonicalDir);

        $originalName = Str::uuid()->toString().'.'.$extension;
        $originalPath = $originalDir.'/'.$originalName;
        Storage::disk('local')->putFileAs($originalDir, $file, $originalName);

        $absoluteOriginal = Storage::disk('local')->path($originalPath);
        $mime = $file->getMimeType() ?: $file->getClientMimeType();

        if (in_array($extension, ['doc', 'docx'], true) || str_contains((string) $mime, 'wordprocessingml')) {
            $converter = app(DocxToPdfConverter::class);
            $converted = $converter->convert(
                $absoluteOriginal,
                Storage::disk('local')->path($canonicalDir),
            );
            $canonicalPath = $canonicalDir.'/'.basename($converted);
        } else {
            $canonicalName = Str::uuid()->toString().'.pdf';
            $canonicalPath = $canonicalDir.'/'.$canonicalName;
            Storage::disk('local')->put($canonicalPath, file_get_contents($absoluteOriginal) ?: '');
        }

        if (! Storage::disk('local')->exists($canonicalPath)) {
            throw new RuntimeException('The canonical PDF could not be stored.');
        }

        $canonicalAbsolute = Storage::disk('local')->path($canonicalPath);
        app(PdfCompatibilityNormalizer::class)->ensureCompatible($canonicalAbsolute);
        $meta = $this->inspectPdf($canonicalAbsolute);
        $hash = hash_file('sha256', $canonicalAbsolute) ?: null;

        return [
            'original_name' => $file->getClientOriginalName(),
            'original_path' => $originalPath,
            'canonical_pdf_path' => $canonicalPath,
            'original_mime' => $mime,
            'page_count' => $meta['page_count'],
            'page_width_mm' => $meta['width'],
            'page_height_mm' => $meta['height'],
            'sha256' => $hash,
        ];
    }

    public function storeGeneratedPdf(string $binary): string
    {
        $path = 'generated_documents/'.now()->format('Y').'/'.now()->format('m').'/'.Str::uuid()->toString().'.pdf';
        Storage::disk('local')->put($path, $binary);

        return $path;
    }

    /**
     * FPDI template metrics for page 1 — same source ControlledPdfFiller uses for AddPage.
     *
     * @return array{page_count: int, width_mm: float, height_mm: float, width: float, height: float}
     */
    public function pageMetrics(string $absolutePath): array
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        app(PdfCompatibilityNormalizer::class)->ensureCompatible($absolutePath);
        $pageCount = $pdf->setSourceFile($absolutePath);
        $size = $pdf->getTemplateSize($pdf->importPage(1));
        $width = (float) $size['width'];
        $height = (float) $size['height'];

        return [
            'page_count' => $pageCount,
            'width_mm' => $width,
            'height_mm' => $height,
            // Legacy keys kept for storeUpload / existing callers.
            'width' => $width,
            'height' => $height,
        ];
    }

    /**
     * @return array{page_count: int, width: float, height: float}
     */
    public function inspectPdf(string $absolutePath): array
    {
        $metrics = $this->pageMetrics($absolutePath);

        return [
            'page_count' => $metrics['page_count'],
            'width' => $metrics['width'],
            'height' => $metrics['height'],
        ];
    }

    /**
     * Re-read the canonical PDF and persist page_width_mm / page_height_mm when drift exceeds threshold.
     * Designer and fill must share these FPDI millimetre values.
     *
     * @return array{width_mm: float, height_mm: float, page_count: int}|null
     */
    public function syncRevisionPageMetrics(ControlledFormRevision $revision, float $driftMm = 0.5): ?array
    {
        if (! $revision->hasCanonicalPdf()) {
            return null;
        }

        $path = $revision->canonicalAbsolutePath();
        if (! is_file($path)) {
            return null;
        }

        $metrics = $this->pageMetrics($path);
        $storedWidth = $revision->page_width_mm !== null ? (float) $revision->page_width_mm : null;
        $storedHeight = $revision->page_height_mm !== null ? (float) $revision->page_height_mm : null;
        $storedCount = $revision->page_count !== null ? (int) $revision->page_count : null;

        $widthDrift = $storedWidth === null || abs($storedWidth - $metrics['width_mm']) > $driftMm;
        $heightDrift = $storedHeight === null || abs($storedHeight - $metrics['height_mm']) > $driftMm;
        $countDrift = $storedCount === null || $storedCount !== $metrics['page_count'];

        if ($widthDrift || $heightDrift || $countDrift) {
            $revision->page_width_mm = $metrics['width_mm'];
            $revision->page_height_mm = $metrics['height_mm'];
            $revision->page_count = $metrics['page_count'];
            $revision->save();
        }

        return [
            'width_mm' => $metrics['width_mm'],
            'height_mm' => $metrics['height_mm'],
            'page_count' => $metrics['page_count'],
        ];
    }

    public function revisionDirectory(ControlledForm $form, ControlledFormRevision $revision): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '-', $form->form_code) ?: 'form';
        $rev = preg_replace('/[^A-Za-z0-9._-]+/', '-', $revision->revision) ?: 'rev';

        return "controlled_forms/{$code}/REV-{$rev}";
    }
}
