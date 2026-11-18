<?php

use App\Enums\ControlledFormRevisionStatus;
use App\Models\ControlledForm;
use App\Models\ControlledFormRevision;
use App\Models\User;
use App\Services\ControlledFormService;
use App\Services\RevisionWorkflow;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Http\UploadedFile;

/**
 * Re-attach FO2 / FO2A / FO2B canonical PDFs from the official wastewater blank
 * after baking the NPPC letterhead image into that source file.
 * Does not re-import Designer field blueprints.
 */
return new class extends Migration
{
    private const PDF = 'PC Wastewater result Form Issue 18 09012026 blank.pdf';

    /** @var list<string> */
    private const FORM_CODES = [
        'LSP-7.8-FO2',
        'LSP-7.8-FO2A',
        'LSP-7.8-FO2B',
    ];

    public function up(): void
    {
        $absolute = resource_path('forms/official/'.self::PDF);
        if (! is_file($absolute)) {
            return;
        }

        $admin = User::query()->where('email', 'admin@nppc.local')->first()
            ?? User::query()->orderBy('id')->first();
        if (! $admin) {
            return;
        }

        $forms = app(ControlledFormService::class);
        $workflow = app(RevisionWorkflow::class);

        foreach (self::FORM_CODES as $formCode) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if (! $form) {
                continue;
            }

            $revision = $form->activeRevision()
                ?? $form->revisions()->latest('id')->first();
            if (! $revision instanceof ControlledFormRevision) {
                continue;
            }

            $wasActive = $revision->status === ControlledFormRevisionStatus::Active;
            if ($wasActive || ! $revision->status->isEditable()) {
                $revision->status = ControlledFormRevisionStatus::Draft;
                $revision->approved_by = null;
                $revision->approved_at = null;
                $revision->save();
            }

            $upload = new UploadedFile($absolute, self::PDF, 'application/pdf', null, true);
            $revision = $forms->attachFile($form, $revision->fresh() ?? $revision, $upload);
            $revision = $revision->fresh() ?? $revision;

            if (
                $revision->hasCanonicalPdf()
                && $revision->fields()->exists()
                && $revision->status !== ControlledFormRevisionStatus::Active
            ) {
                $workflow->activate($revision, $admin);
            }

            $revision = $revision->fresh() ?? $revision;
            if (
                $revision->status === ControlledFormRevisionStatus::Active
                && (int) $form->fresh()->current_revision_id !== (int) $revision->id
            ) {
                $form->update(['current_revision_id' => $revision->id]);
            }
        }
    }

    public function down(): void
    {
        // Letterhead bake is irreversible without restoring a prior PDF backup.
    }
};
