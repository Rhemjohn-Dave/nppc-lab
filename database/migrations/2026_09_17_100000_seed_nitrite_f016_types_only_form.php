<?php

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\User;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Types-only Nitrite Test Result Form (LSP-7.8-F016-NO2) bound to FD-NO2.
 */
return new class extends Migration
{
    public function up(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        $meta = OfficialAnalysisCatalog::resultFormRegistry()['nitrite_food'] ?? null;
        if (! $admin || ! is_array($meta)) {
            return;
        }

        $forms = app(\App\Services\ControlledFormService::class);
        $workflow = app(\App\Services\RevisionWorkflow::class);
        $codes = OfficialAnalysisCatalog::nitriteTypeCodes();

        $orderedIds = collect($codes)
            ->map(fn (string $code) => AnalysisType::query()->where('code', $code)->value('id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();

        if ($orderedIds === []) {
            return;
        }

        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => $meta['form_code']],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s. Types-only individual pay sheet.',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => null,
                'analyst_signatory_slots' => 1,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'name' => $meta['name'],
            'analysis_package_id' => null,
            'analyst_signatory_slots' => 1,
            'analyst_require_prc' => true,
        ])->save();

        $forms->syncBindings($form, $orderedIds, null);

        if (! $forms->hasBlueprint($form)) {
            return;
        }

        $revision = $form->revisions()->where('revision', '1')->first();
        if (! $revision) {
            $revision = $form->revisions()->create([
                'revision' => '1',
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => \App\Models\ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Nitrite F016 types-only dynamic matrix — deploy default revision 1.',
            ]);
        }

        $sourcePdf = $meta['source_pdf'] ?? null;
        if (is_string($sourcePdf) && $sourcePdf !== '' && ! $revision->hasCanonicalPdf()) {
            $absolute = resource_path('forms/official/'.$sourcePdf);
            if (is_file($absolute)) {
                $upload = new UploadedFile($absolute, $sourcePdf, 'application/pdf', null, true);
                $forms->attachFile($form, $revision->fresh(), $upload);
                $revision = $revision->fresh();
            }
        }

        $forms->importBlueprint($revision->fresh());

        if (
            $revision->fresh()?->hasCanonicalPdf()
            && $revision->fresh()?->fields()->exists()
            && $revision->status !== ControlledFormRevisionStatus::Active
        ) {
            $workflow->activate($revision->fresh(), $admin);
            $form->update(['current_revision_id' => $revision->fresh()->id]);
        }
    }

    public function down(): void
    {
        // Non-destructive — blueprint remains the source of truth.
    }
};
