<?php

namespace App\Services;

use App\Models\AnalysisType;
use App\Models\JobOrder;
use setasign\Fpdi\Tcpdf\Fpdi;

class RfaFormPdfFiller
{
    public function __construct(private readonly RfaFormTemplateService $templates) {}

    public function fillFromJobOrder(JobOrder $jobOrder, bool $showResults = false): string
    {
        return $this->fillFromValues($this->valuesFromJobOrder($jobOrder, $showResults));
    }

    /**
     * @param  array<string, string|bool|null>  $values
     */
    public function fillFromValues(array $values): string
    {
        @set_time_limit(300);

        $source = $this->templates->sourceAbsolutePath();
        $pdf = $this->newPdf();
        $pageCount = $pdf->setSourceFile($source);

        for ($pageNo = 1; $pageNo <= $pageCount; $pageNo++) {
            $templateId = $pdf->importPage($pageNo);
            $size = $pdf->getTemplateSize($templateId);
            $orientation = $size['width'] > $size['height'] ? 'L' : 'P';

            $pdf->AddPage($orientation, [$size['width'], $size['height']]);
            $pdf->useTemplate($templateId);

            foreach ($this->templates->fields() as $field) {
                if ((int) ($field['page'] ?? 1) !== $pageNo) {
                    continue;
                }

                $name = $field['name'];
                if (! array_key_exists($name, $values)) {
                    continue;
                }

                $this->writeField($pdf, $field, $values[$name]);
            }
        }

        return $pdf->Output('', 'S');
    }

    /**
     * Render the flat template with a millimetre grid plus every field box
     * drawn and labelled, so positions can be visually calibrated.
     */
    public function calibrationOverlay(): string
    {
        @set_time_limit(300);

        $source = $this->templates->sourceAbsolutePath();
        $pdf = $this->newPdf();
        $pageCount = $pdf->setSourceFile($source);

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

            foreach ($this->templates->fields() as $field) {
                if (! is_array($field) || (int) ($field['page'] ?? 1) !== $pageNo) {
                    continue;
                }

                $fx = (float) $field['x'];
                $fy = (float) $field['y'];
                $fw = (float) $field['w'];
                $fh = (float) $field['h'];
                if ($fw < 1 || $fh < 1) {
                    continue;
                }

                $isCheckbox = ($field['type'] ?? 'text') === 'checkbox';
                $pdf->SetDrawColor(...($isCheckbox ? [220, 40, 40] : [30, 160, 60]));
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($fx, $fy, $fw, $fh);

                $pdf->SetFont('helvetica', '', 3.5);
                $pdf->SetTextColor(...($isCheckbox ? [220, 40, 40] : [30, 120, 50]));
                $pdf->SetXY($fx, max(0, $fy - 2.2));
                $pdf->Cell($fw + 20, 2, (string) $field['name'], 0, 0, 'L');
            }
        }

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetDrawColor(0, 0, 0);

