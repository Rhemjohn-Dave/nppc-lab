<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Heal FO26/FO27 with Multiline results.test_methods_references
 * (covers static Test Methods and References ink on the official blanks).
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach (['LSP-7.8-FO26', 'LSP-7.8-FO27'] as $formCode) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if (! $form || $forms->blueprintConfigKey($form) === null) {
                continue;
            }

            $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
            if (! $revision) {
                continue;
            }

            $forms->importBlueprint($revision);
            $revision->touch();
        }
    }

    public function down(): void
    {
        // Non-destructive — blueprint remains the source of truth.
    }
};
