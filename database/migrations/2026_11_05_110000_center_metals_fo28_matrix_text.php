<?php

use App\Models\ControlledFormField;
use Illuminate\Database\Migrations\Migration;

/**
 * Center FO28 test and method text in the row. Does not move the matrix box.
 */
return new class extends Migration
{
    public function up(): void
    {
        $field = ControlledFormField::query()
            ->where('name', 'metals_fo28_matrix')
            ->whereHas('revision.form', fn ($query) => $query->where('form_code', 'LSP-7.8-FO28'))
            ->first();

        if ($field === null || ! is_array($field->table_config)) {
            return;
        }

        $config = $field->table_config;
        $columns = is_array($config['columns'] ?? null) ? $config['columns'] : [];
        foreach ($columns as $index => $column) {
            if (! is_array($column)) {
                continue;
            }
            if (in_array($column['key'] ?? '', ['test', 'method'], true)) {
                $columns[$index]['valign'] = 'M';
            }
        }
        $config['columns'] = $columns;
        $field->table_config = $config;
        $field->save();
    }

    public function down(): void
    {
        // Leave the centered columns in place.
    }
};
