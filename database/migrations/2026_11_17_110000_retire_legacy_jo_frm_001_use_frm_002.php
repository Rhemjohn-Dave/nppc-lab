<?php

use App\Enums\ControlledFormRevisionStatus;
use App\Enums\JobOrderVariant;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\DocumentApproval;
use App\Models\GeneratedDocument;
use App\Models\PrintLog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * General JO print must use NPPC-LAB-FRM-002 (active Designer plots).
 * Remove legacy NPPC-LAB-FRM-001 so it cannot shadow the resolver.
 */
return new class extends Migration
{
    public function up(): void
    {
        $legacy = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE_LEGACY)
            ->first();

        $current = ControlledForm::query()
            ->where('form_code', ControlledForm::RFA_FORM_CODE)
            ->first();

        if ($current) {
            if ($current->job_order_variant === null) {
                $current->job_order_variant = JobOrderVariant::General;
                $current->save();
            }

            // Ensure at least one active revision when PDF exists.
            $active = $current->activeRevision();
            if (! $active) {
                $candidate = $current->revisions()->orderByDesc('id')->first();
                if ($candidate && $candidate->hasCanonicalPdf()) {
                    $candidate->status = ControlledFormRevisionStatus::Active;
                    $candidate->save();
                    $current->current_revision_id = $candidate->id;
                    $current->save();
                }
            }

            if ($legacy) {
                $this->deleteFormCompletely($legacy);
            }

            return;
        }

        if ($legacy) {
            // No FRM-002 yet: rename legacy shell so existing plots/PDF keep working.
            $legacy->form_code = ControlledForm::RFA_FORM_CODE;
            $legacy->job_order_variant = JobOrderVariant::General;
            $legacy->save();
        }
        // Fresh installs: leave creation to DatabaseSeeder.
    }

    public function down(): void
    {
        // Data deletion of FRM-001 is not restored.
    }

    private function deleteFormCompletely(ControlledForm $form): void
    {
        DB::transaction(function () use ($form): void {
            $form->update(['current_revision_id' => null]);

            $form->revisions()->orderBy('id')->each(function (ControlledFormRevision $revision): void {
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
            });

            $form->analysisTypes()->detach();
            $form->delete();
        });
    }
};
