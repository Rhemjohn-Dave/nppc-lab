<?php

namespace App\Support;

use App\Models\JobOrder;
use App\Services\RfaFormPdfFiller;
use App\Services\RfaFormTemplateService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;

class RfaPdfExporter
{
    public static function download(JobOrder $jobOrder, bool $showResults = false, ?string $filename = null): HttpResponse
    {
        $filename ??= ($showResults ? "RFA-Results-{$jobOrder->reference_no}.pdf" : "RFA-{$jobOrder->reference_no}.pdf");

        $templates = app(RfaFormTemplateService::class);

        if ($templates->hasSource()) {
            $binary = app(RfaFormPdfFiller::class)->fillFromJobOrder($jobOrder, $showResults);

            return response($binary, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            ]);
        }

        $pdf = Pdf::loadView('pdf.request-for-analysis', [
            'jobOrder' => $jobOrder,
            'catalog' => JobOrderFormPresenter::catalog(),
            'documentControl' => OfficialAnalysisCatalog::documentControl(),
            'showResults' => $showResults,
        ])->setPaper('folio');

        return $pdf->download($filename);
    }
}
