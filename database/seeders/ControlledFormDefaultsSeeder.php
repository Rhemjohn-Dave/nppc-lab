<?php

namespace Database\Seeders;

use App\Console\Commands\ExportControlledFormBlueprintsCommand;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\DocumentApproval;
use App\Models\GeneratedDocument;
use App\Models\PrintLog;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\RevisionWorkflow;
use Illuminate\Database\Seeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class ControlledFormDefaultsSeeder extends Seeder
{
    public const DEFAULT_REVISION = '1';

    /**
     * Official PDF under resources/forms/official is reference-only for these codes.
     * Never re-attach over a Designer (or prior) canonical upload on seed.
     *
     * @var list<string>
     */
    public const REFERENCE_ONLY_PDF_FORMS = [
        'LSP-7.8-F016-MILK',
    ];

    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@nppc.local')->first();
        if (! $admin) {
            return;
        }

        $forms = app(ControlledFormService::class);
        $workflow = app(RevisionWorkflow::class);

        foreach (ExportControlledFormBlueprintsCommand::TARGETS as $formCode => $meta) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if (! $form) {
                continue;
            }

            if (! $forms->hasBlueprint($form)) {
                continue;
            }

            $pdfName = $meta['pdf'] ?? null;
            $absolute = is_string($pdfName) && $pdfName !== ''
                ? resource_path('forms/official/'.$pdfName)
                : null;

            if (! is_string($absolute) || ! is_file($absolute)) {
                continue;
            }

            $revision = $this->ensureSoleRevisionOne($form, $admin);
            $preserveUpload = in_array($formCode, self::REFERENCE_ONLY_PDF_FORMS, true)
                && $revision->hasCanonicalPdf();

            if ($preserveUpload) {
                // Keep Designer-uploaded PDF + existing fields; do not replace from official/.
                if (! $revision->fields()->exists()) {
                    $forms->importBlueprint($revision->fresh());
                    $revision = $revision->fresh();
                }

                if (
                    $revision
                    && $revision->hasCanonicalPdf()
                    && $revision->fields()->exists()
                    && $revision->status !== ControlledFormRevisionStatus::Active
                ) {
                    $workflow->activate($revision->fresh(), $admin);
                }

                $form->refresh();
                $revision = $revision->fresh();
                if ($revision && (int) $form->current_revision_id !== (int) $revision->id) {
                    $form->update(['current_revision_id' => $revision->id]);
                }

                continue;
            }

            // Always re-attach the official PDF so deploy matches resources/forms/official
            // (skipped for REFERENCE_ONLY_PDF_FORMS once a canonical upload exists).
            if ($revision->status !== ControlledFormRevisionStatus::Draft) {
                $revision->status = ControlledFormRevisionStatus::Draft;
                $revision->approved_by = null;
                $revision->approved_at = null;
                $revision->save();
            }

            $upload = new UploadedFile(
                $absolute,
                basename($absolute),
                'application/pdf',
                null,
                true,
            );
            $forms->attachFile($form, $revision->fresh(), $upload);
            $revision = $revision->fresh();

            $forms->importBlueprint($revision->fresh());
            $revision = $revision->fresh();

            if (
                $revision
                && $revision->hasCanonicalPdf()
                && $revision->fields()->exists()
            ) {
                $workflow->activate($revision->fresh(), $admin);
            }

            $form->refresh();
            $revision = $revision->fresh();
            if ($revision && (int) $form->current_revision_id !== (int) $revision->id) {
                $form->update(['current_revision_id' => $revision->id]);
            }
        }
    }

    private function ensureSoleRevisionOne(ControlledForm $form, User $admin): ControlledFormRevision
    {
        $source = $form->activeRevision()
            ?? $form->revisions()->whereHas('fields')->latest('id')->first()
            ?? $form->revisions()->latest('id')->first();

        $existingOne = $form->revisions()->where('revision', self::DEFAULT_REVISION)->first();

        if (
            $source instanceof ControlledFormRevision
            && $existingOne instanceof ControlledFormRevision
            && (int) $source->id !== (int) $existingOne->id
        ) {
            $this->deleteRevisionCompletely($existingOne);
            $existingOne = null;
        }

        if ($source instanceof ControlledFormRevision) {
            if ($source->revision !== self::DEFAULT_REVISION) {
                $source->revision = self::DEFAULT_REVISION;
                $source->save();
            }
            $keep = $source->fresh() ?? $source;
        } elseif ($existingOne instanceof ControlledFormRevision) {
            $keep = $existingOne;
        } else {
            $keep = $form->revisions()->create([
                'revision' => self::DEFAULT_REVISION,
                'status' => ControlledFormRevisionStatus::Draft,
                'created_by' => $admin->id,
                'fill_mode' => ControlledFormRevision::FILL_MODE_OVERLAY,
                'notes' => 'Deploy default revision 1 (plotted blueprint + official PDF).',
            ]);
        }

        $obsolete = $form->revisions()
            ->where('id', '!=', $keep->id)
            ->get();

        foreach ($obsolete as $revision) {
            $this->deleteRevisionCompletely($revision);
        }

        return $keep->fresh() ?? $keep;
    }

    private function deleteRevisionCompletely(ControlledFormRevision $revision): void
    {
        $form = $revision->form;
        if ($form && (int) $form->current_revision_id === (int) $revision->id) {
            $form->update(['current_revision_id' => null]);
        }

        $generatedIds = GeneratedDocument::query()
            ->where('controlled_form_revision_id', $revision->id)
            ->pluck('id');

        if ($generatedIds->isNotEmpty()) {
            PrintLog::query()->whereIn('generated_document_id', $generatedIds)->delete();
            GeneratedDocument::query()->whereIn('id', $generatedIds)->delete();
        }

        DocumentApproval::query()
            ->where('controlled_form_revision_id', $revision->id)
            ->delete();

        $revision->fields()->delete();

        foreach ([$revision->canonical_pdf_path, $revision->original_path] as $path) {
            if (is_string($path) && $path !== '' && Storage::disk('local')->exists($path)) {
                Storage::disk('local')->delete($path);
            }
        }

        $revision->delete();
    }
}
