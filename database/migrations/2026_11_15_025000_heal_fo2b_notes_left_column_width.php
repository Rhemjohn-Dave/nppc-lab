<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2B NOTES was full-page width (192mm) and printed under FIELD DATA.
 * Narrow to the left column (~110mm) so Designer FIELD DATA on the right stays clear.
 * Does not rewrite FIELD DATA / signatory offsets (those stay Designer-owned).
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2B')->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $notes = $revision->fields()->where('name', 'fo2_notes')->first();
        if (! $notes instanceof ControlledFormField) {
            return;
        }

        $nextW = 110.0;
        if (abs((float) $notes->width - $nextW) < 0.01) {
            return;
        }

        $notes->width = $nextW;
        $notes->save();
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
