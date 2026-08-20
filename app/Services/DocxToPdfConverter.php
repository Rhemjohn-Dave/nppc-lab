<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class DocxToPdfConverter
{
    public function isAvailable(): bool
    {
        return $this->binary() !== null;
    }

    public function convert(string $docxAbsolutePath, string $outputDirectory): string
    {
        $binary = $this->binary();

        if ($binary === null) {
            throw new RuntimeException(
                'LibreOffice is not installed. DOCX uploads require LibreOffice Headless. Export to PDF and upload the PDF instead.',
            );
        }

        if (! is_dir($outputDirectory) && ! mkdir($outputDirectory, 0755, true) && ! is_dir($outputDirectory)) {
            throw new RuntimeException('Could not create the conversion output directory.');
        }

        $process = new Process([
            $binary,
            '--headless',
            '--nologo',
            '--nofirststartwizard',
            '--convert-to',
            'pdf',
            '--outdir',
            $outputDirectory,
            $docxAbsolutePath,
        ]);
        $process->setTimeout(120);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('LibreOffice failed to convert the DOCX: '.$process->getErrorOutput());
        }

        $pdfName = pathinfo($docxAbsolutePath, PATHINFO_FILENAME).'.pdf';
        $pdfPath = rtrim($outputDirectory, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$pdfName;

        if (! is_file($pdfPath)) {
            throw new RuntimeException('LibreOffice finished but the converted PDF was not found.');
        }

        return $pdfPath;
    }

    private function binary(): ?string
    {
        $candidates = [
            env('LIBREOFFICE_PATH'),
            'soffice',
            'libreoffice',
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
            '/usr/bin/soffice',
            '/usr/bin/libreoffice',
        ];

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
}
