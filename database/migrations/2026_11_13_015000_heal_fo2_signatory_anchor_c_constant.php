<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-heal FO2 / FO2A signatory anchors so offset_mm + above_offset_mm share one C.
 * Prior heal (014000) required the blueprint root (page+fields) and skipped fields.
 * Also syncs absolute y so Designer fallback matches offset deltas.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->healForm('LSP-7.8-FO2', config('result_ww_physico_form_fields.fields', []));
        $this->healForm('LSP-7.8-FO2A', config('result_ww_physico_abstracted_form_fields.fields', []));
    }

    public function down(): void
    {
        // Non-destructive.
    }

    /**
     * @param  list<array<string, mixed>>|array<string, mixed>  $blueprint
     */
    private function healForm(string $formCode, array $blueprint): void
    {
        $form = ControlledForm::query()->where('form_code', $formCode)->first();
        if (! $form instanceof ControlledForm) {
            return;
        }

        $revision = $form->activeRevision() ?? $form->currentRevision;
        if ($revision === null) {
            return;
        }

        $byName = [];
        foreach ($blueprint as $row) {
            if (! is_array($row) || ! isset($row['name']) || ! is_string($row['name'])) {
                continue;
            }
            $anchor = $row['options']['anchor'] ?? null;
            if (! is_array($anchor)) {
                continue;
            }
            if (! isset($anchor['above']) || (string) $anchor['above'] !== 'fo2_form_meta') {
                continue;
            }
            $byName[$row['name']] = [
                'anchor' => $anchor,
                'y' => isset($row['y']) && is_numeric($row['y']) ? (float) $row['y'] : null,
            ];
        }

        if ($byName === []) {
            return;
        }

        $fields = $revision->fields()->whereIn('name', array_keys($byName))->get();
        foreach ($fields as $field) {
            if (! $field instanceof ControlledFormField) {
                continue;
            }
            $wanted = $byName[$field->name] ?? null;
            if (! is_array($wanted)) {
                continue;
            }

            $options = is_array($field->options) ? $field->options : [];
            $anchor = is_array($options['anchor'] ?? null) ? $options['anchor'] : [];
            $nextAnchor = array_merge($anchor, [
                'below' => (string) ($wanted['anchor']['below'] ?? $anchor['below'] ?? ''),
                'offset_mm' => (float) ($wanted['anchor']['offset_mm'] ?? 0),
                'above' => 'fo2_form_meta',
                'above_offset_mm' => (float) ($wanted['anchor']['above_offset_mm'] ?? 0),
            ]);

            $dirty = false;
            if ($nextAnchor != $anchor) {
                $options['anchor'] = $nextAnchor;
                $field->options = $options;
                $dirty = true;
            }

            if ($wanted['y'] !== null && abs((float) $field->y - $wanted['y']) > 0.001) {
                $field->y = $wanted['y'];
                $dirty = true;
            }

            if ($dirty) {
                $field->save();
            }
        }
    }
};
