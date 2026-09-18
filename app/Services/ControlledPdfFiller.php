<?php

namespace App\Services;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledFormRevision;
use App\Support\DynamicTestMatrix;
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

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            foreach ($revision->fields as $field) {
                if ($field->page_number !== $pageNo) {
                    continue;
                }

                $name = $field->name;
                if (! array_key_exists($name, $values)) {
                    continue;
                }

                $this->writeField($pdf, $field->toOverlayArray(), $values[$name], $values);
            }
        }

        return $pdf->Output('', 'S');
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
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        return $pdf->Output('', 'S');
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

        if ($value === null || $value === '') {
            return;
        }

        $this->applyColor($pdf, $field['font_color'] ?? null);
        $this->applyTcpdfFont($pdf, (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY), '', $fontSize);
        $pdf->SetXY($x, $y);

        if ($type === ControlledFormFieldType::Multiline->value) {
            $lineHeight = max(3.0, $fontSize * 0.42);
            $pdf->MultiCell(
                $w,
                $lineHeight,
                (string) $value,
                0,
                $align,
                false,
                1,
                $x,
                $y,
                true,
                0,
                false,
                true,
                $h,
            );

            return;
        }

        $pdf->Cell($w, $h, (string) $value, 0, 0, $align, false, '', 1);
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
            $pdf->MultiCell((float) $field['w'], (float) $field['h'], implode("\n", $lines), 0, 'L', false, 1);

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
                $this->applyTcpdfFont($pdf, $requestedFamily, '', $fontSize);
                $pdf->SetXY($x, $y);
                $pdf->Cell($w, $rowHeight, (string) $text, 0, 0, in_array($align, ['L', 'C', 'R'], true) ? $align : 'L', false, '', 1);
            }
        }
    }

    /**
     * @param  array{x: float, y: float, w: float, h: float, font_size: float, align: string, font_family?: string, font_color?: string, table_config?: array<int|string, mixed>|null}  $field
     * @param  list<array<string, mixed>>  $rows
     * @param  array<string, mixed>  $values
     */
    private function writeDynamicTestMatrix(Fpdi $pdf, array $field, array $rows, array $values = []): void
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
                return;
            }
        }

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
                $columns,
                $w,
                $bodyRowHeight,
                $fontSize,
                $methodFontSize,
                $requestedFamily,
                $testNameBold,
            );

            if ($cursorAfterHeader + $naturalBodyTotal + $natural > $maxY + 0.01) {
                break;
            }

            $bodyPlan[] = ['row' => $row, 'natural' => $natural];
            $naturalBodyTotal += $natural;
        }

        $availableBody = max(0.0, $h - $headerHeight);
        $naturalHeights = array_column($bodyPlan, 'natural');
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

        foreach ($bodyPlan as $index => $item) {
            $lineHeight = $stretchHeights[$index] ?? $item['natural'];

            $this->writeMatrixBodyRow(
                $pdf,
                $columns,
                $item['row'],
                $x,
                $currentY,
                $w,
                $lineHeight,
                $fontSize,
                $methodFontSize,
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

            $key = (string) $column['key'];
            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);
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
                $padding = 1.0;
                $gap = 0.35;
                $textY = $y + $padding;

                if ($name !== '') {
                    $this->applyTcpdfFont($pdf, $requestedFamily, $testNameBold ? 'B' : '', $colFontSize);
                    $nameLineHeight = $this->matrixLineHeight($colFontSize);
                    $pdf->SetXY($cursorX + 0.8, $textY);
                    $pdf->Cell($innerWidth, $nameLineHeight, $name, 0, 0, $align);
                    $textY += $nameLineHeight;
                }

                if ($detail !== '') {
                    if ($name !== '') {
                        $textY += $gap;
                    }

                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $methodFontSize);
                    $detailLineHeight = $this->matrixLineHeight($methodFontSize);
                    $pdf->SetXY($cursorX + 0.8, $textY);
                    $pdf->MultiCell(
                        $innerWidth,
                        $detailLineHeight,
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
            } else {
                $text = $this->matrixCellValue($row, $key);
                if ($text !== '') {
                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $colFontSize);
                    $lineHeight = $this->matrixLineHeight($colFontSize);
                    $innerWidth = max(1.0, $colWidth - 1.6);
                    $wrap = in_array($key, ['method', 'acceptable_values', 'remarks'], true)
                        || strlen($text) > 40;

                    if ($wrap) {
                        $pdf->MultiCell(
                            $innerWidth,
                            $lineHeight,
                            $text,
                            0,
                            $align,
                            false,
                            1,
                            $cursorX + 0.8,
                            $y + 1.0,
                            true,
                        );
                    } else {
                        $pdf->SetXY($cursorX + 0.8, $y + ($rowHeight / 2) - ($lineHeight / 2));
                        $pdf->Cell($innerWidth, $lineHeight, $text, 0, 0, $align);
                    }
                }
            }

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
    ): float {
        $padding = 1.0;
        $gap = 0.35;
        $maxHeight = $baseRowHeight;
        $separateMethodColumn = $this->matrixHasColumnKey($columns, 'method');

        foreach ($columns as $column) {
            if (! is_array($column) || ! isset($column['key'])) {
                continue;
            }

            $key = (string) $column['key'];
            $colWidth = $this->matrixColumnWidth($columns, $column, $totalWidth);
            $colFontSize = isset($column['font_size']) && is_numeric($column['font_size'])
                ? (float) $column['font_size']
                : $fontSize;
            $innerWidth = max(1.0, $colWidth - 1.6);
            $height = $padding;

            if ($key === 'test') {
                $name = (string) ($row['test'] ?? '');
                $method = $separateMethodColumn ? '' : (string) ($row['test_method'] ?? '');
                $detail = (string) ($row['test_detail'] ?? '');

                if ($name !== '') {
                    $this->applyTcpdfFont($pdf, $requestedFamily, $testNameBold ? 'B' : '', $colFontSize);
                    $height += $this->matrixLineHeight($colFontSize);
                }

                if ($detail !== '') {
                    if ($name !== '') {
                        $height += $gap;
                    }

                    $this->applyTcpdfFont($pdf, $requestedFamily, '', $methodFontSize);
                    $height += $pdf->getStringHeight($innerWidth, $detail);
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
                    $height += $pdf->getStringHeight($innerWidth, $text);
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

    private function matrixLineHeight(float $fontSizePt): float
    {
        return max(2.5, $fontSizePt * 0.352778 * 1.2);
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

        return $pdf;
    }
}
