<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-import Proximate / Milk / FO26 / FO27 blueprints so result-column
 * headers pick up label_data_source / sublabel_data_source for live JO values.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach ([
            'LSP-7.8-F016-PROX',
            'LSP-7.8-F016-MILK',
            'LSP-7.8-FO26',
            'LSP-7.8-FO27',
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
        // Non-destructive — blueprint remains the source of truth.
    }
};
