<?php

namespace App\Support;

use App\Models\JobOrderAnalysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;

class AnalysisResultPdfExporter
{
    public static function download(JobOrderAnalysis $analysis, bool $inline = false): HttpResponse
    {
        $analysis->loadMissing(['jobOrder.samples', 'analysisType', 'assignee']);

        $job = $analysis->jobOrder;
        $slug = Str::slug($analysis->analysisType?->code ?: $analysis->name) ?: 'result';
        $filename = "Result-{$job->reference_no}-{$slug}.pdf";

        $pdf = Pdf::loadView('pdf.analysis-result', [
            'analysis' => $analysis,
            'jobOrder' => $job,
            'documentControl' => [
                'lab' => 'NPPC-ADL',
                'form' => 'Analysis Result Sheet',
                'revision' => 'Internal draft',
                'effective' => 'Pending official form',
            ],
        ])->setPaper('a4');

        if ($inline) {
            return $pdf->stream($filename);
        }

        return $pdf->download($filename);
    }
}
