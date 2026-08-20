<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\ControlledDocumentGenerator;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Throwable;

class RfaPdfExporter
{
    public static function download(JobOrder $jobOrder, bool $showResults = false, ?string $filename = null): HttpResponse
    {
        $filename ??= ($showResults ? "RFA-Results-{$jobOrder->reference_no}.pdf" : "RFA-{$jobOrder->reference_no}.pdf");

        $form = ControlledForm::jobOrderForm();
        $revision = $form?->activeRevision();

        if ($revision?->hasCanonicalPdf()) {
            try {
                $user = request()->user();
                $result = app(ControlledDocumentGenerator::class)->fromJobOrder(
                    $jobOrder,
                    $user ?? $jobOrder->receiver ?? User::query()->firstOrFail(),
                    $showResults,
                    persist: false,
                );

                return response($result['binary'], 200, [
                    'Content-Type' => 'application/pdf',
                    'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                    'X-Document-Number' => $result['document']?->document_number ?? '',
                    'X-Document-Id' => (string) ($result['document']?->id ?? ''),
                ]);
            } catch (Throwable $e) {
                report($e);
            }
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