        return $pdf->Output('', 'S');
    }

    /**
     * @return array<string, string|bool|null>
     */
    public function valuesFromJobOrder(JobOrder $jobOrder, bool $showResults = false): array
    {
        $jobOrder->loadMissing(['samples', 'analyses']);

        $values = [
            'reference_no' => $jobOrder->reference_no,
            'customer_name' => $jobOrder->customer_name,
            'address' => $jobOrder->customer_address,
            'contact_no' => $jobOrder->customer_contact,
            'time_date_submitted' => $jobOrder->created_at?->format('m/d/Y h:i A'),
            'sampling_date' => $jobOrder->sampling_date?->format('m/d/Y'),
            'sampling_time' => $jobOrder->sampling_time,
            'sample_collected_by' => $jobOrder->sample_collected_by,
            'sample_storage_temp' => $jobOrder->sample_storage_temp,
            'other_tests' => $jobOrder->other_tests,
            'billing_total' => $jobOrder->total_cost !== null
                ? number_format((float) $jobOrder->total_cost, 2)
                : null,
            'conforme_date' => null,
            'received_date' => $jobOrder->received_at?->format('m/d/Y'),
            'reviewed_date' => $jobOrder->reviewed_at?->format('m/d/Y'),
        ];

        $ownership = mb_strtolower((string) $jobOrder->ownership_type);
        $values['ownership_private'] = $ownership === 'private';
        $values['ownership_commercial'] = $ownership === 'commercial';
        $values['ownership_public'] = $ownership === 'public';

        $classification = mb_strtolower((string) $jobOrder->classification);
        $values['class_aqua'] = str_contains($classification, 'aqua');
        $values['class_potability'] = str_contains($classification, 'potability');
        $values['class_wastewater'] = str_contains($classification, 'wastewater');
        $values['class_agriculture'] = str_contains($classification, 'agriculture');
        $values['class_academic'] = str_contains($classification, 'academic')
            || str_contains($classification, 'research');
        $values['class_others'] = str_contains($classification, 'other');
        $values['class_others_text'] = $values['class_others'] ? (string) $jobOrder->classification : null;

        $fieldData = is_array($jobOrder->field_data) ? $jobOrder->field_data : [];
        $values['potability_sterile'] = (bool) ($fieldData['sterile_bottle'] ?? $fieldData['water_in_sterile_bottle'] ?? false);

        $ww = mb_strtolower((string) $jobOrder->wastewater_source);
        $values['ww_local_district'] = str_contains($ww, 'district');
        $values['ww_faucet'] = str_contains($ww, 'faucet');
        $values['ww_tank'] = str_contains($ww, 'tank');
        $values['ww_deepwell'] = str_contains($ww, 'deepwell') || str_contains($ww, 'deep well');
        $values['ww_others'] = $ww !== '' && ! (
            $values['ww_local_district'] || $values['ww_faucet'] || $values['ww_tank'] || $values['ww_deepwell']
        );
        $values['ww_others_text'] = $values['ww_others'] ? (string) $jobOrder->wastewater_source : null;

        $samples = $jobOrder->samples->values();
        for ($i = 1; $i <= 9; $i++) {
            $sample = $samples->get($i - 1);
            $label = $sample
                ? trim(implode(' / ', array_filter([(string) $sample->sample_code, (string) $sample->description])))
                : null;
            $values["sample_code_{$i}"] = $label;
            $values["control_number_{$i}"] = $sample ? $jobOrder->reference_no : null;
        }

        $codeByTypeId = AnalysisType::query()
            ->whereNotNull('code')
            ->pluck('code', 'id')
            ->all();

        $selectedCodes = [];
        foreach ($jobOrder->analyses as $line) {
            $code = null;
            if ($line->analysis_type_id && isset($codeByTypeId[$line->analysis_type_id])) {
                $code = $codeByTypeId[$line->analysis_type_id];
            }

            if (is_string($code) && $code !== '') {
                $selectedCodes[strtoupper($code)] = true;
            }
        }

        foreach ($this->templates->fields() as $field) {
            if (! is_array($field) || ! isset($field['name'])) {
                continue;
            }

            $name = $field['name'];
            if (! str_starts_with($name, 'chk_')) {
                continue;
            }

            $code = strtoupper(str_replace('_', '-', substr($name, 4)));
            $values[$name] = isset($selectedCodes[$code]);
        }

        $analyses = $jobOrder->analyses->values();
        for ($i = 1; $i <= 14; $i++) {
            $line = $analyses->get($i - 1);
            if (! $line) {
                $values["bill_param_{$i}"] = null;
                $values["bill_price_{$i}"] = null;
                $values["bill_total_{$i}"] = null;

                continue;
            }

            $param = (string) $line->name;
            if ($showResults && filled($line->result_value)) {
                $param .= ' = '.$line->result_value.($line->result_unit ? ' '.$line->result_unit : '');
            }

            $values["bill_param_{$i}"] = $param;
            $values["bill_price_{$i}"] = number_format((float) $line->unit_price, 2);
            $values["bill_total_{$i}"] = number_format((float) $line->total_cost, 2);
        }

        return $values;
    }

    /**
     * Sample values used for admin preview downloads.
     *
     * @return array<string, string|bool|null>
     */
    public function sampleValues(): array
    {
        $values = [
            'reference_no' => '26-0001',
            'customer_name' => 'Sample Customer',
            'address' => 'Bacolod City',
            'contact_no' => '09171234567',
            'time_date_submitted' => now()->format('m/d/Y h:i A'),
            'sampling_date' => now()->format('m/d/Y'),
            'sampling_time' => '09:00 AM',
            'sample_collected_by' => 'Sample Collector',
            'ownership_private' => true,
            'ownership_commercial' => false,
            'ownership_public' => false,
            'class_potability' => true,
            'sample_code_1' => 'SAMPLE-001 / Tap water',
            'control_number_1' => '26-0001',
            'bill_param_1' => 'pH',
            'bill_price_1' => '250.00',
            'bill_total_1' => '250.00',
            'billing_total' => '250.00',
            'received_date' => now()->format('m/d/Y'),
        ];

        foreach ($this->templates->fields() as $field) {
            $name = $field['name'];
            if (! array_key_exists($name, $values)) {
                $values[$name] = ($field['type'] ?? '') === 'checkbox' ? false : null;
            }
        }

        return $values;
    }

    /**
     * @param  array{name: string, type: string, x: float, y: float, w: float, h: float, font_size?: float, align?: string}  $field
     */
    private function writeField(Fpdi $pdf, array $field, mixed $value): void
    {
        if (($field['type'] ?? 'text') === 'checkbox') {
            if (! $value) {
                return;
            }

            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->SetXY((float) $field['x'], (float) $field['y']);
            $pdf->Cell((float) $field['w'], (float) $field['h'], 'X', 0, 0, 'C');

            return;
        }

        if ($value === null || $value === '') {
            return;
        }

        // Skip invisible placeholder result slots
        if ((float) $field['w'] < 1 || (float) $field['h'] < 1) {
            return;
        }

        $fontSize = (float) ($field['font_size'] ?? 8);
        $align = strtoupper((string) ($field['align'] ?? 'L'));
        if (! in_array($align, ['L', 'C', 'R'], true)) {
            $align = 'L';
        }

        $pdf->SetFont('helvetica', '', $fontSize);
        $pdf->SetXY((float) $field['x'], (float) $field['y']);
        $pdf->Cell((float) $field['w'], (float) $field['h'], (string) $value, 0, 0, $align, false, '', 1);
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

        return $pdf;
    }
}
