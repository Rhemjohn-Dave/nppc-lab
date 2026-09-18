<?php

use App\Models\ControlledForm;
use App\Services\ControlledFormStorage;
use Illuminate\Database\Migrations\Migration;

/**
 * Milk official DOC/PDF is reference-only — do not re-attach over Designer uploads.
 * Only sync FPDI page metrics when a canonical PDF is already present.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->first();
        if (! $form) {
            return;
        }

        $revision = $form->activeRevision()
            ?? $form->revisions()->where('revision', '1')->first()
            ?? $form->revisions()->latest('id')->first();

        if (! $revision || ! $revision->hasCanonicalPdf()) {
            return;
        }

        app(ControlledFormStorage::class)->syncRevisionPageMetrics($revision);
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
