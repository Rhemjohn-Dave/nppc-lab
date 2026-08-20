<?php

namespace App\Services;

use RuntimeException;
use setasign\Fpdi\PdfParser\PdfParserException;
use setasign\Fpdi\Tcpdf\Fpdi;
use Symfony\Component\Process\Process;
use Throwable;

class PdfCompatibilityNormalizer
{
    public function isCompatible(string $absolutePath): bool
    {
        try {
            $this->probe($absolutePath);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    public function ensureCompatible(string $absolutePath): void
    {
        if ($this->isCompatible($absolutePath)) {
            return;
        }

        $rewritten = $this->rewrite($absolutePath);

        if (! $this->isCompatible($rewritten)) {
            throw new RuntimeException($this->unsupportedMessage());
        }

        if ($rewritten !== $absolutePath) {
            if (! copy($rewritten, $absolutePath)) {
                throw new RuntimeException('Could not replace the canonical PDF with an FPDI-compatible copy.');
            }
            @unlink($rewritten);
        }
    }

    public function rewrite(string $absolutePath): string
    {
        $tempDir = dirname($absolutePath);
        $out = $tempDir.DIRECTORY_SEPARATOR.uniqid('fpdi14_', true).'.pdf';

        foreach ([
            fn () => $this->pdfLib($absolutePath, $out),
            fn () => $this->ghostscript($absolutePath, $out),
            fn () => $this->qpdf($absolutePath, $out),
            fn () => $this->libreOffice($absolutePath, $out),
        ] as $attempt) {
            try {
                if ($attempt() && is_file($out) && filesize($out) > 0 && $this->isCompatible($out)) {
                    return $out;
                }
            } catch (Throwable) {
                // Try the next converter.
            }

            if (is_file($out)) {
                @unlink($out);
            }
        }

        throw new RuntimeException($this->unsupportedMessage());
    }

    public function isParserException(Throwable $e): bool
    {
        $message = $e->getMessage();

        return $e instanceof PdfParserException
            || str_contains($message, 'compression technique')
            || str_contains($message, 'free parser shipped with FPDI')
            || str_contains($message, 'This PDF document probably uses');
    }

    private function probe(string $absolutePath): void
    {
        $pdf = new Fpdi('P', 'mm');
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->setSourceFile($absolutePath);
    }

    private function pdfLib(string $in, string $out): bool
    {
        $node = $this->findBinary([
            env('NODE_BINARY'),
            env('NODE_PATH_BINARY'),
            'node',
            'node.exe',
            'C:\\Program Files\\nodejs\\node.exe',
            'C:\\Program Files (x86)\\nodejs\\node.exe',
        ]);
        $script = base_path('scripts/normalize-pdf-for-fpdi.mjs');

        if ($node === null || ! is_file($script)) {
            return false;
        }

        $process = new Process([$node, $script, $in, $out], base_path());
        $process->setTimeout(60);
        $process->run();

        if (! $process->isSuccessful()) {
            report(new RuntimeException(
                'Could not rewrite PDF with pdf-lib: '.trim($process->getErrorOutput() ?: $process->getOutput()),
            ));

            return false;
        }

        return is_file($out) && filesize($out) > 0;
    }

    private function ghostscript(string $in, string $out): bool
    {
        $binary = $this->findBinary([
            env('GHOSTSCRIPT_PATH'),
            'gswin64c',
            'gswin32c',
            'gs',
            ...$this->windowsGhostscriptPaths(),
        ]);

        if ($binary === null) {
            return false;
        }

        $process = new Process([
            $binary,
            '-q',
            '-dNOPAUSE',
            '-dBATCH',
            '-dSAFER',
            '-dQUIET',
            '-sDEVICE=pdfwrite',
            '-dCompatibilityLevel=1.4',
            '-dPDFSETTINGS=/prepress',
            '-dAutoRotatePages=/None',
            '-dDetectDuplicateImages=true',
            '-sOutputFile='.$out,
            $in,
        ]);
        $process->setTimeout(120);
        $process->run();

        return $process->isSuccessful() && is_file($out);
    }

    private function qpdf(string $in, string $out): bool
    {
        $binary = $this->findBinary([
            env('QPDF_PATH'),
            'qpdf',
            'C:\\Program Files\\qpdf\\bin\\qpdf.exe',
            'C:\\Program Files\\qpdf\\qpdf.exe',
        ]);

        if ($binary === null) {
            return false;
        }

        $process = new Process([
            $binary,
            '--object-streams=disable',
            '--force-version=1.4',
            $in,
            $out,
        ]);
        $process->setTimeout(60);
        $process->run();

        return $process->isSuccessful() && is_file($out);
    }

    private function libreOffice(string $in, string $out): bool
    {
        $converter = app(DocxToPdfConverter::class);
        if (! $converter->isAvailable()) {
            return false;
        }

        $dir = sys_get_temp_dir().DIRECTORY_SEPARATOR.uniqid('lo_pdf_', true);
        if (! mkdir($dir, 0755, true) && ! is_dir($dir)) {
            return false;
        }

        try {
            $converted = $converter->convert($in, $dir);
            if (! is_file($converted)) {
                return false;
            }

            return copy($converted, $out);
        } finally {
            foreach (glob($dir.DIRECTORY_SEPARATOR.'*') ?: [] as $file) {
                @unlink($file);
            }
            @rmdir($dir);
        }
    }

    /**
     * @return list<string>
     */
    private function windowsGhostscriptPaths(): array
    {
        $paths = [];
        foreach ([
            'C:\\Program Files\\gs\\*\\bin\\gswin64c.exe',
            'C:\\Program Files\\gs\\*\\bin\\gswin32c.exe',
            'C:\\Program Files (x86)\\gs\\*\\bin\\gswin32c.exe',
        ] as $pattern) {
            foreach (glob($pattern) ?: [] as $path) {
                $paths[] = $path;
            }
        }

        rsort($paths);

        return $paths;
    }

    /**
     * @param  list<mixed>  $candidates
     */
    private function findBinary(array $candidates): ?string
    {
        foreach ($candidates as $candidate) {
            if (! is_string($candidate) || $candidate === '') {
                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }

            if (! str_contains($candidate, DIRECTORY_SEPARATOR) && ! str_contains($candidate, '/')) {
                $found = $this->which($candidate);
                if ($found !== null) {
                    return $found;
                }
            }
        }

        return null;
    }

    private function which(string $binary): ?string
    {
        $command = PHP_OS_FAMILY === 'Windows' ? ['where', $binary] : ['which', $binary];
        $process = new Process($command);
        $process->run();

        if (! $process->isSuccessful()) {
            return null;
        }

        $line = trim(strtok($process->getOutput(), "\n") ?: '');

        return $line !== '' && is_file($line) ? $line : null;
    }

    private function unsupportedMessage(): string
    {
        return 'This PDF uses compression that the free FPDI parser cannot read (common with Word, Chrome, or Acrobat PDF 1.5+ exports). '
            .'The original file was kept. Re-export as PDF 1.4 / PDF/A-1b, or install Ghostscript so a compatible canonical copy can be written automatically.';
    }
}
