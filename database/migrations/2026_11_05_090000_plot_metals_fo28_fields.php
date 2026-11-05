<?php

use App\Models\ControlledForm;
use App\Models\User;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Plot the FO28 header, matrix, MDL, notes, and signatories on an empty draft.
 * Skips a revision that already has fields so a later designer edit is kept.
 * Does not attach or replace a PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! User::query()->exists()) {
            return;
        }

        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO28')->first();
        $revision = $form?->revisions()->first();
        if (! $form || ! $revision || $revision->fields()->exists()) {
            return;
        }

        $forms = app(ControlledFormService::class);
        if (! $forms->hasBlueprint($form)) {
            return;
        }

        $forms->importBlueprint($revision);
    }

    public function down(): void
    {
        // Leave plotted fields in place.
    }
};
