<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-import F016-NO2: taller matrix box + preview_rows/preview_data for 4 samples.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-NO2')->first();
        if (! $form || $forms->blueprintConfigKey($form) === null) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
        if (! $revision) {
            return;
        }

        $forms->importBlueprint($revision);
        $revision->touch();
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
