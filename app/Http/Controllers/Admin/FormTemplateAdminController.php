<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\RfaFormPdfFiller;
use App\Services\RfaFormTemplateService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class FormTemplateAdminController extends Controller
{
    public function edit(RfaFormTemplateService $templates): Response
    {
        $meta = $templates->meta();

        return Inertia::render('admin/form-templates', [
            'template' => [
                'has_source' => $templates->hasSource(),
                'has_fillable' => $templates->hasFillable(),
                'original_name' => $meta['original_name'] ?? null,
                'uploaded_at' => $meta['uploaded_at'] ?? null,
                'uploaded_by' => $meta['uploaded_by'] ?? null,
                'notes' => $meta['notes'] ?? null,
                'field_count' => count($templates->fields()),
            ],
        ]);
    }

    public function update(Request $request, RfaFormTemplateService $templates): RedirectResponse
    {
        $data = $request->validate([
            'template' => ['required', 'file', 'mimes:pdf', 'max:10240'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $templates->storeSource(
                $request->file('template'),
                $request->user(),
                $data['notes'] ?? null,
            );
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'template' => 'Could not process the PDF: '.$e->getMessage(),
            ]);
        }

        return back()->with('success', 'RFA form template uploaded and fillable PDF generated.');
    }

    public function regenerate(RfaFormTemplateService $templates): RedirectResponse
    {
        try {
            $templates->regenerateFillable();
        } catch (Throwable $e) {
            report($e);

            return back()->withErrors([
                'template' => $e->getMessage(),
            ]);
        }

        return back()->with('success', 'Fillable PDF regenerated from the current flat template.');
    }

    public function downloadSource(RfaFormTemplateService $templates): BinaryFileResponse
    {
        abort_unless($templates->hasSource(), 404);

        $meta = $templates->meta();
        $name = $meta['original_name'] ?? 'rfa-source.pdf';

        return response()->file($templates->sourceAbsolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function downloadFillable(RfaFormTemplateService $templates): BinaryFileResponse
    {
        abort_unless($templates->hasFillable(), 404);

        return response()->file($templates->fillableAbsolutePath(), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rfa-fillable.pdf"',
        ]);
    }

    public function downloadSample(RfaFormTemplateService $templates, RfaFormPdfFiller $filler): HttpResponse
    {
        abort_unless($templates->hasSource(), 404);

        $binary = $filler->fillFromValues($filler->sampleValues());

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rfa-sample-filled.pdf"',
        ]);
    }

    public function downloadCalibration(RfaFormTemplateService $templates, RfaFormPdfFiller $filler): HttpResponse
    {
        abort_unless($templates->hasSource(), 404);

        $binary = $filler->calibrationOverlay();

        return response($binary, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="rfa-calibration-grid.pdf"',
        ]);
    }
}
