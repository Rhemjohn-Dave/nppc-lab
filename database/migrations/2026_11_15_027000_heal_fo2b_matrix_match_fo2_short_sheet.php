<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * FO2B matrix band should match FO2 (h=120, preview_rows=20) so few-test
 * short-sheet row/font scaling looks the same as wastewater FO2.
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

        $field = $revision->fields()->where('name', 'ww_fo2b_matrix')->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $changed = false;
        if ((float) $field->height < 119.0) {
            $field->height = 120.0;
            $changed = true;
        }

        $config = is_array($field->table_config) ? $field->table_config : [];
        if ((int) ($config['preview_rows'] ?? 0) !== 20) {
            $config['preview_rows'] = 20;
            $field->table_config = $config;
            $changed = true;
        }

        if ($changed) {
            $field->save();
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
