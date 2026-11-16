<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2 live matrix drifted to y≈63 and sliced the blank-PDF
 * "PHYSICO-CHEMICAL TEST RESULTS" title. Restore blueprint clearance
 * (aligned with FO2B under-title start).
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-FO2')->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $blueprint = collect(config('result_ww_physico_form_fields.fields', []))
            ->first(fn ($row) => is_array($row) && ($row['name'] ?? null) === 'ww_fo2_matrix');
        if (! is_array($blueprint)) {
            return;
        }

        $field = $revision->fields()->where('name', 'ww_fo2_matrix')->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $nextY = (float) ($blueprint['y'] ?? 78.945);
        $nextH = (float) ($blueprint['h'] ?? 120.0);
        $dirty = false;
        if (abs((float) $field->y - $nextY) > 0.01) {
            $field->y = $nextY;
            $dirty = true;
        }
        if (abs((float) $field->height - $nextH) > 0.01) {
            $field->height = $nextH;
            $dirty = true;
        }
        if ($dirty) {
            $field->save();
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
