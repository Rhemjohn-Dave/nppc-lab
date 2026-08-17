<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use setasign\Fpdi\Tcpdf\Fpdi;

class RfaFormMakeFillable
{
    public function __construct(private readonly RfaFormTemplateService $templates) {}

    public function generate(): string
    {
        @set_time_limit(300);

        $source = $this->templates->sourceAbsolutePath();
        $meta = $this->templates->meta();
        $fillableRelative = $meta['fillable_path'] ?? null;

        if (! is_string($fillableRelative) || $fillableRelative === '') {
            throw new \RuntimeException('Fillable path is not configured.');
        }

        $pdf = $this->newPdf();
        $pageCount = $pdf->setSourceFile($source);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);
            $pdf->SetFont('helvetica', '', 8);

            foreach ($this->templates->fields() as $field) {
                if ((int) ($field['page'] ?? 1) !== $pageNo) {
                    continue;
                }

                $this->stampField($pdf, $field);
            }
        }

        $binary = $pdf->Output('', 'S');
        Storage::disk('local')->put($fillableRelative, $binary);

        return Storage::disk('local')->path($fillableRelative);
    }

    /**
     * @param  array{name: string, type: string, x: float, y: float, w: float, h: float, font_size?: float}  $field
     */
    private function stampField(Fpdi $pdf, array $field): void
    {
        $name = $field['name'];
        $x = (float) $field['x'];
        $y = (float) $field['y'];
        $w = (float) $field['w'];
        $h = (float) $field['h'];

        $props = [
            'lineWidth' => 0,
            'borderStyle' => 'none',
            'fillColor' => [255, 255, 255],
            'strokeColor' => [255, 255, 255],
        ];

        if (($field['type'] ?? 'text') === 'checkbox') {
            $size = min($w, $h);
            if ($size < 1) {
                return;
            }
            $pdf->CheckBox($name, $size, false, $props, [], 'Yes', $x, $y);

            return;
        }

        if ($w < 1 || $h < 1) {
            return;
        }

        $opt = ['v' => '', 'dv' => ''];

        if (($field['type'] ?? '') === 'multiline') {
            $props['multiline'] = true;
        }

        $pdf->TextField($name, $w, $h, $props, $opt, $x, $y);
    }

    private function newPdf(): Fpdi
    {
        $page = $this->templates->page();
        $pdf = new Fpdi('P', $page['unit'] ?? 'mm', [$page['width'], $page['height']], true, 'UTF-8', false);
        $pdf->setPrintHeader(false);
        $pdf->setPrintFooter(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetAutoPageBreak(false, 0);
        $pdf->setFontSubsetting(false);
        $pdf->setFormDefaultProp([
            'lineWidth' => 0,
            'borderStyle' => 'none',
            'fillColor' => [255, 255, 255],
            'strokeColor' => [255, 255, 255],
        ]);

        return $pdf;
    }
}
