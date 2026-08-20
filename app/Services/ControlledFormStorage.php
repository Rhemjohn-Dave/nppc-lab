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
     * @return array{page_count: int, width: float, height: float}
     */
    public function inspectPdf(string $absolutePath): array
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        app(PdfCompatibilityNormalizer::class)->ensureCompatible($absolutePath);
        $pageCount = $pdf->setSourceFile($absolutePath);
        $size = $pdf->getTemplateSize($pdf->importPage(1));

        return [
            'page_count' => $pageCount,
            'width' => (float) $size['width'],
            'height' => (float) $size['height'],
        ];
    }

    public function revisionDirectory(ControlledForm $form, ControlledFormRevision $revision): string
    {
        $code = preg_replace('/[^A-Za-z0-9._-]+/', '-', $form->form_code) ?: 'form';
        $rev = preg_replace('/[^A-Za-z0-9._-]+/', '-', $revision->revision) ?: 'rev';

        return "controlled_forms/{$code}/REV-{$rev}";
    }
}
