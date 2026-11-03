<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-import FO2 / FO2A / FO3 / FO37 so Test Report form-meta follows the matrix
 * when tall (max with pinned footer) and signatories clamp above it.
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);

        foreach ([
            'LSP-7.8-FO2',
            'LSP-7.8-FO2A',
            'LSP-7.8-FO3',
            'LSP-7.8-FO37',
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
