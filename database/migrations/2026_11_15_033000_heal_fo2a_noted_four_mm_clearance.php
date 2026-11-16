<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2A Noted By designer baseline: Reviewed 13 + h 22 + 4mm = 39.
 * Fill still anchors Noted under measured Reviewed ink at 4mm for all test counts.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2A')->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $field = $revision->fields()->where('name', 'fo2_signatory_noted')->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $options = is_array($field->options) ? $field->options : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
        $next = array_merge($anchor, [
            'below' => 'fo2_notes',
            'offset_mm' => 39.0,
            'above' => 'fo2_form_meta',
            'above_offset_mm' => 18.0,
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
