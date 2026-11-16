<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2A live offsets drifted (Reviewed≈2, Noted≈17) so Noted By sat on the
 * Reviewed license line on few-test sheets. Restore Reviewed/Certified at 13mm
 * and Noted at 43mm (clears Reviewed h=22 + gap). Fill also bumps Noted on
 * non-tall FO2A sheets.
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

        $targets = [
            'fo2_signatory_reviewed' => ['offset_mm' => 13.0, 'above_offset_mm' => 44.0],
            'fo2_signatory_certified' => ['offset_mm' => 13.0, 'above_offset_mm' => 44.0],
            'fo2_signatory_noted' => ['offset_mm' => 43.0, 'above_offset_mm' => 14.0],
            'fo2_form_meta' => ['offset_mm' => 57.0],
        ];

        foreach ($targets as $name => $anchorPatch) {
            $field = $revision->fields()->where('name', $name)->first();
            if (! $field instanceof ControlledFormField) {
                continue;
            }

            $options = is_array($field->options) ? $field->options : [];
            $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
            $next = array_merge($anchor, [
                'below' => 'fo2_notes',
                'offset_mm' => $anchorPatch['offset_mm'],
            ]);
            if ($name !== 'fo2_form_meta') {
                $next['above'] = 'fo2_form_meta';
                $next['above_offset_mm'] = $anchorPatch['above_offset_mm'];
            }
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
