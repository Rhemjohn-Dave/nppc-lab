<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2B FIELD DATA: align under-matrix offset with fo2_notes (18mm) so the right
 * column sits in the NOTES band and no longer overlaps Certified Correct.
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

        $field = $revision->fields()->where('name', 'job_orders.field_data')->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $options = is_array($field->options) ? $field->options : [];
        $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
        $next = array_merge($anchor, [
            'below' => 'ww_fo2b_matrix',
            'offset_mm' => 18.0,
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
