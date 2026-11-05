<?php

use App\Enums\ControlledFormCategory;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\AnalysisType;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\RevisionWorkflow;
use App\Support\OfficialAnalysisCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Put the local FO28 plot (saved PDF + Form Designer fields) on a server
 * that does not already have both. Does not touch any other form.
 */
return new class extends Migration
{
    private const PDF = 'lsp-7.8-fo28-metal-test-results.pdf';

    public function up(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $meta = OfficialAnalysisCatalog::resultFormRegistry()['metals'] ?? null;
        if (! is_array($meta)) {
            return;
        }

        $forms = app(ControlledFormService::class);
        $form = ControlledForm::query()->firstOrCreate(
            ['form_code' => 'LSP-7.8-FO28'],
            [
                'name' => $meta['name'],
                'description' => sprintf(
                    'Official %s · %s · Eff. %s.',
                    $meta['official'],
                    $meta['revision'],
                    $meta['effective'],
                ),
                'department' => 'Laboratory',
                'category' => ControlledFormCategory::AnalysisResult,
                'analysis_package_id' => null,
                'analyst_signatory_slots' => 2,
                'analyst_require_prc' => true,
            ],
        );

        $form->fill([
            'analyst_signatory_slots' => 2,
            'analyst_require_prc' => true,
            'analysis_package_id' => null,
        ])->save();

        $orderedIds = collect(OfficialAnalysisCatalog::metalsResultTypeCodes())
            ->map(fn (string $code) => AnalysisType::query()->where('code', $code)->value('id'))
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->values()
            ->all();
        if ($orderedIds !== []) {
            $forms->syncBindings($form, $orderedIds, null);
        }

        $revision = $form->revisions()->latest('id')->first();
        if ($revision && $revision->hasCanonicalPdf() && $revision->fields()->exists()) {
            return;
        }

        if (! $revision || ! $revision->status->isEditable()) {
            $revision = $form->revisions()->create([
                'revision' => '1',
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Local Form Designer plot for LSP-7.8-FO28.',
            ]);
        }

        $absolute = resource_path('forms/official/'.self::PDF);
        if (! $revision->hasCanonicalPdf() && is_file($absolute)) {
            $upload = new UploadedFile($absolute, self::PDF, 'application/pdf', null, true);
            $revision = $forms->attachFile($form, $revision, $upload);
        }

        if ($forms->hasBlueprint($form)) {
            $forms->importBlueprint($revision->fresh() ?? $revision);
            $revision = $revision->fresh() ?? $revision;
        }

        if (
            $revision->hasCanonicalPdf()
            && $revision->fields()->exists()
            && $revision->status !== ControlledFormRevisionStatus::Active
        ) {
            app(RevisionWorkflow::class)->activate($revision, $admin);
        }
    }

    public function down(): void
    {
        // Leave the deployed PDF and plots in place.
    }
};
