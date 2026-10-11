<?php

namespace App\Support;

use App\Models\JobOrderAnalysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response as HttpResponse;

class AnalysisResultPdfExporter
{
    public static function download(JobOrderAnalysis $analysis, bool $inline = false): HttpResponse
    {
        $analysis->loadMissing(['jobOrder.samples', 'analysisType.category', 'assignee']);

        $job = $analysis->jobOrder;
        $sampleIndex = $job->samples->search(fn ($sample): bool => $sample->id === $analysis->sample_id);
        $controlNumber = $sampleIndex === false ? null : SampleControlNumber::forJobOrder($job, (int) $sampleIndex);
        $filename = ResultPdfFilename::for($job, collect([$analysis]), $controlNumber);

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
