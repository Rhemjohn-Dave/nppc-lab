<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-import FO26/FO27 after restoring results.test_methods_references
 * and food-matrix header data sources on the result column.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach ([
            'LSP-7.8-FO26',
            'LSP-7.8-FO27',
            'LSP-7.8-F016-PROX',
            'LSP-7.8-F016-MILK',
        ] as $formCode) {
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
        // Non-destructive.
    }
};
