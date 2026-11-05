<?php

use App\Models\ControlledFormField;
use App\Support\DynamicTestMatrix;
use Illuminate\Database\Migrations\Migration;

/**
 * FO28 matrix: test name only in the Test column, method in the Method column.
 * Does not move the field box.
 */
return new class extends Migration
{
    public function up(): void
    {
        $field = ControlledFormField::query()
            ->where('name', 'metals_fo28_matrix')
            ->whereHas('revision.form', fn ($query) => $query->where('form_code', 'LSP-7.8-FO28'))
            ->first();

        if ($field === null) {
            return;
        }

        $config = is_array($field->table_config) ? $field->table_config : [];
        $blueprint = config('result_fo28_form_fields.fields', []);
        $matrix = collect($blueprint)->first(fn ($item) => is_array($item) && ($item['name'] ?? '') === 'metals_fo28_matrix');
        $preset = is_array($matrix['table_config'] ?? null) ? $matrix['table_config'] : null;

        if ($preset === null) {
            $preset = [
                'columns' => [
                    ['key' => 'test', 'label' => 'Test', 'width_pct' => 34, 'align' => 'L', 'header_align' => 'C'],
                    ['key' => 'method', 'label' => 'Method', 'width_pct' => 46, 'align' => 'L', 'header_align' => 'C'],
                    ['key' => 'result', 'label' => 'Result', 'width_pct' => 20, 'align' => 'C', 'header_align' => 'C'],
                ],
                'test_name_bold' => true,
                'preview_rows' => 10,
                'max_rows_per_page' => 10,
                'preview_data' => DynamicTestMatrix::metalsFo28SampleRows(),
            ];
        }

        $field->table_config = array_merge($config, $preset);
        $field->save();
    }

    public function down(): void
    {
        // Leave the corrected matrix columns in place.
    }
};
