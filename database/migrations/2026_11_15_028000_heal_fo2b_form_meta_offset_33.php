<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2B fo2_form_meta was offset 90mm under NOTES (should be 33). That inflated
 * short-sheet spare math (notes+meta ≈ 108mm) so 6-test coastal sheets often
 * skipped short-chrome stacking and left FIELD DATA beside NOTES.
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

        $field = $revision->fields()->where('name', 'fo2_form_meta')->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $options = is_array($field->options) ? $field->options : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
        $next = array_merge($anchor, [
            'below' => 'fo2_notes',
            'offset_mm' => 33.0,
        ]);
        if ($next == $anchor) {
            return;
        }

        $options['anchor'] = $next;
        $field->options = $options;
        $field->save();
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
