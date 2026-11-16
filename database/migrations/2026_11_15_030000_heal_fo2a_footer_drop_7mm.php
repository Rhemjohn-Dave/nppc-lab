<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2A abstracted-water footer: drop signatories + Test Report band ~7mm
 * (NOTES offsets +7). Page stamp / meta pin drop is handled in ControlledPdfFiller
 * via FO2A_FOOTER_DROP_MM.
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
            'fo2_signatory_reviewed' => 13.0,
            'fo2_signatory_certified' => 13.0,
            'fo2_signatory_noted' => 37.0,
            'fo2_form_meta' => 57.0,
        ];

        foreach ($targets as $name => $offsetMm) {
            $field = $revision->fields()->where('name', $name)->first();
            if (! $field instanceof ControlledFormField) {
                continue;
            }

            $options = is_array($field->options) ? $field->options : [];
            $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
            $next = array_merge($anchor, [
                'below' => 'fo2_notes',
                'offset_mm' => $offsetMm,
            ]);
            if ($name !== 'fo2_form_meta') {
                $next['above'] = 'fo2_form_meta';
                $next['above_offset_mm'] = max(0.0, 57.0 - $offsetMm);
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
