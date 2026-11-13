<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * Grow FO2 / FO2A dynamic matrix box so Electrical Conductivity + Turbidity
 * (and WW-15 on the shared panel bind) fit in Form Designer and sample PDF.
 *
 * The designer clips body rows that do not fit field height; 106mm was sized
 * for the old 17-row Issue 18 panel.
 */
return new class extends Migration
{
    private const TARGET_HEIGHT_MM = 124.0;

    private const TARGET_PREVIEW_ROWS = 20;

    public function up(): void
    {
        $this->healMatrix('LSP-7.8-FO2', 'ww_fo2_matrix');
        $this->healMatrix('LSP-7.8-FO2A', 'ww_fo2a_matrix');
    }

    public function down(): void
    {
        // Non-destructive.
    }

    private function healMatrix(string $formCode, string $fieldName): void
    {
        $form = ControlledForm::query()->where('form_code', $formCode)->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $field = $revision->fields()->where('name', $fieldName)->first();
        if (! $field instanceof ControlledFormField) {
            return;
        }

        $changed = false;
        if ((float) $field->height < self::TARGET_HEIGHT_MM - 0.01) {
            $field->height = self::TARGET_HEIGHT_MM;
            $changed = true;
        }

        $config = is_array($field->table_config) ? $field->table_config : [];
        if ((int) ($config['preview_rows'] ?? 0) < self::TARGET_PREVIEW_ROWS) {
            $config['preview_rows'] = self::TARGET_PREVIEW_ROWS;
            $field->table_config = $config;
            $changed = true;
        }

        if ($changed) {
            $field->save();
        }
    }
};
