<?php

namespace App\Services;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledFormRevision;
use App\Support\DynamicTestMatrix;
use App\Support\ResultFooter;
use App\Support\TcpdfCalibriFont;
use setasign\Fpdi\Tcpdf\Fpdi;

class ControlledPdfFiller
{
    /**
     * @param  array<string, mixed>  $values
     */
    public function fill(ControlledFormRevision $revision, array $values): string
    {
        @set_time_limit(300);

        $source = $revision->canonicalAbsolutePath();
        $pdf = $this->newPdf($revision);
        $pageCount = $this->openSource($pdf, $source);

        $continuation = $this->matrixContinuation($revision, $values);
        if ($continuation !== null) {
            $templateId = $pdf->importPage(1);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $pageFields = $revision->fields
                ->filter(fn ($field) => (int) $field->page_number === 1)
                ->values();
            $total = count($continuation['chunks']);

            foreach ($continuation['chunks'] as $index => $chunkRows) {
                $pageValues = $values;
                $pageValues[$continuation['name']] = $chunkRows;
                $pdf->AddPage($orientation, [$size['width'], $size['height']]);
                $pdf->useTemplate($templateId);
                $this->drawOverlayPage($pdf, $revision, $pageFields, $pageValues, $index + 1, $total);
            }

            return $pdf->Output('', 'S');
        }

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            $pageFields = $revision->fields
                ->filter(fn ($field) => (int) $field->page_number === $pageNo)
                ->values();

            $this->drawOverlayPage($pdf, $revision, $pageFields, $values, $pageNo, $pageCount);
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Split a page-1 dynamic matrix into chunks when it sets max_rows_per_page
     * (FO28 defaults to 10 even if the designer leaves the option blank).
     *
     * @param  array<string, mixed>  $values
     * @return array{name: string, chunks: list<list<array<string, mixed>>>}|null
     */
    private function matrixContinuation(ControlledFormRevision $revision, array $values): ?array
    {
        $revision->loadMissing(['fields', 'form']);
        $defaultMax = $revision->form?->form_code === 'LSP-7.8-FO28' ? 10 : 0;

        foreach ($revision->fields as $field) {
            if ($field->field_type !== ControlledFormFieldType::DynamicTestMatrix) {
                continue;
            }
            if ((int) $field->page_number !== 1) {
                continue;
            }

            $config = is_array($field->table_config) ? $field->table_config : [];
            $max = (int) ($config['max_rows_per_page'] ?? $defaultMax);
            if ($max < 1) {
                continue;
            }

            $rows = $values[$field->name] ?? null;
            if (! is_array($rows)) {
                continue;
            }

            $rows = array_values(array_filter($rows, 'is_array'));
            if (count($rows) <= $max) {
                continue;
            }

            return [
                'name' => $field->name,
                'chunks' => array_chunk($rows, $max),
            ];
        }

        return null;
    }

    /**
     * @param  \Illuminate\Support\Collection<int, \App\Models\ControlledFormField>  $pageFields
     * @param  array<string, mixed>  $values
     */
    private function drawOverlayPage(
        Fpdi $pdf,
        ControlledFormRevision $revision,
        $pageFields,
        array $values,
        int $pageNo,
        int $pageCount,
    ): void {
        /** @var array<string, float> $matrixBottomY */
        $matrixBottomY = [];

        foreach ($pageFields as $field) {
            if ($field->field_type !== ControlledFormFieldType::DynamicTestMatrix) {
                continue;
            }

            $name = $field->name;
            if (! array_key_exists($name, $values) || ! is_array($values[$name])) {
                continue;
            }

            $overlay = $field->toOverlayArray();
            $matrixBottomY[$name] = $this->writeDynamicTestMatrix(
                $pdf,
                $overlay,
                $values[$name],
                $values,
            );
        }

        $pageHeight = (float) $pdf->getPageHeight();

        /** @var array<string, float> $absoluteFieldY */
        $absoluteFieldY = [];
        foreach ($pageFields as $field) {
            if ($field->field_type === ControlledFormFieldType::DynamicTestMatrix) {
                continue;
            }
            $absoluteFieldY[$field->name] = (float) $field->y;
        }

        $formMetaStampY = null;
        foreach ($pageFields as $field) {
            if (! $this->isFixedFormMetaField($field->name)) {
                continue;
            }
            $overlayMeta = $field->toOverlayArray();
            $metaY = $this->resolveFormMetaY(
                $overlayMeta,
                $matrixBottomY,
                $pageHeight,
            );
            $absoluteFieldY[$field->name] = $metaY;
            $formMetaStampY = $this->pageStampYBelowFormMeta(
                $metaY,
                (float) ($overlayMeta['h'] ?? $field->height),
                (float) ($overlayMeta['font_size'] ?? $field->font_size ?? 7.0),
                $pageHeight,
            );
        }

        $activeFooterVariant = trim((string) ($values['fo2_footer_variant'] ?? ''));
        foreach ($pageFields as $field) {
            if ($field->field_type === ControlledFormFieldType::DynamicTestMatrix) {
                continue;
            }

            $name = $field->name;
            $overlay = $field->toOverlayArray();
            $options = is_array($overlay['options'] ?? null) ? $overlay['options'] : [];
            $fieldFooterVariant = trim((string) ($options['footer_variant'] ?? ''));
            if (
                $fieldFooterVariant !== ''
                && $activeFooterVariant !== ''
                && $fieldFooterVariant !== $activeFooterVariant
            ) {
                continue;
            }
            $staticText = trim((string) ($options['static_text'] ?? ''));
            $hasValue = array_key_exists($name, $values);
            if (! $hasValue && $staticText === '') {
                continue;
            }

            $value = $hasValue ? $values[$name] : null;
            if ($this->isFixedFormMetaField($name)) {
                $overlay['y'] = $absoluteFieldY[$name];
            } else {
                $overlay = $this->clampFo28SignatoriesAboveFormMeta($overlay, $pageFields);
                $overlay = $this->applyAnchorBelow($overlay, $matrixBottomY, $absoluteFieldY);
            }
            $this->writeField($pdf, $overlay, $value, $values);
        }

        $this->writeCenteredPageStamp($pdf, $revision, $pageNo, $pageCount, $formMetaStampY);
    }

    public function calibrationOverlay(ControlledFormRevision $revision): string
    {
        @set_time_limit(300);

        $source = $revision->canonicalAbsolutePath();
        $pdf = $this->newPdf($revision);
        $pageCount = $this->openSource($pdf, $source);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';
            $width = (float) $size['width'];
            $height = (float) $size['height'];

            $pdf->AddPage($orientation, [$width, $height]);
            $pdf->useTemplate($templateId);

            $pdf->SetFont('helvetica', '', 4);
            for ($x = 0; $x <= $width; $x += 5) {
                $isMajor = ((int) $x) % 10 === 0;
                $pdf->SetDrawColor(...($isMajor ? [120, 120, 255] : [210, 210, 210]));
                $pdf->SetLineWidth($isMajor ? 0.15 : 0.05);
                $pdf->Line($x, 0, $x, $height);
                if ($isMajor) {
                    $pdf->SetTextColor(120, 120, 255);
                    $pdf->SetXY($x + 0.2, 0.5);
                    $pdf->Cell(8, 2, (string) (int) $x, 0, 0, 'L');
                }
            }
            for ($y = 0; $y <= $height; $y += 5) {
                $isMajor = ((int) $y) % 10 === 0;
                $pdf->SetDrawColor(...($isMajor ? [120, 120, 255] : [210, 210, 210]));
                $pdf->SetLineWidth($isMajor ? 0.15 : 0.05);
                $pdf->Line(0, $y, $width, $y);
                if ($isMajor) {
                    $pdf->SetTextColor(120, 120, 255);
                    $pdf->SetXY(0.5, $y + 0.2);
                    $pdf->Cell(8, 2, (string) (int) $y, 0, 0, 'L');
                }
            }

            foreach ($revision->fields as $field) {
                if ($field->page_number !== $pageNo) {
                    continue;
                }

                $fx = (float) $field->x;
                $fy = (float) $field->y;
                $fw = (float) $field->width;
                $fh = (float) $field->height;
                if ($fw < 1 || $fh < 1) {
                    continue;
                }

                $isCheckbox = $field->field_type === ControlledFormFieldType::Checkbox;
                $isMatrix = $field->field_type === ControlledFormFieldType::DynamicTestMatrix;
                $pdf->SetDrawColor(...($isCheckbox ? [220, 40, 40] : ($isMatrix ? [180, 80, 20] : [30, 160, 60])));
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($fx, $fy, $fw, $fh);

                $pdf->SetFont('helvetica', '', 3.5);
                $pdf->SetTextColor(...($isCheckbox ? [220, 40, 40] : ($isMatrix ? [180, 80, 20] : [30, 120, 50])));
                $pdf->SetXY($fx, max(0, $fy - 2.2));
                $pdf->Cell($fw + 20, 2, $field->name, 0, 0, 'L');
            }

            $this->writeCenteredPageStamp($pdf, $revision, $pageNo, $pageCount);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        return $pdf->Output('', 'S');
    }

    /** Distance from page bottom to the centered `- page n/total -` baseline (short sheets). */
    private const PAGE_STAMP_BOTTOM_MM = 26.0;

    /** Gap between form-meta block bottom and the page stamp. */
    private const FORM_META_ABOVE_STAMP_MM = 3.5;

    /**
     * Final visual raise for `- page n/total -`.
     * Applied after pin/follow-meta math — PAGE_STAMP_BOTTOM_MM alone is ignored when
     * the stamp tracks under Test Report (pageStampYBelowFormMeta).
     */
    private const PAGE_STAMP_NUDGE_UP_MM = 5.0;

    /** Small extra drop under notes when space remains above the Test Report footer. */
    private const SIGNATORY_NUDGE_MM = 20.0;

    /** Minimum spare room (clamp − flow) before the short-sheet nudge applies. */
    private const SIGNATORY_NUDGE_MIN_SPARE_MM = 24.0;

    private function pageStampY(float $pageHeight): float
    {
        return max(4.0, $pageHeight - self::PAGE_STAMP_BOTTOM_MM);
    }

    /**
     * Minimum Y so Test Report content sits fully above the page stamp.
     */
    private function formMetaYAbovePageStamp(
        float $fieldHeight,
        float $fontSize,
        float $pageHeight,
    ): float {
        $lineHeight = max(3.0, $fontSize * 0.42);
        $contentHeight = min(max($fieldHeight, $lineHeight), $lineHeight * 5.0);

        return $this->pageStampY($pageHeight) - self::FORM_META_ABOVE_STAMP_MM - $contentHeight;
    }

    /**
     * Dynamic Test Report Y: max(matrixBottom + offset, pinnedAboveStamp).
     * Short sheets keep the footer band; tall sheets drop meta below the chrome stack.
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, float>  $matrixBottomY
     */
    private function resolveFormMetaY(array $field, array $matrixBottomY, float $pageHeight): float
    {
        $pinned = $this->formMetaYAbovePageStamp(
            (float) ($field['h'] ?? 18.0),
            (float) ($field['font_size'] ?? 7.0),
            $pageHeight,
        );

        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : null;
        $below = is_array($anchor) ? trim((string) ($anchor['below'] ?? '')) : '';
        $offset = is_array($anchor) && is_numeric($anchor['offset_mm'] ?? null)
            ? (float) $anchor['offset_mm']
            : 0.0;

        if ($below !== '' && array_key_exists($below, $matrixBottomY)) {
            return max($matrixBottomY[$below] + $offset, $pinned);
        }

        return $pinned;
    }

    /**
     * Page stamp sits just under Test Report; follows meta down on tall sheets.
     */
    private function pageStampYBelowFormMeta(
        float $metaY,
        float $fieldHeight,
        float $fontSize,
        float $pageHeight,
    ): float {
        $lineHeight = max(3.0, $fontSize * 0.42);
        $contentHeight = min(max($fieldHeight, $lineHeight), $lineHeight * 5.0);
        $belowMeta = $metaY + $contentHeight + self::FORM_META_ABOVE_STAMP_MM;
        $pinned = $this->pageStampY($pageHeight);

        return min(max($pinned, $belowMeta), max(4.0, $pageHeight - 4.0));
    }

    private function writeCenteredPageStamp(
        Fpdi $pdf,
        ControlledFormRevision $revision,
        int $pageNo,
        int $pageCount,
        ?float $yOverride = null,
    ): void {
        $revision->loadMissing('form');
        if (! ResultFooter::formUsesPageStamp($revision->form)) {
            return;
        }

        $stamp = ResultFooter::pageStampText($pageNo, $pageCount);
        $pageHeight = (float) $pdf->getPageHeight();
        $pageWidth = (float) $pdf->getPageWidth();
        $fontSize = 7.5;
        // FO28 page mark sits in the bottom margin, under the plotted footer.
        // Other stamped sheets keep the raised position used with Test Report.
        if ($revision->form?->form_code === 'LSP-7.8-FO28') {
            $y = max(4.0, $pageHeight - 10.0);
        } else {
            $y = max(4.0, ($yOverride ?? $this->pageStampY($pageHeight)) - self::PAGE_STAMP_NUDGE_UP_MM);
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFont('times', '', $fontSize);
        $textWidth = (float) $pdf->GetStringWidth($stamp);
        $x = max(0.0, ($pageWidth - $textWidth) / 2.0);
        $pdf->SetXY($x, $y);
        $pdf->Cell(max($textWidth, 0.1), $fontSize * 0.45, $stamp, 0, 0, 'L');
    }

    /**
     * @param  array{name: string, type: string, page: int, x: float, y: float, w: float, h: float, font_size: float, align: string, font_family?: string, font_color?: string, table_config?: array<int|string, mixed>|null}  $field
     * @param  array<string, mixed>  $allValues
     */
    private function writeField(Fpdi $pdf, array $field, mixed $value, array $allValues = []): void
    {
        $type = $field['type'] ?? 'text';

        if ($type === ControlledFormFieldType::DynamicTestMatrix->value && is_array($value)) {
            $this->writeDynamicTestMatrix($pdf, $field, $value, $allValues);

            return;
        }

        if ($type === ControlledFormFieldType::Table->value && is_array($value)) {
            $this->writeTable($pdf, $field, $value);

            return;
        }

        if (in_array($type, [ControlledFormFieldType::Checkbox->value, ControlledFormFieldType::Radio->value], true)) {
            if (! $value) {
                return;
            }

            $this->applyTcpdfFont($pdf, (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY), 'B', 8);
            $this->applyColor($pdf, $field['font_color'] ?? null);
            $pdf->SetXY((float) $field['x'], (float) $field['y']);
            $pdf->Cell((float) $field['w'], (float) $field['h'], 'X', 0, 0, 'C');

            return;
        }

        if ((float) $field['w'] < 1 || (float) $field['h'] < 1) {
            return;
        }

        $fontSize = (float) ($field['font_size'] ?? 11);
        $align = strtoupper((string) ($field['align'] ?? 'L'));
        if (! in_array($align, ['L', 'C', 'R'], true)) {
            $align = 'L';
        }

        $x = (float) $field['x'];
        $y = (float) $field['y'];
        $w = (float) $field['w'];
        $h = (float) $field['h'];

        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $cover = ($options['cover'] ?? false) === true;
        if ($cover) {
            $pdf->SetFillColor(255, 255, 255);
            $pdf->Rect($x, $y, $w, $h, 'F');
        }

        $staticText = trim((string) ($options['static_text'] ?? ''));
        if ($staticText !== '' && ($options['prefer_static'] ?? false) === true) {
            $value = $staticText;
        } elseif (($value === null || $value === '') && $staticText !== '') {
            $value = $staticText;
        }

        if ($value === null || $value === '') {
            return;
        }

        $this->applyColor($pdf, $field['font_color'] ?? null);
        $family = (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY);
        $forceBold = ($options['bold'] ?? false) === true;
        $this->applyTcpdfFont($pdf, $family, $forceBold ? 'B' : '', $fontSize);

        $plainForScientific = \App\Support\InlineBoldMarkup::strip(trim((string) $value));
        $scientific = is_scalar($value) ? $this->splitScientificValue($plainForScientific) : null;
        if ($scientific !== null && ! \App\Support\InlineBoldMarkup::hasMarkup((string) $value)) {
            $this->writeScientificInBox($pdf, $scientific, $x, $y, $w, $h, $align, $family, $fontSize);

            return;
        }

        $value = $this->plainScientific((string) $value);
        $pdf->SetXY($x, $y);

        if ($type === ControlledFormFieldType::Multiline->value) {
            $lineHeight = max(3.0, $fontSize * 0.42);
            $listStyle = strtolower(trim((string) ($options['list_style'] ?? '')));
            if (in_array($listStyle, ['numbered', 'bullet'], true)) {
                $this->writeMultilineList(
                    $pdf,
                    (string) $value,
                    $listStyle,
                    $x,
                    $y,
                    $w,
                    $h,
                    $lineHeight,
                    $align,
                    (float) ($options['list_indent_mm'] ?? 6),
                    $family,
                    $fontSize,
                    $forceBold,
                );

                return;
            }

            $this->writeStyledMultiline(
                $pdf,
                (string) $value,
                $x,
                $y,
                $w,
                $h,
                $lineHeight,
                $family,
                $fontSize,
                $forceBold,
            );

            return;
        }

        $this->writeStyledSingleLine(
            $pdf,
            (string) $value,
            $x,
            $y,
            $w,
            $h,
            $align,
            $family,
            $fontSize,
            $forceBold,
        );
    }

    /**
     * Draw multiline notes as a hanging-indent numbered or bullet list.
     * Header lines (e.g. "NOTES :") stay flush left; list body wraps under the text, not the marker.
     */
    private function writeMultilineList(
        Fpdi $pdf,
        string $text,
        string $listStyle,
        float $x,
        float $y,
        float $w,
        float $h,
        float $lineHeight,
        string $align,
        float $gutterMm,
        string $family = TcpdfCalibriFont::FAMILY,
        float $fontSize = 11.0,
        bool $forceBold = false,
    ): void {
        $gutter = max(4.0, min($gutterMm, max(4.0, $w * 0.35)));
        $bodyWidth = max(1.0, $w - $gutter);
        $bottom = $y + $h;
        $cursorY = $y;

        [$headers, $items] = $this->parseMultilineList($text, $listStyle);

        foreach ($headers as $header) {
            if ($cursorY + $lineHeight > $bottom + 0.01) {
                return;
            }
            $remaining = max($lineHeight, $bottom - $cursorY);
            $cursorY = $this->writeStyledMultiline(
                $pdf,
                $header,
                $x,
                $cursorY,
                $w,
                $remaining,
                $lineHeight,
                $family,
                $fontSize,
                $forceBold,
            );
        }

        foreach ($items as $item) {
            if ($cursorY + $lineHeight > $bottom + 0.01) {
                return;
            }

            $marker = (string) ($item['marker'] ?? '');
            $body = (string) ($item['body'] ?? '');
            $remaining = max($lineHeight, $bottom - $cursorY);
            $rowStartY = $cursorY;

            $this->applyTcpdfFont($pdf, $family, $forceBold ? 'B' : '', $fontSize);
            $pdf->SetXY($x, $cursorY);
            $pdf->Cell($gutter, $lineHeight, $marker, 0, 0, 'L', false, '', 0, false, 'T', 'T');

            $bodyEndY = $this->writeStyledMultiline(
                $pdf,
                $body,
                $x + $gutter,
                $rowStartY,
                $bodyWidth,
                $remaining,
                $lineHeight,
                $family,
                $fontSize,
                $forceBold,
            );
            $cursorY = max($cursorY + $lineHeight, $bodyEndY);
        }
    }

    /**
     * @return float Next Y cursor after drawing (bottom of last line used).
     */
    private function writeStyledMultiline(
        Fpdi $pdf,
        string $text,
        float $x,
        float $y,
        float $w,
        float $h,
        float $lineHeight,
        string $family,
        float $fontSize,
        bool $forceBold,
    ): float {
        $bottom = $y + $h;
        $cursorY = $y;
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [''];

        foreach ($lines as $lineIndex => $line) {
            if ($cursorY + $lineHeight > $bottom + 0.01) {
                return $cursorY;
            }

            if ($line === '') {
                $cursorY += $lineHeight;

                continue;
            }

            $segments = \App\Support\InlineBoldMarkup::segments($line);
            if ($forceBold) {
                $segments = array_map(
                    static fn (array $seg): array => ['text' => $seg['text'], 'bold' => true],
                    $segments,
                );
            }

            $cursorX = $x;
            $tokens = $this->tokenizeStyledSegments($segments);

            foreach ($tokens as $token) {
                if ($cursorY + $lineHeight > $bottom + 0.01) {
                    return $cursorY;
                }

                $style = ($token['bold'] ?? false) ? 'B' : '';
                $this->applyTcpdfFont($pdf, $family, $style, $fontSize);
                $chunk = (string) ($token['text'] ?? '');
                if ($chunk === '') {
                    continue;
                }

                $chunkWidth = (float) $pdf->GetStringWidth($chunk);
                $lineRight = $x + $w;

                if ($cursorX > $x + 0.01 && $cursorX + $chunkWidth > $lineRight + 0.01) {
                    $cursorY += $lineHeight;
                    $cursorX = $x;
                    if ($cursorY + $lineHeight > $bottom + 0.01) {
                        return $cursorY;
                    }
                }

                // Hard-wrap oversized tokens (no spaces).
                while ($chunk !== '' && $cursorY + $lineHeight <= $bottom + 0.01) {
                    $this->applyTcpdfFont($pdf, $family, $style, $fontSize);
                    $avail = max(0.5, $lineRight - $cursorX);
                    $chunkWidth = (float) $pdf->GetStringWidth($chunk);

                    if ($chunkWidth <= $avail + 0.01) {
                        $pdf->SetXY($cursorX, $cursorY);
                        $pdf->Cell($chunkWidth, $lineHeight, $chunk, 0, 0, 'L', false, '', 0, false, 'T', 'T');
                        $cursorX += $chunkWidth;
                        break;
                    }

                    if ($cursorX > $x + 0.01) {
                        $cursorY += $lineHeight;
                        $cursorX = $x;
                        if ($cursorY + $lineHeight > $bottom + 0.01) {
                            return $cursorY;
                        }

                        continue;
                    }

                    $fit = $this->fitTextWidth($pdf, $chunk, $avail);
                    if ($fit === '') {
                        break;
                    }
                    $fitWidth = (float) $pdf->GetStringWidth($fit);
                    $pdf->SetXY($cursorX, $cursorY);
                    $pdf->Cell($fitWidth, $lineHeight, $fit, 0, 0, 'L', false, '', 0, false, 'T', 'T');
                    $chunk = substr($chunk, strlen($fit));
                    $cursorY += $lineHeight;
                    $cursorX = $x;
                }
            }

            if ($lineIndex < count($lines) - 1) {
                $cursorY += $lineHeight;
            } elseif ($cursorX > $x + 0.01) {
                // Stay on the line that holds content; next caller advances.
                $cursorY += $lineHeight;
            } else {
                $cursorY += $lineHeight;
            }
        }

        return $cursorY;
    }

    private function writeStyledSingleLine(
        Fpdi $pdf,
        string $text,
        float $x,
        float $y,
        float $w,
        float $h,
        string $align,
        string $family,
        float $fontSize,
        bool $forceBold,
    ): void {
        $segments = \App\Support\InlineBoldMarkup::segments($text);
        if ($forceBold) {
            $segments = array_map(
                static fn (array $seg): array => ['text' => $seg['text'], 'bold' => true],
                $segments,
            );
        }

        $hasInlineBold = false;
        foreach ($segments as $seg) {
            if ($seg['bold']) {
                $hasInlineBold = true;
                break;
            }
        }

        if (! $hasInlineBold && count($segments) <= 1) {
            $plain = \App\Support\InlineBoldMarkup::strip($text);
            $this->applyTcpdfFont($pdf, $family, $forceBold ? 'B' : '', $fontSize);
            $pdf->SetXY($x, $y);
            $pdf->Cell($w, $h, $plain, 0, 0, $align, false, '', 1);

            return;
        }

        $totalWidth = 0.0;
        foreach ($segments as $seg) {
            $this->applyTcpdfFont($pdf, $family, ($seg['bold'] || $forceBold) ? 'B' : '', $fontSize);
            $totalWidth += (float) $pdf->GetStringWidth($seg['text']);
        }

        $cursorX = match ($align) {
            'C' => $x + max(0.0, ($w - $totalWidth) / 2),
            'R' => $x + max(0.0, $w - $totalWidth),
            default => $x,
        };

        foreach ($segments as $seg) {
            $style = ($seg['bold'] || $forceBold) ? 'B' : '';
            $this->applyTcpdfFont($pdf, $family, $style, $fontSize);
            $segWidth = (float) $pdf->GetStringWidth($seg['text']);
            if ($cursorX + $segWidth > $x + $w + 0.01 && $cursorX > $x + 0.01) {
                break;
            }
            $pdf->SetXY($cursorX, $y);
            $pdf->Cell(min($segWidth, max(0.5, $x + $w - $cursorX)), $h, $seg['text'], 0, 0, 'L', false, '', 0, false, 'T', 'M');
            $cursorX += $segWidth;
        }
    }

    /**
     * @param  list<array{text: string, bold: bool}>  $segments
     * @return list<array{text: string, bold: bool}>
     */
    private function tokenizeStyledSegments(array $segments): array
    {
        $tokens = [];
        foreach ($segments as $seg) {
            $text = (string) ($seg['text'] ?? '');
            $bold = (bool) ($seg['bold'] ?? false);
            if ($text === '') {
                continue;
            }
            if (preg_match_all('/\S+\s*|\s+/u', $text, $matches) > 0) {
                foreach ($matches[0] as $piece) {
                    if ($piece === '') {
                        continue;
                    }
                    $tokens[] = ['text' => $piece, 'bold' => $bold];
                }
            } else {
                $tokens[] = ['text' => $text, 'bold' => $bold];
            }
        }

        return $tokens;
    }

    private function fitTextWidth(Fpdi $pdf, string $text, float $maxWidth): string
    {
        if ($text === '' || $maxWidth < 0.5) {
            return '';
        }
        if ((float) $pdf->GetStringWidth($text) <= $maxWidth + 0.01) {
            return $text;
        }

        $low = 0;
        $high = strlen($text);
        $fit = '';
        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);
            if ($mid <= 0) {
                break;
            }
            $candidate = substr($text, 0, $mid);
            if ((float) $pdf->GetStringWidth($candidate) <= $maxWidth + 0.01) {
                $fit = $candidate;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        return $fit;
    }

    /**
     * @return array{0: list<string>, 1: list<array{marker: string, body: string}>}
     */
    private function parseMultilineList(string $text, string $listStyle): array
    {
        $lines = preg_split("/\r\n|\n|\r/", $text) ?: [];
        $headers = [];
        $items = [];
        $inItems = false;

        foreach ($lines as $raw) {
            $line = rtrim((string) $raw);
            if ($line === '' && ! $inItems) {
                continue;
            }

            if ($listStyle === 'numbered' && preg_match('/^(\d+\.)\s+(.*)$/u', $line, $match) === 1) {
                $inItems = true;
                $items[] = [
                    'marker' => (string) $match[1],
                    'body' => (string) $match[2],
                ];

                continue;
            }

            if ($listStyle === 'bullet' && preg_match('/^([-*•])\s+(.*)$/u', $line, $match) === 1) {
                $inItems = true;
                $items[] = [
                    'marker' => '•',
                    'body' => (string) $match[2],
                ];

                continue;
            }

            if ($inItems && $line !== '' && preg_match('/^\s+/u', $line) === 1 && $items !== []) {
                $last = count($items) - 1;
                $items[$last]['body'] .= ' '.trim($line);

                continue;
            }

            if (! $inItems && $line !== '') {
                $headers[] = $line;
            }
        }

        return [$headers, $items];
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float, font_size: float, align: string, font_family?: string, font_color?: string, table_config?: array<int|string, mixed>|null}  $field
     * @param  list<array<string, mixed>>  $rows
     */
    private function writeTable(Fpdi $pdf, array $field, array $rows): void
    {
        $config = is_array($field['table_config'] ?? null) ? $field['table_config'] : [];
        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
        $rowHeight = (float) ($config['row_height'] ?? 4.5);
        $maxRows = (int) ($config['max_rows'] ?? count($rows));
        $requestedFamily = (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY);
        $this->applyColor($pdf, $field['font_color'] ?? null);

        if ($columns === []) {
            $this->applyTcpdfFont($pdf, $requestedFamily, '', (float) ($field['font_size'] ?? 7));
            $pdf->SetXY((float) $field['x'], (float) $field['y']);
            $lines = [];
            foreach (array_slice($rows, 0, max(1, $maxRows)) as $row) {
                $lines[] = implode(' / ', array_filter(array_map(fn ($v) => is_scalar($v) ? (string) $v : '', $row)));
            }
            $pdf->MultiCell((float) $field['w'], (float) $field['h'], $this->plainScientific(implode("\n", $lines)), 0, 'L', false, 1);

            return;
        }

        foreach (array_slice($rows, 0, max(1, $maxRows)) as $index => $row) {
            if (! is_array($row)) {
                continue;
            }

            $y = (float) $field['y'] + ($index * $rowHeight);
            foreach ($columns as $column) {
                if (! is_array($column) || ! isset($column['key'])) {
                    continue;
                }

                $text = $row[(string) $column['key']] ?? null;
                if ($text === null || $text === '') {
                    continue;
                }

                $x = (float) $field['x'] + (float) ($column['x_offset'] ?? 0);
                $w = (float) ($column['width'] ?? 20);
                $fontSize = (float) ($column['font_size'] ?? $field['font_size'] ?? 7);
                $align = strtoupper((string) ($column['align'] ?? 'L'));
                $align = in_array($align, ['L', 'C', 'R'], true) ? $align : 'L';
                $this->applyTcpdfFont($pdf, $requestedFamily, '', $fontSize);
                $scientific = is_scalar($text) ? $this->splitScientificValue(trim((string) $text)) : null;
                if ($scientific !== null) {
                    $this->writeScientificInBox($pdf, $scientific, $x, $y, $w, $rowHeight, $align, $requestedFamily, $fontSize);

                    continue;
                }
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $rowHeight, $this->plainScientific((string) $text), 0, 0, $align, false, '', 1);
            }
        }
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float, font_size: float, align: string, font_family?: string, font_color?: string, table_config?: array<int|string, mixed>|null}  $field
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $values
     */
    /**
     * @return float Content bottom Y (mm from page top) after drawing the table.
     */
    private function writeDynamicTestMatrix(Fpdi $pdf, array $field, array $rows, array $values = []): float
    {
        $rawConfig = is_array($field['table_config'] ?? null) ? $field['table_config'] : DynamicTestMatrix::defaultConfig();
        $config = DynamicTestMatrix::normalizeStoredTableConfig(
            (string) ($field['name'] ?? ''),
            ControlledFormFieldType::DynamicTestMatrix,
            $rawConfig,
            null,
        );
        if (! is_array($config)) {
            $config = DynamicTestMatrix::defaultConfig();
        }
        $columns = is_array($config['columns'] ?? null) && $config['columns'] !== []
            ? $config['columns']
            : DynamicTestMatrix::defaultConfig()['columns'];
        $columns = DynamicTestMatrix::resolveHeaderSources($columns, $values);
        $rows = DynamicTestMatrix::placeOutcomeForColumns($rows, $columns);
        $rows = DynamicTestMatrix::applyDesignerTextEdits($rows, $config);
        $rows = array_map(
            static fn ($row) => is_array($row)
                ? DynamicTestMatrix::sanitizeRawFinishAcceptableValues($row)
                : $row,
            $rows,
        );
        $rowHeight = (float) ($config['row_height_mm'] ?? 7);
        $headerRow = (bool) ($config['header_row'] ?? true);
        $border = (bool) ($config['border'] ?? true);
        $fontSize = (float) ($field['font_size'] ?? 8);
        $requestedFamily = (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY);
        $methodFontSize = isset($config['method_font_size'])
            && is_numeric($config['method_font_size'])
            && (float) $config['method_font_size'] > 0
            ? (float) $config['method_font_size']
            : max(6, $fontSize - 1);
        $headerBold = ($config['header_bold'] ?? true) !== false;
        $testNameBold = ($config['test_name_bold'] ?? true) !== false;
        $headerFontSize = isset($config['header_font_size']) && is_numeric($config['header_font_size'])
            ? (float) $config['header_font_size']
            : $fontSize;
        $configuredHeaderHeight = isset($config['header_row_height_mm']) && is_numeric($config['header_row_height_mm'])
            ? (float) $config['header_row_height_mm']
            : null;
        $bodyRowHeight = $rowHeight;

        $x = (float) $field['x'];
        $y = (float) $field['y'];
        $w = (float) $field['w'];
        $h = (float) $field['h'];
        $maxY = $y + $h;

        $this->applyColor($pdf, $field['font_color'] ?? null);

        $headerHeight = 0.0;
        if ($headerRow) {
            $headerHeight = $this->measureMatrixHeaderRowHeight(
                $columns,
                $rowHeight,
                $headerFontSize,
                $configuredHeaderHeight,
            );

            if ($y + $headerHeight > $maxY) {
                return $y;
            }
        }

        // Long lists (e.g. FO3 with every test) shrink body text so rows are not cut off at the box edge.
        [$bodyColumns, $bodyFontSize, $bodyMethodFontSize] = $this->fitMatrixBodyFonts(
            $pdf,
            $rows,
            $columns,
            $w,
            $bodyRowHeight,
            $fontSize,
            $methodFontSize,
            $requestedFamily,
            $testNameBold,
            $maxY - ($y + $headerHeight),
            $config,
        );

        // Measure natural body heights for rows that fit; do not invent empty slots.
        $bodyPlan = [];
        $naturalBodyTotal = 0.0;
        $cursorAfterHeader = $y + $headerHeight;

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            $natural = $this->measureMatrixBodyRowHeight(
                $pdf,
                $row,
                $bodyColumns,
                $w,
                DynamicTestMatrix::rowMinHeightMm($config, $row, $bodyRowHeight),
                $bodyFontSize,
                $bodyMethodFontSize,
                $requestedFamily,
                $testNameBold,
                DynamicTestMatrix::rowSpanRole($row) === 'continue',
            ) + DynamicTestMatrix::rowExtraMm($config);

            if ($cursorAfterHeader + $naturalBodyTotal + $natural > $maxY + 0.01) {
                break;
            }

            $bodyPlan[] = ['row' => $row, 'natural' => $natural];
            $naturalBodyTotal += $natural;
        }

        $availableBody = max(0.0, $h - $headerHeight);
        $naturalHeights = array_column($bodyPlan, 'natural');
        // FO28 keeps the plotted box for 10 rows. A shorter list ends after the
        // last test so the unused rows stay open and the signatory block can
        // drop into that space instead of stretching empty cells.
        if ((string) ($field['name'] ?? '') === 'metals_fo28_matrix') {
            $config['stretch_body'] = false;
        }
        $stretchBody = ($config['stretch_body'] ?? true) !== false;
        $stretchHeights = $stretchBody
            ? DynamicTestMatrix::stretchBodyRowHeights($naturalHeights, $availableBody)
            : array_values($naturalHeights);

        $currentY = $y;

        if ($headerRow && $headerHeight > 0) {
            $this->writeMatrixHeaderRow(
                $pdf,
                $columns,
                $x,
                $currentY,
                $w,
                $headerHeight,
                $headerFontSize,
                $requestedFamily,
                $border,
                $headerBold,
            );
            $currentY += $headerHeight;
        }

        $planCount = count($bodyPlan);
        for ($index = 0; $index < $planCount; $index++) {
            $item = $bodyPlan[$index];
            $lineHeight = $stretchHeights[$index] ?? $item['natural'];

            $spanRows = [];
            $spanHeights = [];
            if (is_array($item['row']) && DynamicTestMatrix::rowSpanRole($item['row']) === 'start') {
                $spanRows[] = $item['row'];
                $spanHeights[] = $lineHeight;
                $look = $index + 1;
                while ($look < $planCount) {
                    $cand = $bodyPlan[$look];
                    if (
                        ! is_array($cand['row'] ?? null)
                        || ! DynamicTestMatrix::rowContinuesSpan($item['row'], $cand['row'])
                    ) {
                        break;
                    }
                    $spanRows[] = $cand['row'];
                    $spanHeights[] = $stretchHeights[$look] ?? $cand['natural'];
                    $look++;
                }
            }

            if (count($spanRows) >= 2) {
                $this->writeMatrixBodySpanGroup(
                    $pdf,
                    $bodyColumns,
                    $spanRows,
                    $spanHeights,
                    $x,
                    $currentY,
                    $w,
                    $bodyFontSize,
                    $bodyMethodFontSize,
                    $requestedFamily,
                    $border,
                    $testNameBold,
                );
                $currentY += array_sum($spanHeights);
                $index += count($spanRows) - 1;

                continue;
            }

            $this->writeMatrixBodyRow(
                $pdf,
                $bodyColumns,
                $item['row'],
                $x,
                $currentY,
                $w,
                $lineHeight,
                $bodyFontSize,
                $bodyMethodFontSize,
                $requestedFamily,
                $border,
                $testNameBold,
            );
            $currentY += $lineHeight;
        }

        // Stretch-on (Milk/FO2): frame the full designer field box.
        // Stretch-off (Nitrite): frame content only so leftover capacity is not a giant empty cell.
        $contentHeight = max(0.0, $currentY - $y);
        $frameHeight = $stretchBody ? $h : $contentHeight;
        if ($border && $frameHeight > 0.5) {
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.15);
            $pdf->Rect($x, $y, $w, $frameHeight);
        }

