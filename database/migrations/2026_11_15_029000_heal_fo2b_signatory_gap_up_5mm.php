<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2B Reviewed/Certified were at NOTES offset 25mm (live drift). Pull 5mm up
 * toward the FIELD DATA/NOTES chrome (offset 20, above_offset synced to meta 33).
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

        foreach (['fo2_signatory_reviewed', 'fo2_signatory_certified'] as $name) {
            $field = $revision->fields()->where('name', $name)->first();
            if (! $field instanceof ControlledFormField) {
                continue;
            }

            $options = is_array($field->options) ? $field->options : [];
            $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
            $current = is_numeric($anchor['offset_mm'] ?? null)
                ? (float) $anchor['offset_mm']
                : 25.0;
            $nextOffset = max(1.5, $current - 5.0);
            $next = array_merge($anchor, [
                'below' => 'fo2_notes',
                'offset_mm' => $nextOffset,
                'above' => 'fo2_form_meta',
                'above_offset_mm' => max(0.0, 33.0 - $nextOffset),
            ]);
            if ($next == $anchor) {
                continue;
            }

            $options['anchor'] = $next;
            $field->options = $options;
            $field->save();
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
