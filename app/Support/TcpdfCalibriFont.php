<?php

namespace App\Support;

use TCPDF_FONTS;

class TcpdfCalibriFont
{
    public const FAMILY = 'calibri';

    public const BOLD_FAMILY = 'calibrib';

    public static function phpPath(): string
    {
        return storage_path('app/tcpdf-fonts/calibri.php');
    }

    public static function boldPhpPath(): string
    {
        return storage_path('app/tcpdf-fonts/calibrib.php');
    }

    public static function ensure(): bool
    {
        return self::ensureFont(self::phpPath(), self::locateTtf());
    }

    public static function ensureBold(): bool
    {
        return self::ensureFont(self::boldPhpPath(), self::locateTtf('calibrib.ttf'));
    }

    public static function locateTtf(string $file = 'calibri.ttf'): ?string
    {
        $candidates = [
            storage_path('app/fonts/'.$file),
            resource_path('fonts/'.$file),
        ];

        $winDir = getenv('WINDIR') ?: getenv('SystemRoot') ?: 'C:\\Windows';
        $candidates[] = $winDir.'\\Fonts\\'.$file;
        $candidates[] = $winDir.'\\Fonts\\'.ucfirst($file);

        foreach ($candidates as $path) {
            if (is_file($path)) {
                return $path;
            }
        }

        return null;
    }

    private static function ensureFont(string $php, ?string $ttf): bool
    {
        if (is_file($php)) {
            return true;
        }

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
}