        return $currentY;
    }

    private function isFixedFormMetaField(string $name): bool
    {
        return in_array($name, ['fo2_form_meta', 'fo3_form_meta', 'fo37_form_meta', 'fo28_form_meta'], true);
    }

    /**
     * Signatory chrome that drops into the open rows under a short FO28 matrix.
     *
     * @var list<string>
     */
    private const FO28_SIGNATORY_FIELDS = [
        'fo28_label_analyzed_by',
        'fo28_label_approved_by',
        'results.analyst_name',
        'results.analyst_name_2',
        'fo28_role_analyzed',
        'fo28_role_approved',
        'fo28_prc_label',
        'results.analyst_prc',
        'fo28_prc_label_2',
        'results.analyst_prc_2',
    ];

    /**
     * Give FO28 signatories the same above-form-meta clamp FO2 and FO3 use,
     * measured from the plotted gap between each line and the form block.
     *
     * @param  array<string, mixed>  $field
     * @param  \Illuminate\Support\Collection<int, \App\Models\ControlledFormField>  $pageFields
     * @return array<string, mixed>
     */
    private function clampFo28SignatoriesAboveFormMeta(array $field, $pageFields): array
    {
        $name = (string) ($field['name'] ?? '');
        if (! in_array($name, self::FO28_SIGNATORY_FIELDS, true)) {
            return $field;
        }

        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : null;
        if ($anchor === null || trim((string) ($anchor['below'] ?? '')) !== 'metals_fo28_matrix') {
            return $field;
        }

        $fieldOffset = is_numeric($anchor['offset_mm'] ?? null) ? (float) $anchor['offset_mm'] : 0.0;
        $metaOffset = null;
        foreach ($pageFields as $pageField) {
            if ($pageField->name !== 'fo28_form_meta') {
                continue;
            }
            $metaAnchor = is_array($pageField->options['anchor'] ?? null) ? $pageField->options['anchor'] : [];
            if (is_numeric($metaAnchor['offset_mm'] ?? null)) {
                $metaOffset = (float) $metaAnchor['offset_mm'];
            }
            break;
        }
        if ($metaOffset === null || $metaOffset <= $fieldOffset) {
            return $field;
        }

        $anchor['above'] = 'fo28_form_meta';
        $anchor['above_offset_mm'] = $metaOffset - $fieldOffset;
        $options['anchor'] = $anchor;
        $field['options'] = $options;

        return $field;
    }

    /**
     * Rewrite field y from options.anchor.below (matrix) and/or options.anchor.above (fixed field).
     * When both are set: y = min(matrixBottom + offset_mm + optionalNudge, aboveY - above_offset_mm).
     * Nudge applies only when spare room above the Test Report clamp is large (short sheets);
     * long matrices use normal flow/clamp with no nudge.
     * Form-meta fields are never repositioned here (resolved separately).
     *
     * @param  array<string, mixed>  $field
     * @param  array<string, float>  $matrixBottomY
     * @param  array<string, float>  $absoluteFieldY
     * @return array<string, mixed>
     */
    private function applyAnchorBelow(
        array $field,
        array $matrixBottomY,
        array $absoluteFieldY = [],
    ): array {
        if ($this->isFixedFormMetaField((string) ($field['name'] ?? ''))) {
            return $field;
        }

        $options = is_array($field['options'] ?? null) ? $field['options'] : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : null;
        if ($anchor === null) {
            return $field;
        }

        $below = trim((string) ($anchor['below'] ?? ''));
        $above = trim((string) ($anchor['above'] ?? ''));
        $offset = is_numeric($anchor['offset_mm'] ?? null)
            ? (float) $anchor['offset_mm']
            : 0.0;
        $aboveOffset = is_numeric($anchor['above_offset_mm'] ?? null)
            ? (float) $anchor['above_offset_mm']
            : 0.0;

        $fromBelow = null;
        if ($below !== '' && array_key_exists($below, $matrixBottomY)) {
            $fromBelow = $matrixBottomY[$below] + $offset;
        }

        $fromAbove = null;
        if ($above !== '' && array_key_exists($above, $absoluteFieldY)) {
            $fromAbove = $absoluteFieldY[$above] - $aboveOffset;
        }

        if ($fromBelow === null && $fromAbove === null) {
            return $field;
        }

        if ($fromBelow !== null && $fromAbove !== null) {
            // Nudge only on short sheets with clear spare room; long matrices stay normal.
            $spare = $fromAbove - $fromBelow;
            // FO28 short sheets park the signatory stack in the open space above
            // the form block. A full sheet has no spare, so the plotted gaps stay.
            if ($above === 'fo28_form_meta' && $spare >= self::SIGNATORY_NUDGE_MIN_SPARE_MM) {
                $field['y'] = $fromAbove;
            } else {
                $nudge = $spare >= self::SIGNATORY_NUDGE_MIN_SPARE_MM
                    ? self::SIGNATORY_NUDGE_MM
                    : 0.0;
                $field['y'] = min($fromBelow + $nudge, $fromAbove);
            }
        } elseif ($fromBelow !== null) {
            $field['y'] = $fromBelow;
        } else {
            $field['y'] = $fromAbove;
        }

        return $field;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function writeMatrixHeaderRow(
        Fpdi $pdf,
        array $columns,
        float $x,
        float $y,
        float $totalWidth,
        float $rowHeight,
        float $fontSize,
        string $requestedFamily,
        bool $border,
        bool $headerBold,
    ): void {
        $cursorX = $x;

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);
            $colFontSize = isset($column['header_font_size']) && is_numeric($column['header_font_size'])
                ? (float) $column['header_font_size']
                : (isset($column['font_size']) && is_numeric($column['font_size'])
                    ? (float) $column['font_size']
                    : $fontSize);
            $align = strtoupper((string) ($column['header_align'] ?? 'C'));
            if (! in_array($align, ['L', 'C', 'R'], true)) {
                $align = 'C';
            }

            if ($border) {
                $pdf->SetDrawColor(0, 0, 0);
                $pdf->SetLineWidth(0.1);
                $pdf->Rect($cursorX, $y, $colWidth, $rowHeight);
            }

            $label = (string) ($column['label'] ?? strtoupper((string) $column['key']));
            $sublines = $this->matrixHeaderSublines($column);
            $padding = 1.0;
            $gap = 0.35;
            $labelLineHeight = $this->matrixLineHeight($colFontSize);
            $subAlign = strtoupper((string) ($column['sublabel_align'] ?? $align));
            if (! in_array($subAlign, ['L', 'C', 'R'], true)) {
                $subAlign = $align;
            }

            if ($sublines !== []) {
                $subFontSize = max(6, $colFontSize - 1);
                $subLineHeight = $this->matrixLineHeight($subFontSize);
                $startY = $y + $padding;

                $this->applyTcpdfFont($pdf, $requestedFamily, $headerBold ? 'B' : '', $colFontSize);
                $this->writeAlignedMatrixText(
                    $pdf,
                    $cursorX,
                    $startY,
                    $colWidth,
                    $labelLineHeight,
                    $label,
                    $align,
                );

                $this->applyTcpdfFont($pdf, $requestedFamily, '', $subFontSize);
                $lineY = $startY + $labelLineHeight + $gap;
                foreach ($sublines as $subline) {
                    $this->writeAlignedMatrixText(
                        $pdf,
                        $cursorX,
                        $lineY,
                        $colWidth,
                        $subLineHeight,
                        $subline,
                        $subAlign,
                    );
                    $lineY += $subLineHeight;
                }
            } else {
                $startY = $y + (($rowHeight - $labelLineHeight) / 2);

                $this->applyTcpdfFont($pdf, $requestedFamily, $headerBold ? 'B' : '', $colFontSize);
                $this->writeAlignedMatrixText(
                    $pdf,
                    $cursorX,
                    $startY,
                    $colWidth,
                    $labelLineHeight,
                    $label,
                    $align,
                );
            }

            $cursorX += $colWidth;
        }
    }

    private function writeAlignedMatrixText(
        Fpdi $pdf,
        float $x,
        float $y,
        float $width,
        float $height,
        string $text,
        string $align,
    ): void {
        $textWidth = $pdf->GetStringWidth($text);
        $pad = 0.6;
        $drawX = match ($align) {
            'R' => $x + max($pad, $width - $textWidth - $pad),
            'C' => $x + max(0.0, ($width - $textWidth) / 2),
            default => $x + $pad,
        };

        $pdf->SetXY($drawX, $y);
        $pdf->Cell(max($textWidth, 0.1), $height, $text, 0, 0, 'L');
    }

    /**
     * Raw/Finish pair: Test/Method/Remarks as one tall cell; Results/Acceptable Values split.
     *
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $startRow
     * @param  array<string, mixed>  $continueRow
     */
    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  list<array<string, mixed>>  $rows  start + one or more continue rows
     * @param  list<float>  $heightsMm
     */
    private function writeMatrixBodySpanGroup(
        Fpdi $pdf,
        array $columns,
        array $rows,
        array $heightsMm,
        float $x,
        float $y,
        float $totalWidth,
        float $fontSize,
        float $methodFontSize,
        string $requestedFamily,
        bool $border,
        bool $testNameBold,
    ): void {
        $spanHeight = array_sum($heightsMm);
        $startRow = $rows[0] ?? [];
        $cursorX = $x;

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $key = (string) $column['key'];
            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);

            if (DynamicTestMatrix::isRowSpanColumnKey($key)) {
                $this->writeMatrixBodyCell(
                    $pdf,
                    $columns,
                    $column,
                    $startRow,
                    $cursorX,
                    $y,
                    $colWidth,
                    $spanHeight,
                    $fontSize,
                    $methodFontSize,
                    $requestedFamily,
                    $border,
                    $testNameBold,
                );
            } else {
                $cellY = $y;
                foreach ($rows as $rowIndex => $row) {
                    $rowHeight = $heightsMm[$rowIndex] ?? 0.0;
                    $this->writeMatrixBodyCell(
                        $pdf,
                        $columns,
                        $column,
                        $row,
                        $cursorX,
                        $cellY,
                        $colWidth,
                        $rowHeight,
                        $fontSize,
                        $methodFontSize,
                        $requestedFamily,
                        $border,
                        $testNameBold,
                    );
                    $cellY += $rowHeight;
                }
            }

            $cursorX += $colWidth;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $column
     * @param  array<string, mixed>  $row
     */
    private function writeMatrixBodyCell(
        Fpdi $pdf,
        array $columns,
        array $column,
        array $row,
        float $cursorX,
        float $y,
        float $colWidth,
        float $rowHeight,
        float $fontSize,
        float $methodFontSize,
        string $requestedFamily,
        bool $border,
        bool $testNameBold,
    ): void {
        $key = (string) $column['key'];
        $colFontSize = isset($column['font_size']) && is_numeric($column['font_size'])
            ? (float) $column['font_size']
            : $fontSize;
        $align = strtoupper((string) ($column['align'] ?? ($key === 'test' ? 'L' : 'C')));
        if (! in_array($align, ['L', 'C', 'R'], true)) {
            $align = $key === 'test' ? 'L' : 'C';
        }

        if ($border) {
            $pdf->SetDrawColor(0, 0, 0);
            $pdf->SetLineWidth(0.1);
            $pdf->Rect($cursorX, $y, $colWidth, $rowHeight);
        }

        if ($key === 'test') {
            $name = (string) ($row['test'] ?? '');
            $nestMethod = ! $this->matrixHasColumnKey($columns, 'method');
            $method = $nestMethod ? (string) ($row['test_method'] ?? '') : '';
            $detail = (string) ($row['test_detail'] ?? '');
            $innerWidth = max(1.0, $colWidth - 1.6);
            [$padding, $gap] = $this->matrixTestCellSpacing($detail);
            $detailFontSize = $this->matrixDetailFontSize($methodFontSize);
            $centerName = $this->matrixColumnValign($column, $key) === 'M' && $detail === '' && $method === '';
            $textY = $y + $padding;

            if ($name !== '' && $centerName) {
                $this->applyTcpdfFont($pdf, $requestedFamily, $testNameBold ? 'B' : '', $colFontSize);
                $blockHeight = $this->matrixTextOverflows($pdf, $pdf->GetStringWidth($name), $innerWidth)
                    ? $pdf->getStringHeight($innerWidth, $name)
                    : $this->matrixLineHeight($colFontSize);
                $textY = $y + max(0.2, ($rowHeight - $blockHeight) / 2);
            }

            if ($name !== '') {
                $this->applyTcpdfFont($pdf, $requestedFamily, $testNameBold ? 'B' : '', $colFontSize);
                $nameLineHeight = $this->matrixTestCellLineHeight($colFontSize, $detail);
                if ($this->matrixTextOverflows($pdf, $pdf->GetStringWidth($name), $innerWidth)) {
                    $pdf->MultiCell(
                        $innerWidth,
                        $nameLineHeight,
                        $name,
                        0,
                        $align,
                        false,
                        1,
                        $cursorX + 0.8,
                        $textY,
                        true,
                    );
                    $textY += $pdf->getLastH();
                } else {
                    $pdf->SetXY($cursorX + 0.8, $textY);
                    $pdf->Cell($innerWidth, $nameLineHeight, $name, 0, 0, $align);
                    $textY += $nameLineHeight;
                }
            }

            if ($detail !== '') {
                if ($name !== '') {
                    $textY += $gap;
                }

                $this->applyTcpdfFont($pdf, $requestedFamily, '', $detailFontSize);
                $pdf->SetXY($cursorX + 0.8, $textY);
                if ($this->matrixTextOverflows($pdf, $pdf->GetStringWidth($detail), $innerWidth)) {
                    $pdf->MultiCell(
                        $innerWidth,
                        $this->matrixLineHeight($detailFontSize),
                        $detail,
                        0,
                        $align,
                        false,
                        1,
                        $cursorX + 0.8,
                        $textY,
                        true,
                    );
                    $textY += $pdf->getLastH();
                } else {
                    $detailLineHeight = $this->matrixTestCellLineHeight($detailFontSize, $detail);
                    $pdf->Cell($innerWidth, $detailLineHeight, $detail, 0, 0, $align);
                    $textY += $detailLineHeight;
                }
            }

            if ($method !== '') {
                if ($name !== '' || $detail !== '') {
                    $textY += $gap;
                }

                $this->applyTcpdfFont($pdf, $requestedFamily, '', $methodFontSize);
                $methodLineHeight = $this->matrixLineHeight($methodFontSize);
                $pdf->SetXY($cursorX + 0.8, $textY);
                $pdf->MultiCell(
                    $innerWidth,
                    $methodLineHeight,
                    $method,
                    0,
                    $align,
                    false,
                    1,
                    $cursorX + 0.8,
                    $textY,
                    true,
                );
            }

            return;
        }

        $text = $this->matrixCellValue($row, $key);
        if ($text === '') {
            return;
        }

        $this->applyTcpdfFont($pdf, $requestedFamily, '', $colFontSize);
        $lineHeight = $this->matrixLineHeight($colFontSize);
        $innerWidth = max(1.0, $colWidth - 1.6);
        $scientific = $this->splitScientificValue($text);
        $textWidth = $scientific !== null
            ? $this->scientificValueWidth($pdf, $scientific, $requestedFamily, $colFontSize)
            : $pdf->GetStringWidth($text);
        $topAligned = $this->matrixColumnValign($column, $key) === 'T';
        $wrap = $topAligned
            || strlen($text) > 40
            || $this->matrixTextOverflows($pdf, $textWidth, $innerWidth);

        if ($scientific !== null) {
            $sciFontSize = $colFontSize;
            while ($sciFontSize > 5.0 && $this->matrixTextOverflows($pdf, $textWidth, $innerWidth)) {
                $sciFontSize = max(5.0, $sciFontSize - 0.25);
                $textWidth = $this->scientificValueWidth($pdf, $scientific, $requestedFamily, $sciFontSize);
            }
            $sciLineHeight = $this->matrixLineHeight($sciFontSize);
            $this->writeScientificValue(
                $pdf,
                $scientific,
                $cursorX + 0.8,
                $topAligned ? $y + 1.0 : $y + ($rowHeight / 2) - ($sciLineHeight / 2),
                $innerWidth,
                $sciLineHeight,
                $textWidth,
                $align,
                $requestedFamily,
                $sciFontSize,
            );
            $this->applyTcpdfFont($pdf, $requestedFamily, '', $colFontSize);
        } elseif ($wrap) {
            $text = $this->plainScientific($text);
            $wrapY = $y + 1.0;
            if (! $topAligned) {
                $wrapHeight = $pdf->getStringHeight($innerWidth, $text);
                $wrapY = $y + max(1.0, ($rowHeight - $wrapHeight) / 2);
            }
            $pdf->MultiCell(
                $innerWidth,
                $lineHeight,
                $text,
                0,
                $align,
                false,
                1,
                $cursorX + 0.8,
                $wrapY,
                true,
            );
        } else {
            $pdf->SetXY($cursorX + 0.8, $y + ($rowHeight / 2) - ($lineHeight / 2));
            $pdf->Cell($innerWidth, $lineHeight, $this->plainScientific($text), 0, 0, $align);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $row
     */
    private function writeMatrixBodyRow(
        Fpdi $pdf,
        array $columns,
        array $row,
        float $x,
        float $y,
        float $totalWidth,
        float $rowHeight,
        float $fontSize,
        float $methodFontSize,
        string $requestedFamily,
        bool $border,
        bool $testNameBold,
    ): void {
        $cursorX = $x;

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);
            $this->writeMatrixBodyCell(
                $pdf,
                $columns,
                $column,
                $row,
                $cursorX,
                $y,
                $colWidth,
                $rowHeight,
                $fontSize,
                $methodFontSize,
                $requestedFamily,
                $border,
                $testNameBold,
            );
            $cursorX += $colWidth;
        }
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function measureMatrixHeaderRowHeight(
        array $columns,
        float $baseRowHeight,
        float $fontSize,
        ?float $configuredHeaderHeight = null,
    ): float {
        $padding = 1.0;
        $gap = 0.35;
        $maxHeight = $configuredHeaderHeight ?? $baseRowHeight;

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $colFontSize = isset($column['header_font_size']) && is_numeric($column['header_font_size'])
                ? (float) $column['header_font_size']
                : $fontSize;
            $labelLineHeight = $this->matrixLineHeight($colFontSize);
            $sublines = $this->matrixHeaderSublines($column);
            $colHeight = $padding + $labelLineHeight + $padding;

            if ($sublines !== []) {
                $subLineHeight = $this->matrixLineHeight(max(6, $colFontSize - 1));
                $colHeight = $padding + $labelLineHeight + $gap + (count($sublines) * $subLineHeight) + $padding;
            }

            $maxHeight = max($maxHeight, $colHeight);
        }

        return $maxHeight;
    }

    /**
     * @param  array<string, mixed>  $column
     * @return list<string>
     */
    private function matrixHeaderSublines(array $column): array
    {
        if (isset($column['sublabels']) && is_array($column['sublabels'])) {
            return array_values(array_filter(
                array_map(static fn ($line): string => trim((string) $line), $column['sublabels']),
                static fn (string $line): bool => $line !== '',
            ));
        }

        $sublabel = isset($column['sublabel']) ? (string) $column['sublabel'] : '';
        if ($sublabel === '') {
            return [];
        }

        $parts = preg_split("/\r\n|\n|\r/", $sublabel) ?: [];

        return array_values(array_filter(
            array_map(static fn ($line): string => trim((string) $line), $parts),
            static fn (string $line): bool => $line !== '',
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function matrixTestColumn(array $columns): ?array
    {
        foreach ($columns as $column) {
            if (is_array($column) && ($column['key'] ?? null) === 'test') {
                return $column;
            }
        }

        return null;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $row
     */
    private function measureMatrixBodyRowHeight(
        Fpdi $pdf,
        array $row,
        array $columns,
        float $totalWidth,
        float $baseRowHeight,
        float $fontSize,
        float $methodFontSize,
        string $requestedFamily,
        bool $testNameBold,
        bool $skipSpanColumns = false,
    ): float {
        $maxHeight = $baseRowHeight;
        $separateMethodColumn = $this->matrixHasColumnKey($columns, 'method');

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $key = (string) $column['key'];
            if ($skipSpanColumns && DynamicTestMatrix::isRowSpanColumnKey($key)) {
                continue;
            }
            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);
            $colFontSize = isset($column['font_size']) && is_numeric($column['font_size'])
                ? (float) $column['font_size']
                : $fontSize;
            $innerWidth = max(1.0, $colWidth - 1.6);
            $detail = $key === 'test' ? (string) ($row['test_detail'] ?? '') : '';
            [$padding, $gap] = $this->matrixTestCellSpacing($detail);
            $height = $padding;

            if ($key === 'test') {
                $name = (string) ($row['test'] ?? '');
                $method = $separateMethodColumn ? '' : (string) ($row['test_method'] ?? '');

                if ($name !== '') {
                    $this->applyTcpdfFont($pdf, $requestedFamily, $testNameBold ? 'B' : '', $colFontSize);
                    $height += $this->matrixTextOverflows($pdf, $pdf->GetStringWidth($name), $innerWidth)
                        ? $pdf->getStringHeight($innerWidth, $name)
                        : $this->matrixTestCellLineHeight($colFontSize, $detail);
                }

                if ($detail !== '') {
                    if ($name !== '') {
                        $height += $gap;
                    }

                    $detailFontSize = $this->matrixDetailFontSize($methodFontSize);
                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $detailFontSize);
                    $height += $this->matrixTextOverflows($pdf, $pdf->GetStringWidth($detail), $innerWidth)
                        ? $pdf->getStringHeight($innerWidth, $detail)
                        : $this->matrixTestCellLineHeight($detailFontSize, $detail);
                }

                if ($method !== '') {
                    if ($name !== '' || $detail !== '') {
                        $height += $gap;
                    }

                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $methodFontSize);
                    $height += $pdf->getStringHeight($innerWidth, $method);
                }
            } else {
                $text = $this->matrixCellValue($row, $key);
                if ($text !== '') {
                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $colFontSize);
                    $height += $this->splitScientificValue($text) !== null
                        ? $this->matrixLineHeight($colFontSize)
                        : $pdf->getStringHeight($innerWidth, $text);
                }
            }

            $maxHeight = max($maxHeight, $height + $padding);
        }

        return $maxHeight;
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     */
    private function matrixHasColumnKey(array $columns, string $key): bool
    {
        foreach ($columns as $column) {
            if (is_array($column) && ($column['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<mixed>  $rows
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $config
     * @return array{0: list<array<string, mixed>>, 1: float, 2: float}
     */
    private function fitMatrixBodyFonts(
        Fpdi $pdf,
        array $rows,
        array $columns,
        float $totalWidth,
        float $rowHeight,
        float $fontSize,
        float $methodFontSize,
        string $family,
        bool $testNameBold,
        float $availableHeight,
        array $config = [],
    ): array {
        $minimum = 6.0;
        $measureTotal = function (array $cols, float $bodySize, float $methodSize) use ($pdf, $rows, $totalWidth, $rowHeight, $family, $testNameBold, $config): float {
            $total = 0.0;
            foreach ($rows as $row) {
                if (is_array($row)) {
                    $total += $this->measureMatrixBodyRowHeight(
                        $pdf,
                        $row,
                        $cols,
                        $totalWidth,
                        DynamicTestMatrix::rowMinHeightMm($config, $row, $rowHeight),
                        $bodySize,
                        $methodSize,
                        $family,
                        $testNameBold,
                        DynamicTestMatrix::rowSpanRole($row) === 'continue',
                    ) + DynamicTestMatrix::rowExtraMm($config);
                }
            }

            return $total;
        };

        if ($measureTotal($columns, $fontSize, $methodFontSize) <= $availableHeight + 0.01) {
            return [$columns, $fontSize, $methodFontSize];
        }

        // Stage 1: long method text gives way first so test names / results keep designer sizes.
        $methodIndex = null;
        $methodColumnSize = null;
        foreach ($columns as $index => $column) {
            if (is_array($column) && ($column['key'] ?? null) === 'method') {
                $methodIndex = $index;
                $methodColumnSize = isset($column['font_size']) && is_numeric($column['font_size'])
                    ? (float) $column['font_size']
                    : $fontSize;
            }
        }

        $shrinkMethodColumn = $methodIndex !== null;
        $nestedMethodSize = $methodFontSize;
        $columnSize = $methodColumnSize ?? 0.0;

        while (true) {
            $nextColumnSize = $shrinkMethodColumn && $columnSize - 0.5 >= $minimum ? $columnSize - 0.5 : $columnSize;
            $nextNestedSize = $nestedMethodSize - 0.5 >= $minimum ? $nestedMethodSize - 0.5 : $nestedMethodSize;
            if ($nextColumnSize === $columnSize && $nextNestedSize === $nestedMethodSize) {
                break;
            }

            $columnSize = $nextColumnSize;
            $nestedMethodSize = $nextNestedSize;
            $candidate = $columns;
            if ($shrinkMethodColumn) {
                $candidate[$methodIndex]['font_size'] = $columnSize;
            }

            if ($measureTotal($candidate, $fontSize, $nestedMethodSize) <= $availableHeight + 0.01) {
                return [$candidate, $fontSize, $nestedMethodSize];
            }
        }

        // Stage 2: still too tall — shrink every body column together.
        $reference = max($fontSize, $methodFontSize);
        foreach ($columns as $column) {
            if (is_array($column) && isset($column['font_size']) && is_numeric($column['font_size'])) {
                $reference = max($reference, (float) $column['font_size']);
            }
        }

        // Lower floor than stage 1: smaller text beats silently dropping test rows.
        $minimum = 5.0;
        $scale = 1.0;
        $scaledColumns = $columns;

        for ($step = 1; ; $step++) {
            $total = $measureTotal($scaledColumns, $fontSize * $scale, $methodFontSize * $scale);

            $nextReference = $reference - (0.5 * $step);
            if ($total <= $availableHeight + 0.01 || $nextReference < $minimum) {
                break;
            }

            $scale = $nextReference / $reference;
            $scaledColumns = array_map(function ($column) use ($scale) {
                if (is_array($column) && isset($column['font_size']) && is_numeric($column['font_size'])) {
                    $column['font_size'] = (float) $column['font_size'] * $scale;
                }

                return $column;
            }, $columns);
        }

        return [$scaledColumns, $fontSize * $scale, $methodFontSize * $scale];
    }

    private function matrixTextOverflows(Fpdi $pdf, float $textWidth, float $cellWidth): bool
    {
        $padding = $pdf->getCellPaddings();

        return $textWidth > $cellWidth - (float) $padding['L'] - (float) $padding['R'] + 0.01;
    }

    /**
     * Split "4×10⁻⁴ mg/L" into base / ASCII exponent / tail. Core PDF fonts lack Unicode superscripts.
     *
     * @return array{base: string, exponent: string, tail: string}|null
     */
    private function splitScientificValue(string $text): ?array
    {
        if (! preg_match('/^(.*?×\s?10)([⁺⁻]?[⁰¹²³⁴⁵⁶⁷⁸⁹]+)(.*)$/u', $text, $matches)) {
            return null;
        }

        return [
            'base' => $matches[1],
            'exponent' => strtr($matches[2], [
                '⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4',
                '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9',
                '⁺' => '+', '⁻' => '-',
            ]),
            'tail' => $matches[3],
        ];
    }

    /**
     * "Weight 2.3×10⁻⁶ g" → "Weight 2.3×10-6 g" for text that cannot be drawn as base + superscript.
     */
    private function plainScientific(string $text): string
    {
        if (! str_contains($text, '×')) {
            return $text;
        }

        return (string) preg_replace_callback(
            '/×(\s?)10([⁺⁻]?[⁰¹²³⁴⁵⁶⁷⁸⁹]+)/u',
            fn (array $m): string => '×'.$m[1].'10'.strtr($m[2], [
                '⁰' => '0', '¹' => '1', '²' => '2', '³' => '3', '⁴' => '4',
                '⁵' => '5', '⁶' => '6', '⁷' => '7', '⁸' => '8', '⁹' => '9',
                '⁺' => '+', '⁻' => '-',
            ]),
            $text,
        );
    }

    /**
     * Draw base + superscript power on one line inside a field box, shrinking the font to fit.
     *
     * @param  array{base: string, exponent: string, tail: string}  $parts
     */
    private function writeScientificInBox(
        Fpdi $pdf,
        array $parts,
        float $x,
        float $y,
        float $w,
        float $h,
        string $align,
        string $family,
        float $fontSize,
    ): void {
        $innerWidth = max(1.0, $w - 0.4);
        $size = $fontSize;
        $textWidth = $this->scientificValueWidth($pdf, $parts, $family, $size);
        while ($size > 5.0 && $textWidth > $innerWidth) {
            $size = max(5.0, $size - 0.25);
            $textWidth = $this->scientificValueWidth($pdf, $parts, $family, $size);
        }

        $lineHeight = $this->matrixLineHeight($size);
        $this->writeScientificValue(
            $pdf,
            $parts,
            $x + 0.2,
            $y + max(0.0, ($h - $lineHeight) / 2),
            $innerWidth,
            $lineHeight,
            $textWidth,
            $align,
            $family,
            $size,
        );
        $this->applyTcpdfFont($pdf, $family, '', $fontSize);
    }

    private function scientificExponentSize(float $fontSize): float
    {
        return max(4.0, $fontSize * 0.65);
    }

    /**
     * @param  array{base: string, exponent: string, tail: string}  $parts
     */
    private function scientificValueWidth(Fpdi $pdf, array $parts, string $family, float $fontSize): float
    {
        $this->applyTcpdfFont($pdf, $family, '', $fontSize);
        $width = $pdf->GetStringWidth($parts['base']) + $pdf->GetStringWidth($parts['tail']);
        $this->applyTcpdfFont($pdf, $family, '', $this->scientificExponentSize($fontSize));
        $width += $pdf->GetStringWidth($parts['exponent']);
        $this->applyTcpdfFont($pdf, $family, '', $fontSize);

        return $width;
    }

    /**
     * @param  array{base: string, exponent: string, tail: string}  $parts
     */
    private function writeScientificValue(
        Fpdi $pdf,
        array $parts,
        float $x,
        float $y,
        float $width,
        float $lineHeight,
        float $textWidth,
        string $align,
        string $family,
        float $fontSize,
    ): void {
        $drawX = match ($align) {
            'R' => $x + max(0.0, $width - $textWidth),
            'C' => $x + max(0.0, ($width - $textWidth) / 2),
            default => $x,
        };
        $expSize = $this->scientificExponentSize($fontSize);
        $paddings = $pdf->getCellPaddings();
        $pdf->setCellPaddings(0, 0, 0, 0);

        $this->applyTcpdfFont($pdf, $family, '', $fontSize);
        $baseWidth = $pdf->GetStringWidth($parts['base']);
        $pdf->SetXY($drawX, $y);
        $pdf->Cell($baseWidth, $lineHeight, $parts['base'], 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $drawX += $baseWidth;

        $this->applyTcpdfFont($pdf, $family, '', $expSize);
        $expWidth = $pdf->GetStringWidth($parts['exponent']);
        $pdf->SetXY($drawX, $y - ($lineHeight * 0.35));
        $pdf->Cell($expWidth, $lineHeight, $parts['exponent'], 0, 0, 'L', false, '', 0, false, 'T', 'M');
        $drawX += $expWidth;

        $this->applyTcpdfFont($pdf, $family, '', $fontSize);
        if ($parts['tail'] !== '') {
            $pdf->SetXY($drawX, $y);
            $pdf->Cell($pdf->GetStringWidth($parts['tail']), $lineHeight, $parts['tail'], 0, 0, 'L', false, '', 0, false, 'T', 'M');
        }

        $pdf->setCellPaddings($paddings['L'], $paddings['T'], $paddings['R'], $paddings['B']);
    }

    /**
     * Body vertical alignment. Method and acceptable-value columns stay top-aligned
     * unless the column sets valign to M (FO28 test and method).
     *
     * @param  array<string, mixed>  $column
     */
    private function matrixColumnValign(array $column, string $key): string
    {
        $valign = strtoupper((string) ($column['valign'] ?? ''));
        if (in_array($valign, ['M', 'MIDDLE', 'C'], true)) {
            return 'M';
        }
        if (in_array($valign, ['T', 'TOP'], true)) {
            return 'T';
        }

        if (in_array($key, ['method', 'acceptable_values', 'test'], true)) {
            return 'T';
        }

        return 'M';
    }

    /**
     * Rows with a Time/Date line (FO2) use tighter spacing so a full list fits the box.
     *
     * @return array{0: float, 1: float}
     */
    private function matrixTestCellSpacing(string $detail): array
    {
        return $detail !== '' ? [0.4, 0.1] : [1.0, 0.35];
    }

    private function matrixDetailFontSize(float $methodFontSize): float
    {
        return min($methodFontSize, 5.5);
    }

    private function matrixLineHeight(float $fontSizePt): float
    {
        return max(2.5, $fontSizePt * 0.352778 * 1.2);
    }

    /**
     * Single-line height inside test cells that carry a Time/Date line, so 17 FO2 rows
     * fit the box at the designer font size.
     */
    private function matrixTestCellLineHeight(float $fontSizePt, string $detail): float
    {
        return $detail !== '' ? $fontSizePt * 0.352778 * 1.05 : $this->matrixLineHeight($fontSizePt);
    }

    /**
     * @param  list<array<string, mixed>>  $columns
     * @param  array<string, mixed>  $column
     */
    private function matrixColumnWidth(array $columns, array $column, float $totalWidth): float
    {
        $widths = DynamicTestMatrix::normalizedColumnWidths($columns, $totalWidth);

        foreach ($columns as $index => $candidate) {
            if (! is_array($candidate)) {
                continue;
            }

            if ($candidate === $column || ($candidate['key'] ?? null) === ($column['key'] ?? null)) {
                return (float) ($widths[$index] ?? 0);
            }
        }

        return $totalWidth / max(1, count($columns));
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function matrixCellValue(array $row, string $key): string
    {
        if ($key === 'result' && ! filled($row['result'] ?? null) && filled($row['sample_1'] ?? null)) {
            return (string) $row['sample_1'];
        }

        $value = $row[$key] ?? null;

        return $value !== null && $value !== '' ? (string) $value : '';
    }

    private function applyColor(Fpdi $pdf, ?string $hex): void
    {
        $rgb = $this->hexToRgb($hex);
        $pdf->SetTextColor($rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @return array{0: int, 1: int, 2: int}
     */
    private function hexToRgb(?string $hex): array
    {
        $hex = ltrim((string) $hex, '#');
        if (strlen($hex) === 3) {
            $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
        }
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return [0, 0, 0];
        }

        return [
            hexdec(substr($hex, 0, 2)),
            hexdec(substr($hex, 2, 2)),
            hexdec(substr($hex, 4, 2)),
        ];
    }

    private function applyTcpdfFont(Fpdi $pdf, string $family, string $style, float $size): void
    {
        $resolved = $this->tcpdfFamily($family);

        if ($resolved === TcpdfCalibriFont::FAMILY && str_contains($style, 'B') && TcpdfCalibriFont::ensureBold()) {
            $pdf->SetFont(TcpdfCalibriFont::BOLD_FAMILY, str_replace('B', '', $style), $size, TcpdfCalibriFont::boldPhpPath());

            return;
        }

        if ($resolved === TcpdfCalibriFont::FAMILY && TcpdfCalibriFont::ensure()) {
            $pdf->SetFont(TcpdfCalibriFont::FAMILY, $style, $size, TcpdfCalibriFont::phpPath());

            return;
        }

        $pdf->SetFont($resolved === TcpdfCalibriFont::FAMILY ? 'helvetica' : $resolved, $style, $size);
    }

    private function tcpdfFamily(string $family): string
    {
        $normalized = strtolower($family);

        return match ($normalized) {
            'times', 'times-roman', 'serif' => 'times',
            'courier', 'monospace' => 'courier',
            'helvetica', 'arial' => 'helvetica',
            default => TcpdfCalibriFont::FAMILY,
        };
    }

    private function openSource(Fpdi $pdf, string $path): int
    {
        app(PdfCompatibilityNormalizer::class)->ensureCompatible($path);

        return $pdf->setSourceFile($path);
    }

    private function newPdf(ControlledFormRevision $revision): Fpdi
    {
        $page = $revision->page();
        $pdf = new Fpdi('P', $page['unit'] ?? 'mm', [$page['width'], $page['height']], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(false);
        if (app()->runningUnitTests()) {
            $pdf->setCompression(false);
        }

        return $pdf;
    }
}
