<?php

namespace App\Support;

/**
 * Lightweight **bold** markers for controlled-form static text.
 * Designer stores markers in options.static_text; PDF filler and canvas preview parse them.
 */
final class InlineBoldMarkup
{
    /**
     * Exact **…** pairs only — do not steal FO2 footnote markers like **Not / ***Not.
     */
    private const PAIR_PATTERN = '/(?<!\*)\*\*(?!\*)(.*?)(?<!\*)\*\*(?!\*)/s';

    /**
     * @return list<array{text: string, bold: bool}>
     */
    public static function segments(string $text): array
    {
        if ($text === '') {
            return [];
        }

        if (! str_contains($text, '**')) {
            return [['text' => $text, 'bold' => false]];
        }

        $parts = preg_split(self::PAIR_PATTERN, $text, -1, PREG_SPLIT_DELIM_CAPTURE);
        if ($parts === false) {
            return [['text' => $text, 'bold' => false]];
        }

        $segments = [];
        foreach ($parts as $index => $part) {
            if ($part === '') {
                continue;
            }
            $segments[] = [
                'text' => $part,
                'bold' => $index % 2 === 1,
            ];
        }

        return $segments === [] ? [['text' => $text, 'bold' => false]] : $segments;
    }

    public static function strip(string $text): string
    {
        if (! str_contains($text, '**')) {
            return $text;
        }

        return (string) preg_replace(self::PAIR_PATTERN, '$1', $text);
    }

    public static function hasMarkup(string $text): bool
    {
        return str_contains($text, '**');
    }
}
