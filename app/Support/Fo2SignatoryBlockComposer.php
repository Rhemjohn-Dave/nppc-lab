<?php

namespace App\Support;

/**
 * Builds FO2-family signatory multiline text (label + people + detail lines).
 */
final class Fo2SignatoryBlockComposer
{
    /**
     * Prefer designer static_text (layout/spacing/indents); inject live names/PRCs when present.
     *
     * @param  array<string, mixed>  $block  options.signatory_block
     * @param  array<string, mixed>  $bag
     */
    public static function resolve(array $block, array $bag = [], ?string $staticText = null): string
    {
        $static = (string) ($staticText ?? '');
        if (trim($static) !== '') {
            return self::applyBagToStaticText($static, $block, $bag);
        }

        return self::compose($block, $bag);
    }

    /**
     * @param  array<string, mixed>  $block  options.signatory_block
     * @param  array<string, mixed>  $bag
     */
    public static function compose(array $block, array $bag = []): string
    {
        $label = trim((string) ($block['label'] ?? ''));
        $people = is_array($block['people'] ?? null) ? $block['people'] : [];

        $sections = [];
        foreach ($people as $person) {
            if (! is_array($person)) {
                continue;
            }
            $section = self::composePerson($person, $bag);
            if ($section !== '') {
                $sections[] = $section;
            }
        }

        if ($sections === [] && $label === '') {
            return '';
        }

        $parts = [];
        if ($label !== '') {
            $parts[] = $label;
        }
        $parts[] = implode("\n\n", $sections);

        return trim(implode("\n", array_filter($parts, static fn (string $p): bool => $p !== '')));
    }

    /**
     * Sample static_text for Form Designer when no live bag is present.
     *
     * @param  array<string, mixed>  $block
     */
    public static function previewText(array $block): string
    {
        return self::compose($block, []);
    }

    /**
     * Keep designer layout (blank lines, tabs) and swap fallback names/PRCs for live bag values.
     *
     * @param  array<string, mixed>  $block
     * @param  array<string, mixed>  $bag
     */
    public static function applyBagToStaticText(string $staticText, array $block, array $bag): string
    {
        $text = $staticText;
        $people = is_array($block['people'] ?? null) ? $block['people'] : [];

        foreach ($people as $person) {
            if (! is_array($person)) {
                continue;
            }

            $fallback = trim((string) ($person['fallback_name'] ?? ''));
            $nameKey = trim((string) ($person['name_key'] ?? ''));
            $liveName = $nameKey !== '' ? trim((string) ($bag[$nameKey] ?? '')) : '';
            $printedName = $fallback;
            if ($liveName !== '' && $fallback !== '' && $liveName !== $fallback) {
                $text = str_replace($fallback, $liveName, $text);
                $printedName = $liveName;
            } elseif ($liveName !== '') {
                $printedName = $liveName;
            }

            $prcKey = trim((string) ($person['prc_key'] ?? ''));
            $livePrc = $prcKey !== '' ? trim((string) ($bag[$prcKey] ?? '')) : '';
            if ($livePrc === '' || $printedName === '') {
                continue;
            }

            $linesAfter = is_array($person['lines_after_name'] ?? null)
                ? $person['lines_after_name']
                : [];
            $licenseLine = '';
            foreach (array_reverse($linesAfter) as $line) {
                if (is_string($line) && str_contains($line, '#')) {
                    $licenseLine = rtrim($line);
                    break;
                }
            }
            if ($licenseLine === '') {
                continue;
            }

            $namePos = strpos($text, $printedName);
            if ($namePos === false) {
                continue;
            }
            $searchFrom = $namePos + strlen($printedName);
            $licensePos = strpos($text, $licenseLine, $searchFrom);
            if ($licensePos === false) {
                continue;
            }

            $withPrc = self::appendPrc($licenseLine, $livePrc);
            if ($withPrc === $licenseLine) {
                continue;
            }

            $text = substr($text, 0, $licensePos)
                .$withPrc
                .substr($text, $licensePos + strlen($licenseLine));
        }

        return $text;
    }

    /**
     * @param  array<string, mixed>  $person
     * @param  array<string, mixed>  $bag
     */
    private static function composePerson(array $person, array $bag): string
    {
        $nameKey = trim((string) ($person['name_key'] ?? ''));
        $fallback = trim((string) ($person['fallback_name'] ?? ''));
        $name = $nameKey !== '' ? trim((string) ($bag[$nameKey] ?? '')) : '';
        if ($name === '') {
            $name = $fallback;
        }

        $prcKey = trim((string) ($person['prc_key'] ?? ''));
        $prc = $prcKey !== '' ? trim((string) ($bag[$prcKey] ?? '')) : '';

        $linesAfter = is_array($person['lines_after_name'] ?? null)
            ? $person['lines_after_name']
            : [];

        $lines = [];
        if ($name !== '') {
            $lines[] = $name;
        }

        $detailLines = [];
        foreach ($linesAfter as $line) {
            if (! is_string($line) && ! is_numeric($line)) {
                continue;
            }
            $detailLines[] = (string) $line;
        }

        if ($detailLines !== []) {
            $lastIndex = array_key_last($detailLines);
            if ($prc !== '' && is_int($lastIndex)) {
                $detailLines[$lastIndex] = self::appendPrc($detailLines[$lastIndex], $prc);
            }
            foreach ($detailLines as $detail) {
                $trimmed = trim($detail);
                if ($trimmed !== '') {
                    $lines[] = $trimmed;
                }
            }
        }

        return implode("\n", $lines);
    }

    private static function appendPrc(string $line, string $prc): string
    {
        $trimmed = rtrim($line);
        if ($trimmed === '') {
            return $prc;
        }
        // Already embeds a license number after #.
        if (preg_match('/#\s*\S+/', $trimmed) === 1) {
            return $trimmed;
        }
        if (str_ends_with($trimmed, '#')) {
            return $trimmed.$prc;
        }

        return $trimmed.' '.$prc;
    }
}
