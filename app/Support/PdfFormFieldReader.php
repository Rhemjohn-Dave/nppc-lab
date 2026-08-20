<?php

namespace App\Support;

class PdfFormFieldReader
{
    /**
     * Extract AcroForm field names from a PDF binary.
     *
     * @return list<string>
     */
    public static function names(string $binary): array
    {
        $names = [];

        if (preg_match_all('/\/T\s*\(((?:\\\\.|[^\\\\)])*)\)/', $binary, $literalMatches)) {
            foreach ($literalMatches[1] as $raw) {
                $decoded = stripcslashes($raw);
                if (self::isFieldName($decoded)) {
                    $names[] = $decoded;
                }
            }
        }

        if (preg_match_all('/\/T\s*\/([A-Za-z][A-Za-z0-9._-]*)/', $binary, $nameMatches)) {
            foreach ($nameMatches[1] as $decoded) {
                if (self::isFieldName($decoded)) {
                    $names[] = $decoded;
                }
            }
        }

        if (preg_match_all('/\/T\s*<([0-9A-Fa-f]+)>/', $binary, $hexMatches)) {
            foreach ($hexMatches[1] as $hex) {
                $decoded = (string) hex2bin($hex);
                if (self::isFieldName($decoded)) {
                    $names[] = $decoded;
                }
            }
        }

        $unique = array_values(array_unique($names));
        sort($unique);

        return $unique;
    }

    private static function isFieldName(string $name): bool
    {
        $name = trim($name);

        return $name !== '' && (bool) preg_match('/^[A-Za-z][A-Za-z0-9._-]{0,99}$/', $name);
    }
}
