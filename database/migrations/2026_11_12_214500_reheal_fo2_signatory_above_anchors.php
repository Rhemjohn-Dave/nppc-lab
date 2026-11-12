<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-apply FO2/FO2A blueprint anchors after Form Designer saves stripped
 * anchor.above (Designer previously rewrote anchor as below+offset only).
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach ([
            'LSP-7.8-FO2',
            'LSP-7.8-FO2A',
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
