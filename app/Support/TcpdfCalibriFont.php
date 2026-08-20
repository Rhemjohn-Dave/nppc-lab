<?php

namespace App\Support;

use TCPDF_FONTS;

class TcpdfCalibriFont
{
    public const FAMILY = 'calibri';

    public static function phpPath(): string
    {
        return storage_path('app/tcpdf-fonts/calibri.php');
    }

    public static function ensure(): bool
    {
        $php = self::phpPath();
        if (is_file($php)) {
            return true;
        }

        $ttf = self::locateTtf();
        if ($ttf === null) {
            return false;
        }

        $outDir = dirname($php);
        if (! is_dir($outDir) && ! mkdir($outDir, 0755, true) && ! is_dir($outDir)) {
            return false;
        }

        $name = TCPDF_FONTS::addTTFfont($ttf, 'TrueTypeUnicode', '', 96, $outDir.DIRECTORY_SEPARATOR);

        return is_string($name) && $name !== '' && is_file($outDir.DIRECTORY_SEPARATOR.$name.'.php');
    }

    public static function locateTtf(): ?string
    {
        $candidates = [
            storage_path('app/fonts/calibri.ttf'),
            resource_path('fonts/calibri.ttf'),
        ];

        $winDir = getenv('WINDIR') ?: getenv('SystemRoot') ?: 'C:\\Windows';
        $candidates[] = $winDir.'\\Fonts\\calibri.ttf';
        $candidates[] = $winDir.'\\Fonts\\Calibri.ttf';

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }
}
