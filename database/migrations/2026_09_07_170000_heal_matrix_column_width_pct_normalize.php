<?php

use App\Enums\ControlledFormFieldType;
use App\Support\DynamicTestMatrix;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Designer HTML tables always fill the field box; incomplete width_pct sums
 * (e.g. 41+45=86) made PDF matrices draw narrower. Persist normalized %.
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
            if (! is_array($config) || ! is_array($config['columns'] ?? null) || $config['columns'] === []) {
                continue;
            }

            $columns = $config['columns'];
            $sum = 0.0;
            foreach ($columns as $column) {
                if (! is_array($column)) {
                    continue;
                }
                $pct = (float) ($column['width_pct'] ?? 0);
                if ($pct > 0) {
                    $sum += $pct;
                }
            }

            if ($sum <= 0 || abs($sum - 100) < 0.05) {
                continue;
            }

            $normalized = DynamicTestMatrix::normalizedWidthPercentages($columns);
            foreach ($columns as $index => $column) {
                if (! is_array($column)) {
                    continue;
                }
                $config['columns'][$index]['width_pct'] = round((float) ($normalized[$index] ?? 0), 3);
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
