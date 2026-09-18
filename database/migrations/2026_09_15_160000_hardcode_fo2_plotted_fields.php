<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Persist Active FO2 Form Designer coordinates into the live revision
 * from `config/result_ww_physico_form_fields.php` (captured plotted layout).
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $form) {
            return;
        }

        $forms = app(ControlledFormService::class);
        if ($forms->blueprintConfigKey($form) === null) {
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
        // Non-destructive — blueprint remains the source of truth.
    }
};
