<?php

namespace App\Services;

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledFormRevision;
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

                $this->writeField($pdf, $field->toOverlayArray(), $values[$name]);
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
                $pdf->SetDrawColor(...($isCheckbox ? [220, 40, 40] : [30, 160, 60]));
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($fx, $fy, $fw, $fh);

                $pdf->SetFont('helvetica', '', 3.5);
                $pdf->SetTextColor(...($isCheckbox ? [220, 40, 40] : [30, 120, 50]));
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
     */
    private function writeField(Fpdi $pdf, array $field, mixed $value): void
    {
        $type = $field['type'] ?? 'text';

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

        if ($value === null || $value === '') {
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

        $this->applyColor($pdf, $field['font_color'] ?? null);
        $this->applyTcpdfFont($pdf, (string) ($field['font_family'] ?? TcpdfCalibriFont::FAMILY), '', $fontSize);
        $pdf->SetXY((float) $field['x'], (float) $field['y']);

        if ($type === ControlledFormFieldType::Multiline->value) {
            $pdf->MultiCell((float) $field['w'], (float) $field['h'], (string) $value, 0, $align, false, 1);

            return;
        }

        $pdf->Cell((float) $field['w'], (float) $field['h'], (string) $value, 0, 0, $align, false, '', 1);
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
