<?php

use App\Console\Commands\ExportControlledFormBlueprintsCommand;
use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\DocumentApproval;
use App\Models\GeneratedDocument;
use App\Models\PrintLog;
use App\Services\ControlledFormService;
use App\Services\ControlledFormStorage;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Collapse every plotted default controlled form to a single active revision "1"
 * and import the committed config/*_form_fields.php blueprints.
 * Does not replace canonical PDFs on the kept revision.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (array_keys(ExportControlledFormBlueprintsCommand::TARGETS) as $formCode) {
            $this->deploySoleRevision((string) $formCode);
        }
    }

    public function down(): void
    {
        // Non-destructive — cannot restore deleted revisions.
    }

    private function deploySoleRevision(string $formCode): void
    {
        $form = ControlledForm::query()->where('form_code', $formCode)->first();
        if ($form === null) {
            return;
        }

        $slots = $this->signatorySettings($formCode);
        if ($slots !== null) {
            $form->fill($slots)->save();
        }

        $forms = app(ControlledFormService::class);
        if (! $forms->hasBlueprint($form)) {
            return;
        }

        $keep = $this->resolveKeepRevision($form);
        if ($keep === null) {
            return;
        }

        $form->revisions()
            ->where('id', '!=', $keep->id)
            ->get()
            ->each(fn (ControlledFormRevision $revision) => $this->deleteRevisionCompletely($revision));

        $keep = $keep->fresh(['fields', 'form']) ?? $keep;
        if ($keep->revision !== '1') {
            $keep->revision = '1';
            $keep->save();
        }

        $forms->importBlueprint($keep->fresh(['fields', 'form']) ?? $keep);
        $keep = $keep->fresh(['fields', 'form']) ?? $keep;

        app(ControlledFormStorage::class)->syncRevisionPageMetrics($keep);

        $keep->status = ControlledFormRevisionStatus::Active;
        $keep->save();

        $form->refresh();
        $form->current_revision_id = $keep->id;
        $form->save();
    }

    /**
     * @return array{analyst_signatory_slots: int, analyst_require_prc: bool}|null
     */
    private function signatorySettings(string $formCode): ?array
    {
        return match ($formCode) {
            'LSP-7.8-FO37', 'LSP-7.8-FO3', 'LSP-7.8-F016-PROX' => [
                'analyst_signatory_slots' => 2,
                'analyst_require_prc' => true,
            ],
            'LSP-7.8-FO26', 'LSP-7.8-FO27' => [
                'analyst_signatory_slots' => 1,
                'analyst_require_prc' => false,
            ],
            'LSP-7.8-F016-MILK', 'LSP-7.8-F016-NO2' => [
                'analyst_signatory_slots' => 1,
                'analyst_require_prc' => true,
            ],
            'LSP-7.8-FO2' => [
                'analyst_signatory_slots' => 4,
                'analyst_require_prc' => true,
            ],
            'LSP-7.8-FO2A' => [
                'analyst_signatory_slots' => 3,
                'analyst_require_prc' => true,
            ],
            default => null,
        };
    }

    private function resolveKeepRevision(ControlledForm $form): ?ControlledFormRevision
    {
        $active = $form->revisions()
            ->where('status', ControlledFormRevisionStatus::Active)
            ->whereHas('fields')
            ->orderByDesc('id')
            ->first();
        if ($active instanceof ControlledFormRevision) {
            return $active;
        }

        return $form->revisions()
            ->whereHas('fields')
            ->orderByDesc('id')
            ->first();
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
};
