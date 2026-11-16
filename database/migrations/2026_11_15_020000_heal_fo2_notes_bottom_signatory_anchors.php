<?php

use App\Models\ControlledForm;
use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * Re-anchor FO2 / FO2A / FO2B signatories and fo2_form_meta under fo2_notes
 * (NOTES ink bottom + fixed offsets) instead of the test matrix.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->healForm('LSP-7.8-FO2', config('result_ww_physico_form_fields.fields', []));
        $this->healForm('LSP-7.8-FO2A', config('result_ww_physico_abstracted_form_fields.fields', []));
        $this->healForm('LSP-7.8-FO2B', config('result_ww_physico_coastal_form_fields.fields', []));
    }

    public function down(): void
    {
        // Non-destructive.
    }

    /**
     * @param  list<array<string, mixed>>  $blueprint
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
            $name = $row['name'];
            $anchor = $row['options']['anchor'] ?? null;
            if (! is_array($anchor)) {
                continue;
            }
            $below = trim((string) ($anchor['below'] ?? ''));
            if ($below !== 'fo2_notes') {
                continue;
            }
            if (
                $name !== 'fo2_form_meta'
                && ! str_starts_with($name, 'fo2_signatory_')
            ) {
                continue;
            }
            $byName[$name] = $anchor;
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
            $next = array_merge($anchor, [
                'below' => 'fo2_notes',
                'offset_mm' => (float) ($wanted['offset_mm'] ?? 0),
            ]);
            if (isset($wanted['above'])) {
                $next['above'] = (string) $wanted['above'];
            }
            if (isset($wanted['above_offset_mm'])) {
                $next['above_offset_mm'] = (float) $wanted['above_offset_mm'];
            } elseif ($field->name === 'fo2_form_meta') {
                unset($next['above'], $next['above_offset_mm']);
            }

            if ($next == $anchor) {
                continue;
            }

            $options['anchor'] = $next;
            $field->options = $options;
            $field->save();
        }
    }
};
