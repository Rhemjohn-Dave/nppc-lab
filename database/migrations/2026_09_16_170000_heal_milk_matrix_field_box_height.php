<?php

use App\Enums\ControlledFormFieldType;
use App\Models\ControlledForm;
use Illuminate\Database\Migrations\Migration;

/**
 * Sync Milk matrix field box height from blueprint so stretched rows fill
 * Designer → Preview → Download the same region. Does NOT replace canonical PDF.
 */
return new class extends Migration
{
    public function up(): void
    {
        $form = ControlledForm::query()->where('form_code', 'LSP-7.8-F016-MILK')->first();
        if (! $form) {
            return;
        }

        $blueprint = collect(config('result_milk_form_fields.fields', []))
            ->first(fn ($field): bool => is_array($field) && ($field['name'] ?? '') === 'milk_f016_matrix');

        if (! is_array($blueprint)) {
            return;
        }

        $revisions = $form->revisions()->with('fields')->get();
        foreach ($revisions as $revision) {
            $matrix = $revision->fields
                ->first(fn ($field): bool => $field->field_type === ControlledFormFieldType::DynamicTestMatrix
                    || $field->name === 'milk_f016_matrix');

            if (! $matrix) {
                continue;
            }

            $matrix->x = (float) ($blueprint['x'] ?? $matrix->x);
            $matrix->y = (float) ($blueprint['y'] ?? $matrix->y);
            $matrix->width = (float) ($blueprint['w'] ?? $matrix->width);
            $matrix->height = (float) ($blueprint['h'] ?? $matrix->height);

            if (is_array($blueprint['table_config'] ?? null)) {
                $matrix->table_config = $blueprint['table_config'];
            }

            $matrix->save();
        }
    }

    public function down(): void
    {
        // Non-destructive.
    }
};
