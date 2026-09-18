<?php

use App\Enums\ControlledFormFieldType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Sample Description / SS used sublabel_align=L by default while the
 * designer Alignment control only updated header_align. Center stuck forms.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = now();
        $matrixType = ControlledFormFieldType::DynamicTestMatrix->value;

        $fields = DB::table('controlled_form_fields')
            ->where('field_type', $matrixType)
            ->whereNotNull('table_config')
            ->get(['id', 'table_config']);

        foreach ($fields as $field) {
            $config = json_decode((string) $field->table_config, true);
            if (! is_array($config) || ! is_array($config['columns'] ?? null)) {
                continue;
            }

            $changed = false;
            foreach ($config['columns'] as $index => $column) {
                if (! is_array($column)) {
                    continue;
                }

                $key = (string) ($column['key'] ?? '');
                $subAlign = strtoupper((string) ($column['sublabel_align'] ?? ''));
                $hasSublabel = filled($column['sublabel'] ?? null)
                    || (is_array($column['sublabels'] ?? null) && $column['sublabels'] !== []);

                if (! $hasSublabel || $subAlign !== 'L') {
                    continue;
                }

                // Result / control-number style columns that carried the
                // Sample Description sublabel defaulted to left.
                if (in_array($key, ['result', 'sample_1', 'control_number'], true)
                    || str_contains(mb_strtolower((string) ($column['label'] ?? '')), 'control')
                ) {
                    $config['columns'][$index]['sublabel_align'] = 'C';
                    $changed = true;
                }
            }

            if (! $changed) {
                continue;
            }

            DB::table('controlled_form_fields')
                ->where('id', $field->id)
                ->update([
                    'table_config' => json_encode($config),
                    'updated_at' => $now,
                ]);
        }
    }

    public function down(): void
    {
        //
    }
};
