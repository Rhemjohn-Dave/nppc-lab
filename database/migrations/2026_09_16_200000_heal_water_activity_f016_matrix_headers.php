<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormService;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-import F016-WA so normalize hard-locks Water Activity, Aw headers
 * (stale Milk-style Control Number / Sample Description on the result column).
 */
return new class extends Migration
{
    public function up(): void
    {
        $forms = app(ControlledFormService::class);
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-WA')->first();
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
