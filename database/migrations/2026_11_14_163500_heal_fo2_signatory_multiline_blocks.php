<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Replace FO2 / FO2A / FO2B signatory micro-fields with multiline blocks
 * from the WW physico blueprints (label + name + details in one field).
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach (['LSP-7.8-FO2', 'LSP-7.8-FO2A', 'LSP-7.8-FO2B'] as $formCode) {
            $form = ControlledForm::query()->where('form_code', $formCode)->first();
            if (! $form instanceof ControlledForm) {
                continue;
            }
            if ($forms->blueprintConfigKey($form) === null) {
                continue;
            }

            $revision = $form->activeRevision() ?? $form->revisions()->latest('id')->first();
            if ($revision === null) {
                continue;
            }

            $forms->importBlueprint($revision);
            $revision->touch();
        }
    }

    public function down(): void
    {
        // Non-destructive — prior micro-fields are not restored automatically.
    }
};
