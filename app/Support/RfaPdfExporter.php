<?php

namespace App\Support;

use App\Models\ControlledForm;
use App\Models\JobOrder;
use App\Models\User;
use App\Services\ControlledDocumentGenerator;
use Illuminate\Http\Response as HttpResponse;
use RuntimeException;
use Throwable;

class RfaPdfExporter
{
    public static function download(
        JobOrder $jobOrder,
        bool $showResults = false,
        ?string $filename = null,
        bool $inline = false,
    ): HttpResponse {
        $filename ??= ($showResults ? "RFA-Results-{$jobOrder->reference_no}.pdf" : "RFA-{$jobOrder->reference_no}.pdf");

        $form = ControlledForm::jobOrderFormFor($jobOrder);
        $revision = $form?->activeRevision();

        if (! $form || ! $revision?->hasCanonicalPdf()) {
            abort(
                422,
                'No active Job Order controlled form PDF is configured for this classification. Upload and activate the official template in Controlled Forms.',
            );
        }

        try {
            $user = request()->user();
            $result = app(ControlledDocumentGenerator::class)->fromJobOrder(
                $jobOrder,
                $user ?? $jobOrder->receiver ?? User::query()->firstOrFail(),
                $showResults,
                persist: false,
            );
        } catch (Throwable $e) {
            report($e);

            throw new RuntimeException(
                'Could not generate the Job Order PDF from the controlled form: '.$e->getMessage(),
                previous: $e,
            );
        }

        $disposition = $inline ? 'inline' : 'attachment';

        return response($result['binary'], 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
            'X-Document-Number' => $result['document']?->document_number ?? '',
            'X-Document-Id' => (string) ($result['document']?->id ?? ''),
            'X-Job-Order-Form' => $form->form_code,
        ]);
    }
}
